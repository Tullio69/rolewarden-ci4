<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserRoles;

/**
 * The role every new user gets: users who register through Shield and users
 * created from the panel. Kept by CodeIgniter's Settings library (the
 * `settings` table Shield already needs), so it changes at runtime from the
 * panel; the default comes from Config\RoleWarden::$defaultRole.
 *
 * Stored as a role slug; an empty string means "no role", which is also what
 * a slug whose role has since been deleted falls back to.
 */
final class DefaultRole
{
    public const KEY = 'RoleWarden.defaultRole';

    /**
     * @return array<string, mixed>|null the role row, or null for no role
     */
    public static function role(): ?array
    {
        helper('setting');
        $slug = setting(self::KEY);

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $role = model(RoleModel::class)->where('slug', $slug)->first();

        return self::reject($role) === null ? $role : null;
    }

    /**
     * Why a role cannot be the default, as a RoleWarden.panel.settings.* language key;
     * null when it can. A super admin role would hand full control to anyone who signs up.
     *
     * @param array<string, mixed>|null $role
     */
    public static function reject(?array $role): ?string
    {
        if ($role === null) {
            return 'defaultRoleMissing';
        }

        return (int) ($role['is_super_admin'] ?? 0) === 1 ? 'defaultRoleSuperAdmin' : null;
    }

    public static function assignTo(int $userId): void
    {
        $role = self::role();

        if ($role !== null) {
            (new UserRoles())->assign($userId, (int) $role['id']);
        }
    }
}
