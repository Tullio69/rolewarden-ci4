<?php
/**
 * R1 copy of verify-v7.lib.php (Collaudatore ad Hoc): identical, except that the application under test runs on PHP 8.5
 * in Docker (image r1-col-php:8.5, official php:8.5 + intl, mysqli; error_reporting -1) instead of the host's PHP:
 *   server_start(): `php -S 0.0.0.0:8071` with the same router in container r1-srv, port 127.0.0.1:8071, the work folder
 *                   bind-mounted at /w and tests/Integration at /t (read only); database and Mailpit reached as
 *                   host.docker.internal (process environment of the container, which CodeIgniter's .env does not override);
 *   server_stop():  container removed, its output appended to the server log;
 *   spark():        `php spark` in a throwaway container of the same image, same mounts and environment;
 *   junction():     a real copy (robocopy) instead of an NTFS junction, which a Linux container cannot follow.
 * The harness itself, its HTTP client, mysqli and Chrome stay on the host.
 * Original header:
 * V7 "Confezione" black-box helpers (Collaudatore ad Hoc, Claude). HTTP only towards the served
 * application, mysqli only towards rolewarden_test on 127.0.0.1:3317. Credentials only from the
 * process variables RW_DB_USERNAME / RW_DB_PASSWORD, never written to a file by this harness.
 */
declare(strict_types=1);

const PORT7 = 8071;
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
    $p = (string) getenv('RW_DB_PASSWORD');
    return $p === '' ? $s : str_replace($p, '[omesso]', $s);
}

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        foreach (['RW_DB_USERNAME', 'RW_DB_PASSWORD'] as $v) if (getenv($v) === false) exit("Missing $v\n");
        $db = new mysqli('127.0.0.1', getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', 3317);
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
    db()->query('CREATE DATABASE rolewarden_test /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */');
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
        $this->jar = sys_get_temp_dir() . '/rwv7-' . $name . '-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.jar';
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

const R1IMG = 'r1-col-php:8.5';
const R1CT = 'r1-srv';
/** Host path (work folder or tests/Integration) -> path inside the container. */
function r1path(string $p): string
{
    $p = str_replace('\\', '/', $p);
    $w = rtrim(str_replace('\\', '/', (string) getenv('RW_R1_REGWORK')), '/');
    $t = str_replace('\\', '/', __DIR__);
    foreach ([[$w, '/w'], [$t, '/t']] as [$h, $c]) if ($h !== '' && stripos($p, $h) === 0) return $c . substr($p, strlen($h));
    throw new RuntimeException("path outside the mounts: $p");
}
function r1env(array $env): array
{
    $a = [];
    // R1 resumption: no hostname/port override here; CodeIgniter's DotEnv puts the dotted .env key in $_ENV, which wins over
    // the process environment, so database and Mailpit are reached through the sidecar r1-fwd (r1fwd()) at 127.0.0.1
    foreach ($env as $k => $v) { if ($v === false || $v === null) continue; $a[] = '-e'; $a[] = "$k=$v"; }
    return $a;
}
function r1mounts(): array
{
    return ['-v', str_replace('\\', '/', (string) getenv('RW_R1_REGWORK')) . ':/w', '-v', str_replace('\\', '/', __DIR__) . ':/t:ro'];
}
/** R1 resumption: sidecar r1-fwd (nginx stream, verify-r1.docker/fwd.conf) that owns the network namespace of the PHP containers and publishes 8071. */
function r1fwd(): array
{
    [$rc, $st] = r1run(['docker', 'inspect', '-f', '{{.State.Running}}', 'r1-fwd']);
    if (trim($st) !== 'true') {
        r1run(['docker', 'rm', '-f', 'r1-fwd']);
        [$rc, $o] = r1run(['docker', 'run', '-d', '--name', 'r1-fwd', '-p', '127.0.0.1:' . PORT7 . ':' . PORT7, '-v', str_replace('\\', '/', __DIR__) . '/verify-r1.docker/fwd.conf:/etc/nginx/nginx.conf:ro', 'nginx:alpine']);
        if ($rc !== 0) exit("sidecar r1-fwd did not start: $o
");
        usleep(1500000);
    }
    return ['--network', 'container:r1-fwd'];
}
function r1fwd_stop(): void { r1run(['docker', 'rm', '-f', 'r1-fwd']); }
function r1run(array $cmd): array
{
    $p = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($p), $o];
}
/** Starts `php -S 0.0.0.0:8071` with the V7 router on $docroot inside the PHP 8.5 container; $prefix is the subfolder. */
function server_start(string $docroot, string $prefix, string $log, array $env = []): void
{
    server_stop();
    $e = ['RW7_PREFIX' => $prefix] + $env;
    unset($e['RW_DB_USERNAME'], $e['RW_DB_PASSWORD']);
    $cmd = array_merge(['docker', 'run', '-d', '--name', R1CT], r1fwd(), r1mounts(), r1env($e), ['-w', r1path($docroot), '--entrypoint', 'php', R1IMG, /* R1 resumption: the image entrypoint runs through sh (dash), which drops the dotted database.* variables */
        '-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-d', 'max_execution_time=30', '-d', 'memory_limit=512M', '-S', '0.0.0.0:' . PORT7, '-t', r1path($docroot), '/t/verify-v7.router.php']);
    [$rc, $o] = r1run($cmd);
    if ($rc !== 0) { file_put_contents($log, "docker run failed: $o\n", FILE_APPEND); exit("server did not start (docker run rc=$rc)\n"); }
    $GLOBALS['SRV7'] = $log;
    for ($i = 0; $i < 80; $i++) {
        usleep(250000);
        $s = @fsockopen('127.0.0.1', PORT7, $en, $es, 1);
        if ($s) {
            fclose($s);
            [$rc2, $st] = r1run(['docker', 'inspect', '-f', '{{.State.Running}}', R1CT]);
            if (trim($st) === 'true') { usleep(500000); info('server ' . R1CT . ' (' . R1IMG . ") on 127.0.0.1:" . PORT7 . " prefix='$prefix' docroot " . r1path($docroot)); return; }
        }
    }
    server_stop();
    exit("server did not start\n");
}
function server_stop(): void
{
    if ($GLOBALS['SRV7']) {
        [$rc, $o] = r1run(['docker', 'logs', R1CT]);
        @file_put_contents($GLOBALS['SRV7'], $o, FILE_APPEND);
        r1run(['docker', 'rm', '-f', R1CT]);
        $GLOBALS['SRV7'] = null;
    }
}
register_shutdown_function(function () { server_stop(); r1fwd_stop(); }); // R1 resumption: sidecar removed too

/** R1: a real copy instead of a junction (a Linux container cannot follow an NTFS junction). */
function junction(string $link, string $target): void
{
    copytree($target, $link);
    if (! is_dir($link)) throw new RuntimeException('copy instead of junction failed');
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
    unset($env['RW_DB_USERNAME'], $env['RW_DB_PASSWORD']);
    $cmd = array_merge(['docker', 'run', '--rm', '-i'], r1fwd(), r1mounts(), r1env($env), ['-w', r1path($app), '--entrypoint', 'php', R1IMG, '-d', 'error_reporting=-1', 'spark'] /* R1 resumption: see server_start() */, $args);
    $p = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
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
