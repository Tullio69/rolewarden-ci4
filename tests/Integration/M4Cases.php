<?php
declare(strict_types=1);

namespace App\Commands;

use RoleWarden\Authorization\ProtectionException as Protection;
use RoleWarden\Models\{RoleModel, PermissionModel, UserModel, UserRoles};

/** Black-box cases copied into the isolated host by verify-m4.php. */
class M4Cases extends \CodeIgniter\CLI\BaseCommand
{
    protected $group = 'Tests';
    protected $name = 'm4:verify';
    protected $description = 'Independent M4 acceptance checks';
    private int $pass = 0;
    private int $fail = 0;
    private int $serial = 100;

    private function check(string $name, bool $ok, mixed $detail = null): void
    {
        $ok ? $this->pass++ : $this->fail++;
        echo ($ok ? 'PASS ' : 'FAIL ') . $name;
        if (!$ok && $detail !== null) {
            echo ' ' . str_replace(getenv('RW_DB_PASSWORD'), '[redacted]', json_encode($detail));
        }
        echo "\n";
    }

    private function add(string $table, array $data): int
    {
        db_connect()->table($table)->insert($data);
        return (int) db_connect()->insertID();
    }

    private function role(?int $parent = null, int $super = 0): int
    {
        $slug = 'm4-role-' . ++$this->serial;
        return $this->add('acl_roles', ['name'=>$slug,'slug'=>$slug,'parent_id'=>$parent,'is_system'=>0,'is_super_admin'=>$super]);
    }

    private function user(int $active = 1): int
    {
        return $this->add('users', ['username'=>'m4-user-'.++$this->serial,'active'=>$active]);
    }

    private function permission(): array
    {
        $slug = 'm4.action' . ++$this->serial;
        return [$this->add('acl_permissions', ['slug'=>$slug,'area'=>'m4','is_system'=>0]),$slug];
    }

    private function link(int $user, int $role): void
    {
        (new UserRoles())->assign($user, $role);
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (db_connect()->listTables() as $table) {
            $rows = db_connect()->table($table)->get()->getResultArray();
            $rows = array_map(fn($r)=>json_encode($r), $rows);
            sort($rows);
            $result[$table] = $rows;
        }
        ksort($result);
        return $result;
    }

    private function refused(string $name, callable $call, string $reason, bool $argument = false): void
    {
        $before = $this->snapshot();
        $error = null;
        try { $call(); } catch (\Throwable $e) { $error = $e; }
        $ok = $argument ? $error instanceof \InvalidArgumentException
            : $error instanceof Protection && $error->reason === $reason;
        $this->check($name.' exception', $ok, $error ? [get_class($error),$error->getMessage()] : 'no exception');
        $after = $this->snapshot();
        $changed = [];
        foreach ($before as $table => $rows) {
            if ($rows !== ($after[$table] ?? null)) {
                $changed[$table] = ['removed'=>array_values(array_diff($rows,$after[$table]??[])),
                    'added'=>array_values(array_diff($after[$table]??[],$rows))];
            }
        }
        $this->check($name.' database unchanged', $before === $after, $changed);
    }

    private function allowed(string $name, callable $call, callable $state): void
    {
        try { $result = $call(); $this->check($name, $result !== false && $state(), $result); }
        catch (\Throwable $e) { $this->check($name, false, [get_class($e),$e->getMessage()]); }
    }

    private function row(string $table, int $id): ?array
    {
        return db_connect()->table($table)->where('id',$id)->get()->getRowArray();
    }

    public function run(array $params)
    {
        error_reporting(E_ALL);
        if (db_connect()->query('SELECT DATABASE() AS n')->getRow()->n !== 'rolewarden_test') {
            throw new \RuntimeException('Unsafe database');
        }
        try {
            $this->check('Guard public class available', class_exists(\RoleWarden\Authorization\Guard::class));
            foreach (['lastSuperAdmin','hierarchyCycle','parentMissing','systemRecord'] as $key) {
                $full = 'RoleWarden.protection.'.$key;
                $this->check('language '.$full, is_string(lang($full)) && lang($full) !== $full && lang($full) !== '');
            }
            db_connect()->query('SELECT 1');
            \Config\Database::seeder()->call('RoleWarden\\Database\\Seeds\\RoleWardenSeeder');
            $u = $this->user();
            foreach (db_connect()->table('acl_roles')->where('is_system',1)->get()->getResultArray() as $r) {
                $this->link($u,(int)$r['id']);
                foreach ([false,true] as $purge) {
                    $this->refused('seed role '.$r['slug'].' delete purge='.(int)$purge,
                        fn()=>(new RoleModel())->delete($r['id'],$purge),Protection::SYSTEM_RECORD);
                }
            }
            foreach (db_connect()->table('acl_permissions')->where('is_system',1)->get()->getResultArray() as $p) {
                $this->add('acl_user_permissions',['user_id'=>$u,'permission_id'=>$p['id'],'granted'=>1]);
                $this->refused('seed permission '.$p['slug'].' delete',fn()=>(new PermissionModel())->delete($p['id']),Protection::SYSTEM_RECORD);
            }
            // Fixture-only SQL teardown: no other active super admin leaks into the matrix.
            db_connect()->table('acl_user_roles')->where('user_id',$u)->delete();
            $a=$this->role(); $b=$this->role($a); $c=$this->role($b); $d=$this->role();
            foreach (['self'=>$a,'direct'=>$b,'long'=>$c] as $label=>$parent) {
                $this->refused('hierarchy '.$label.' save',fn()=>(new RoleModel())->save(['id'=>$a,'parent_id'=>$parent]),Protection::HIERARCHY_CYCLE);
            }
            $this->refused('missing parent update',fn()=>(new RoleModel())->update($a,['parent_id'=>9999999]),Protection::PARENT_MISSING);
            $this->refused('missing parent insert',fn()=>(new RoleModel())->insert(['name'=>'missing','slug'=>'missing','parent_id'=>9999999]),Protection::PARENT_MISSING);
            $this->allowed('valid re-parent',fn()=>(new RoleModel())->save(['id'=>$b,'parent_id'=>$d]),fn()=>(int)$this->row('acl_roles',$b)['parent_id']===$d);
            $this->allowed('clear parent',fn()=>(new RoleModel())->save(['id'=>$b,'parent_id'=>null]),fn()=>$this->row('acl_roles',$b)['parent_id']===null);

            foreach (['delete-user','deactivate','revoke','delete-role','clear-flag'] as $operation) {
                foreach (['none','active','inactive','deleted-role','second-role'] as $other) {
                    $r=$this->role(null,1); $target=$this->user(); $this->link($target,$r);
                    $extra=null; $otherUser=null;
                    if ($other === 'second-role') { $extra=$this->role(null,1); $this->link($target,$extra); }
                    elseif ($other !== 'none') {
                        $extra=$this->role(null,1); $otherUser=$this->user($other==='inactive'?0:1); $this->link($otherUser,$extra);
                        if ($other==='deleted-role') { db_connect()->table('acl_roles')->where('id',$extra)->update(['deleted_at'=>date('Y-m-d H:i:s')]); }
                    }
                    $call=match($operation) {
                        'delete-user'=>fn()=>(new UserModel())->delete($target),
                        'deactivate'=>fn()=>(new UserModel())->update($target,['active'=>0]),
                        'revoke'=>fn()=>(new UserRoles())->revoke($target,$r),
                        'delete-role'=>fn()=>(new RoleModel())->delete($r),
                        'clear-flag'=>fn()=>(new RoleModel())->save(['id'=>$r,'is_super_admin'=>0]),
                    };
                    $state=match($operation) {
                        'delete-user'=>fn()=>$this->row('users',$target)===null || $this->row('users',$target)['deleted_at']!==null,
                        'deactivate'=>fn()=>(int)$this->row('users',$target)['active']===0,
                        'revoke'=>fn()=>db_connect()->table('acl_user_roles')->where(['user_id'=>$target,'role_id'=>$r])->countAllResults()===0,
                        'delete-role'=>fn()=>$this->row('acl_roles',$r)['deleted_at']!==null,
                        'clear-flag'=>fn()=>(int)$this->row('acl_roles',$r)['is_super_admin']===0,
                    };
                    $name=$operation.' other='.$other;
                    if ($other==='active' || ($other==='second-role' && in_array($operation,['revoke','delete-role','clear-flag'],true))) {
                        $this->allowed($name,$call,$state);
                    } else { $this->refused($name,$call,Protection::LAST_SUPER_ADMIN); }
                    db_connect()->table('acl_user_roles')->where('user_id',$target)->delete();
                    if ($otherUser) { db_connect()->table('acl_user_roles')->where('user_id',$otherUser)->delete(); }
                }
            }
            foreach ([RoleModel::class=>'acl_roles',PermissionModel::class=>'acl_permissions',UserModel::class=>'users'] as $class=>$table) {
                $field=$table==='users'?'active':'is_system';
                $this->refused($class.' unbounded update',fn()=>(new $class())->where($field,0)->update(null,[$field=>1]),'',true);
                $this->refused($class.' unbounded delete',fn()=>(new $class())->where($field,0)->delete(),'',true);
            }
            [$probePermission,$probeSlug]=$this->permission();
            $this->refused('permission unbounded slug update',
                fn()=>(new PermissionModel())->where('slug',$probeSlug)->update(null,['slug'=>$probeSlug.'changed']),'',true);
            $probeUser=$this->user(0);
            $this->refused('inactive user unbounded activation isolated',
                fn()=>(new UserModel())->where('id',$probeUser)->update(null,['active'=>1]),'',true);
            $ordinaryRole=$this->role();
            $this->allowed('non-system leaf role deleted',fn()=>(new RoleModel())->delete($ordinaryRole),
                fn()=>$this->row('acl_roles',$ordinaryRole)['deleted_at']!==null);
            [$ordinaryPermission]=$this->permission();
            $this->allowed('non-system permission deleted',fn()=>(new PermissionModel())->delete($ordinaryPermission),
                fn()=>$this->row('acl_permissions',$ordinaryPermission)===null);
            foreach (['delete-role','reparent','delete-permission','revoke','deactivate'] as $operation) {
                $user=$this->user(); $parent=$this->role(); $child=$this->role($parent); $grandchild=$this->role($child);
                $descendant=$this->user(); $this->link($user,$child); $this->link($descendant,$grandchild);
                [$p,$slug]=$this->permission();
                $this->add('acl_role_permissions',['role_id'=>$parent,'permission_id'=>$p]);
                $resolver=service('rolewarden');
                $this->check('cache '.$operation.' warm direct', $resolver->can($user,$slug));
                $this->check('cache '.$operation.' warm descendant', $resolver->can($descendant,$slug));
                $call=match($operation) {
                    'delete-role'=>fn()=>(new RoleModel())->delete($parent),
                    'reparent'=>fn()=>(new RoleModel())->update($child,['parent_id'=>null]),
                    'delete-permission'=>fn()=>(new PermissionModel())->delete($p),
                    'revoke'=>fn()=>(new UserRoles())->revoke($user,$child),
                    'deactivate'=>fn()=>(new UserModel())->update($user,['active'=>0]),
                };
                $this->allowed('cache '.$operation.' write',$call,fn()=>true);
                $this->check('cache '.$operation.' immediate new truth', !$resolver->can($user,$slug));
                $descendantExpected=in_array($operation,['revoke','deactivate'],true);
                $this->check('cache '.$operation.' descendant truth', $resolver->can($descendant,$slug)===$descendantExpected);
            }
            // Clear the test cache only after all M4 assertions, before independent M3 fixtures reuse IDs.
            cache()->clean();
        } catch (\Throwable $e) {
            $this->check('M4 suite completion',false,[get_class($e),$e->getMessage()]);
        }
        echo "M4 CASES {$this->pass} PASS, {$this->fail} FAIL\n";
    }
}
