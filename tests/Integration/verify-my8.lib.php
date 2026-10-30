<?php
/**
 * MY8 (Collaudatore ad Hoc, Claude): copy of verify-v7.lib.php for the MySQL 8 stage. Changes, nothing else:
 *   - mysqli towards rolewarden_test on 127.0.0.1:MY8_DBPORT (default 3318, the temporary MySQL 8 container);
 *   - php -S on port MY8_PORT (default 8072) with the V7 router (verify-v7.router.php, unchanged);
 *   - Client cookie jars named rwmy8-*.
 * Credentials only from the process variables RW_DB_USERNAME / RW_DB_PASSWORD (and MY8_APP_USER / MY8_APP_PASS
 * for the application's own database user), never written to a file by this harness.
 */
declare(strict_types=1);

define('PORT7', (int) (getenv('MY8_PORT') ?: 8072));
define('DBPORT8', (int) (getenv('MY8_DBPORT') ?: 3318));
const MP7 = 'http://localhost:8026/api/v1/';

$GLOBALS['results'] = [];
$GLOBALS['ambiguities'] = [];
$GLOBALS['SRV7'] = null;
$GLOBALS['SRV7_PIDS'] = [];

function check(string $id, string $label, bool $ok, string $detail = ''): bool
{
    $GLOBALS['results'][] = [$id, $label, $ok, $detail];
    printf("[%s] %-6s %s%s\n", $ok ? 'PASS' : 'FAIL', $id, $label, $ok ? '' : "\n         -> " . redact($detail));
    return $ok;
}
function amb(string $id, string $label, string $detail): void
{
    $GLOBALS['ambiguities'][] = [$id, $label, $detail];
    printf("[AMB]  %-6s %s\n         -> %s\n", $id, $label, redact($detail));
}
function info(string $s): void { echo '[INFO] ' . redact($s) . "\n"; }
function redact(string $s): string
{
    foreach (['RW_DB_PASSWORD', 'MY8_APP_PASS'] as $v) { $p = (string) getenv($v); if ($p !== '') $s = str_replace($p, '[omesso]', $s); }
    return $s;
}

function db(bool $reconnect = false): mysqli
{
    static $db = null;
    if ($reconnect) $db = null; // after the server was stopped and started again (MY8 X04)
    if ($db === null) {
        foreach (['RW_DB_USERNAME', 'RW_DB_PASSWORD'] as $v) if (getenv($v) === false) exit("Missing $v\n");
        $db = new mysqli('127.0.0.1', getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', DBPORT8);
        if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') exit("Refusing to run outside rolewarden_test\n");
    }
    return $db;
}
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    if ($params) { $params = array_map(fn ($p) => $p === null ? null : (string) $p, $params); $st->bind_param(str_repeat('s', count($params)), ...$params); }
    $st->execute();
    $r = $st->get_result();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function q1(string $sql, array $params = []) { $r = q($sql, $params); return $r ? array_values($r[0])[0] : null; }
function tables(): array { $t = array_map(fn ($r) => array_values($r)[0], q('SHOW TABLES')); sort($t); return $t; }
function empty_db(): void
{
    db()->query('DROP DATABASE rolewarden_test');
    db()->query('CREATE DATABASE rolewarden_test');
    db()->select_db('rolewarden_test');
}
/** Whole content of rolewarden_test (structure and rows), for "nothing changed" checks. */
function dbstate(): string
{
    $o = '';
    // Volatile rows that every signed-in request touches (Shield's last_active, the session tracker,
    // sign-in attempts, the activity log of sign-ins) are left out: the check is about what an
    // installer could create or change.
    foreach (tables() as $t) {
        $o .= $t . ':' . json_encode(q("SHOW CREATE TABLE `$t`"));
        if (in_array($t, ['acl_sessions', 'auth_logins', 'auth_remember_tokens', 'acl_activity_log'], true)) { $o .= "\n"; continue; }
        $rows = q("SELECT * FROM `$t`");
        foreach ($rows as &$r) unset($r['last_active'], $r['last_used_at'], $r['updated_at']);
        unset($r);
        $o .= json_encode($rows) . "\n";
    }
    return md5($o);
}

function mailpit(string $method, string $path)
{
    $c = curl_init(MP7 . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10]);
    $b = (string) curl_exec($c);
    curl_close($c);
    $j = json_decode($b, true);
    return $j ?? $b;
}

final class Client
{
    public string $jar;
    public int $status = 0;
    public string $body = '';
    public array $headers = [];
    public string $location = '';
    public array $extra = [];

    public function __construct(public string $base, public string $name = 'c')
    {
        $this->jar = sys_get_temp_dir() . '/rwmy8-' . $name . '-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.jar';
    }
    public function req(string $method, string $url, array|string|null $data = null, array $headers = []): self
    {
        $url = preg_match('#^https?://#', $url) ? $url : (str_starts_with($url, '/') ? preg_replace('#^(https?://[^/]+).*$#', '$1', $this->base) . $url : $this->base . $url);
        $headers = array_merge($this->extra, $headers);
        if (! preg_grep('/^Accept:/i', $headers)) $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $ch = curl_init($url);
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $h) { $p = explode(':', $h, 2); if (count($p) === 2) $this->headers[strtolower(trim($p[0]))] = trim($p[1]); return strlen($h); },
        ]);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        $this->body = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = $this->headers['location'] ?? '';
        $this->url = $url;
        curl_close($ch);
        return $this;
    }
    public string $url = '';
    public function get(string $u): string { $this->req('GET', $u); return $this->body; }
    /** GET following redirects (max 5); returns the final body. */
    public function go(string $u): string
    {
        $this->req('GET', $u);
        for ($i = 0; $i < 5 && in_array($this->status, [301, 302, 303, 307], true); $i++) $this->req('GET', abs_url($this->url, $this->location));
        return $this->body;
    }
    public function post(string $u, array $data): self { return $this->req('POST', $u, $data); }
    public function cookie(string $name): string { preg_match('/\t' . preg_quote($name, '/') . '\t([^\s]+)/', (string) @file_get_contents($this->jar), $m); return $m[1] ?? ''; }
}
function abs_url(string $from, string $loc): string
{
    if ($loc === '') return $from; // an empty form action is the page itself
    if (preg_match('#^https?://#', $loc)) return $loc;
    $p = parse_url($from);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($loc, '/')) return $origin . $loc;
    $dir = preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');
    return $origin . $dir . $loc;
}

function dom(string $h): DOMXPath { $d = new DOMDocument(); @$d->loadHTML('<?xml encoding="UTF-8">' . $h); return new DOMXPath($d); }
function forms(string $h): array
{
    $xp = dom($h); $out = [];
    foreach ($xp->query('//form') as $f) {
        $fields = []; $types = [];
        foreach ($xp->query('.//input', $f) as $i) {
            $n = $i->getAttribute('name'); if ($n === '') continue;
            $t = strtolower($i->getAttribute('type') ?: 'text');
            if (in_array($t, ['checkbox', 'radio'], true) && ! $i->hasAttribute('checked')) { $types[$n] = $t; continue; }
            $fields[$n] = $i->getAttribute('value'); $types[$n] = $t;
        }
        foreach ($xp->query('.//select', $f) as $s) {
            $v = null; foreach ($xp->query('.//option', $s) as $o) if ($v === null || $o->hasAttribute('selected')) $v = $o->hasAttribute('value') ? $o->getAttribute('value') : trim($o->textContent);
            $fields[$s->getAttribute('name')] = $v; $types[$s->getAttribute('name')] = 'select';
        }
        foreach ($xp->query('.//textarea', $f) as $t) { $fields[$t->getAttribute('name')] = $t->textContent; $types[$t->getAttribute('name')] = 'textarea'; }
        foreach ($xp->query('.//button[@name]', $f) as $b) { $types[$b->getAttribute('name')] = 'button:' . $b->getAttribute('value'); }
        $out[] = ['action' => $f->getAttribute('action'), 'method' => strtolower($f->getAttribute('method') ?: 'get'), 'fields' => $fields, 'types' => $types, 'text' => trim(preg_replace('/\s+/', ' ', $f->textContent)), 'html' => $f->ownerDocument->saveHTML($f)];
    }
    return $out;
}
function form_with(string $h, string $field): ?array { foreach (forms($h) as $f) if (array_key_exists($field, $f['types'])) return $f; return null; }
function text_of(string $h): string
{
    $xp = dom($h);
    foreach ($xp->query('//script|//style|//noscript') as $n) $n->parentNode->removeChild($n);
    $b = $xp->query('//body')->item(0);
    return trim(preg_replace('/\s+/u', ' ', $b ? $b->textContent : strip_tags($h)));
}
function csrf_of(string $h): string { return preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $h, $m) ? $m[1] : (preg_match('/<meta name="[^"]*csrf[^"]*" content="([^"]+)"/i', $h, $m) ? $m[1] : ''); }
function leaks_sql(string $b): bool
{
    $b = preg_replace('/<div id="debug-bar".*$/s', '', $b);
    $b = preg_replace('/<script[^>]*id="debugbar[^>]*>.*?<\/script>/s', '', $b);
    return (bool) preg_match('/(SQLSTATE|mysqli_sql_exception|You have an error in your SQL syntax|DatabaseException|Duplicate entry|foreign key constraint|Unknown column|Access denied for user|Unknown database|No such host|php_network_getaddresses|Connection refused|getaddrinfo|actively refused|Unable to connect to the database|errno|Unknown MySQL server host|Can\'t connect to MySQL)/i', $b);
}

/** Starts `php -S 127.0.0.1:8071` with the V7 router on $docroot; $prefix is the subfolder ('' for root). */
function server_start(string $docroot, string $prefix, string $log, array $env = []): void
{
    server_stop();
    $e = getenv();
    unset($e['RW_DB_USERNAME'], $e['RW_DB_PASSWORD'], $e['MY8_APP_USER'], $e['MY8_APP_PASS']); // the application under test never sees the harness credentials
    $e['RW7_PREFIX'] = $prefix;
    $e = array_merge($e, $env);
    $cmd = ['php', '-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-d', 'max_execution_time=30', '-S', '127.0.0.1:' . PORT7, '-t', $docroot, __DIR__ . '/verify-v7.router.php'];
    $p = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $docroot, $e, ['bypass_shell' => true]);
    $GLOBALS['SRV7'] = $p;
    $pid = proc_get_status($p)['pid'];
    $GLOBALS['SRV7_PIDS'][] = $pid;
    for ($i = 0; $i < 40; $i++) {
        usleep(250000);
        $s = @fsockopen('127.0.0.1', PORT7, $en, $es, 1);
        if ($s) { fclose($s); info("server pid=$pid on 127.0.0.1:" . PORT7 . " prefix='$prefix'"); return; }
    }
    server_stop();
    exit("server did not start\n");
}
function server_stop(): void
{
    if ($GLOBALS['SRV7']) {
        $pid = proc_get_status($GLOBALS['SRV7'])['pid'];
        proc_terminate($GLOBALS['SRV7']);
        proc_close($GLOBALS['SRV7']);
        $GLOBALS['SRV7'] = null;
        // closed by PID only, never by image name
        exec('tasklist /FI "PID eq ' . (int) $pid . '" /NH', $o);
        if (preg_grep('/php\.exe/i', $o)) exec('taskkill /PID ' . (int) $pid . ' /F');
    }
}
register_shutdown_function('server_stop');

function junction(string $link, string $target): void
{
    $cmd = ['powershell', '-NoProfile', '-Command', "New-Item -ItemType Junction -Path '$link' -Target '$target' | Out-Null"];
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    if (proc_close($p) !== 0 || ! is_dir($link)) throw new RuntimeException('junction failed');
}
function rrmdir(string $d): void
{
    if (! file_exists($d) && ! is_link($d)) return;
    // junctions first: removed as links, their target untouched
    if (is_link($d) || (is_dir($d) && readlink($d) !== false && realpath($d) !== $d)) { @rmdir($d); return; }
    if (is_dir($d)) {
        foreach (scandir($d) as $f) if ($f !== '.' && $f !== '..') rrmdir("$d/$f");
        @chmod($d, 0777); rmdir($d);
    } else { @chmod($d, 0666); unlink($d); }
}
function copytree(string $src, string $dst): void
{
    $cp = proc_open(['robocopy', $src, $dst, '/E', '/XJ', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    if (proc_close($cp) >= 8) throw new RuntimeException("copy failed $src");
}
function spark(string $app, array $args, array $env = []): string
{
    $e = array_merge(getenv(), $env);
    unset($e['RW_DB_USERNAME'], $e['RW_DB_PASSWORD'], $e['MY8_APP_USER'], $e['MY8_APP_PASS']);
    $p = proc_open(array_merge(['php', '-d', 'error_reporting=-1', 'spark'], $args), [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $app, $e, ['bypass_shell' => true]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $rc = proc_close($p);
    return "rc=$rc\n" . redact($out);
}
function logs_of(string $app): array
{
    $l = [];
    foreach (glob("$app/writable/logs/*.log") ?: [] as $f) $l = array_merge($l, file($f, FILE_IGNORE_NEW_LINES));
    return $l;
}
function bad_log_lines(array $lines): array
{
    return array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)));
}
