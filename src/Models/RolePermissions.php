<?php

declare(strict_types=1);

namespace RoleWarden\Models;

/**
 * Permissions granted directly to a role (the matrix). Composite key, so this
 * is a thin query-builder wrapper instead of a Model, like UserRoles.
 */
class RolePermissions
{
    public function grant(int $roleId, int $permissionId): void
    {
        $table = db_connect()->table(config('RoleWarden')->table('role_permissions'));

        if ($table->where('role_id', $roleId)->where('permission_id', $permissionId)->countAllResults() === 0) {
            $table->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        service('rolewarden')->forgetRole($roleId);
    }

    public function revoke(int $roleId, int $permissionId): void
    {
        db_connect()->table(config('RoleWarden')->table('role_permissions'))
            ->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();

        service('rolewarden')->forgetRole($roleId);
    }
}
