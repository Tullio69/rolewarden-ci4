<?php
/**
 * V7 "Confezione": independent black-box verification (Collaudatore ad Hoc, Claude) of commit
 * 2836697 and of the two zips built from it (build/rolewarden-app-1.0.0-rc.v7.zip,
 * build/rolewarden-module-1.0.0-rc.v7.zip). Assertions come only from docs/SPEC.md (Modello dati,
 * Pannello admin with the V7 decisions of 2026-10-07), docs/BRIEF-v1.0.md (V7, definition of done,
 * Pubblicazione, protocol), README ("Two ways to install", "The browser installer", "Building the
 * zips", "Languages") and the mandate. Field names, routes and messages are read from served HTML.
 *
 * Usage: php verify-v7.php <work-dir> <app-zip-extracted> <module-zip-extracted> <module-2836697> <app-test>
 * Only rolewarden_test on 127.0.0.1:3317; credentials only from RW_DB_USERNAME / RW_DB_PASSWORD.
 * The application under test never receives them as process variables except in phase B06 (the
 * copy of the test application, configured like every earlier regression run); the installer gets
 * them through its own form, and writes them to the .env of a throwaway copy that is deleted.
 * Mail only to Mailpit (smtp 127.0.0.1:1026); PHP's mail() disabled in every server.
 */
declare(strict_types=1);
require __DIR__ . '/verify-v7.lib.php';

[$_, $WORK, $ZIPAPP, $ZIPMOD, $MOD, $APPTEST] = $argv + array_fill(0, 6, null);
foreach ([$WORK, $ZIPAPP, $ZIPMOD, $MOD, $APPTEST] as $d) if (! $d || ! is_dir($d)) exit("missing dir $d\n");
$WORK = str_replace('\\', '/', $WORK);
if (tables() !== []) exit("ABORT: rolewarden_test is not empty at start\n");
if ((mailpit('GET', 'messages')['messages_count'] ?? -1) !== 0) exit("ABORT: Mailpit not empty at start\n");

const SRVENV = ['logger.threshold' => '9', 'database.default.hostname' => '127.0.0.1', 'database.default.port' => '3317'];
const MAILENV = "\nemail.protocol = smtp\nemail.SMTPHost = 127.0.0.1\nemail.SMTPPort = 1026\nemail.SMTPCrypto = ''\nemail.fromEmail = noreply@rolewarden.test\nemail.fromName = 'RoleWarden Test'\nlogger.threshold = 9\n";
const PW = 'Quartz-Lantern-9183';
$SEEN = [];          // every response body seen, for the publication scans
$LOGS = [];          // app log lines per phase
$SERVERLOGS = [];

function seen(Client $c, string $tag): string { $GLOBALS['SEEN'][] = [$tag, $c->status, $c->url, $c->body]; return $c->body; }
function G(Client $c, string $u, string $tag = ''): string { $c->get($u); return seen($c, $tag ?: $u); }
function P(Client $c, string $u, array $d, string $tag = ''): string { $c->post($u, $d); return seen($c, $tag ?: "POST $u"); }

/** Fresh copy of the full application (zip) at $WORK/$name, served at $prefix ('' or '/sub'). */
function fresh_app(string $name, string $prefix): array
{
    global $WORK, $ZIPAPP;
    $app = "$WORK/$name";
    copytree($ZIPAPP, $app);
    if ($prefix === '') return [$app, "$app/public"];
    mkdir("$WORK/root-$name");
    junction("$WORK/root-$name" . $prefix, "$app/public");
    return [$app, "$WORK/root-$name"];
}
function serve(string $app, string $docroot, string $prefix, string $tag, array $env = SRVENV): Client
{
    global $WORK;
    $GLOBALS['SERVERLOGS'][] = "$WORK/server-$tag.log";
    server_start($docroot, $prefix, "$WORK/server-$tag.log", $env);
    return new Client('http://127.0.0.1:' . PORT7 . $prefix . '/', $tag);
}
function code_of(string $app): string { return trim((string) @file_get_contents("$app/writable/rolewarden/install-code.txt")); }
function step_of(string $h): string { $f = form_with($h, 'step'); return $f['fields']['step'] ?? (str_contains($h, 'href="">') ? 'link' : 'none'); }
function inst_post(Client $c, array $values, string $tag, bool $csrf = true): string
{
    $h = G($c, 'rolewarden/install', "GET install before $tag");
    $tok = csrf_of($h);
    if ($tok === '') { $h2 = G($c, 'login', 'GET login for token'); $tok = csrf_of($h2); }
    $d = $values;
    if ($csrf) $d = ['csrf_test_name' => $tok] + $d;
    return P($c, 'rolewarden/install', $d, $tag);
}
function errors_text(string $h): string { return text_of($h); }
function env_map(string $file): array
{
    $o = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $l) if (preg_match('/^\s*([A-Za-z_.]+)\s*=\s*(.*)$/', $l, $m)) $o[$m[1]] = trim($m[2], " \t\"'");
    return $o;
}
function login(Client $c, string $email, string $pw): bool
{
    $h = G($c, 'login', 'GET login');
    $f = form_with($h, 'password');
    if (! $f) return false;
    $v = array_replace($f['fields'], ['email' => $email, 'password' => $pw]);
    unset($v['remember']);
    P($c, 'login', $v, 'POST login');
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, 'login');
}
function options(string $h, string $name): array
{
    $xp = dom($h); $o = [];
    foreach ($xp->query('//select[@name="' . $name . '"]/option') as $op) $o[$op->getAttribute('value')] = trim($op->textContent);
    return $o;
}
function uid(string $email): ?int { $v = q1("SELECT user_id FROM auth_identities WHERE type='email_password' AND secret=?", [$email]); return $v === null ? null : (int) $v; }
function flat(array $a, string $p = ''): array { $o = []; foreach ($a as $k => $v) { if (is_array($v)) $o += flat($v, "$p$k."); else $o["$p$k"] = (string) $v; } return $o; }
function ilocale(string $h): string { return preg_match('/<html[^>]*\blang="([^"]*)"/', $h, $m) ? $m[1] : ''; }

/** Panel screens a super admin can open, with the ids that exist. */
function screens(int $uid, int $rid): array
{
    return ['rolewarden/users', 'rolewarden/users/create', 'rolewarden/users/import', "rolewarden/users/$uid", "rolewarden/users/$uid/edit",
        'rolewarden/roles', 'rolewarden/roles/create', "rolewarden/roles/$rid", "rolewarden/roles/$rid/edit", 'rolewarden/permissions',
        'rolewarden/settings', 'rolewarden/activity', 'rolewarden/appearance', 'rolewarden/profile', 'rolewarden/sessions'];
}

/* ============================================================== A: main path, root, empty DB, no .env */
echo "\n== A: installer from zero (full application zip, document root public/, empty database, no .env)\n";
[$A, $Adoc] = fresh_app('appA', '');
check('A00', 'Zip application ships without .env and without writable/rolewarden', ! file_exists("$A/.env") && ! file_exists("$A/writable/rolewarden"));
$c = serve($A, $Adoc, '', 'A');
$h = G($c, '', 'GET / before install');
check('A01', 'Home before installation redirects to the installer', in_array($c->status, [302, 303], true) && abs_url($c->url, $c->location) === 'http://127.0.0.1:8071/rolewarden/install', "status {$c->status} location {$c->location}");
check('A02a', 'Setup code file does not exist before the first visit', ! file_exists("$A/writable/rolewarden/install-code.txt"));
$h = G($c, 'rolewarden/install', 'GET install first');
$code = code_of($A);
check('A02', 'First visit: 200, requirement list with PHP 8.3, intl, mbstring, json, mysqli, writable/ and .env', $c->status === 200 && (bool) preg_match('/PHP 8\.3[^<]*or later/', $h) && str_contains($h, 'intl') && str_contains($h, 'mbstring') && str_contains($h, 'json') && str_contains($h, 'mysqli') && str_contains($h, 'writable/') && str_contains($h, '.env'), text_of($h));
check('A03', 'Setup code file created in writable/rolewarden/install-code.txt at the first visit, and not printed in the page', strlen($code) >= 8 && ! str_contains($h, $code), 'len ' . strlen($code));
check('A03b', 'Code step asks for the code (form with step=code and a code field)', step_of($h) === 'code' && form_with($h, 'code') !== null);
check('A03c', '<html lang> is "en" with no Accept-Language', ilocale($h) === 'en', ilocale($h));
function asset_status(Client $c, string $h): array
{
    $o = []; preg_match_all('/<(?:link|script)[^>]+(?:href|src)="([^"]+)"/', $h, $mm);
    foreach ($mm[1] as $a) { $abs = abs_url($c->url, html_entity_decode($a)); $t = new Client($c->base, 'asset'); $t->get($abs); seen($t, "asset $abs"); $o[] = "$abs {$t->status} " . ($t->headers['content-type'] ?? ''); }
    return $o;
}
$as = asset_status($c, $h);
check('A03d', 'Before installation the installer\'s stylesheet loads (200, text/css)', $as !== [] && ! array_filter($as, fn ($a) => ! preg_match('/ 200 text\/css/', $a) && str_contains($a, '.css')), json_encode($as));
// Accept-Language: Italian browser on the installer
$ci = new Client($c->base, 'A-it'); $ci->extra = ['Accept-Language: it-IT,it;q=0.9,en;q=0.5'];
$hi = G($ci, 'rolewarden/install', 'GET install it');
check('A04', 'Installer in Italian for an Italian browser (Accept-Language), lang="it"', ilocale($hi) === 'it' && str_contains($hi, 'Installa RoleWarden') && str_contains($hi, 'Codice di installazione'), ilocale($hi) . ' ' . substr(text_of($hi), 0, 200));
$ce = new Client($c->base, 'A-de'); $ce->extra = ['Accept-Language: de-DE,de;q=0.9'];
$he = G($ce, 'rolewarden/install', 'GET install de');
info('Italian installer text: ' . substr(text_of($hi), 0, 700));
check('A04b', 'A browser asking for an unsupported language gets English', ilocale($he) === 'en' && str_contains($he, 'Install RoleWarden'), ilocale($he));
check('A04c', 'Setup code not regenerated by later visits', code_of($A) === $code);

// Wrong / empty code, CSRF
$h = inst_post($c, ['step' => 'code', 'code' => 'WRONG-' . $code], 'code wrong');
check('A05', 'Wrong code refused with a message, still on the code step', $c->status === 200 && step_of($h) === 'code' && str_contains($h, 'not the setup code'), text_of($h));
$h = inst_post($c, ['step' => 'code', 'code' => ''], 'code empty');
check('A05b', 'Empty code refused, still on the code step', step_of($h) === 'code' && ! form_with($h, 'hostname'), text_of($h));
$h = inst_post($c, ['step' => 'code', 'code' => '"><script>alert(7)</script>'], 'code markup');
check('A05c', 'Markup typed as the code is not echoed raw', ! str_contains($h, '<script>alert(7)') && step_of($h) === 'code');
$h = inst_post($c, ['step' => 'code', 'code' => $code], 'code no csrf', false);
check('A06', 'Right code without CSRF token is refused (no database form)', ! form_with($h, 'hostname'), "status {$c->status}");
$h = inst_post($c, ['step' => 'code', 'code' => $code, 'csrf_test_name' => str_repeat('a', 32)], 'code bad csrf', false);
check('A06b', 'Right code with a wrong CSRF token is refused', ! form_with($h, 'hostname'), "status {$c->status}");
$h = G($c, 'rolewarden/install', 'GET after refused code');
check('A06c', 'After the refused attempts the installer is still at the code step', step_of($h) === 'code');
$h = inst_post($c, ['step' => 'code', 'code' => " $code "], 'code ok');
$f = form_with($h, 'hostname');
check('A07', 'Right code opens the database step', $f !== null, text_of($h));
check('A07b', 'Proposed application address is the real one (http://127.0.0.1:8071/)', ($f['fields']['baseURL'] ?? '') === 'http://127.0.0.1:8071/', $f['fields']['baseURL'] ?? '');
check('A07c', 'Database password field is a password input and is not prefilled', ($f['types']['password'] ?? '') === 'password' && ($f['fields']['password'] ?? '') === '');
// Validation
$h = inst_post($c, ['step' => 'database', 'baseURL' => 'not a url', 'hostname' => '', 'port' => 'abc', 'database' => '', 'username' => '', 'password' => ''], 'db invalid');
$t = text_of($h);
check('A08', 'Database step validation: address, server, port, name and user each get an error', substr_count($t, 'field') >= 5 && str_contains($t, 'valid URL') && form_with($h, 'hostname') !== null && ! file_exists("$A/.env"), $t);
// Failures: wrong password, closed port, missing database
$logBefore = count(logs_of($A));
$fails = [
    'wrong password' => ['hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => 'not-the-password-7'],
    'closed port' => ['hostname' => '127.0.0.1', 'port' => '3399', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')],
    'unknown host' => ['hostname' => 'db.invalid', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')],
    'missing database' => ['hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rw7_does_not_exist', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')],
    'markup in fields' => ['hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rw"<b>x</b>\'', 'username' => 'u"<i>y</i>', 'password' => 'p"<q>'],
];
foreach ($fails as $k => $v) {
    $t0 = microtime(true);
    $h = inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/'] + $v, "db $k");
    $txt = text_of($h);
    check('A09-' . substr(md5($k), 0, 3), "Connection failure ($k): generic message, no SQL or driver detail on screen, no .env written", str_contains($txt, 'Could not connect to the database') && ! leaks_sql($h) && ! file_exists("$A/.env") && form_with($h, 'hostname') !== null, $txt . sprintf(' (%.1fs)', microtime(true) - $t0));
    if ($k === 'markup in fields') {
        check('A09m', 'Markup and quotes typed in the database fields are escaped when shown again', ! str_contains($h, '<b>x</b>') && ! str_contains($h, '<i>y</i>') && ! str_contains($h, '<q>') && str_contains($h, 'rw&quot;&lt;b&gt;x'), substr($h, strpos($h, 'rw-install-database') ?: 0, 300));
    }
}
check('A09d', 'A failed connection never creates the database it was given', q('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', ['rw7_does_not_exist']) === []);
$newlog = array_slice(logs_of($A), $logBefore);
check('A09l', 'The reason of a failed connection goes to writable/logs', (bool) preg_grep('/Access denied|connection failed|Unable to connect/i', $newlog));
// Success
$h = inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'db ok');
$env = is_file("$A/.env") ? env_map("$A/.env") : [];
check('A10', 'Successful connection: .env written with the address and the database settings', ($env['app.baseURL'] ?? '') === 'http://127.0.0.1:8071/' && ($env['database.default.hostname'] ?? '') === '127.0.0.1' && ($env['database.default.port'] ?? '') === '3317' && ($env['database.default.database'] ?? '') === 'rolewarden_test' && ($env['database.default.username'] ?? '') === getenv('RW_DB_USERNAME') && ($env['database.default.password'] ?? '') === getenv('RW_DB_PASSWORD'), json_encode(array_keys($env)));
check('A10b', 'New encryption key generated (hex2bin, 32 bytes)', (bool) preg_match('/^hex2bin:[0-9a-f]{64}$/', $env['encryption.key'] ?? ''));
check('A10c', 'CI_ENVIRONMENT set to production when absent', ($env['CI_ENVIRONMENT'] ?? '') === 'production', $env['CI_ENVIRONMENT'] ?? 'absent');
check('A10d', 'Page says the settings are saved and offers to continue; no SQL detail', str_contains(text_of($h), 'Settings saved') && ! leaks_sql($h), text_of($h));
check('A10e', 'No table created yet by the database step', tables() === []);
// Step skip at this point: admin before tables
$h = inst_post($c, ['step' => 'admin', 'username' => 'early', 'email' => 'early@example.test', 'password' => PW, 'password_confirm' => PW], 'admin before tables');
check('A11s', 'POST of the admin step before the tables: nothing created, no SQL on screen', tables() === [] && ! leaks_sql($h), text_of($h));
$h = G($c, 'rolewarden/install', 'GET migrate form');
check('A11a', 'Next step offers "Create the tables"', step_of($h) === 'migrate', text_of($h));
$h = inst_post($c, ['step' => 'migrate'], 'migrate no csrf', false);
check('A11c', 'Tables step without CSRF token refused, nothing created', tables() === [], "status {$c->status}");
$t0 = microtime(true);
$h = inst_post($c, ['step' => 'migrate'], 'migrate');
$tb = tables();
info(sprintf('migration step took %.1fs; tables: %s', microtime(true) - $t0, implode(',', $tb)));
$need = ['settings', 'users', 'auth_identities', 'auth_logins', 'auth_remember_tokens', 'auth_groups_users', 'auth_permissions_users', 'auth_token_logins', 'acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles', 'acl_user_permissions', 'acl_sessions', 'acl_activity_log', 'migrations'];
check('A11', 'Tables of Settings, Shield and RoleWarden created', array_diff($need, $tb) === [], json_encode(array_values(array_diff($need, $tb))));
$batches = q('SELECT namespace, GROUP_CONCAT(DISTINCT batch) b FROM migrations GROUP BY namespace');
$bmap = array_column($batches, 'b', 'namespace');
check('A11b', 'Settings, Shield and RoleWarden each in a batch of its own (README)', count($bmap) === 3 && count(array_unique($bmap)) === 3 && ! array_filter($bmap, fn ($b) => str_contains($b, ',')), json_encode($bmap));
$roles = q('SELECT slug, is_system, is_super_admin FROM acl_roles WHERE deleted_at IS NULL ORDER BY id');
check('A12', 'Only the system roles exist (no Shield sample group turned into a role)', $roles !== [] && ! array_filter($roles, fn ($r) => (int) $r['is_system'] !== 1) && in_array('super-admin', array_column($roles, 'slug'), true), json_encode($roles));
$perms = q('SELECT slug, is_system FROM acl_permissions');
check('A12b', 'Only system permissions exist (no Shield sample permission imported)', $perms !== [] && ! array_filter($perms, fn ($p) => (int) $p['is_system'] !== 1), json_encode(array_filter($perms, fn ($p) => (int) $p['is_system'] !== 1)));
check('A12c', 'No user yet, no Shield group assignment', (int) q1('SELECT COUNT(*) FROM users') === 0 && (int) q1('SELECT COUNT(*) FROM auth_groups_users') === 0);
check('A12d', 'Tables page without SQL detail', ! leaks_sql($h));
$out = spark($A, ['migrate:status']);
check('A10f', '.env readable by CodeIgniter: spark, without any harness variable, connects with it and lists the migrations', str_contains($out, 'rc=0') && str_contains($out, 'RoleWarden') && ! preg_match('/Unable to connect|Access denied/i', $out), substr($out, 0, 400));
$state = dbstate();
$h = inst_post($c, ['step' => 'migrate'], 'migrate again');
check('A12e', 'Tables step posted twice changes nothing', dbstate() === $state && ! leaks_sql($h), text_of($h));
// First super admin
$h = G($c, 'rolewarden/install', 'GET admin form');
$f = form_with($h, 'password_confirm') ?? form_with($h, 'email');
check('A13a', 'Admin step offers username, email, password and its repetition', $f !== null && isset($f['types']['username'], $f['types']['email'], $f['types']['password']), text_of($h));
$bad = [
    'short password' => ['username' => 'boss7', 'email' => 'boss7@example.test', 'password' => 'short', 'password_confirm' => 'short'],
    'common password' => ['username' => 'boss7', 'email' => 'boss7@example.test', 'password' => 'password123', 'password_confirm' => 'password123'],
    'personal password' => ['username' => 'boss7', 'email' => 'boss7@example.test', 'password' => 'boss7boss7boss7', 'password_confirm' => 'boss7boss7boss7'],
    'mismatch' => ['username' => 'boss7', 'email' => 'boss7@example.test', 'password' => PW, 'password_confirm' => PW . 'x'],
    'bad email' => ['username' => 'boss7', 'email' => 'not-an-email', 'password' => PW, 'password_confirm' => PW],
    'bad username' => ['username' => '<b>x</b>"', 'email' => 'boss7@example.test', 'password' => PW, 'password_confirm' => PW],
    'empty' => ['username' => '', 'email' => '', 'password' => '', 'password_confirm' => ''],
];
foreach ($bad as $k => $v) {
    $h = inst_post($c, ['step' => 'admin'] + $v, "admin $k");
    check('A13-' . substr(md5($k), 0, 3), "Admin step refuses: $k (Shield rules), no user created, message shown", (int) q1('SELECT COUNT(*) FROM users') === 0 && form_with($h, 'password_confirm') !== null && ! leaks_sql($h) && ! str_contains($h, '<b>x</b>'), text_of($h));
    info("admin $k -> " . substr(text_of($h), 0, 260));
}
$h = inst_post($c, ['step' => 'admin', 'username' => 'boss7', 'email' => 'boss7@example.test', 'password' => PW, 'password_confirm' => PW], 'admin no csrf', false);
check('A13c', 'Admin step without CSRF token refused, no user', (int) q1('SELECT COUNT(*) FROM users') === 0);
$h = inst_post($c, ['step' => 'admin', 'username' => 'boss7', 'email' => 'boss7@example.test', 'password' => PW, 'password_confirm' => PW], 'admin ok');
$u = uid('boss7@example.test');
check('A13', 'First super admin created through Shield: one user, active, with the super-admin role', $u !== null && (int) q1('SELECT COUNT(*) FROM users') === 1 && (int) q1('SELECT active FROM users WHERE id=?', [$u]) === 1 && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$u]) === 'super-admin', json_encode(q('SELECT id, username, active, status FROM users')));
$hash = (string) q1("SELECT secret2 FROM auth_identities WHERE user_id=? AND type='email_password'", [$u]);
check('A13d', 'Password stored as a hash that verifies it (Shield default hashing)', password_verify(PW, $hash) && preg_match('/^\$(2y|argon2)/', $hash) === 1);
check('A13e', 'Done page: installed, names the account, links to sign in; no SQL', str_contains(text_of($h), 'Installed') && str_contains($h, 'boss7@example.test') && ! leaks_sql($h), text_of($h));
check('A14', 'Lock file written in writable/rolewarden and setup code deleted', is_file("$A/writable/rolewarden/installed.lock") && ! file_exists("$A/writable/rolewarden/install-code.txt"), json_encode(scandir("$A/writable/rolewarden")));
check('A14b', 'Shield group assignment: none (roles live in the panel)', (int) q1('SELECT COUNT(*) FROM auth_groups_users') === 0, json_encode(q('SELECT * FROM auth_groups_users')));
// mail to Mailpit before any request that could send
file_put_contents("$A/.env", MAILENV, FILE_APPEND);
// Sign in and every screen
$adm = new Client($c->base, 'A-boss');
check('A15', 'The new super admin signs in', login($adm, 'boss7@example.test', PW), "status {$adm->status} {$adm->location}");
$rid = (int) q1("SELECT id FROM acl_roles WHERE slug='admin'");
$bad200 = [];
foreach (screens($u, $rid) as $s) { $h = G($adm, $s, "A screen $s"); if ($adm->status !== 200 || leaks_sql($h)) $bad200[] = "$s {$adm->status}"; }
check('A15b', 'Every panel screen answers 200 for the new super admin, no SQL detail', $bad200 === [], json_encode($bad200));
$as = asset_status($adm, G($adm, 'rolewarden/users', 'users for assets'));
check('A15c', 'After installation the panel CSS and JS load (200)', $as !== [] && ! array_filter($as, fn ($a) => ! str_contains($a, ' 200 ')), json_encode($as));
$as = asset_status($anonA = new Client($c->base, 'A-anon-assets'), G($anonA, 'login', 'login for assets'));
check('A15d', 'After installation the sign-in page assets load for an anonymous visitor (200)', $as !== [] && ! array_filter($as, fn ($a) => ! str_contains($a, ' 200 ')), json_encode($as));
$h = G($adm, '', 'GET / after install');
check('A16', 'Home after installation leads to the panel', in_array($adm->status, [302, 303], true) && str_ends_with(abs_url($adm->url, $adm->location), '/rolewarden/users'), $adm->location);
$anon = new Client($c->base, 'A-anon');
G($anon, '', 'GET / anon after');
check('A16b', 'Anonymous home after installation leads to the panel, which asks to sign in', in_array($anon->status, [302, 303], true) && str_contains($anon->location, 'rolewarden/users'));

/* ============================================================== B: refusal to run again */
echo "\n== B: refusal to run again\n";
$envBefore = file_get_contents("$A/.env");
$state = dbstate();
$users = (int) q1('SELECT COUNT(*) FROM users');
$h = G($anon, 'rolewarden/install', 'GET install after');
check('B01', 'GET installer after installation: "Already installed", no form', str_contains(text_of($h), 'Already installed') && forms($h) === [], "status {$anon->status} " . text_of($h));
check('B01c', 'The "Already installed" page shows no step counter (D6 recheck)', ! preg_match('/step \d+ of \d+/i', text_of($h)), text_of($h));
info("installer after install answers HTTP {$anon->status}");
check('B01b', 'Visiting the installed installer does not create a new setup code', ! file_exists("$A/writable/rolewarden/install-code.txt"));
$attempts = [
    ['step' => 'code', 'code' => $code],
    ['step' => 'database', 'baseURL' => 'http://evil.test/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')],
    ['step' => 'migrate'],
    ['step' => 'admin', 'username' => 'intruder', 'email' => 'intruder@example.test', 'password' => PW, 'password_confirm' => PW],
];
foreach (['anonymous' => $anon, 'signed-in super admin' => $adm] as $who => $cl) {
    foreach ($attempts as $a) {
        $tok = csrf_of(G($cl, $who === 'anonymous' ? 'login' : 'rolewarden/profile', 'token page'));
        $h = P($cl, 'rolewarden/install', ['csrf_test_name' => $tok] + $a, "POST {$a['step']} after install ($who)");
        check('B02-' . $a['step'][0] . ($who[0]), "After installation, hand-made POST of step {$a['step']} with valid CSRF ($who) changes nothing", dbstate() === $state && file_get_contents("$A/.env") === $envBefore && ! file_exists("$A/writable/rolewarden/install-code.txt") && ! leaks_sql($h) && $tok !== '', "status {$cl->status} tok=" . ($tok !== '' ? 'y' : 'n') . ' ' . text_of($h));
    }
}
// lock file removed: an active super admin still blocks the installer
rename("$A/writable/rolewarden/installed.lock", "$WORK/installed.lock.aside");
$fresh = new Client($c->base, 'B-nolock');
$h = G($fresh, 'rolewarden/install', 'GET install no lock');
check('B03', 'Without the lock file but with an active super admin, the installer refuses', str_contains(text_of($h), 'Already installed') && form_with($h, 'step') === null, text_of($h));
foreach ($attempts as $a) {
    $tok = csrf_of(G($fresh, 'login', 'token page'));
    $h = P($fresh, 'rolewarden/install', ['csrf_test_name' => $tok] + $a, "POST {$a['step']} no lock");
    check('B03-' . $a['step'][0], "Without lock file, POST of step {$a['step']} with valid CSRF changes nothing", dbstate() === $state && file_get_contents("$A/.env") === $envBefore && ! leaks_sql($h), text_of($h));
}
check('B03c', 'Without lock file no setup code is created either', ! file_exists("$A/writable/rolewarden/install-code.txt"));
// super admin deactivated and no lock: report only (the SPEC speaks of an existing/active super admin)
q('UPDATE users SET active=0 WHERE id=?', [$u]);
$h = G(new Client($c->base, 'B-inactive'), 'rolewarden/install', 'GET install inactive sa');
info('No lock and the only super admin inactive: installer shows step "' . step_of($h) . '" / ' . substr(text_of($h), 0, 120) . ' / code file ' . (file_exists("$A/writable/rolewarden/install-code.txt") ? 'created' : 'absent'));
q('UPDATE users SET active=1 WHERE id=?', [$u]);
@unlink("$A/writable/rolewarden/install-code.txt");
rename("$WORK/installed.lock.aside", "$A/writable/rolewarden/installed.lock");
$state = dbstate();

/* ============================================================== D: the full application */
echo "\n== D: full application (CSRF on login, module update)\n";
$x = new Client($c->base, 'D-csrf');
G($x, 'login', 'GET login');
P($x, 'login', ['email' => 'boss7@example.test', 'password' => PW], 'POST login no csrf');
$after = G($x, 'rolewarden/users', 'GET users after login without CSRF');
check('D01', 'CSRF active on the login form: a sign-in without token is refused', $x->status !== 200 || ! str_contains($after, 'boss7@example.test'), "status {$x->status}");
$hl = G($x, 'login', 'GET login form');
check('D01b', 'Login form carries the CSRF field', str_contains($hl, 'name="csrf_test_name"'));
$xi = new Client($c->base, 'D-it'); $xi->extra = ['Accept-Language: it,en;q=0.5'];
$hl = G($xi, 'login', 'GET login it');
check('D02', 'Login page in Italian for an Italian browser (lang="it")', ilocale($hl) === 'it', ilocale($hl) . ' ' . substr(text_of($hl), 0, 200));
// module update: replace modules/rolewarden with the module zip, migrate -n RoleWarden
server_stop();
rrmdir("$A/modules/rolewarden");
copytree($ZIPMOD, "$A/modules/rolewarden");
$out = spark($A, ['migrate', '-n', 'RoleWarden']);
check('D03', 'Module update: folder replaced with the module zip, `php spark migrate -n RoleWarden` succeeds', str_contains($out, 'rc=0') && ! preg_match('/error|exception/i', $out), substr($out, 0, 400));
check('D03b', 'Update of the same version changes nothing in the database', dbstate() === $state);
$c = serve($A, $Adoc, '', 'A2');
$adm = new Client($c->base, 'A-boss2');
$okLogin = login($adm, 'boss7@example.test', PW);
$bad200 = [];
foreach (screens($u, $rid) as $s) { $h = G($adm, $s, "A2 screen $s"); if ($adm->status !== 200) $bad200[] = "$s {$adm->status}"; }
check('D03c', 'After the module update the super admin signs in and every screen answers 200', $okLogin && $bad200 === [], json_encode($bad200));
$state = dbstate();

/* ============================================================== E: languages */
echo "\n== E: languages\n";
$EN = flat(require "$ZIPMOD/src/Language/en/RoleWarden.php");
$IT = flat(require "$ZIPMOD/src/Language/it/RoleWarden.php");
$h = G($adm, 'rolewarden/profile', 'profile');
$lf = null; foreach (forms($h) as $ff) if (str_ends_with($ff['action'], 'profile/language')) $lf = $ff;
$lname = $lf ? (array_keys(array_filter($lf['types'], fn ($t) => $t === 'select'))[0] ?? null) : null;
$opts = $lname ? options($h, $lname) : [];
info('language options: ' . json_encode($opts, JSON_UNESCAPED_UNICODE));
check('E01', 'Profile has a language selector: the application\'s language, English, Italiano', $lf !== null && count($opts) === 3 && in_array('English', $opts, true) && in_array('Italiano', $opts, true) && isset($opts['']), json_encode($opts, JSON_UNESCAPED_UNICODE));
// second user, English, admin role; third user Italian
$hc = G($adm, 'rolewarden/users/create', 'create user form');
$fc = form_with($hc, 'email');
info('create user fields: ' . json_encode(array_keys($fc['types'] ?? [])));
function create_user(Client $adm, string $username, string $email): ?int
{
    $h = G($adm, 'rolewarden/users/create', 'create user form');
    $f = form_with($h, 'email');
    $v = array_replace($f['fields'], ['username' => $username, 'email' => $email, 'password' => PW]);
    foreach ($f['types'] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = PW;
    P($adm, $f['action'], $v, "create $username");
    return uid($email);
}
function assign_role(Client $adm, int $uid, string $slug): void
{
    $h = G($adm, "rolewarden/users/$uid", 'user page');
    foreach (forms($h) as $f) if (str_ends_with($f['action'], "/users/$uid/roles")) {
        $name = array_keys(array_filter($f['types'], fn ($t) => $t === 'select'))[0];
        $rid = q1('SELECT id FROM acl_roles WHERE slug=?', [$slug]);
        P($adm, $f['action'], array_replace($f['fields'], [$name => $rid]), "assign $slug");
        return;
    }
}
$eva = create_user($adm, 'eva7', 'eva7@example.test');
$ugo = create_user($adm, 'ugo7', 'ugo7@example.test');
if ($eva) assign_role($adm, $eva, 'admin');
check('E00', 'Two more users created from the panel (eva7 admin, ugo7)', $eva && $ugo && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug=?', [$eva, 'admin']) === 'admin');
function set_lang(Client $cl, string $value): string
{
    $h = G($cl, 'rolewarden/profile', 'profile');
    foreach (forms($h) as $f) if (str_ends_with($f['action'], 'profile/language')) {
        $n = array_keys(array_filter($f['types'], fn ($t) => $t === 'select'))[0];
        P($cl, $f['action'], array_replace($f['fields'], [$n => $value]), "set language $value");
        return $cl->location ? G($cl, abs_url($cl->url, $cl->location), 'after language') : $cl->body;
    }
    return '';
}
function lang_selected(Client $cl): ?string
{
    $h = G($cl, 'rolewarden/profile', 'profile');
    $xp = dom($h);
    foreach ($xp->query('//form[contains(@action,"profile/language")]//select/option[@selected]') as $o) return $o->getAttribute('value');
    return '';
}
$cEva = new Client($c->base, 'E-eva'); login($cEva, 'eva7@example.test', PW);
$cUgo = new Client($c->base, 'E-ugo'); login($cUgo, 'ugo7@example.test', PW);
$h = set_lang($adm, 'it');
check('E02', 'Choosing Italiano: panel in Italian, toast "Lingua salvata.", <html lang="it">', ilocale($h) === 'it' && str_contains($h, 'Lingua salvata.'), ilocale($h) . ' ' . substr(text_of($h), 0, 300));
check('E02b', 'The choice is saved for that user', lang_selected($adm) === 'it');
$he = G($cEva, 'rolewarden/users', 'eva users');
check('E03', 'Another user is not affected (eva7 still in English)', ilocale($he) === 'en' && lang_selected($cEva) === '', ilocale($he));
set_lang($cUgo, 'it');
set_lang($cEva, 'en');
check('E03b', 'Each user keeps their own choice (boss7 it, eva7 en, ugo7 it)', lang_selected($adm) === 'it' && lang_selected($cEva) === 'en' && lang_selected($cUgo) === 'it');
foreach (['xx', 'de', '../en', '<b>', 'IT'] as $bad) {
    set_lang($cUgo, $bad);
    $sel = lang_selected($cUgo);
    $page = G($cUgo, 'rolewarden/profile', 'profile');
    $sv = q1("SELECT value FROM settings WHERE context=? AND `key` LIKE '%ocale%' OR context=? AND `key` LIKE '%anguage%'", ['user:' . $ugo, 'user:' . $ugo]);
    check('E04-' . substr(md5($bad), 0, 3), "A value not in the list ($bad) is ignored", $sel === 'it' && ilocale($page) === 'it', "selected=$sel lang=" . ilocale($page) . ' stored=' . json_encode($sv));
}
// A sign-in again keeps the language
$cUgo2 = new Client($c->base, 'E-ugo2'); login($cUgo2, 'ugo7@example.test', PW);
check('E04b', 'The choice survives a new sign-in', ilocale(G($cUgo2, 'rolewarden/profile', 'profile')) === 'it');
// Import 30 users so that pagination and dates show (in Italian)
$csv = "email,username\n"; for ($i = 1; $i <= 30; $i++) $csv .= "p$i@example.test,pag$i\n";
$hi = G($adm, 'rolewarden/users/import', 'import it');
$fi = null; foreach (forms($hi) as $ff) if (str_ends_with($ff['action'], 'import/preview')) $fi = $ff;
if ($fi) {
    $bd = '----rw7' . bin2hex(random_bytes(6));
    $body = ''; foreach ($fi['fields'] as $k => $v) $body .= "--$bd\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
    $fileField = 'csv'; if (preg_match('/<input[^>]*type="file"[^>]*name="([^"]+)"/', $fi['html'], $m) || preg_match('/<input[^>]*name="([^"]+)"[^>]*type="file"/', $fi['html'], $m)) $fileField = $m[1];
    $body .= "--$bd\r\nContent-Disposition: form-data; name=\"$fileField\"; filename=\"u.csv\"\r\nContent-Type: text/csv\r\n\r\n$csv\r\n--$bd--\r\n";
    $adm->req('POST', $fi['action'], $body, ['Content-Type: multipart/form-data; boundary=' . $bd]);
    $hp = G($adm, abs_url($adm->url, $adm->location ?: 'rolewarden/users/import'), 'import preview it');
    seen($adm, 'import preview it');
    $cf = null; foreach (forms($hp) as $ff) if (str_ends_with($ff['action'], 'import/confirm')) $cf = $ff;
    if ($cf) { P($adm, $cf['action'], $cf['fields'], 'import confirm it'); G($adm, abs_url($adm->url, $adm->location ?: 'rolewarden/users'), 'after import it'); }
}
check('E05a', 'Fixture: 30 users imported from the Italian panel (pagination needs more than one page)', (int) q1('SELECT COUNT(*) FROM users') >= 33, (string) q1('SELECT COUNT(*) FROM users'));
// validation messages in Italian (codeigniter4/translations)
$h = G($adm, 'rolewarden/users/create', 'create it');
$f = form_with($h, 'email');
P($adm, $f['action'], array_replace($f['fields'], ['username' => '', 'email' => 'x', 'password' => '']), 'create invalid it');
$hv = $adm->location ? G($adm, abs_url($adm->url, $adm->location), 'create invalid it shown') : $adm->body;
$tv = text_of($hv);
info('Italian validation page: ' . substr($tv, 0, 600));
check('E05', 'Form validation messages in Italian (CodeIgniter translations)', (bool) preg_match('/obbligatori|deve contenere|valido|campo/i', $tv) && ! preg_match('/field is required|must contain a valid/i', $tv), $tv);
// Pagination, dates and every screen in Italian, scanned for English left over
$frag = [];
foreach ($EN as $k => $v) {
    if (($IT[$k] ?? null) === $v) continue;
    foreach (preg_split('/\{[^{}]*(\{[^{}]*\}[^{}]*)*\}|%[sd]|<[^>]+>/', $v) as $p) { $p = trim($p, " .,:;·–-()"); if (mb_strlen($p) >= 9 && str_contains($p, ' ')) $frag[$p] = $k; }
}
$IT_TEXT = implode("\n", $IT);
$frag = array_filter($frag, fn ($k, $p) => ! str_contains($IT_TEXT, $p), ARRAY_FILTER_USE_BOTH);
$left = []; $attrsSeen = []; $seeded = [];
$scan = array_merge(screens($u, $rid), ["rolewarden/users/$eva", 'rolewarden/users?page=2', 'rolewarden/activity?page=2', 'rolewarden/users/create']);
foreach ($scan as $s) {
    $h = G($adm, $s, "IT $s");
    file_put_contents("$WORK/page-it-" . preg_replace('/[^a-z0-9]+/', '-', $s) . '.html', $h);
    if ($adm->status !== 200) { $left[] = "$s HTTP {$adm->status}"; continue; }
    if (ilocale($h) !== 'it') $left[] = "$s lang=" . ilocale($h);
    $t = text_of($h);
    $xp = dom($h); $attr = [];
    foreach ($xp->query('//@aria-label|//@title|//@placeholder|//@alt|//@aria-description|//@data-confirm|//@value[../@type="submit"]') as $a) $attr[] = $a->nodeValue;
    // Content seeded in the database (system roles and permissions, area names) is reported apart (AMB A1).
    $dbtext = []; foreach (q('SELECT name, description FROM acl_roles') as $r) { $dbtext[] = $r['name']; $dbtext[] = (string) $r['description']; }
    foreach (q('SELECT slug, description FROM acl_permissions') as $r) { $dbtext[] = (string) $r['description']; $dbtext[] = ucfirst(explode('.', $r['slug'])[0]); }
    usort($dbtext, fn ($a, $b) => strlen($b) <=> strlen($a));
    $tClean = $t; foreach ($dbtext as $dv) if ($dv !== '' && str_contains($tClean, $dv)) { $seeded[$dv] = $s; $tClean = str_replace($dv, ' ', $tClean); }
    // English literals inside Alpine expressions (shown when the page changes, e.g. the save bar)
    // a literal is English when one of its words (3+ letters) never occurs in the Italian language file
    $notIt = function (string $l) use ($IT_TEXT): bool { preg_match_all('/\p{L}{3,}/u', $l, $w); foreach ($w[0] as $x) if (mb_stripos($IT_TEXT, $x) === false) return true; return false; };
    if (preg_match_all('/(?:x-text|x-html|\s:[a-z-]+)="([^"]*)"/', $h, $xm)) foreach ($xm[1] as $expr) if (preg_match_all("/'([^']*)'/", html_entity_decode($expr), $lit)) foreach ($lit[1] as $l) { $l = str_replace('\x20', ' ', $l); if (preg_match('/[a-z]{3,}/', $l) && preg_match('/\s/', trim($l) === '' ? '' : $l) && ! preg_match('/^\s*(rw-|is-)/', $l) && $notIt($l)) $left[] = "$s: Alpine literal '" . $l . "'"; }
    if (preg_match_all('/<span>\s*<b x-text="[^"]*"><\/b>\s*([^<]+)<\/span>/u', $h, $bm)) foreach ($bm[1] as $tail) if ($notIt(html_entity_decode($tail))) $left[] = "$s: save-bar text \"" . trim(html_entity_decode($tail)) . '"';
    // texts carried as JSON for Alpine (matrix tooltips)
    if (preg_match_all('/"tooltip":"([^"]*)"/', html_entity_decode($h), $tm)) foreach (array_unique($tm[1]) as $tt) { $tt = json_decode('"' . $tt . '"') ?? $tt; $core = trim(preg_replace('/[a-z_]+\.[a-z_]+/', '', $tt), ' ·'); if ($core !== '' && $notIt($core)) $left[] = "$s: matrix tooltip \"" . preg_replace('/^[a-z_]+\.[a-z_]+ · /', '<perm> · ', $tt) . '"'; }
    $all = $tClean . "\n" . implode("\n", $attr);
    foreach ($frag as $p => $k) if (str_contains($all, $p)) $left[] = "$s: \"$p\" ($k)";
    foreach ($attr as $a) $attrsSeen[$a] = $s;
    if (preg_match_all('/\b(Showing|Previous|Next|Save|Cancel|Delete|Edit|Sign out|Sign in|Search|Filter|Users|Roles|Permissions|Settings|Activity|Appearance|Profile|Sessions|Light|Dark|System|Dismiss|Close|Never|ago|minutes?|hours?|days?|January|February|March|April|May|June|July|August|September|October|November|December|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/', $all, $mm)) {
        foreach (array_unique($mm[0]) as $w) $left[] = "$s: English word \"$w\"";
    }
}
$left = array_values(array_unique($left));
check('E06', 'No English text left in the Italian panel (all screens, aria labels, titles, placeholders, Alpine texts)', $left === [], json_encode($left, JSON_UNESCAPED_UNICODE));
if ($seeded) amb('A1', 'Database content seeded in English shows as is in the Italian panel', 'System role names and descriptions, permission descriptions and area headings (e.g. ' . json_encode(array_slice(array_keys($seeded), 0, 10), JSON_UNESCAPED_UNICODE) . ') are rows of acl_roles/acl_permissions, not language lines. The DoD says "every interface string exists in English and Italian"; SPEC does not say whether seeded records count as interface strings.');
file_put_contents("$WORK/page-it-validation.html", $hv);
$hp = G($adm, 'rolewarden/permissions', 'IT permissions D6');
check('E12a', 'Permissions page notes say "permesso di sistema", not "ruolo di sistema" (D6 recheck)', str_contains(text_of($hp), 'permesso di sistema') && ! str_contains(text_of($hp), 'ruolo di sistema'), substr(text_of($hp), 0, 400));
$hr = G($adm, 'rolewarden/roles', 'IT roles D6');
check('E12b', 'Roles and Permissions overline reads "Autorizzazioni" (D6 recheck)', str_contains(text_of($hr), 'Autorizzazioni') && str_contains(text_of($hp), 'Autorizzazioni'), substr(text_of($hr), 0, 200));
$hac = G($adm, 'rolewarden/activity', 'IT activity D6');
check('E12c', 'Activity action list: "Browser non più ricordato" (D6 recheck)', str_contains(text_of($hac), 'Browser non più ricordato') && ! str_contains(text_of($hac), 'Browser ricordato dimenticato'));
$hu1 = G($adm, "rolewarden/users/$eva", 'IT user page D4');
check('E12d', 'Italian save bar and row labels come from the language files (no "unsaved", "Grant every action", "Granted by")', ! preg_match('/unsaved|Grant every action|Granted by|Not granted|overrides/', html_entity_decode($hu1)) && ! preg_match('/unsaved|Grant every action|Granted by|Not granted/', html_entity_decode(G($adm, "rolewarden/roles/$rid", 'IT role page D4'))), '');
check('E06b', 'Italian validation summary has no English prefix ("Error:")', ! preg_match('/\bError:/', text_of($hv)), substr(text_of($hv), 0, 300));
info('attribute values seen in Italian: ' . json_encode(array_keys($attrsSeen), JSON_UNESCAPED_UNICODE));
// the panel's own JavaScript as the browser receives it, kept for the report (strings it renders)
preg_match_all('/<script[^>]+src="([^"]+)"/', G($adm, "rolewarden/users/$eva", 'IT js list'), $js);
foreach ($js[1] as $src) { $t = new Client($adm->base, 'js'); $t->get(abs_url($adm->url, html_entity_decode($src))); file_put_contents("$WORK/page-asset-" . basename(parse_url($src, PHP_URL_PATH)) . '.html', $t->body); }
$hu = G($adm, 'rolewarden/users', 'IT users list');
check('E07', 'Pagination of the users list in Italian ("Successiva", aria "Paginazione")', str_contains($hu, 'Successiva') && str_contains($hu, 'Paginazione'), substr(text_of($hu), -400));
$ha = G($adm, 'rolewarden/activity', 'IT activity');
$hae = G($cEva, 'rolewarden/activity', 'EN activity'); file_put_contents("$WORK/page-en-activity.html", $hae);
info('activity text (it): ' . substr(text_of($ha), 0, 900));
info('activity text (en): ' . substr(text_of($hae), 0, 900));
preg_match_all('/<time[^>]*>([^<]*)<\/time>/', $ha, $tm);
info('activity dates (it): ' . json_encode(array_slice(array_unique($tm[1]), 0, 6), JSON_UNESCAPED_UNICODE));
$hp = G($adm, "rolewarden/users/$eva", 'IT user page');
preg_match_all('/<time[^>]*>([^<]*)<\/time>/', $hp, $tm2);
info('user page dates (it): ' . json_encode(array_slice(array_unique($tm2[1]), 0, 6), JSON_UNESCAPED_UNICODE));
$hpe = G($cEva, "rolewarden/users/$ugo", 'EN user page');
preg_match_all('/<time[^>]*>([^<]*)<\/time>/', $hpe, $tm3);
info('user page dates (en): ' . json_encode(array_slice(array_unique($tm3[1]), 0, 6), JSON_UNESCAPED_UNICODE));
$datesIt = implode(' ', array_merge($tm[1], $tm2[1]));
check('E08', 'Dates in the Italian panel are not in English (no English month names or "ago")', ! preg_match('/\b(ago|January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/', $datesIt . ' ' . text_of($ha)), $datesIt);
// Security emails in the recipient's language
mailpit('DELETE', 'messages');
function set_password(Client $by, int $target, string $pw): void
{
    $h = G($by, "rolewarden/users/$target/edit", 'edit user');
    foreach (forms($h) as $f) if (array_key_exists('password', $f['types'])) {
        $v = array_replace($f['fields'], ['password' => $pw]);
        foreach ($f['types'] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = $pw;
        P($by, $f['action'] ?: "rolewarden/users/$target", $v, "set password $target");
        return;
    }
}
function wait_mail(int $n): array { for ($i = 0; $i < 40; $i++) { $m = mailpit('GET', 'messages'); if (($m['messages_count'] ?? 0) >= $n) return $m['messages']; usleep(250000); } return mailpit('GET', 'messages')['messages'] ?? []; }
set_password($adm, $eva, PW . 'e');
$m1 = wait_mail(1);
$s1 = array_map(fn ($m) => [$m['To'][0]['Address'] ?? '', $m['Subject']], $m1);
check('E09', 'Admin in Italian sets the password of an English user: the email is in English', count($m1) === 1 && ($s1[0][0] ?? '') === 'eva7@example.test' && str_contains($s1[0][1], 'password') && ! str_contains($s1[0][1], 'cambiata'), json_encode($s1, JSON_UNESCAPED_UNICODE));
mailpit('DELETE', 'messages');
$cEva = new Client($c->base, 'E-eva2'); login($cEva, 'eva7@example.test', PW . 'e');
set_password($cEva, $ugo, PW . 'u');
$m2 = wait_mail(1);
$s2 = array_map(fn ($m) => [$m['To'][0]['Address'] ?? '', $m['Subject']], $m2);
check('E09b', 'Admin in English sets the password of an Italian user: the email is in Italian', count($m2) === 1 && ($s2[0][0] ?? '') === 'ugo7@example.test' && str_contains($s2[0][1], 'cambiata'), json_encode($s2, JSON_UNESCAPED_UNICODE));
if ($m2) { $full = mailpit('GET', 'message/' . $m2[0]['ID']); info('Italian email text: ' . substr(preg_replace('/\s+/', ' ', (string) ($full['Text'] ?? '')), 0, 500)); }
if ($m2) { $full = mailpit('GET', 'message/' . $m2[0]['ID']); $bodyIt = (string) ($full['Text'] ?? ''); check('E09d', 'Date in the Italian email is localised (no ISO yyyy-mm-dd) (D6 recheck)', ! preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $bodyIt), substr(preg_replace('/\s+/', ' ', $bodyIt), 0, 400)); }
if ($m2) { $full = mailpit('GET', 'message/' . $m2[0]['ID']); check('E09c', 'The Italian email body is Italian throughout (no English sentence of the English template)', ! preg_match('/\b(Hello|your password|If you did not|administrator set)\b/i', (string) ($full['Text'] ?? '') . (string) ($full['HTML'] ?? '')), substr((string) ($full['Text'] ?? ''), 0, 400)); }
mailpit('DELETE', 'messages');
// Application language: a user who has not chosen follows the application (Accept-Language)
$ugo3 = new Client($c->base, 'E-noset'); $ugo3->extra = ['Accept-Language: it'];
$pId = uid('p1@example.test');
// p1 has no password known: give one through the panel
set_password($adm, $pId, PW . 'p');
mailpit('DELETE', 'messages');
login($ugo3, 'p1@example.test', PW . 'p');
$hp1 = G($ugo3, 'rolewarden/profile', 'p1 profile it browser');
check('E10', 'A user without a choice follows the application language (Italian browser, negotiated locale)', ilocale($hp1) === 'it', ilocale($hp1));
// A language added by the application
@mkdir("$A/app/Language/fr", 0777, true);
file_put_contents("$A/app/Language/fr/RoleWarden.php", "<?php\nreturn ['panel' => ['nav' => ['users' => 'Utilisateurs'], 'account' => ['language' => 'Langue']]];\n");
$h = G($adm, 'rolewarden/profile', 'profile with fr');
$opts = options($h, $lname ?? 'language');
info('language options with fr: ' . json_encode($opts, JSON_UNESCAPED_UNICODE));
check('E11', 'A language file added by the application appears in the selector named in its own language', isset($opts['fr']) && mb_strtolower($opts['fr']) === 'français', json_encode($opts, JSON_UNESCAPED_UNICODE));
$h = set_lang($cEva, 'fr');
$hf = G($cEva, 'rolewarden/users', 'fr users');
check('E11b', 'Choosing it: translated lines in French, missing lines fall back to English', lang_selected($cEva) === 'fr' && str_contains($hf, 'Utilisateurs') && str_contains($hf, '>Roles<') || (lang_selected($cEva) === 'fr' && str_contains($hf, 'Utilisateurs') && str_contains(text_of($hf), 'Roles')), 'selected=' . lang_selected($cEva) . ' lang=' . ilocale($hf) . ' ' . substr(text_of($hf), 0, 300));
check('E11c', 'Missing French lines do not show raw language keys', ! preg_match('/RoleWarden\.[a-z]+\.[a-zA-Z.]+/', text_of($hf)), substr(text_of($hf), 0, 300));
set_lang($cEva, 'en');
unlink("$A/app/Language/fr/RoleWarden.php"); rmdir("$A/app/Language/fr");
server_stop();
$LOGS['A'] = logs_of($A);

/* ============================================================== C: installer variants */
echo "\n== C1: .env not writable, application at the root\n";
empty_db();
[$C1, $C1doc] = fresh_app('appC1', '');
file_put_contents("$C1/.env", '');
exec('attrib +R "' . str_replace('/', '\\', "$C1/.env") . '"');
check('C1a', 'Fixture: .env read-only for the web server', ! is_writable("$C1/.env"));
$c = serve($C1, $C1doc, '', 'C1');
$h = G($c, 'rolewarden/install', 'C1 install');
info('C1 requirements: ' . substr(text_of($h), 0, 500));
check('C1b', 'Requirements report .env as not writable but let the installation go on', step_of($h) === 'code' && (bool) preg_match('/\.env writable by the web server\s*(by hand|manual|missing)/i', text_of($h)), text_of($h));
inst_post($c, ['step' => 'code', 'code' => code_of($C1)], 'C1 code');
$h = inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'C1 db');
$xp = dom($h); $shown = '';
foreach ($xp->query('//textarea|//pre|//code') as $n) if (str_contains($n->textContent, 'database.default')) $shown = $n->textContent;
check('C1c', 'The .env content to copy by hand is shown (address, database, key, environment), .env untouched', $shown !== '' && str_contains($shown, 'database.default.database') && str_contains($shown, 'encryption.key') && str_contains($shown, 'app.baseURL') && filesize("$C1/.env") === 0, text_of($h));
check('C1d', 'The page with the .env content carries no SQL detail and is sent no-store', ! leaks_sql($h) && str_contains($c->headers['cache-control'] ?? '', 'no-store'), json_encode($c->headers['cache-control'] ?? ''));
info('C1 cache-control of the page with the credentials: ' . ($c->headers['cache-control'] ?? 'none'));
exec('attrib -R "' . str_replace('/', '\\', "$C1/.env") . '"');
file_put_contents("$C1/.env", html_entity_decode($shown));
$h = G($c, 'rolewarden/install', 'C1 continue');
check('C1e', 'With the copied .env saved, the installer continues to the tables', step_of($h) === 'migrate', text_of($h));
inst_post($c, ['step' => 'migrate'], 'C1 migrate');
G($c, 'rolewarden/install', 'C1 admin form');
$h = inst_post($c, ['step' => 'admin', 'username' => 'root7', 'email' => 'root7@example.test', 'password' => PW, 'password_confirm' => PW], 'C1 admin');
$cl = new Client($c->base, 'C1-adm');
check('C1f', 'Installation completes and the super admin signs in', uid('root7@example.test') !== null && login($cl, 'root7@example.test', PW) && is_file("$C1/writable/rolewarden/installed.lock"));
$envC1 = env_map("$C1/.env");
check('C1g', 'The copied .env is read by CodeIgniter (environment production, key present)', ($envC1['CI_ENVIRONMENT'] ?? '') === 'production' && str_starts_with($envC1['encryption.key'] ?? '', 'hex2bin:'), json_encode(array_keys($envC1)));
server_stop();
$LOGS['C1'] = logs_of($C1);

echo "\n== C2: .env already present with other keys, application in a subfolder\n";
empty_db();
[$C2, $C2doc] = fresh_app('appC2', '/sub');
$preKey = 'hex2bin:' . bin2hex(random_bytes(32));
$pre = "# kept by the buyer\nCI_ENVIRONMENT = production\nencryption.key = $preKey\ncustom.flag = keep-me\n" . MAILENV;
file_put_contents("$C2/.env", $pre);
$c = serve($C2, $C2doc, '/sub', 'C2');
G($c, '', 'C2 home');
check('C2a', 'Subfolder: home redirects to the installer under the subfolder', in_array($c->status, [302, 303], true) && abs_url($c->url, $c->location) === 'http://127.0.0.1:8071/sub/rolewarden/install', $c->location);
$h = G($c, 'rolewarden/install', 'C2 install');
$assets = []; preg_match_all('/(?:href|src)="([^"#]+)"/', $h, $mm);
foreach ($mm[1] as $a) { $abs = abs_url($c->url, html_entity_decode($a)); $t = new Client($c->base, 'asset'); $t->get($abs); $assets[] = "$abs {$t->status}"; }
check('C2b', 'Subfolder: stylesheets and links of the installer resolve under /sub/ and load', ! array_filter($assets, fn ($a) => ! str_contains($a, '/sub/') || ! str_ends_with($a, ' 200')), json_encode($assets));
// skipping steps without a verified code
$state0 = dbstate();
$h = inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/sub/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'C2 db without code');
check('C2s1', 'Database step without a verified code: .env not touched', file_get_contents("$C2/.env") === $pre && ! leaks_sql($h), text_of($h));
$h = inst_post($c, ['step' => 'migrate'], 'C2 migrate without code');
check('C2s2', 'Tables step without a verified code: nothing created', tables() === [] && ! leaks_sql($h), text_of($h));
$h = inst_post($c, ['step' => 'admin', 'username' => 'sneak7', 'email' => 'sneak7@example.test', 'password' => PW, 'password_confirm' => PW], 'C2 admin without code');
check('C2s3', 'Admin step without a verified code: nothing created, no SQL on screen', tables() === [] && ! leaks_sql($h), text_of($h));
$h = inst_post($c, ['step' => 'code', 'code' => code_of($C2)], 'C2 code', false);
check('C2c1', 'Code step: missing CSRF refused', form_with($h, 'hostname') === null);
$h = inst_post($c, ['step' => 'code', 'code' => code_of($C2)], 'C2 code');
$f = form_with($h, 'hostname');
check('C2c', 'Subfolder: proposed address is http://127.0.0.1:8071/sub/', ($f['fields']['baseURL'] ?? '') === 'http://127.0.0.1:8071/sub/', $f['fields']['baseURL'] ?? '');
$h = inst_post($c, ['step' => 'migrate'], 'C2 migrate before db');
check('C2s4', 'Tables step before the database step (code verified): nothing created', tables() === [] && file_get_contents("$C2/.env") === $pre && ! leaks_sql($h), text_of($h));
$h = inst_post($c, ['step' => 'admin', 'username' => 'sneak7', 'email' => 'sneak7@example.test', 'password' => PW, 'password_confirm' => PW], 'C2 admin before db');
check('C2s5', 'Admin step before the database step (code verified): nothing created', tables() === [] && ! leaks_sql($h), text_of($h));
$dbv = ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/sub/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')];
$h = inst_post($c, $dbv + ['csrf_test_name' => 'deadbeef'], 'C2 db bad csrf', false);
check('C2c2', 'Database step: wrong CSRF refused, .env untouched', file_get_contents("$C2/.env") === $pre);
$h = inst_post($c, $dbv, 'C2 db no csrf', false);
check('C2c2b', 'Database step: missing CSRF refused, .env untouched', file_get_contents("$C2/.env") === $pre);
$h = inst_post($c, $dbv, 'C2 db');
$env = env_map("$C2/.env");
check('C2d', 'Existing .env keys kept (comment, custom key, mail settings)', str_contains(file_get_contents("$C2/.env"), '# kept by the buyer') && ($env['custom.flag'] ?? '') === 'keep-me' && ($env['email.SMTPPort'] ?? '') === '1026', file_get_contents("$C2/.env") ? json_encode(array_keys($env)) : '');
check('C2e', 'Existing encryption key and CI_ENVIRONMENT kept, and not duplicated', ($env['encryption.key'] ?? '') === $preKey && ($env['CI_ENVIRONMENT'] ?? '') === 'production' && substr_count(file_get_contents("$C2/.env"), 'encryption.key') === 1 && substr_count(file_get_contents("$C2/.env"), 'CI_ENVIRONMENT') === 1, json_encode([$env['CI_ENVIRONMENT'] ?? null, ($env['encryption.key'] ?? '') === $preKey]));
check('C2f', 'Address and database written for the subfolder', ($env['app.baseURL'] ?? '') === 'http://127.0.0.1:8071/sub/' && ($env['database.default.database'] ?? '') === 'rolewarden_test');
$h = G($c, 'rolewarden/install', 'C2 migrate form');
$h = inst_post($c, ['step' => 'admin', 'username' => 'sneak7', 'email' => 'sneak7@example.test', 'password' => PW, 'password_confirm' => PW], 'C2 admin before tables');
check('C2s6', 'Admin step before the tables (database done): nothing created, no SQL on screen', tables() === [] && ! leaks_sql($h), text_of($h));
$h = inst_post($c, ['step' => 'migrate', 'csrf_test_name' => 'deadbeef'], 'C2 migrate bad csrf', false);
check('C2c3', 'Tables step: wrong CSRF refused', tables() === []);
inst_post($c, ['step' => 'migrate'], 'C2 migrate');
G($c, 'rolewarden/install', 'C2 admin form');
$h = inst_post($c, ['step' => 'admin', 'username' => 'sub7', 'email' => 'sub7@example.test', 'password' => PW, 'password_confirm' => PW, 'csrf_test_name' => 'deadbeef'], 'C2 admin bad csrf', false);
check('C2c4', 'Admin step: wrong CSRF refused', uid('sub7@example.test') === null);
$h = inst_post($c, ['step' => 'admin', 'username' => 'sub7', 'email' => 'sub7@example.test', 'password' => PW, 'password_confirm' => PW], 'C2 admin');
$links = []; preg_match_all('/(?:href|action)="([^"]*)"/', $h, $mm); foreach ($mm[1] as $a) $links[] = abs_url($c->url, html_entity_decode($a));
check('C2g', 'Subfolder: installation done, sign-in link points under /sub/', uid('sub7@example.test') !== null && $links !== [] && ! array_filter($links, fn ($l) => ! str_starts_with($l, 'http://127.0.0.1:8071/sub/')), json_encode($links));
$cl = new Client($c->base, 'C2-adm');
$okl = login($cl, 'sub7@example.test', PW);
$sid = uid('sub7@example.test'); $rid2 = (int) q1("SELECT id FROM acl_roles WHERE slug='admin'");
$badl = []; $bad200 = [];
foreach (screens($sid, $rid2) as $s) {
    $h = G($cl, $s, "C2 $s");
    if ($cl->status !== 200) $bad200[] = "$s {$cl->status}";
    preg_match_all('/(?:href|action|src)="([^"#]*)"/', $h, $mm);
    foreach ($mm[1] as $a) { if ($a === '' || str_starts_with($a, 'mailto:') || str_starts_with($a, 'data:')) continue; $abs = abs_url($cl->url, html_entity_decode($a)); if (str_starts_with($abs, 'http://127.0.0.1:8071/') && ! str_starts_with($abs, 'http://127.0.0.1:8071/sub/')) $badl[] = "$s -> $a"; }
}
check('C2h', 'Subfolder: the super admin signs in and every screen answers 200', $okl && $bad200 === [], json_encode($bad200));
check('C2i', 'Subfolder: every link, form and asset of the panel stays under /sub/', $badl === [], json_encode(array_slice(array_unique($badl), 0, 20)));
G($cl, '', 'C2 home after');
check('C2j', 'Subfolder: home after installation leads to the panel under /sub/', str_starts_with(abs_url($cl->url, $cl->location), 'http://127.0.0.1:8071/sub/rolewarden/users'), $cl->location);
server_stop();
$LOGS['C2'] = logs_of($C2);


echo "\n== C3: .env already present with CI_ENVIRONMENT = development (kept by the installer, README)\n";
empty_db();
[$C3, $C3doc] = fresh_app('appC3', '');
file_put_contents("$C3/.env", "CI_ENVIRONMENT = development\n" . MAILENV);
$c = serve($C3, $C3doc, '', 'C3');
$h = G($c, '', 'C3 home');
file_put_contents("$WORK/page-c3-home.html", $h);
check('C3a', 'Development .env before installation: home leads to the installer, no SQL or driver detail on screen', in_array($c->status, [302, 303], true) && ! leaks_sql($h), "status {$c->status} " . substr(text_of($h), 0, 300));
$h = G($c, 'rolewarden/install', 'C3 install');
file_put_contents("$WORK/page-c3-install.html", $h);
check('C3b', 'Development .env before installation: the installer opens (code step), no SQL or driver detail on screen', $c->status === 200 && step_of($h) === 'code' && ! leaks_sql($h), "status {$c->status} " . substr(text_of($h), 0, 300));
$dbg = fn (string $x) => (bool) preg_match('/id="debug-bar"|toolbarContainer|ci-debugbar|SYSTEMPATH|Backtrace|Displayed at/', $x);
check('C3b2', 'Development before installation: no debug toolbar or debug detail on the installer', ! $dbg($h), substr(text_of($h), 0, 200));
$as = asset_status($c, $h);
check('C3b3', 'Development before installation: the installer stylesheet loads (200, text/css)', $as !== [] && ! array_filter($as, fn ($a) => str_contains($a, '.css') && ! preg_match('/ 200 text\/css/', $a)), json_encode($as));
$x = new Client($c->base, 'C3-404'); $h404 = G($x, 'no/such/page-7', 'C3 404');
check('C3b4', 'Development before installation: an unknown URL shows no debug detail (no backtrace, no paths)', ! $dbg($h404) && ! leaks_sql($h404) && ! str_contains($h404, 'verify-v7.work'), "status {$x->status} " . substr(text_of($h404), 0, 200));
$h = inst_post($c, ['step' => 'admin', 'username' => 'early', 'email' => 'early@example.test', 'password' => PW, 'password_confirm' => PW], 'C3 admin without code');
check('C3b5', 'Development: admin step posted without code shows no SQL and no debug detail', ! leaks_sql($h) && ! $dbg($h) && tables() === [], "status {$c->status} " . substr(text_of($h), 0, 200));
$h = G($c, 'rolewarden/install', 'C3 install again');
if (step_of($h) === 'code') {
    inst_post($c, ['step' => 'code', 'code' => code_of($C3)], 'C3 code');
    foreach (['missing database' => ['database' => 'rw7_does_not_exist', 'password' => getenv('RW_DB_PASSWORD')], 'unknown host' => ['hostname' => 'db.invalid', 'password' => getenv('RW_DB_PASSWORD')]] as $k => $v) {
        $h = inst_post($c, array_replace(['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME')], $v), "C3 db $k");
        check('C3c-' . substr(md5($k), 0, 3), "Development: failed connection ($k) shows no SQL or debug detail", ! leaks_sql($h) && ! $dbg($h) && str_contains(text_of($h), 'Could not connect'), substr(text_of($h), 0, 200));
    }
    $h = inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => 'wrong-7'], 'C3 db wrong');
    check('C3c', 'Development: a failed connection still shows no SQL or driver detail', ! leaks_sql($h), substr(text_of($h), 0, 300));
    inst_post($c, ['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'C3 db');
    $e3 = env_map("$C3/.env");
    check('C3d', 'Existing CI_ENVIRONMENT (development) kept, a key generated', ($e3['CI_ENVIRONMENT'] ?? '') === 'development' && str_starts_with($e3['encryption.key'] ?? '', 'hex2bin:') && substr_count(file_get_contents("$C3/.env"), 'CI_ENVIRONMENT') === 1, json_encode([$e3['CI_ENVIRONMENT'] ?? null]));
    G($c, 'rolewarden/install', 'C3 migrate form');
    $h = inst_post($c, ['step' => 'admin', 'username' => 'early', 'email' => 'early@example.test', 'password' => PW, 'password_confirm' => PW], 'C3 admin before tables');
    check('C3d2', 'Development: admin step before the tables shows no SQL and no debug detail, creates nothing', ! leaks_sql($h) && ! $dbg($h) && tables() === [], substr(text_of($h), 0, 200));
    G($c, 'rolewarden/install', 'C3 migrate form again');
    $h = inst_post($c, ['step' => 'migrate'], 'C3 migrate');
    check('C3d3', 'Development: tables step shows no debug detail', ! leaks_sql($h) && ! $dbg($h) && in_array('acl_roles', tables(), true), substr(text_of($h), 0, 200));
    G($c, 'rolewarden/install', 'C3 admin form');
    $h = inst_post($c, ['step' => 'admin', 'username' => 'dev7', 'email' => 'dev7@example.test', 'password' => PW, 'password_confirm' => PW], 'C3 admin');
    $cl = new Client($c->base, 'C3-adm');
    check('C3e', 'Development: installation completes and the super admin signs in', uid('dev7@example.test') !== null && login($cl, 'dev7@example.test', PW), substr(text_of($h), 0, 300));
    check('C3e2', 'Development: done page without debug detail; lock written', ! $dbg($h) && is_file("$C3/writable/rolewarden/installed.lock"), substr(text_of($h), 0, 200));
    $sid3 = uid('dev7@example.test'); $rid3 = (int) q1("SELECT id FROM acl_roles WHERE slug='admin'"); $b3 = [];
    foreach (screens($sid3, $rid3) as $sc) { $hh = G($cl, $sc, "C3 $sc"); if ($cl->status !== 200 || leaks_sql($hh)) $b3[] = "$sc {$cl->status}"; }
    check('C3f', 'Development after installation: every screen answers 200, no SQL', $b3 === [], json_encode($b3));
}
server_stop();
$LOGS['C3'] = logs_of($C3);

/* ============================================================== B06: test application, super admin, no lock */
echo "\n== B06: copy of the test application with an active super admin and no lock file\n";
empty_db();
$T = "$WORK/apptest";
$cp = proc_open(['robocopy', $APPTEST, $T, '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp); stream_get_contents($pp[1]); proc_close($cp);
foreach (['cache', 'session', 'logs', 'uploads', 'debugbar'] as $d) @mkdir("$T/writable/$d", 0777, true);
$j = "$T/vendor/rolewarden/codeigniter4-rolewarden"; if (is_dir($j)) @rmdir($j);
junction($j, $MOD);
file_put_contents("$T/.env", "CI_ENVIRONMENT = production\napp.baseURL = 'http://127.0.0.1:8071/'\ndatabase.default.hostname = 127.0.0.1\ndatabase.default.port = 3317\ndatabase.default.database = rolewarden_test\ndatabase.default.DBDriver = MySQLi\n" . MAILENV);
$tenv = ['database.default.username' => getenv('RW_DB_USERNAME'), 'database.default.password' => getenv('RW_DB_PASSWORD'), 'logger.threshold' => '9'];
foreach ([['migrate', '-n', 'CodeIgniter\Settings'], ['migrate', '-n', 'CodeIgniter\Shield'], ['migrate', '-n', 'RoleWarden'], ['db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder']] as $a) {
    $o = spark($T, $a, $tenv); if (! str_contains($o, 'rc=0')) info('spark ' . implode(' ', $a) . ': ' . substr($o, 0, 200));
}
// the account as Shield stores it (users + email_password identity, PASSWORD_DEFAULT hash), active
q("INSERT INTO users (username, active, created_at, updated_at) VALUES ('tsa7', 1, NOW(), NOW())");
q("INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?, 'email_password', 'tsa7@example.test', ?, 0, NOW(), NOW())", [db()->insert_id, password_hash(PW, PASSWORD_DEFAULT)]);
q("INSERT INTO acl_user_roles (user_id, role_id) SELECT i.user_id, r.id FROM auth_identities i JOIN acl_roles r ON r.slug='super-admin' WHERE i.type='email_password' AND i.secret='tsa7@example.test'");
check('B06a', 'Fixture: test application installed by the README commands, one active super admin, no lock file', (int) q1("SELECT COUNT(*) FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE r.slug='super-admin' AND u.active=1") === 1 && ! file_exists("$T/writable/rolewarden/installed.lock"));
$state = dbstate();
$c = serve($T, "$T/public", '', 'B06', $tenv);
$tc = new Client($c->base, 'B06');
$h = G($tc, 'rolewarden/install', 'B06 install');
check('B06', 'Test application with an active super admin: installer refuses (no code, no form)', str_contains(text_of($h), 'Already installed') && form_with($h, 'step') === null && ! file_exists("$T/writable/rolewarden/install-code.txt"), "status {$tc->status} " . substr(text_of($h), 0, 300));
foreach ($attempts as $a) {
    $tok = csrf_of(G($tc, 'login', 'token'));
    $h = P($tc, 'rolewarden/install', ['csrf_test_name' => $tok] + $a, "B06 POST {$a['step']}");
    check('B06-' . $a['step'][0], "Test application: POST of step {$a['step']} with valid CSRF changes nothing", dbstate() === $state && ! file_exists("$T/writable/rolewarden/install-code.txt") && ! leaks_sql($h), "status {$tc->status} " . text_of($h));
}
server_stop();
$LOGS['B06'] = logs_of($T);
@rmdir($j);

/* ============================================================== F: publication scans over every response */
echo "\n== F: publication\n";
$leak = []; $ext = []; $raw = [];
foreach ($SEEN as [$tag, $st, $url, $body]) {
    if (leaks_sql($body)) $leak[] = "$tag ($st)";
    if (preg_match_all('/<(?:script|link|img|iframe)[^>]+(?:src|href)="(https?:)?\/\/([^"\/]+)/i', $body, $m)) foreach ($m[2] as $hst) if (! in_array($hst, ['127.0.0.1:8071'], true)) $ext[] = "$tag -> $hst";
    if (preg_match('/<(?:b|i|q)>(x|y)<\//', $body) || str_contains($body, '<script>alert(7)')) $raw[] = $tag;
    if (! str_starts_with($tag, 'asset') && preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|ErrorException)/', $body)) $raw[] = "$tag PHP-ERROR-TEXT";
}
check('F01', 'No SQL or driver detail in any page of any path (installer failures included)', $leak === [], json_encode($leak));
check('F02', 'No page loads anything from outside the server (scripts, styles, images)', $ext === [], json_encode(array_values(array_unique($ext))));
check('F03', 'No unescaped markup echoed and no PHP error text in any page', $raw === [], json_encode($raw));
$srv = [];
foreach ($SERVERLOGS as $f) foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $l) if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal|Parse)|Warning:|Deprecated:/', $l)) $srv[] = basename($f) . ': ' . $l;
check('F04', 'No PHP warning, notice or deprecation in the server output (error_reporting=-1)', $srv === [], json_encode(array_slice($srv, 0, 10)));
foreach ($LOGS as $k => $lines) {
    $bad = bad_log_lines($lines);
    $pre = array_filter($bad, fn ($l) => preg_match('/Error connecting to the database|doesn\'t exist|installer: database connection failed|Unable to connect/', $l));
    $other = array_values(array_diff($bad, $pre));
    file_put_contents("$WORK/verify-v7.applog-$k.log", implode("\n", $lines) . "\n");
    info("log $k: " . count($lines) . ' lines, ' . count($bad) . ' at WARNING or above (' . count($pre) . ' about the database not configured / tables not created yet / the failed connections asked for)');
    check("F05$k", "Application log ($k) at threshold 9: no warning or error other than the expected database failures", $other === [], json_encode(array_slice(array_map(fn ($l) => substr($l, 0, 200), $other), 0, 8)));
    $preNoAsk = array_filter($bad, fn ($l) => ! str_contains($l, 'installer: database connection failed') && ! preg_match("/Access denied for user '(root|u)|rw7_does_not_exist|db\.invalid|3399|Unknown database|getaddrinfo|actively refused|Rifiuto persistente|No such host|php_network/", $l) && ! preg_match('/^\s/', $l));
    check("F06$k", "Application log ($k): no WARNING/ERROR/CRITICAL line apart from the failed connections the test asked for", $preNoAsk === [], count($preNoAsk) . ' lines, e.g. ' . json_encode(array_slice(array_map(fn ($l) => substr($l, 0, 160), array_values($preNoAsk)), 0, 3)));
}

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "\nTOTAL: $pass PASS, $fail FAIL, " . count($GLOBALS['ambiguities']) . " AMB\n";
