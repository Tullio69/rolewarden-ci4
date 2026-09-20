<?php

declare(strict_types=1);

namespace RoleWarden\Tests;

// Black-box contract checks: no module implementation is included or inspected.
// Destructive only on an initially empty rolewarden_test database; refuse otherwise.
$app = $argv[1] ?? throw new \RuntimeException('Pass the temporary CI4 app path.');
$password = getenv('RW_DB_PASSWORD') ?: throw new \RuntimeException('Set RW_DB_PASSWORD.');
$host = getenv('RW_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('RW_DB_PORT') ?: 3306);
$user = getenv('RW_DB_USER') ?: 'root';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new \mysqli($host, $user, $password, 'rolewarden_test', $port);
$db->set_charset('utf8mb4');
$originalEnv = file_get_contents($app . '/.env');
$failures = 0;
$checks = 0;
$tables = static function () use ($db): array {
    $names = array_column($db->query('SHOW TABLES')->fetch_all(), 0);
    sort($names);
    return $names;
};
if ($tables() !== []) {
    throw new \RuntimeException('rolewarden_test must be empty. Back it up and empty it explicitly before running.');
}
$check = static function (string $label, bool $ok, mixed $observed = null) use (&$checks, &$failures): void {
    ++$checks;
    $failures += (int) !$ok;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label;
    if ($observed !== null) {
        echo ' | ' . json_encode($observed, JSON_UNESCAPED_SLASHES);
    }
    echo PHP_EOL;
};
$configure = static function (string $prefix, int $dbPort, string $environment = 'development') use ($app, $originalEnv, $host, $user, $password): void {
    $values = [
        'CI_ENVIRONMENT' => $environment,
        'database.default.hostname' => $host,
        'database.default.database' => 'rolewarden_test',
        'database.default.username' => $user,
        'database.default.password' => $password,
        'database.default.DBDriver' => 'MySQLi',
        'database.default.DBPrefix' => '',
        'database.default.port' => (string) $dbPort,
    ];
    if ($prefix !== '') {
        $values['rolewarden.tablePrefix'] = $prefix;
    }
    $env = $originalEnv;
    // Remove this setting too when checking the default prefix.
    foreach (array_unique([...array_keys($values), 'rolewarden.tablePrefix']) as $key) {
        $env = preg_replace('/^\s*' . preg_quote($key, '/') . '\s*=.*$/m', '', $env);
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
    foreach ($values as $key => $value) {
        if (strpbrk($value, "\r\n\"\\") !== false) {
            throw new \RuntimeException('Unsupported dotenv value; use a simple local test credential.');
        }
        $env .= "\n{$key} = \"{$value}\"";
    }
    file_put_contents($app . '/.env', $env . "\n");
};
$spark = static function (array $args) use ($app): array {
    $process = proc_open([PHP_BINARY, 'spark', ...$args, '--no-header', '--no-ansi'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $app);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    echo "\n$ php spark " . implode(' ', $args) . "\nexit={$code}\n{$out}{$err}\n";
    return [$code, $out . $err];
};
$scalar = static fn (string $sql): mixed => $db->query($sql)->fetch_row()[0];
$insert = static function (string $table, array $data) use ($db): int {
    // Supply non-contract required display fields from database metadata, not source code.
    foreach ($db->query("SHOW COLUMNS FROM `{$table}`") as $column) {
        $name = $column['Field'];
        if (!array_key_exists($name, $data) && $column['Null'] === 'NO' && $column['Default'] === null && !str_contains($column['Extra'], 'auto_increment')) {
            if (preg_match('/char|text/', $column['Type'])) {
                $data[$name] = 'm1-verification';
            }
        }
    }
    $columns = implode('`,`', array_keys($data));
    $placeholders = implode(',', array_fill(0, count($data), '?'));
    $stmt = $db->prepare("INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})");
    $stmt->execute(array_values($data));
    return (int) $db->insert_id;
};
$reject = static function (string $label, callable $operation, int $expectedCode) use ($check): void {
    try {
        $operation();
        $check($label, false, 'invalid row accepted');
    } catch (\mysqli_sql_exception $e) {
        $check($label, $e->getCode() === $expectedCode, ['database_error_code' => $e->getCode()]);
    }
};
$seed = ['db:seed', 'RoleWarden\\Database\\Seeds\\RoleWardenSeeder'];
$sqlLeak = '/SQLSTATE|mysqli_sql_exception|BaseConnection\.php|Unknown database|Table [\'"].*doesn.t exist|SELECT\s+.+\s+FROM|INSERT INTO|Unable to connect to the database|MySQLi.*:/is';
try {
    $configure('', $port);
    $spark(['help', 'migrate:rollback']);
    foreach (['development', 'production'] as $environment) {
        $configure('', $port, $environment);
        [$code, $out] = $spark($seed);
        $check("9 seed without migrations ({$environment}): no SQL disclosure", !preg_match($sqlLeak, $out));
        $configure('', 1, $environment);
        [$code, $out] = $spark(['migrate', '--all']);
        $check("9 unreachable DB ({$environment}): no SQL disclosure", !preg_match($sqlLeak, $out));
    }
    foreach (['acl_', 'xx_'] as $prefix) {
        $configure($prefix === 'acl_' ? '' : $prefix, $port);
        [$code] = $spark(['migrate', '--all']);
        $expected = array_map(static fn ($name) => $prefix . $name, ['roles', 'permissions', 'role_permissions', 'user_roles', 'user_permissions']);
        $actual = $tables();
        $module = array_values(array_filter($actual, static fn ($name) => str_starts_with($name, 'acl_') || str_starts_with($name, 'xx_')));
        sort($expected);
        $check("1/2 {$prefix} exact five MVP tables", $code === 0 && $module === $expected, $actual);
        $baseline = array_values(array_diff($actual, $expected));
        echo 'OBSERVATION non-module tables (including framework Settings dependency): ' . json_encode($baseline) . PHP_EOL;

        $snapshot = static function () use ($db, $prefix): array {
            $result = [];
            foreach (['roles', 'permissions', 'role_permissions', 'user_roles', 'user_permissions'] as $table) {
                $rows = $db->query("SELECT * FROM {$prefix}{$table}")->fetch_all(MYSQLI_ASSOC);
                usort($rows, static fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
                $result[$table] = $rows;
            }
            return $result;
        };
        [$firstCode] = $spark($seed);
        $first = $snapshot();
        sleep(1); // Detect accidental timestamp updates on a second seed.
        [$secondCode] = $spark($seed);
        $second = $snapshot();
        $check("8 {$prefix} seed idempotent counts", $firstCode === 0 && $secondCode === 0 && array_map('count', $first) === array_map('count', $second), array_map('count', $second));
        echo 'OBSERVATION seed identical rows: ' . ($first === $second ? 'yes' : 'no') . PHP_EOL;
        $roles = $db->query("SELECT slug,is_system,is_super_admin FROM {$prefix}roles ORDER BY slug")->fetch_all(MYSQLI_ASSOC);
        $check("8 {$prefix} exact system roles and flags", $roles == [
            ['slug' => 'admin', 'is_system' => 1, 'is_super_admin' => 0],
            ['slug' => 'super-admin', 'is_system' => 1, 'is_super_admin' => 1],
            ['slug' => 'user', 'is_system' => 1, 'is_super_admin' => 0],
        ], $roles);
        $expectedSlugs = ['users.view','users.create','users.update','users.delete','users.activate','roles.view','roles.create','roles.update','roles.delete','roles.assign','permissions.view','permissions.override'];
        sort($expectedSlugs);
        $permissions = $db->query("SELECT slug,is_system FROM {$prefix}permissions ORDER BY slug")->fetch_all(MYSQLI_ASSOC);
        $check("8 {$prefix} exact 12 system permissions and lowercase format", array_column($permissions, 'slug') === $expectedSlugs && array_reduce($permissions, static fn ($ok, $p) => $ok && (int) $p['is_system'] === 1 && preg_match('/^[a-z]+\.[a-z]+$/D', $p['slug']), true), $permissions);
        $assigned = $db->query("SELECT r.slug,COUNT(rp.permission_id) AS n FROM {$prefix}roles r LEFT JOIN {$prefix}role_permissions rp ON rp.role_id=r.id GROUP BY r.id,r.slug ORDER BY r.slug")->fetch_all(MYSQLI_ASSOC);
        $check("8 {$prefix} admin=12, super-admin=user=0", $assigned == [['slug'=>'admin','n'=>12],['slug'=>'super-admin','n'=>0],['slug'=>'user','n'=>0]], $assigned);
        $adminPermissions = array_column($db->query("SELECT p.slug FROM {$prefix}role_permissions rp JOIN {$prefix}roles r ON r.id=rp.role_id JOIN {$prefix}permissions p ON p.id=rp.permission_id WHERE r.slug='admin' ORDER BY p.slug")->fetch_all(MYSQLI_ASSOC), 'slug');
        $check("8 {$prefix} admin receives exactly the agreed permission set", $adminPermissions === $expectedSlugs);

        $db->begin_transaction();
        try {
            $r = $insert($prefix . 'roles', ['slug'=>'m1-role']);
            $p = $insert($prefix . 'permissions', ['slug'=>'m1.view']);
            $u = $insert('users', ['username'=>'m1-verification']);
            $reject("3 {$prefix} unique role slug", static fn () => $insert($prefix.'roles', ['slug'=>'m1-role']), 1062);
            $reject("3 {$prefix} unique permission slug", static fn () => $insert($prefix.'permissions', ['slug'=>'m1.view']), 1062);
            $insert($prefix.'user_roles', ['user_id'=>$u,'role_id'=>$r]);
            $insert($prefix.'role_permissions', ['role_id'=>$r,'permission_id'=>$p]);
            $insert($prefix.'user_permissions', ['user_id'=>$u,'permission_id'=>$p,'granted'=>1]);
            $check("6 {$prefix} granted=1 stored", (int)$scalar("SELECT granted FROM {$prefix}user_permissions WHERE user_id={$u} AND permission_id={$p}") === 1);
            $reject("6 {$prefix} unique user+permission with opposite outcome", static fn () => $insert($prefix.'user_permissions', ['user_id'=>$u,'permission_id'=>$p,'granted'=>0]), 1062);
            $db->query("UPDATE {$prefix}user_permissions SET granted=0 WHERE user_id={$u} AND permission_id={$p}");
            $check("6 {$prefix} denied=0 stored", (int)$scalar("SELECT granted FROM {$prefix}user_permissions WHERE user_id={$u} AND permission_id={$p}") === 0);
            try {
                $db->query("UPDATE {$prefix}user_permissions SET granted=2 WHERE user_id={$u} AND permission_id={$p}");
                echo "OBSERVATION {$prefix} granted=2 accepted; spec must locate binary-domain enforcement.\n";
            } catch (\mysqli_sql_exception $e) {
                echo "OBSERVATION {$prefix} granted=2 rejected with code {$e->getCode()}.\n";
            }
            $db->query("UPDATE {$prefix}user_permissions SET granted=0 WHERE user_id={$u} AND permission_id={$p}");
            $db->query("UPDATE {$prefix}roles SET deleted_at=NOW() WHERE id={$r}");
            $check("5 {$prefix} soft-delete timestamp preserves role and assignments", (int)$scalar("SELECT COUNT(*) FROM {$prefix}roles WHERE id={$r} AND deleted_at IS NOT NULL") === 1 && (int)$scalar("SELECT COUNT(*) FROM {$prefix}user_roles WHERE role_id={$r}") === 1 && (int)$scalar("SELECT COUNT(*) FROM {$prefix}role_permissions WHERE role_id={$r}") === 1);
            $db->query("DELETE FROM users WHERE id={$u}");
            $check("4 {$prefix} hard user deletion cascades both bridges", (int)$scalar("SELECT COUNT(*) FROM {$prefix}user_roles WHERE user_id={$u}") === 0 && (int)$scalar("SELECT COUNT(*) FROM {$prefix}user_permissions WHERE user_id={$u}") === 0);
            $u = $insert('users', ['username'=>'m1-verification-2']);
            $insert($prefix.'user_roles', ['user_id'=>$u,'role_id'=>$r]);
            $insert($prefix.'user_permissions', ['user_id'=>$u,'permission_id'=>$p,'granted'=>0]);
            $db->query("DELETE FROM {$prefix}permissions WHERE id={$p}");
            $check("4 {$prefix} permission deletion cascades both bridges", (int)$scalar("SELECT COUNT(*) FROM {$prefix}role_permissions WHERE permission_id={$p}") === 0 && (int)$scalar("SELECT COUNT(*) FROM {$prefix}user_permissions WHERE permission_id={$p}") === 0);
            $p = $insert($prefix.'permissions', ['slug'=>'m1.other']);
            $insert($prefix.'role_permissions', ['role_id'=>$r,'permission_id'=>$p]);
            $parentColumns = $db->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$prefix}roles' AND REFERENCED_TABLE_NAME='{$prefix}roles'")->fetch_all(MYSQLI_ASSOC);
            $check("4 {$prefix} parent role FK exists", count($parentColumns) === 1, $parentColumns);
            if (count($parentColumns) === 1) {
                $parent = $parentColumns[0]['COLUMN_NAME'];
                $child = $insert($prefix.'roles', ['slug'=>'m1-child', $parent=>$r]);
            }
            $db->query("DELETE FROM {$prefix}roles WHERE id={$r}");
            $check("4 {$prefix} role deletion cascades both bridges", (int)$scalar("SELECT COUNT(*) FROM {$prefix}user_roles WHERE role_id={$r}") === 0 && (int)$scalar("SELECT COUNT(*) FROM {$prefix}role_permissions WHERE role_id={$r}") === 0);
            if (count($parentColumns) === 1) {
                $check("4 {$prefix} deleted parent leaves no dangling reference", (int)$scalar("SELECT COUNT(*) FROM {$prefix}roles c LEFT JOIN {$prefix}roles p ON p.id=c.`{$parent}` WHERE c.`{$parent}` IS NOT NULL AND p.id IS NULL") === 0);
                echo 'OBSERVATION child after hard parent deletion: ' . json_encode($db->query("SELECT id,`{$parent}` FROM {$prefix}roles WHERE id={$child}")->fetch_all(MYSQLI_ASSOC)) . PHP_EOL;
            }
            foreach ([[$prefix.'user_roles',['user_id'=>4294967294,'role_id'=>4294967294]],[$prefix.'user_permissions',['user_id'=>4294967294,'permission_id'=>4294967294,'granted'=>1]],[$prefix.'role_permissions',['role_id'=>4294967294,'permission_id'=>4294967294]]] as [$table,$data]) {
                $reject("4 {$table} rejects orphan row", static fn () => $insert($table,$data), 1452);
            }
        } finally {
            $db->rollback();
        }
        [$code] = $spark(['migrate:rollback', '-n', 'RoleWarden']);
        $remainingMigrations = (int)$scalar("SELECT COUNT(*) FROM migrations WHERE namespace='RoleWarden'");
        $check("7 {$prefix} rollback leaves only original Shield tables and migrations", $code === 0 && $tables() === $baseline && $remainingMigrations === 0, ['tables'=>$tables(),'module_migration_rows'=>$remainingMigrations]);
        [$code] = $spark(['migrate', '--all']);
        $check("7 {$prefix} remigration works", $code === 0 && array_diff($expected,$tables()) === []);
        [$code] = $spark(['migrate:rollback', '-n', 'RoleWarden']);
        $check("7 {$prefix} second rollback clean", $code === 0 && $tables() === $baseline);
    }
    // Additional installation topology: Shield exists in an earlier migration batch.
    // Keep the fresh-install failures above; this does not replace their contract.
    $configure('', $port);
    $spark(['migrate', '-n', 'CodeIgniter\\Shield']);
    $spark(['migrate', '-n', 'CodeIgniter\\Settings']);
    $existingBaseline = $tables();
    $check('supplemental existing Shield fixture ready', in_array('users', $existingBaseline, true));
    foreach (['acl_', 'xx_'] as $prefix) {
        $configure($prefix === 'acl_' ? '' : $prefix, $port);
        $spark(['migrate', '--all']);
        echo 'OBSERVATION migration batches: ' . json_encode($db->query('SELECT namespace,batch,COUNT(*) AS n FROM migrations GROUP BY namespace,batch ORDER BY batch,namespace')->fetch_all(MYSQLI_ASSOC)) . PHP_EOL;
        [$code] = $spark(['migrate:rollback', '-n', 'RoleWarden']);
        $check("7 supplemental {$prefix} rollback with preexisting Shield", $code === 0 && $tables() === $existingBaseline, $tables());
    }
} catch (\Throwable $e) {
    $check('verification execution completed', false, get_class($e) . ': ' . $e->getMessage());
} finally {
    file_put_contents($app . '/.env', $originalEnv);
    $check('temporary app .env restored byte-for-byte', file_get_contents($app . '/.env') === $originalEnv);
    // The database was empty on entry. Remove only the tables created during this run.
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables() as $table) {
        $db->query('DROP TABLE `' . str_replace('`', '``', $table) . '`');
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $check('rolewarden_test restored to initial empty state', $tables() === []);
}
echo "\nRESULT {$checks} checks, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);
