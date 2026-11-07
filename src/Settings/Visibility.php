<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use RoleWarden\Database\Seeds\RoleWardenSeeder;

/**
 * Who may open RoleWarden's own profile and sessions screens: everyone signed in
 * ("all", what 1.1.0 did) or only users holding at least one panel permission.
 * Stored by the Settings library; anything unset or invalid reads as "all".
 */
final class Visibility
{
    public const KEY = 'RoleWarden.visibility';
    public const ALL = 'all';
    public const ADMINS = 'admins';

    public static function normalize(mixed $stored): string
    {
        return $stored === self::ADMINS ? self::ADMINS : self::ALL;
    }

    public static function mode(): string
    {
        helper('setting');

        return self::normalize(setting(self::KEY));
    }

    public static function allows(string $mode, bool $holdsPanelPermission): bool
    {
        return $mode !== self::ADMINS || $holdsPanelPermission;
    }

    public static function allowsUser(int $userId): bool
    {
        $mode = self::mode();

        // The permission lookup only matters in "admins" mode.
        return $mode === self::ALL || self::allows($mode, service('rolewarden')->canAny($userId, RoleWardenSeeder::slugs()));
    }
}
