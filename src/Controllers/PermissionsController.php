<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use RoleWarden\Authorization\Resolver;
use RoleWarden\Entities\User;
use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;

class PermissionsController extends BaseController
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $permissions = model(PermissionModel::class)->orderBy('area')->orderBy('slug')->findAll();
        $grantedBy = $this->grantedBy();
        $byArea = [];

        foreach ($permissions as $permission) {
            $permission['roles'] = $grantedBy[(int) $permission['id']] ?? [];
            $byArea[$permission['area']][] = $permission;
        }

        ksort($byArea);

        return rw_panel('RoleWarden\Views\permissions\index', [
            'byArea' => $byArea,
        ], 'permissions', lang('RoleWarden.panel.permissions.indexTitle'));
    }

    /**
     * Check a permission (SPEC, v1.1 decisions): who holds it and why, and for
     * one user the outcome with the rule that decides it. Every answer comes
     * from the resolver; nothing here re-applies the precedence.
     */
    public function check(): string
    {
        $permissions = model(PermissionModel::class)->orderBy('area')->orderBy('slug')->findAll();
        $slug = rw_query('permission');
        $known = in_array($slug, array_column($permissions, 'slug'), true);
        $who = rw_query('user');
        $user = $who !== '' ? $this->findUser($who) : null;
        $resolver = service('rolewarden');
        $page = max(1, (int) rw_query('page'));
        $holders = [];
        $total = 0;

        if ($known) {
            $ids = model(PermissionModel::class)->holderIds($slug);
            $total = count($ids);
            $pageIds = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
            $users = $pageIds === [] ? [] : array_column(model(UserModel::class)->whereIn('id', $pageIds)->findAll(), null, 'id');

            foreach ($pageIds as $id) {
                if (isset($users[$id])) {
                    $holders[] = ['user' => $users[$id], 'why' => $resolver->explain($id, $slug)];
                }
            }
        }

        $pager = service('pager');
        $pager->store('default', $page, self::PER_PAGE, $total);
        $pager->only(['permission', 'user']);

        return rw_panel('RoleWarden\Views\permissions\check', [
            'permissions' => $permissions,
            'slug' => $known ? $slug : '',
            'unknown' => $slug !== '' && ! $known,
            'who' => $who,
            'user' => $user,
            'verdict' => $known && $user !== null ? $resolver->explain((int) $user->id, $slug) : null,
            'rules' => [Resolver::RULE_INACTIVE, Resolver::RULE_OVERRIDE_DENIED, Resolver::RULE_OVERRIDE_GRANTED, Resolver::RULE_SUPER_ADMIN, Resolver::RULE_ROLE, Resolver::RULE_NONE],
            'holders' => $holders,
            'total' => $total,
            'roleNames' => array_column(model(RoleModel::class)->findAll(), 'name', 'id'),
            'pager' => $pager,
            'start' => ($page - 1) * self::PER_PAGE,
        ], 'check', lang('RoleWarden.panel.check.title'));
    }

    /**
     * A user by id, username or email; null when none matches.
     */
    private function findUser(string $who): ?User
    {
        $users = model(UserModel::class);
        $user = ctype_digit($who) ? $users->find((int) $who) : $users->where('username', $who)->first();

        return $user ?? $users->findByCredentials(['email' => $who]);
    }

    /**
     * Permission id => roles that grant it, on their own or through a parent.
     *
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function grantedBy(): array
    {
        $roles = array_column(model(RoleModel::class)->orderBy('name')->findAll(), null, 'id');
        $direct = [];

        foreach (db_connect()->table(config('RoleWarden')->table('role_permissions'))->get()->getResultArray() as $row) {
            $direct[(int) $row['role_id']][] = (int) $row['permission_id'];
        }

        $out = [];

        foreach ($roles as $id => $role) {
            $held = [];
            $seen = [];

            for ($at = (int) $id; isset($roles[$at]) && ! isset($seen[$at]); $at = (int) $roles[$at]['parent_id']) {
                $seen[$at] = true;
                $held += array_flip($direct[$at] ?? []);
            }

            foreach (array_keys($held) as $permissionId) {
                $out[$permissionId][] = ['id' => (int) $id, 'name' => (string) $role['name']];
            }
        }

        return $out;
    }
}
