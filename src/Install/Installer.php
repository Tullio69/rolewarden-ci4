<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as Database;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Entities\User;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserRoles;
use Throwable;

/**
 * The steps of the browser installer (SPEC, V7 decisions): requirements,
 * database settings written to .env, migrations and system roles, first super
 * admin, then a lock file. Every step is idempotent, and the installer works
 * out where it stands from the state of the application, not from a session.
 */
final class Installer
{
    public const MIN_PHP = '8.3.0';
    public const EXTENSIONS = ['intl', 'mbstring', 'json', 'mysqli'];

    /** Applied in this order, each in a batch of its own, as the README's manual install does. */
    public const NAMESPACES = ['CodeIgniter\Settings', 'CodeIgniter\Shield', 'RoleWarden'];

    public static function lockFile(): string
    {
        return WRITEPATH . 'rolewarden/installed.lock';
    }

    public static function codeFile(): string
    {
        return WRITEPATH . 'rolewarden/install-code.txt';
    }

    public static function envFile(): string
    {
        return ROOTPATH . '.env';
    }

    /**
     * @return list<array{key: string, ok: bool, detail: string, blocking: bool}>
     */
    public static function requirements(): array
    {
        $checks = [['key' => 'php', 'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='), 'detail' => PHP_VERSION, 'blocking' => true]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = ['key' => 'extension', 'ok' => extension_loaded($extension), 'detail' => $extension, 'blocking' => true];
        }

        $checks[] = ['key' => 'writable', 'ok' => is_writable(WRITEPATH), 'detail' => 'writable/', 'blocking' => true];
        // Not blocking: when .env cannot be written, the installer shows what to put in it.
        $env = self::envFile();
        $checks[] = ['key' => 'env', 'ok' => is_file($env) ? is_writable($env) : is_writable(ROOTPATH), 'detail' => '.env', 'blocking' => false];

        return $checks;
    }

    /**
     * @param list<array{key: string, ok: bool, detail: string, blocking: bool}> $checks
     */
    public static function requirementsMet(array $checks): bool
    {
        return array_filter($checks, static fn (array $c): bool => $c['blocking'] && ! $c['ok']) === [];
    }

    /**
     * Installed once the lock file exists, or when the application already has
     * an active super admin (an existing install, or one made by hand).
     */
    public static function installed(): bool
    {
        return is_file(self::lockFile()) || self::hasSuperAdmin();
    }

    /**
     * The one-time setup code, created on first use. Only someone who can read
     * the server's files can install, so a stranger who finds the installer
     * of a fresh upload first cannot make themselves super admin.
     */
    public static function code(): string
    {
        $file = self::codeFile();

        if (! is_file($file)) {
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }

            file_put_contents($file, bin2hex(random_bytes(8)) . "\n");
        }

        return trim((string) file_get_contents($file));
    }

    /**
     * Whether the default connection names a database at all. Until it does
     * (the complete application before the installer writes .env), nothing
     * tries to connect.
     */
    public static function databaseConfigured(): bool
    {
        $config = config('Database');

        return (string) ($config->{$config->defaultGroup}['database'] ?? '') !== '';
    }

    /**
     * The database from the current configuration, when it answers and names a
     * database; null otherwise.
     */
    public static function database(): ?BaseConnection
    {
        try {
            if (! self::databaseConfigured()) {
                return null;
            }

            $db = Database::connect(null, false);
            $db->initialize();

            return $db;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Tries the posted database settings. The driver's message goes to the log
     * only: it can carry SQL details that never reach the screen.
     *
     * @param array{hostname: string, port: int, database: string, username: string, password: string} $settings
     */
    public static function canConnect(array $settings): bool
    {
        try {
            $db = Database::connect($settings + ['DBDriver' => 'MySQLi', 'DBDebug' => true, 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci'], false);
            $db->initialize();
            $db->close();

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RoleWarden installer: database connection failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * $current with each of $values set: an existing line for the key is
     * replaced, a missing one appended. Values are double-quoted with
     * backslashes and quotes escaped, the form CodeIgniter's DotEnv reads back.
     *
     * @param array<string, string> $values
     */
    public static function env(string $current, array $values): string
    {
        $lines = $current === '' ? [] : preg_split('/\R/', rtrim($current, "\r\n"));

        foreach ($values as $key => $value) {
            $line = $key . ' = "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            $pattern = '/^\s*#?\s*' . preg_quote($key, '/') . '\s*=/';
            $found = false;

            foreach ($lines as $i => $existing) {
                // A commented-out default (as in CodeIgniter's env template) is replaced in place.
                if (preg_match($pattern, $existing) === 1) {
                    if (! $found) {
                        $lines[$i] = $line;
                        $found = true;
                    } elseif (! str_starts_with(ltrim($existing), '#')) {
                        unset($lines[$i]);
                    }
                }
            }

            if (! $found) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * The .env values for the posted settings. The encryption key is generated
     * only when the application has none yet, and the environment is set to
     * production only when .env does not name one.
     *
     * @param array{baseURL: string, hostname: string, port: int, database: string, username: string, password: string} $settings
     *
     * @return array<string, string>
     */
    public static function envValues(array $settings, string $current): array
    {
        $values = [];

        if (preg_match('/^\s*CI_ENVIRONMENT\s*=/m', $current) !== 1) {
            $values['CI_ENVIRONMENT'] = 'production';
        }

        $values['app.baseURL'] = rtrim($settings['baseURL'], '/') . '/';

        foreach (['hostname', 'database', 'username', 'password', 'port'] as $key) {
            $values['database.default.' . $key] = (string) $settings[$key];
        }

        $values['database.default.DBDriver'] = 'MySQLi';

        if ((string) config('Encryption')->key === '') {
            $values['encryption.key'] = 'hex2bin:' . bin2hex(random_bytes(32));
        }

        return $values;
    }

    /**
     * Whether the module's tables are all there, at the latest migration.
     */
    public static function migrated(BaseConnection $db): bool
    {
        try {
            if (! $db->tableExists(config('Migrations')->table)) {
                return false;
            }

            $runner = service('migrations', null, null, false);

            foreach (self::NAMESPACES as $namespace) {
                $files = $runner->setNamespace($namespace)->findMigrations();
                $done = $db->table(config('Migrations')->table)->where('namespace', $namespace)->countAllResults();

                if ($done < count($files)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function migrate(): bool
    {
        try {
            foreach (self::NAMESPACES as $namespace) {
                $runner = service('migrations', null, null, false);

                if (! $runner->setNamespace($namespace)->latest()) {
                    return false;
                }
            }

            Database::seeder()->call(RoleWardenSeeder::class);

            return true;
        } catch (Throwable $e) {
            log_message('error', 'RoleWarden installer: migrations failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Creates the first super admin through Shield, active, with the system
     * super-admin role, then locks the installer.
     */
    public static function createSuperAdmin(string $username, string $email, string $password): bool
    {
        $role = model(RoleModel::class)->where('slug', 'super-admin')->first();

        if ($role === null) {
            return false;
        }

        $users = model(UserModel::class);
        $users->save(new User(['username' => $username, 'email' => $email, 'password' => $password]));
        $id = (int) $users->getInsertID();
        $users->activate($users->findById($id));
        (new UserRoles())->assign($id, (int) $role['id']);

        return self::finish();
    }

    public static function finish(): bool
    {
        $done = file_put_contents(self::lockFile(), date('c') . "\n") !== false;
        @unlink(self::codeFile());

        return $done;
    }

    private static function hasSuperAdmin(): bool
    {
        $db = self::database();

        if ($db === null) {
            return false;
        }

        try {
            $cfg = config('RoleWarden');

            // Checked first: a query on a missing table is an error in the log.
            if (! $db->tableExists($cfg->table('user_roles')) || ! $db->tableExists($cfg->table('roles'))) {
                return false;
            }

            return $db->table($cfg->table('user_roles') . ' ur')
                ->join($cfg->table('roles') . ' r', 'r.id = ur.role_id')
                ->join(config('Auth')->tables['users'] . ' u', 'u.id = ur.user_id')
                ->where('r.is_super_admin', 1)->where('u.active', 1)->where('u.deleted_at', null)
                ->countAllResults() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
