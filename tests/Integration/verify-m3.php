<?php
declare(strict_types=1);

// Black-box M3 acceptance checks. Never load module implementation as source.
error_reporting(E_ALL);
ini_set('display_errors', '1');
$app = $argv[1] ?? '';
$sibling = $argv[2] ?? '';
if (realpath($app) !== realpath(__DIR__ . '/.m3-app') || !getenv('RW_DB_PASSWORD')) {
    throw new RuntimeException('Use verify-m3.ps1 with RW_DB_PASSWORD.');
}
$pass = $fail = 0;
function check(string $name, bool $ok, mixed $detail = null): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    if (is_array($detail) && isset($detail['trace'])) { $detail = array_intersect_key($detail, array_flip(['exception','type','message'])); }
    $detail = str_replace(getenv('RW_DB_PASSWORD'), '[redacted]', (string)json_encode($detail));
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . (!$ok && $detail !== 'null' ? ' ' . substr($detail,0,2000) : '') . "\n";
}
function command(array $args): array
{
    global $app;
    if ($args[0] === PHP_BINARY) { array_splice($args, 1, 0, ['-d', 'auto_prepend_file='.$app.'/isolate.php']); }
    $p = proc_open($args, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $app);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
$repo = dirname(__DIR__, 2);
[$exit, $head] = command(['git','-C',$repo,'rev-parse','HEAD']);
check('target commit 41db1d1', $exit === 0 && trim($head) === '41db1d15eba787d8a5a89d46487fff961ff793fa');
[$exit] = command(['git','-C',$repo,'diff','--quiet','246d031','--','src/Authorization']);
check('Authorization unchanged since 246d031', $exit === 0);
$frameworkTokens = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/src/Authorization')) as $file) {
    if ($file->getExtension() !== 'php') { continue; }
    foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
        if (is_array($token) && !in_array($token[0], [T_COMMENT,T_DOC_COMMENT], true) && preg_match('/CodeIgniter|Shield/i',$token[1])) { $frameworkTokens++; }
    }
}
check('Authorization no framework references outside comments', $frameworkTokens === 0);
// Connection coordinates only; the password is always taken from RW_DB_PASSWORD.
$env = file_get_contents($sibling . '/.env');
foreach (['hostname' => '127.0.0.1', 'port' => '3306', 'username' => 'rolewarden'] as $key => $default) {
    preg_match('/^\s*database\.default\.' . $key . '\s*=\s*([^\r\n]+)/m', $env, $m);
    putenv('RW_DB_' . strtoupper($key) . '=' . (getenv('RW_DB_' . strtoupper($key)) ?: trim($m[1] ?? $default, " \t\"'")));
}
unset($env);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(getenv('RW_DB_HOSTNAME'), getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', (int)getenv('RW_DB_PORT'));
} catch (mysqli_sql_exception $e) {
    echo "BLOCKED database connection unavailable (driver code {$e->getCode()}); no database changes made\n";
    echo "TOTAL $pass PASS, $fail FAIL; runtime controls NOT RUN\n";
    exit(2);
}
$db->set_charset('utf8mb4');
if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') { throw new RuntimeException('Unsafe database'); }
function tables(): array { global $db; return array_column($db->query('SHOW FULL TABLES')->fetch_all(), 0); }
function wipe(): void {
    global $db;
    if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') { throw new RuntimeException('Unsafe database'); }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (tables() as $t) { $db->query('DROP TABLE `' . str_replace('`','``',$t) . '`'); }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}
function insert(string $table, array $data): int {
    global $db;
    $q = $db->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0,count($data),'?')) . ')');
    $q->execute(array_values($data)); return (int)$db->insert_id;
}
$backup = [];
foreach ($db->query('SHOW FULL TABLES')->fetch_all() as [$t,$type]) {
    if ($type !== 'BASE TABLE') { throw new RuntimeException('Unsupported view; no changes made'); }
    $backup[$t] = [$db->query("SHOW CREATE TABLE `$t`")->fetch_row()[1], $db->query("SELECT * FROM `$t`")->fetch_all(MYSQLI_ASSOC)];
}
if ($db->query('SHOW TRIGGERS')->num_rows) { throw new RuntimeException('Triggers present; no changes made'); }
file_put_contents($app . '/db-backup.json', json_encode($backup, JSON_THROW_ON_ERROR));
$server = null;
try {
    wipe(); check('rolewarden_test emptied after snapshot', tables() === []);
    file_put_contents($app . '/isolate.php', <<<'PHP'
<?php
$loader = require __DIR__ . '/vendor/autoload.php';
$loader->setPsr4('Config\\', __DIR__ . '/app/Config');
$loader->setPsr4('App\\', __DIR__ . '/app');
$map = [];
foreach ($loader->getClassMap() as $class => $path) {
 if (str_starts_with($class, 'Config\\')) { $local = __DIR__.'/app/'.str_replace('\\','/',$class).'.php'; }
 elseif (str_starts_with($class, 'App\\')) { $local = __DIR__.'/app/'.str_replace('\\','/',substr($class,4)).'.php'; }
 else { continue; }
 if (is_file($local)) { $map[$class] = $local; }
}
$loader->addClassMap($map);
PHP);
    file_put_contents($app . '/app/Config/Database.php', <<<'PHP'
<?php
namespace Config;
class Database extends \CodeIgniter\Database\Config {
 public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
 public string $defaultGroup = 'default';
 public array $default = [];
 public function __construct() {
  $this->default = ['DSN'=>'','hostname'=>getenv('RW_DB_HOSTNAME'),'username'=>getenv('RW_DB_USERNAME'),'password'=>getenv('RW_DB_PASSWORD'),'database'=>'rolewarden_test','DBDriver'=>'MySQLi','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8mb4','DBCollat'=>'utf8mb4_general_ci','swapPre'=>'','encrypt'=>false,'compress'=>false,'strictOn'=>true,'failover'=>[],'port'=>(int)getenv('RW_DB_PORT')];
  parent::__construct();
 }
}
PHP);
    file_put_contents($app . '/.env', "CI_ENVIRONMENT = development\napp.baseURL = 'http://127.0.0.1:18973/'\n");
    $authPath = $app . '/app/Config/Auth.php';
    $auth = file_get_contents($authPath);
    $auth = preg_replace_callback('/public\s+string\s+\$userProvider\s*=\s*[^;]+;/', fn() => 'public string $userProvider = \\RoleWarden\\Models\\UserModel::class;', $auth, 1, $n);
    if ($n !== 1) { throw new RuntimeException('Host Auth userProvider declaration not found'); }
    file_put_contents($authPath, $auth);
    // Runtime probe lives only in the isolated app; operations are loopback-only.
    file_put_contents($app . '/app/Controllers/M3Probe.php', <<<'PHP'
<?php
namespace App\Controllers;
class M3Probe extends \CodeIgniter\Controller {
 public function run($action = 'state', $id = '0') {
  error_reporting(E_ALL);
  helper('rolewarden');
  try {
   $r = service('rolewarden');
   $provider = auth()->getProvider();
   if ($action === 'login') { auth()->login($provider->findById((int)$id)); }
   if ($action === 'forgetUser') { $r->forgetUser((int)$id); }
   if ($action === 'forgetRole') { $r->forgetRole((int)$id); }
   if ($action === 'message') { return $this->response->setJSON(['error'=>session()->getFlashdata('error'),'expected'=>lang('RoleWarden.accessDenied')]); }
   if ($action === 'uppercase') {
    $u = $provider->findById((int)$id); $result = [];
    foreach (['entity'=>fn()=>$u->can('USERS.view'), 'resolver'=>fn()=>$r->can((int)$id,'USERS.view')] as $key=>$call) {
     try { $result[$key] = $call(); } catch (\InvalidArgumentException) { $result[$key] = 'InvalidArgumentException'; }
    }
    return $this->response->setJSON($result);
   }
   if ($action === 'invalid') {
    $u = $provider->findById((int)$id);
    $results = [];
    foreach (['users.*',"users.view\n",'USERS.view','users','',"users.view\0"] as $slug) {
     try { $u->can($slug); $results[] = false; } catch (\InvalidArgumentException) { $results[] = true; }
    }
    foreach ([['users.view','users.*'],['users.*','users.view']] as $slugs) {
     try { $u->can(...$slugs); $results[] = false; } catch (\InvalidArgumentException) { $results[] = true; }
    }
    return $this->response->setJSON($results);
   }
   $u = $action === 'entity' ? $provider->findById((int)$id) : auth()->user();
   $slugs = ['users.view','users.update','users.delete','roles.view','extra.unknown'];
   $answers = []; foreach ($slugs as $slug) { $answers[$slug] = $u ? $u->can($slug) : false; }
   return $this->response->setJSON([
    'db'=>db_connect()->query('SELECT DATABASE() AS n')->getRow()->n,
    'shared'=>$r === service('rolewarden'), 'resolver'=>$r instanceof \RoleWarden\Authorization\Resolver,
    'provider'=>get_class($provider), 'shield'=>$u instanceof \CodeIgniter\Shield\Entities\User,
    'id'=>$u?->id, 'can'=>$answers,
    'any'=>$u ? $u->can('users.delete','users.view') : false,
    'groups'=>$u ? $u->getGroups() : [],
    'child'=>$u ? $u->inGroup('child') : false,
    'case'=>$u ? $u->inGroup('ChIlD') : false,
    'parent'=>$u ? $u->inGroup('parent') : false,
    'helpers'=>[can('users.view'),can_any(['users.delete','users.view']),can_all(['users.view','users.update']),permissions()],
    'permissionAlias'=>config('Filters')->aliases['permission'] ?? null,
    'versions'=>[PHP_VERSION,\Composer\InstalledVersions::getPrettyVersion('codeigniter4/framework'),\Composer\InstalledVersions::getPrettyVersion('codeigniter4/shield')],
   ]);
  } catch (\Throwable $e) { return $this->response->setStatusCode(500)->setJSON(['exception'=>get_class($e),'message'=>$e->getMessage()]); }
 }
 public function allowed() { return $this->response->setJSON(['passed'=>true]); }
}
PHP);
    file_put_contents($app . '/app/Config/Routes.php', <<<'PHP'
<?php
use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
service('auth')->routes($routes);
$routes->get('m3/(:segment)/(:num)', 'M3Probe::run/$1/$2');
$routes->get('m3/(:segment)', 'M3Probe::run/$1');
$routes->get('m3-allow', 'M3Probe::allowed', ['filter'=>'can:users.view,users.update']);
$routes->get('m3-deny', 'M3Probe::allowed', ['filter'=>'can:users.delete']);
PHP);
    @mkdir($app . '/app/Commands');
    file_put_contents($app . '/app/Commands/M3Env.php', <<<'PHP'
<?php
namespace App\Commands;
class M3Env extends \CodeIgniter\CLI\BaseCommand {
 protected $group = 'Tests'; protected $name = 'm3:env'; protected $description = 'Probe isolated configuration';
 public function run(array $params) {
  $c = config('Database');
  echo json_encode(['file'=>(new \ReflectionClass($c))->getFileName(),'database'=>$c->default['database']??null,'userSet'=>!empty($c->default['username']),'envSet'=>(bool)getenv('RW_DB_PASSWORD'),'app'=>APPPATH]);
 }
}
PHP);
    [$probeExit, $probeOut] = command([PHP_BINARY, 'spark', 'm3:env', '--no-header']);
    $probe = json_decode($probeOut, true);
    check('temporary app configuration isolated', ($probe['database']??null)==='rolewarden_test' && ($probe['userSet']??false) && str_starts_with($probe['file']??'', $app), $probe);
    if (($probe['database']??null) !== 'rolewarden_test') { throw new RuntimeException('Unsafe runtime configuration'); }
    foreach (['CodeIgniter\\Settings','CodeIgniter\\Shield','RoleWarden'] as $ns) {
        [$exit,$out] = command([PHP_BINARY, 'spark','migrate','-n',$ns,'--no-header']);
        check('migration '.$ns, $exit === 0 && !preg_match('/(Exception|Error|failed)/i',$out), str_replace(getenv('RW_DB_PASSWORD'), '[redacted]', $out));
    }
    foreach (['users','acl_roles','acl_permissions','acl_user_roles','acl_role_permissions','acl_user_permissions'] as $t) {
        if (!in_array($t,tables(),true)) { throw new RuntimeException('Missing table '.$t); }
    }
    $now = date('Y-m-d H:i:s');
    foreach ([1=>1,2=>1,3=>0,4=>1,5=>1] as $id=>$active) { insert('users',['id'=>$id,'username'=>'m3user'.$id,'active'=>$active,'created_at'=>$now]); }
    foreach ([1=>['parent',null,0],2=>['child',1,0],3=>['root',null,1],4=>['deleted',null,1],5=>['grandchild',2,0]] as $id=>[$slug,$parent,$super]) {
        insert('acl_roles',['id'=>$id,'name'=>$slug,'slug'=>$slug,'parent_id'=>$parent,'is_system'=>0,'is_super_admin'=>$super,'deleted_at'=>$id===4?$now:null]);
    }
    foreach ([1=>'users.view',2=>'users.update',3=>'users.delete',4=>'roles.view'] as $id=>$slug) {
        insert('acl_permissions',['id'=>$id,'slug'=>$slug,'area'=>explode('.',$slug)[0],'is_system'=>0]);
    }
    foreach ([[1,1],[2,2],[2,3],[4,3]] as [$r,$p]) { insert('acl_role_permissions',['role_id'=>$r,'permission_id'=>$p]); }
    foreach ([[1,2],[2,3],[3,3],[4,4],[5,5]] as [$u,$r]) { insert('acl_user_roles',['user_id'=>$u,'role_id'=>$r]); }
    foreach ([[1,3,0],[1,4,1],[2,3,0],[3,4,1]] as [$u,$p,$g]) { insert('acl_user_permissions',['user_id'=>$u,'permission_id'=>$p,'granted'=>$g]); }
    file_put_contents($app.'/router.php', "<?php require __DIR__.'/isolate.php'; require __DIR__.'/public/index.php';");
    $server = proc_open([PHP_BINARY,'-S','127.0.0.1:18973','-t',$app.'/public',$app.'/router.php'],[0=>['pipe','r'],1=>['file',$app.'/server.log','a'],2=>['file',$app.'/server.log','a']],$pipes,$app);
    fclose($pipes[0]);
    $cookie = '';
    function http(string $path, bool $session = true): array {
        global $cookie;
        $headers=[];
        $ch=curl_init('http://127.0.0.1:18973/'.$path);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIE=>$session?$cookie:'',CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$headers,&$cookie,$session) { $headers[] = trim($line); if ($session && preg_match('/^Set-Cookie:\s*([^;]+)/i',$line,$m)) { $cookie=$m[1]; } return strlen($line); }]);
        $body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        return ['status'=>$code,'headers'=>$headers,'json'=>json_decode((string)$body,true),'body'=>(string)$body];
    }
    for ($i=0;$i<30;$i++) { usleep(100000); $guest=http('m3/state',false); if ($guest['status']) { break; } }
    if (!is_array($guest['json'])) { file_put_contents($app.'/probe-failure.html',$guest['body']); throw new RuntimeException('Probe HTTP '.$guest['status'].' (non-JSON)'); }
    check('guest helpers false/empty', ($guest['json']['helpers']??null)===[false,false,false,[]],$guest['json']);
    check('service shared resolver', ($guest['json']['shared']??false)&&($guest['json']['resolver']??false));
    check('HTTP database isolated',($guest['json']['db']??null)==='rolewarden_test');
    if (($guest['json']['db']??null)!=='rolewarden_test') { throw new RuntimeException('HTTP isolation preflight failed'); }
    echo 'RUNTIME '.json_encode($guest['json']['versions'])."\n";
    check('host provider selected',($guest['json']['provider']??null)==='RoleWarden\\Models\\UserModel');
    check('Shield permission alias untouched',($guest['json']['permissionAlias']??null)==='CodeIgniter\\Shield\\Filters\\PermissionFilter',$guest['json']['permissionAlias']??null);
    $g=http('m3-allow',false);
    check('guest filter redirects login',$g['status']>=300&&$g['status']<400&&(bool)preg_grep('~^Location:.*login~i',$g['headers']),[$g['status'],$g['headers']]);
    $expected=[1=>[true,true,false,true,false],2=>[true,true,false,true,true],3=>[false,false,false,false,false],4=>[false,false,false,false,false],5=>[true,true,true,false,false]];
    foreach ($expected as $id=>$values) {
        $e=http('m3/entity/'.$id)['json'];
        check("user $id Shield subclass",$e['shield']??false,$e);
        foreach (array_combine(['users.view','users.update','users.delete','roles.view','extra.unknown'],$values) as $slug=>$value) { check("user $id can $slug",($e['can'][$slug]??null)===$value,$e['can']??$e); }
        check("user $id any-of",($e['any']??null)===($values[0]||$values[2]));
    }
    $e=http('m3/entity/1')['json'];
    check('direct group only',($e['groups']??null)===['child']&&($e['child']??false)&&!($e['parent']??true),$e['groups']??null);
    check('group case insensitive',$e['case']??false);
    check('deleted group omitted',(http('m3/entity/4')['json']['groups']??null)===[]);
    foreach ([1,2,3] as $id) { $invalid=http('m3/invalid/'.$id)['json']; check("user $id rejects all malformed slugs",$invalid===array_fill(0,8,true),$invalid); }
    echo 'REPRO uppercase '.json_encode(http('m3/uppercase/1')['json'])."\n";
    $login=http('m3/login/1');
    check('Shield session login',($login['json']['id']??null)==1,$login['json']);
    $state=http('m3/state')['json'];
    $helpers=$state['helpers']??[];
    if (isset($helpers[3]) && is_array($helpers[3])) { sort($helpers[3]); }
    check('helpers authenticated',$helpers===[true,true,true,['roles.view','users.update','users.view']],$helpers);
    check('any-of route passes',http('m3-allow')['json']===['passed'=>true]);
    $denied=http('m3-deny'); $message=http('m3/message')['json'];
    check('denied route does not execute',($denied['json']['passed']??false)!==true&&($denied['status']>=300),$denied['status']);
    check('denied message translated',is_string($message['expected']??null)&&$message['expected']!=='RoleWarden.accessDenied'&&(($message['error']??null)===$message['expected']||str_contains($denied['body'],$message['expected'])),$message);
    // Change through SQL; invalidate through the public resolver in a separate HTTP request.
    $db->query('UPDATE acl_user_permissions SET granted=0 WHERE user_id=1 AND permission_id=4');
    http('m3/forgetUser/1'); $state=http('m3/state')['json'];
    check('forgetUser next request same login',($state['id']??null)==1&&($state['can']['roles.view']??null)===false);
    $db->query('DELETE FROM acl_role_permissions WHERE role_id=1 AND permission_id=1');
    http('m3/forgetRole/1');
    check('forgetRole child revoked next request',(http('m3/state')['json']['can']['users.view']??null)===false);
    check('forgetRole grandchild revoked',(http('m3/entity/5')['json']['can']['users.view']??null)===false);
    check('any-of route second permission passes',http('m3-allow')['json']===['passed'=>true]);
    $db->query('DELETE FROM acl_role_permissions WHERE role_id=2 AND permission_id=2');
    http('m3/forgetRole/2'); $revoked=http('m3-allow');
    check('filter revocation without logout',$revoked['status']>=300&&($revoked['json']['passed']??false)!==true);
    insert('acl_role_permissions',['role_id'=>1,'permission_id'=>1]); http('m3/forgetRole/1');
    check('role grant next request',(http('m3/state')['json']['can']['users.view']??null)===true);
    $db->query("UPDATE acl_roles SET deleted_at='$now' WHERE id=1"); http('m3/forgetRole/1');
    check('soft-deleted parent grants nothing',(http('m3/state')['json']['can']['users.view']??null)===false);
    $db->query("UPDATE acl_roles SET deleted_at='$now' WHERE id=2"); http('m3/forgetUser/1');
    check('soft-deleted direct role disappears',(http('m3/state')['json']['groups']??null)===[]);
    // Change only the host table mapping, then rename the existing Shield table.
    $auth = preg_replace("/(['\"]users['\"]\s*=>\s*)['\"]users['\"]/", '$1\'m3_people\'', $auth, 1, $count);
    if ($count !== 1) { throw new RuntimeException('Host Auth users table mapping not found'); }
    $db->query('RENAME TABLE users TO m3_people'); file_put_contents($authPath,$auth);
    http('m3/forgetUser/2'); $renamed=http('m3/entity/2')['json'];
    check('configured Shield users table',($renamed['shield']??false)&&($renamed['can']['users.view']??false),$renamed);
    $db->query('UPDATE m3_people SET active=0 WHERE id=2'); http('m3/forgetUser/2');
    check('configured table inactive denies',(http('m3/entity/2')['json']['can']['users.view']??null)===false);
    $warnings = preg_match('/PHP (Warning|Notice|Deprecated|Fatal error)/', file_get_contents($app.'/server.log'));
    check('HTTP E_ALL no PHP diagnostics',$warnings===0);
} catch (Throwable $e) {
    $safe = str_replace(getenv('RW_DB_PASSWORD'),'[redacted]',$e->getMessage());
    check('harness completion',false,get_class($e).': '.$safe);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    try {
        wipe(); $db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($backup as $t=>[$ddl,$rows]) { $db->query($ddl); foreach ($rows as $row) { insert($t,$row); } }
        $db->query('SET FOREIGN_KEY_CHECKS=1');
        $after=[]; foreach(tables() as $t) { $after[$t]=[$db->query("SHOW CREATE TABLE `$t`")->fetch_row()[1],$db->query("SELECT * FROM `$t`")->fetch_all(MYSQLI_ASSOC)]; }
        check('database restored exactly',$after===$backup);
    } catch (Throwable $e) { file_put_contents($app.'/RESTORE-FAILED','Keep db-backup.json for recovery'); throw $e; }
}
echo "TOTAL $pass PASS, $fail FAIL\n";
exit($fail ? 1 : 0);
