<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use RoleWarden\Models\PermissionModel;

class PermissionsController extends BaseController
{
    public function index(): string
    {
        $permissions = model(PermissionModel::class)->orderBy('area')->orderBy('slug')->findAll();
        $byArea = [];

        foreach ($permissions as $permission) {
            $byArea[$permission['area']][] = $permission;
        }

        ksort($byArea);

        return rw_panel('RoleWarden\Views\permissions\index', [
            'byArea' => $byArea,
        ], 'permissions', lang('RoleWarden.panel.permissions.indexTitle'));
    }
}
