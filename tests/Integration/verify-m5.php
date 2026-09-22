<?php

declare(strict_types=1);

/**
 * M5 environment preflight ONLY. This is not the functional panel verifier.
 * Run: php tests/Integration/verify-m5.php
 * Credentials: RW_DB_USERNAME and RW_DB_PASSWORD in the process environment.
 * Optional: RW_DB_HOSTNAME (127.0.0.1), RW_DB_PORT (3306).
 * No dotenv reads, writes, migrations, or connection to another database.
 */
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$passed = 0;
$blocked = 0;
function result(string $status, string $message): void
{
    global $passed, $blocked;
    $passed += (int) ($status === 'PASS');
    $blocked += (int) ($status === 'BLOCKED');
    echo $status, ' ', $message, PHP_EOL;
}

$root = dirname(__DIR__, 2);
chdir($root);
$head = trim((string) shell_exec('git rev-parse HEAD'));
result($head === '670dd97730bb121d06f2c355034ecec0fef96089' ? 'PASS' : 'BLOCKED', 'Target HEAD 670dd97');
result(PHP_VERSION_ID >= 80300 ? 'PASS' : 'BLOCKED', 'PHP ' . PHP_VERSION);
foreach (['mysqli', 'curl', 'dom'] as $extension) {
    result(extension_loaded($extension) ? 'PASS' : 'BLOCKED', 'Extension ' . $extension);
}

$username = getenv('RW_DB_USERNAME');
$password = getenv('RW_DB_PASSWORD');
if ($username === false || $username === '' || $password === false) {
    result('BLOCKED', 'Explicit RW_DB_USERNAME / RW_DB_PASSWORD missing; no DB connection attempted');
} elseif ($blocked === 0) {
    $connection = null;
    try {
        $connection = new mysqli(
            getenv('RW_DB_HOSTNAME') ?: '127.0.0.1',
            $username,
            $password,
            'rolewarden_test',
            (int) (getenv('RW_DB_PORT') ?: 3306),
        );
        $selected = $connection->query('SELECT DATABASE()')->fetch_row()[0];
        result($selected === 'rolewarden_test' ? 'PASS' : 'BLOCKED', 'Selected database is rolewarden_test');
    } catch (mysqli_sql_exception $exception) {
        // Never print connection diagnostics, which may contain account details.
        result('BLOCKED', 'Database connection unavailable; mysqli code ' . $exception->getCode());
    } finally {
        $connection?->close();
    }
}

echo "Functional panel checks: NOT EXECUTED by this preflight", PHP_EOL;
echo "Preflight: {$passed} PASS, {$blocked} BLOCKED", PHP_EOL;
exit($blocked === 0 ? 0 : 2);
