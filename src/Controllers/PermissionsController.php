<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;

class PermissionsController extends BaseController
{
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
