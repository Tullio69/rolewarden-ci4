<?php
// S2 copy of verify-s1.php (Collaudatore ad Hoc): same suite, only the phase loader changes (output names verify-s2.*).
/**
 * S1 black-box suite (Collaudatore ad Hoc, Claude): step 1 of the publication roadmap (30de388 against b1cca14):
 * one language ($languages), no sideways scroll below 1440px, seeded roles and permissions translated, V8 A1.
 * Same setup as verify-v8b.php (copied), plus phases N (languages), Y (layout at 1280/1366/1440/1920 with data of
 * its own), T (translation of seeded names), D (demo removal and real users' log rows), Q (saved choices when app/Config/RoleWarden.php is added), W (baseline matrix widths), B (clean-install seed vs b1cca14).
 * A phase without an S1 file runs the V8b phase file of the same letter, its fixed output names under __DIR__
 * renamed verify-s1.v8b.* so that no V8b file is overwritten.
 * Usage: php verify-s1.php <work> <mod> <modfull> <app-test> [phases] [label]   (<work>/basefull = b1cca14 + demo)
 *
 * Header of verify-v8b.php follows.
 * V8b black-box suite (Collaudatore ad Hoc, Claude): the changes after V8 (baseline be64b53) up to 6d21875.
 * Assertions only from docs/SPEC.md, docs/BRIEF-v1.0.md, CHANGELOG.md, README.md, documentation/,
 * docs/design-system/README.md (permission marks) and components/PermissionMatrix/README.md, the language
 * files (translation only) and the HTML/CSS/JS a browser receives. No controller, model, view or adapter read.
 * HTTP towards php -S (verify-v7 router), mysqli towards rolewarden_test on 127.0.0.1:3317 only,
 * Chrome headless (verify-v8b.browser.mjs) with a throwaway profile under the work folder.
 *
 * Usage: php verify-v8b.php <work> <mod> <modfull> <app-test> [phases] [label]
 *   phases: X (probe, exploration only), L (language switch), M (matrices), P (Permissions "Granted by"),
 *           I (installer), Y (layout), G (logs)
 */
declare(strict_types=1);

require __DIR__ . '/verify-v8.lib.php';

[$_, $WORK, $MOD, $MODFULL, $APPTEST] = $argv + array_fill(0, 5, null);
$PHASES = explode(',', $argv[5] ?? 'L,M,P,Y,I,G');
$LABEL = $argv[6] ?? 'run';
foreach ([$WORK, $MOD, $MODFULL, $APPTEST] as $d) if (! $d || ! is_dir($d)) exit("missing dir $d\n");
$WORK = str_replace('\\', '/', $WORK);
if (tables() !== []) exit("ABORT: rolewarden_test is not empty at start\n");
if ((mailpit('GET', 'messages')['messages_count'] ?? -1) !== 0) exit("ABORT: Mailpit not empty at start\n");

const OPW = 'Owner-Lantern-5521';
const DPW = 'Demo-Pass-8437-Qx';
const MAILENV8 = "email.protocol = smtp\nemail.SMTPHost = 127.0.0.1\nemail.SMTPPort = 1026\nemail.SMTPCrypto = ''\nemail.fromEmail = noreply@rolewarden.test\nemail.fromName = 'RoleWarden Test'\n";
const BASE = 'http://127.0.0.1:8071/';

$DBENV = ['database.default.username' => getenv('RW_DB_USERNAME'), 'database.default.password' => getenv('RW_DB_PASSWORD'), 'logger.threshold' => '9'];
$SERVERLOGS = [];
$APPS = [];
$SEEN = [];
$EN = require "$MOD/src/Language/en/RoleWarden.php";
$IT = require "$MOD/src/Language/it/RoleWarden.php";

function seen(Client $c, string $tag): string { $GLOBALS['SEEN'][] = [$tag, $c->status, $c->url, $c->body]; return $c->body; }
function G(Client $c, string $u, string $tag = ''): string { $c->get($u); return seen($c, $tag ?: "GET $u"); }
function P(Client $c, string $u, array $d, string $tag = '', array $h = []): string { $c->req('POST', $u, $d, $h); return seen($c, $tag ?: "POST $u"); }
function sp8(string $app, array $args, array $env = []): string { return spark($app, $args, $GLOBALS['DBENV'] + $env); }
function rc(string $out): int { return (int) (preg_match('/^rc=(-?\d+)/', $out, $m) ? $m[1] : -99); }
function short(string $s, int $n = 400): string { $s = trim(preg_replace('/\s+/', ' ', $s)); return strlen($s) > $n ? substr($s, 0, $n) . '...' : $s; }
function phperr(string $s): bool { return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|ErrorException|Uncaught|TypeError|ArgumentCountError)/', $s); }
function L(array $lang, string $path): string { $v = $lang; foreach (explode('.', $path) as $k) $v = $v[$k] ?? null; return is_string($v) ? $v : ''; }

function app_copy(string $name, string $mod, bool $wireDemo, string $envName = '', string $extraEnv = ''): string
{
    global $WORK, $APPTEST, $APPS;
    $T = "$WORK/$name";
    if ($envName === '') $envName = getenv('RW_V8B_ENV') ?: 'development';
    $cp = proc_open(['robocopy', $APPTEST, $T, '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', 'builds', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp);
    stream_get_contents($pp[1]); stream_get_contents($pp[2]);
    if (proc_close($cp) >= 8) throw new RuntimeException('copy failed');
    foreach (['cache', 'session', 'logs', 'uploads', 'debugbar', 'tmp'] as $d) @mkdir("$T/writable/$d", 0777, true);
    $j = "$T/vendor/rolewarden/codeigniter4-rolewarden";
    @mkdir(dirname($j), 0777, true);
    if (is_dir($j)) @rmdir($j);
    junction($j, $mod);
    if (! $wireDemo) {
        $a = preg_replace("/^\s*'RoleWardenDemo'\s*=>.*\n/m", '', file_get_contents("$T/app/Config/Autoload.php"));
        file_put_contents("$T/app/Config/Autoload.php", $a);
        $r = preg_replace("/^.*RoleWardenDemo.*\n/m", '', file_get_contents("$T/app/Config/Routes.php"));
        file_put_contents("$T/app/Config/Routes.php", $r);
    }
    write_env($T, $envName, $extraEnv);
    $APPS[] = $T;
    return $T;
}
function write_env(string $T, string $envName, string $extra = ''): void
{
    file_put_contents("$T/.env", "CI_ENVIRONMENT = $envName\napp.baseURL = '" . BASE . "'\ndatabase.default.hostname = 127.0.0.1\ndatabase.default.port = 3317\ndatabase.default.database = rolewarden_test\ndatabase.default.DBDriver = MySQLi\nlogger.threshold = 9\nrolewarden.signInRate = 1000\n" . MAILENV8 . $extra);
    $e = file_get_contents("$T/.env");
    foreach (['email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026'] as $l) if (! str_contains($e, $l)) exit("ABORT: mail not on Mailpit\n");
}
function serve8(string $app, string $tag, array $env = []): Client
{
    global $WORK, $DBENV;
    $GLOBALS['SERVERLOGS'][] = "$WORK/server-$tag.log";
    server_start("$app/public", '', "$WORK/server-$tag.log", $DBENV + $env);
    return new Client(BASE, $tag);
}
function base_install(string $app): bool
{
    empty_db();
    $ok = true;
    foreach ([['migrate', '-n', 'CodeIgniter\Settings'], ['migrate', '-n', 'CodeIgniter\Shield'], ['migrate', '-n', 'RoleWarden'], ['db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder']] as $a) {
        $o = sp8($app, $a);
        if (rc($o) !== 0) { info('spark ' . implode(' ', $a) . ': ' . short($o)); $ok = false; }
    }
    return $ok;
}
function c8(string $name): Client { return new Client(BASE, $name); }
function login8(Client $c, string $email, string $pw): bool
{
    $h = G($c, 'login', "login form $email");
    $f = form_with($h, 'password');
    if (! $f) return false;
    $v = array_replace($f['fields'], ['email' => $email, 'password' => $pw]);
    unset($v['remember']);
    P($c, 'login', $v, "login $email");
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, '/login');
}
function role_id(string $slug): int { return (int) q1('SELECT id FROM acl_roles WHERE slug=?', [$slug]); }
function perm_id(string $slug): int { return (int) q1('SELECT id FROM acl_permissions WHERE slug=?', [$slug]); }
function h1(string $h): string { $xp = dom($h); $n = $xp->query('//h1')->item(0); return $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : ''; }
function html_lang(string $h): string { return preg_match('/<html[^>]*\blang="([^"]*)"/i', $h, $m) ? $m[1] : ''; }

/** Runs a Chrome job; returns the decoded result. */
function browser(array $steps, string $tag, int $timeout = 300000): array
{
    global $WORK;
    static $n = 0; $n++;
    $job = ['profile' => "$WORK/chrome-v8b-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 400];
    $jf = "$WORK/job-$n.json";
    file_put_contents($jf, json_encode($job));
    $p = proc_open(['node', __DIR__ . '/verify-v8b.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'profileRemoved' => false]; }
    if ($r['errors']) info("browser $tag errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    return $r;
}
function bstep(array $r, string $tag) { foreach ($r['steps'] as $s) if (($s['tag'] ?? '') === $tag) return $s; return null; }
function bval(array $r, string $tag) { return bstep($r, $tag)['value'] ?? null; }
function blogin(string $email, string $pw, string $tag = 'login'): array { return ['op' => 'login', 'url' => BASE . 'login', 'email' => $email, 'password' => $pw, 'tag' => $tag]; }

// ---------------------------------------------------------------- setup common to every phase
$APP = app_copy('app', $MODFULL, true);
info('CI_ENVIRONMENT of the copies: ' . (getenv('RW_V8B_ENV') ?: 'development'));
check('S01', 'Base install (Settings, Shield, RoleWarden migrations and seed) on rolewarden_test', base_install($APP));
$OWNER = make_owner('owner@v8b.test', OPW, 'v8bowner', 'super-admin');
$ADMIN = make_owner('admin@v8b.test', OPW, 'v8badmin', 'admin');
$PLAIN = make_owner('plain@v8b.test', OPW, 'v8bplain', 'user');
$o = sp8($APP, ['demo:install'], ['demo.password' => DPW]);
check('S02', 'demo:install for the demo pages (exit 0)', rc($o) === 0, short($o));
foreach ($PHASES as $ph) {
    // S2: the S1 phase file of the letter, else the V8b one; every file it writes under tests/Integration is renamed
    // verify-s2.s1.* / verify-s2.v8b.* (S2 writes nothing else), the copy runs from the work folder.
    $src = __DIR__ . '/verify-s1.phase-' . strtolower($ph) . '.php';
    if (! is_file($src)) $src = __DIR__ . '/verify-v8b.phase-' . strtolower($ph) . '.php';
    if (! is_file($src)) { info("phase $ph: no file"); continue; }
    $here = var_export(__DIR__, true);
    $code = file_get_contents($src);
    $code = str_replace(["__DIR__ . '/' . basename(\$f)"], ["$here . '/verify-s2.' . preg_replace('/^verify-(s1|v8b)\\./', '\$1.', basename(\$f))"], $code);
    $code = str_replace(["__DIR__ . '/verify-s1.", '__DIR__ . "/verify-s1.', "__DIR__ . '/verify-v8b.", '__DIR__ . "/verify-v8b.'], ["$here . '/verify-s2.s1.", "$here . \"/verify-s2.s1.", "$here . '/verify-s2.v8b.", "$here . \"/verify-s2.v8b."], $code);
    $code = str_replace(["'/verify-s2.s1.v8b.", '"/verify-s2.s1.v8b.'], ["'/verify-s2.v8b.", '"/verify-s2.v8b.'], $code);
    if (str_contains($code, '__DIR__')) exit("ABORT: phase $ph still uses __DIR__\n");
    $f = "$WORK/verify-s2.phase-" . strtolower($ph) . '.php';
    file_put_contents($f, $code);
    echo "\n===== phase $ph =====\n";
    require $f;
    server_stop();
}

// ---------------------------------------------------------------- results
server_stop();
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
printf("\nTOTAL: %d PASS, %d FAIL, %d AMB\n", $pass, $fail, count($GLOBALS['ambiguities']));
foreach ($GLOBALS['results'] as $r) if (! $r[2]) echo "FAIL {$r[0]} {$r[1]}\n";
exit($fail ? 1 : 0);
