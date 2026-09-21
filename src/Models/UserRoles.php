<?php

declare(strict_types=1);

namespace RoleWarden\Models;

/**
 * Role assignments. The pivot has a composite key, which CI4 models do not
 * handle, so this is a thin query-builder wrapper instead of a Model.
 */
class UserRoles
{
    public function assign(int $userId, int $roleId): void
    {
        $table = db_connect()->table(config('RoleWarden')->table('user_roles'));

        if ($table->where('user_id', $userId)->where('role_id', $roleId)->countAllResults() === 0) {
            $table->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }

        service('rolewarden')->forgetUser($userId);
    }

    public function revoke(int $userId, int $roleId): void
    {
        service('rolewardenGuard')->assertRoleUnassignable($userId, $roleId);

        db_connect()->table(config('RoleWarden')->table('user_roles'))
            ->where('user_id', $userId)->where('role_id', $roleId)->delete();

        service('rolewarden')->forgetUser($userId);
    }
}
