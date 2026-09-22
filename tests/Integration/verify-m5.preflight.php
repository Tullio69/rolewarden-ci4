<?php

declare(strict_types=1);

// Read-only prerequisite check. Never falls back to the host application's .env.
error_reporting(E_ALL);
$password = getenv('RW_DB_PASSWORD');
if ($password === false) {
    echo "BLOCKED: RW_DB_PASSWORD missing; no database connection attempted.\n";
    exit(2);
}

$host = getenv('RW_DB_HOSTNAME') ?: '127.0.0.1';
$port = getenv('RW_DB_PORT') ?: '3306';
$username = getenv('RW_DB_USERNAME');
if ($username === false || $username === '' || !ctype_digit($port)) {
    echo "BLOCKED: supply RW_DB_USERNAME and a valid RW_DB_PORT in the environment.\n";
    exit(2);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $db->real_connect($host, $username, $password, 'rolewarden_test', (int) $port);
    $selected = $db->query('SELECT DATABASE()')->fetch_row()[0];
    if ($selected !== 'rolewarden_test') {
        throw new RuntimeException('Unexpected database');
    }
    echo "PASS: selected database is exactly rolewarden_test; no writes performed.\n";
    $db->close();
    echo "Functional M5 tests have not been executed by this preflight.\n";
} catch (Throwable $error) {
    // Do not print driver diagnostics, connection parameters or secrets.
    echo 'BLOCKED: database preflight failed (code ' . (int) $error->getCode() . "). No writes performed.\n";
    exit(2);
}
