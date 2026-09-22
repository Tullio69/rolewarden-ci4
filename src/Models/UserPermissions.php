<?php

declare(strict_types=1);

namespace RoleWarden\Models;

/**
 * Per-user permission overrides. Composite key, so this is a thin
 * query-builder wrapper instead of a Model, like UserRoles.
 */
class UserPermissions
{
    public function set(int $userId, int $permissionId, bool $granted): void
    {
        $table = db_connect()->table(config('RoleWarden')->table('user_permissions'));
        $pair = ['user_id' => $userId, 'permission_id' => $permissionId];

        if ($table->where($pair)->countAllResults() > 0) {
            $table->where($pair)->update(['granted' => $granted ? 1 : 0]);
        } else {
            $table->insert($pair + ['granted' => $granted ? 1 : 0]);
        }

        service('rolewarden')->forgetUser($userId);
    }

    public function clear(int $userId, int $permissionId): void
    {
        db_connect()->table(config('RoleWarden')->table('user_permissions'))
            ->where('user_id', $userId)->where('permission_id', $permissionId)->delete();

        service('rolewarden')->forgetUser($userId);
    }
}
