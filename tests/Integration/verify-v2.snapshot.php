<?php
// Canonical SQL snapshot of an EMPTY database. Fail closed on any table/view/routine/event.
declare(strict_types=1);
$d = new mysqli('127.0.0.1', getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', 3307);
if ($d->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') exit(2);
if (($argv[1] ?? '') === 'restore') {
    $sql = file_get_contents($argv[2]);
    if (!str_starts_with($sql, "CREATE DATABASE `rolewarden_test` ")) exit(3);
    $d->query('DROP DATABASE `rolewarden_test`');
    $d->query($sql);
    exit;
}
foreach (["SHOW FULL TABLES", "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='rolewarden_test'", "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA='rolewarden_test'"] as $query) {
    if ($d->query($query)->num_rows !== 0) { fwrite(STDERR, "ABORT: this snapshot adapter requires an empty rolewarden_test\n"); exit(4); }
}
echo $d->query('SHOW CREATE DATABASE `rolewarden_test`')->fetch_row()[1], ";\n";
