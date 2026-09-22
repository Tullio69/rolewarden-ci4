<?php
// Independent test DB keeper. Snapshot stays in memory; no credentials in files.
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('RW_DB_HOSTNAME'), getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', (int) getenv('RW_DB_PORT'));
$db->set_charset('utf8mb4');
if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') { exit(3); }
function snapshot(mysqli $db): array {
    $out = [];
    foreach ($db->query('SHOW TABLES')->fetch_all() as [$name]) {
        $q = '`' . str_replace('`', '``', $name) . '`';
        $rows = $db->query("SELECT * FROM $q")->fetch_all(MYSQLI_ASSOC);
        usort($rows, fn($a, $b) => strcmp(serialize($a), serialize($b)));
        $out[$name] = [$db->query("SHOW CREATE TABLE $q")->fetch_row()[1], $rows];
    }
    ksort($out); return $out;
}
function clearDB(mysqli $db): void {
    if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') { throw new RuntimeException('Database guard'); }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetch_all() as [$name]) { $db->query('DROP TABLE `' . str_replace('`', '``', $name) . '`'); }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}
$original = snapshot($db); $mutated = false;
echo json_encode(['ready'=>true, 'tables'=>count($original), 'hash'=>hash('sha256', serialize($original))]) . "\n";
try {
    while (($line = fgets(STDIN)) !== false) {
        try {
            $req = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if ($req['op'] === 'clear') { $mutated = true; clearDB($db); $out = true; }
            elseif ($req['op'] === 'query') {
                $st = $db->prepare($req['sql']);
                if (!empty($req['args'])) { $args = $req['args']; $st->bind_param(str_repeat('s', count($args)), ...$args); }
                $st->execute(); $result = $st->get_result();
                $out = $result ? $result->fetch_all(MYSQLI_ASSOC) : ['affected'=>$st->affected_rows, 'id'=>$db->insert_id];
            } elseif ($req['op'] === 'hash') { $out = hash('sha256', serialize(snapshot($db))); }
            elseif ($req['op'] === 'restore') { break; }
            else { throw new RuntimeException('Unknown operation'); }
            echo json_encode(['ok'=>true,'data'=>$out], JSON_THROW_ON_ERROR) . "\n";
        } catch (Throwable $e) { echo json_encode(['ok'=>false, 'error'=>get_class($e), 'code'=>$e->getCode()]) . "\n"; }
    }
} finally {
    if ($mutated) {
        clearDB($db); $db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($original as [$ddl, $rows]) {
            $db->query($ddl);
            if (!$rows) { continue; }
            preg_match('/CREATE TABLE `([^`]+)`/', $ddl, $m);
            $columns = '`' . implode('`,`', array_keys($rows[0])) . '`';
            $st = $db->prepare('INSERT INTO `' . $m[1] . '` (' . $columns . ') VALUES (' . implode(',', array_fill(0, count($rows[0]), '?')) . ')');
            foreach ($rows as $row) { $v = array_values($row); $st->bind_param(str_repeat('s', count($v)), ...$v); $st->execute(); }
        }
        $db->query('SET FOREIGN_KEY_CHECKS=1');
    }
    $same = snapshot($db) === $original;
    echo json_encode(['restored'=>$same,'hash'=>hash('sha256', serialize(snapshot($db)))]) . "\n";
    if (!$same) { exit(4); }
}
