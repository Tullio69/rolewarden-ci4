<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use Throwable;

/**
 * Whether the module's tables exist. Between wiring the module into a host
 * (user provider, routes) and running its migrations they do not: then every
 * RoleWarden lookup on the host answers "no roles, no permissions" instead of
 * a database error, and the panel shows the "not installed yet" page.
 *
 * Checked once per request. A database that is not configured or does not
 * answer reads as "not installed": RoleWarden stays out of the way and the
 * host's own queries report the real problem.
 */
final class Readiness
{
    private static ?bool $exists = null;

    public static function tablesExist(): bool
    {
        if (self::$exists !== null) {
            return self::$exists;
        }

        try {
            // Not cached by the connection: the answer is already kept here, for this request.
            return self::$exists = Installer::databaseConfigured()
                && db_connect()->tableExists(config('RoleWarden')->table('user_roles'), false);
        } catch (Throwable) {
            return self::$exists = false;
        }
    }

    /** After the migrations ran (the installer) or the tables were dropped (tests). */
    public static function forget(): void
    {
        self::$exists = null;
    }
}
