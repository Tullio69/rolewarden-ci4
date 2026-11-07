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
 * Checked once per request: the answer is kept until forget(), which
 * Config/Events.php calls first thing on pre_system, so a worker process
 * serving many requests asks again for each one. A database that is not
 * configured reads as "not installed"; one that does not answer too, logged
 * as an error: RoleWarden stays out of the way and the host's own queries
 * report the real problem.
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
        } catch (Throwable $e) {
            log_message('error', 'RoleWarden: database not reachable, module treated as not installed ({class} {code}).', ['class' => $e::class, 'code' => $e->getCode()]);

            return self::$exists = false;
        }
    }

    /** After the migrations ran (the installer) or the tables were dropped (tests). */
    public static function forget(): void
    {
        self::$exists = null;
    }
}
