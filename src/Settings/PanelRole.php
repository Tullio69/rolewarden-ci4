<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

/**
 * The role that holds the module's own permissions and is handed new ones when
 * an upgrade adds them. Stored by the Settings library; unset means "admin"
 * (what 1.1.0 did), an empty string means nobody.
 */
final class PanelRole
{
    public const KEY = 'RoleWarden.panelRole';

    public static function resolve(mixed $stored): ?string
    {
        if ($stored === null) {
            return 'admin';
        }

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    public static function slug(): ?string
    {
        helper('setting');

        return self::resolve(setting(self::KEY));
    }

    /** The id of the live role with the panel role's slug, or null. */
    public static function roleId(): ?int
    {
        $slug = self::slug();

        if ($slug === null) {
            return null;
        }

        $cfg = config('RoleWarden');
        $row = db_connect()->table($cfg->table('roles'))->select('id')->where('slug', $slug)->where('deleted_at', null)->get()->getRow();

        return $row === null ? null : (int) $row->id;
    }
}
