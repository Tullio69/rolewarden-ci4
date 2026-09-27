<?php

/**
 * V1 "Riscontri e impostazioni" black-box verification (Collaudatore ad Hoc, in place of Codex).
 * Written against docs/SPEC.md (Pannello admin, V1 decisions, Toast level), docs/BRIEF-v1.0.md,
 * README (Updating the module, Wiring, Settings and the default role) and the design-system
 * READMEs (Toast, Settings, Button). Field names and formats are read from the served HTML.
 *
 * Usage: php verify-v1-m1.php <app-copy> <module-3631fd2> <module-86f6935>
 * DB credentials only from RW_DB_USERNAME / RW_DB_PASSWORD (passed to CI4 as process env);
 * host and port forced to 127.0.0.1:3307; refuses anything but rolewarden_test.
 */

declare(strict_types=1);

[$_, $APP, $MOD_OLD, $MOD_NEW] = $argv + [null, null, null, null];
putenv('RW_DB_HOSTNAME=127.0.0.1');
putenv('RW_DB_PORT=3307');
putenv('RW_V1_APP=' . str_replace('\\', '/', $APP));

require __DIR__ . '/verify-v1.lib.php';
require __DIR__ . '/verify-v1.fixtures.php';

if (! preg_match('/^database\.default\.database = rolewarden_test$/m', (string) file_get_contents("$APP/.env"))) {
    exit("ABORT: copy .env does not name rolewarden_test\n");
}

const PORT = 8070;
$SRV = null;

function ci_env(): array
{
    $e = getenv();
    $e['database.default.username'] = getenv('RW_DB_USERNAME');
    $e['database.default.password'] = getenv('RW_DB_PASSWORD');
    return $e;
}

function redact(string $s): string
{
    return str_replace([getenv('RW_DB_PASSWORD'), getenv('RW_DB_USERNAME')], '[omesso]', $s);
}

function spark(string ...$args): string
{
    global $APP;
    $cmd = array_merge(['php', '-d', 'error_reporting=-1', 'spark'], $args);
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $APP, ci_env(), ['bypass_shell' => true]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $rc = proc_close($p);
    $out = redact($out);
    echo "   spark " . implode(' ', $args) . " (rc=$rc): " . trim(preg_replace('/\s+/', ' ', substr($out, -300))) . "\n";
    return "rc=$rc\n" . $out;
}

function server_start(): void
{
    global $APP, $SRV;
    $cmd = ['php', '-d', 'error_reporting=-1', '-S', 'localhost:' . PORT, '-t', 'public', 'vendor/codeigniter4/framework/system/rewrite.php'];
    $log = __DIR__ . '/verify-v1-m1.server.log';
    $SRV = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $APP, ci_env(), ['bypass_shell' => true]);
    for ($i = 0; $i < 20; $i++) {
        usleep(500000);
        $c = curl_init('http://localhost:' . PORT . '/index.php/login');
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        curl_exec($c);
        if (curl_getinfo($c, CURLINFO_RESPONSE_CODE) > 0) {
            return;
        }
    }
    server_stop();
    exit("server did not start\n");
}

function server_stop(): void
{
    global $SRV;
    if ($SRV) {
        $pid = proc_get_status($SRV)['pid'];
        exec("taskkill /F /T /PID $pid 2>NUL");
        proc_close($SRV);
        $SRV = null;
    }
}
register_shutdown_function('server_stop');

function use_module(string $dir): void
{
    global $APP;
    $j = "$APP/vendor/rolewarden/codeigniter4-rolewarden";
    if (is_dir($j) || is_link($j)) {
        exec('cmd /c rmdir "' . str_replace('/', '\\', $j) . '"');
    }
    exec('cmd /c mklink /J "' . str_replace('/', '\\', $j) . '" "' . str_replace('/', '\\', $dir) . '" >NUL', $o, $rc);
    if ($rc !== 0 || ! is_dir("$j/src")) {
        exit("junction failed\n");
    }
    foreach (glob("$APP/writable/cache/*") as $f) {
        if (basename($f) !== 'index.html') {
            @unlink($f);
        }
    }
    echo "   module -> " . basename($dir) . "\n";
}

function reset_db(): void
{
    db()->query('DROP DATABASE rolewarden_test');
    db()->query('CREATE DATABASE rolewarden_test');
    db()->select_db('rolewarden_test');
}

function readme_install(): void
{
    spark('migrate', '-n', 'CodeIgniter\Settings');
    spark('migrate', '-n', 'CodeIgniter\Shield');
    spark('migrate', '-n', 'RoleWarden');
    spark('db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder');
}

/** Perms granted to a role slug, as slugs. */
function role_perms(string $slug): array
{
    return array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE r.slug=? ORDER BY p.slug', [$slug]), 'slug');
}

function acl_state(): string
{
    $out = '';
    foreach (['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles', 'acl_user_permissions'] as $t) {
        $rows = q("SELECT * FROM $t");
        $out .= "$t:" . json_encode($rows) . "\n";
    }
    $out .= 'migrations:' . json_encode(q('SELECT class, namespace, batch FROM migrations ORDER BY id')) . "\n";
    return $out;
}

/** Toasts as served without JavaScript (the <noscript> static stack). */
function static_toasts(string $html): array
{
    $out = [];
    if (preg_match('/<div class="rw-toasts rw-toasts--static">(.*?)<\/div>\s*<\/noscript>/s', $html, $m)) {
        preg_match_all('/<div class="rw-toast rw-toast--(\w+)"([^>]*)>\s*<p><span class="rw-toast__kind">([^<]*)<\/span>\s*(.*?)<\/p>/s', $m[1], $ts, PREG_SET_ORDER);
        foreach ($ts as $t) {
            $out[] = ['type' => $t[1], 'alert' => str_contains($t[2], 'role="alert"'), 'kind' => $t[3], 'msg' => trim(html_entity_decode(strip_tags($t[4]))), 'close' => str_contains($m[1], 'rw-toast__close')];
        }
    }
    return $out;
}

/** Messages handed to the Alpine stack (x-data JSON). */
function js_toasts(string $html): array
{
    if (! preg_match('/<div class="rw-toasts" aria-live="polite" x-data="rwToasts\(([^"]*)\)"/', $html, $m)) {
        return [];
    }
    return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true)['messages'] ?? [];
}

function toast_ok(array $ts, string $type): bool
{
    $want = ['success' => 'Saved', 'warning' => 'Warning:', 'error' => 'Error:'][$type];
    foreach ($ts as $t) {
        if ($t['type'] === $type && $t['kind'] === $want && $t['msg'] !== '' && ($type !== 'error' || $t['alert']) && ! $t['close']) {
            return true;
        }
    }
    return false;
}

function after(Client $c): Client
{
    $path = preg_replace('#^.*?index\.php/?#', '', $c->location);
    return $c->get($path === '' ? 'rolewarden/users' : $path);
}

/** The page reached after a form POST shows a toast of $type (static + JS), never in granted colour. */
function action_toast(string $id, string $label, Client $c, string $type): void
{
    $was = $c->status;
    $page = after($c);
    $GLOBALS['SEEN'][] = [$label, $page->status, $page->body];
    $st = static_toasts($page->body);
    $js = js_toasts($page->body);
    check($id, "$label: $type toast (HTTP $was then {$page->status})",
        in_array($was, [302, 303], true) && toast_ok($st, $type) && count($js) >= 1 && $js[0]['type'] === $type && ! preg_match('/rw-toast[^"]*granted|granted[^"]*rw-toast/', $page->body),
        'static=' . json_encode($st) . ' js=' . json_encode($js));
}

function nav_settings(string $html): bool
{
    return (bool) preg_match('#<ul class="rw-nav">(?:(?!</ul>).)*/rolewarden/settings"#s', $html);
}

function selected_default(string $html): ?string
{
    if (! preg_match('#<select[^>]*name="default_role"[^>]*>(.*?)</select>#s', $html, $m)) {
        return null;
    }
    return preg_match('/<option value="([^"]*)" selected/', $m[1], $o) ? $o[1] : '(none selected)';
}

function options_default(string $html): array
{
    preg_match('#<select[^>]*name="default_role"[^>]*>(.*?)</select>#s', $html, $m);
    preg_match_all('/<option value="([^"]*)"/', $m[1] ?? '', $o);
    return $o[1];
}

function facts(string $html): array
{
    preg_match('#<dt>Last changed</dt><dd>(.*?)</dd>#s', $html, $a);
    preg_match('#<dt>By</dt><dd>(.*?)</dd>#s', $html, $b);
    return [trim(html_entity_decode($a[1] ?? '?')), trim(html_entity_decode($b[1] ?? '?'))];
}

function add_user(string $username, string $email, ?string $roleSlug, string $pw): int
{
    q('INSERT INTO users (username, active, created_at, updated_at) VALUES (?,1,NOW(),NOW())', [$username]);
    $id = (int) db()->insert_id;
    q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,?,?,?,0,NOW(),NOW())', [$id, 'email_password', $email, password_hash($pw, PASSWORD_DEFAULT)]);
    if ($roleSlug !== null) {
        q('INSERT INTO acl_user_roles (user_id, role_id) SELECT ?, id FROM acl_roles WHERE slug=?', [$id, $roleSlug]);
    }
    return $id;
}

function add_role(string $slug, array $perms, int $super = 0): int
{
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at) VALUES (?,?,?,NULL,0,?,NOW())', [$slug, strtoupper($slug), 'V1-M1 test role', $super]);
    $id = (int) db()->insert_id;
    foreach ($perms as $p) {
        q('INSERT INTO acl_role_permissions (role_id, permission_id) SELECT ?, id FROM acl_permissions WHERE slug=?', [$id, $p]);
    }
    return $id;
}

function roles_of(string $email): array
{
    return array_column(q('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id JOIN auth_identities i ON i.user_id=ur.user_id WHERE i.secret=? ORDER BY r.slug', [$email]), 'slug');
}

function user_exists(string $email): bool
{
    return (int) q1('SELECT COUNT(*) FROM auth_identities WHERE secret=?', [$email]) === 1;
}

function stored(): string
{
    return json_encode(q('SELECT class, `key`, value FROM settings ORDER BY id'));
}

function register(string $u, string $email, string $pw): Client
{
    $c = new Client('m1-reg-' . $u);
    $t = $c->token('register');
    $c->req('POST', 'register', ['csrf_test_name' => $t, 'email' => $email, 'username' => $u, 'password' => $pw, 'password_confirm' => $pw]);
    $GLOBALS['SEEN'][] = ["register $u", $c->status, $c->body];
    return $c;
}

function panel_create(Client $adm, string $u, string $email, string $pw): Client
{
    return $adm->post('rolewarden/users', ['username' => $u, 'email' => $email, 'password' => $pw], 'rolewarden/users/create');
}

function set_default(Client $adm, string $v): void
{
    $adm->post('rolewarden/settings', ['default_role' => $v], 'rolewarden/settings');
}

$SEEN = [];
$AJAX = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];

// ======================================================================= A. UPGRADE PATH
echo "\n== A. Upgrade path: install 3631fd2 per README, then replace the module folder with 86f6935\n";
reset_db();
use_module($MOD_OLD);
readme_install();
$F = make_fixtures();
$U = $F['u'];
$pw = $F['pw'];

server_start();
$adm = new Client('m1-admin-old');
$loggedOld = $adm->login($U['admin2']['email'], $pw);
$oldUsers = $adm->get('rolewarden/users');
$SEEN[] = ['old users', $oldUsers->status, $oldUsers->body];
$oldHasNav = nav_settings($oldUsers->body);
server_stop();
$preUpgrade = acl_state();
$before = (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug IN ('settings.view','settings.update')");
check('A01', '3631fd2 installed per README, admin signed in, no settings.* permission, no Settings menu', $loggedOld && $before === 0 && ! $oldHasNav && $oldUsers->status === 200, "login=$loggedOld before=$before nav=$oldHasNav");

use_module($MOD_NEW);
$mig = spark('migrate', '-n', 'RoleWarden');
$ap = role_perms('admin');
check('A02', 'Upgrade: `php spark migrate -n RoleWarden` succeeds and adds settings.view + settings.update', str_starts_with($mig, 'rc=0') && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug IN ('settings.view','settings.update')") === 2, substr($mig, 0, 200));
check('A03', 'Upgrade: both granted to the admin role', in_array('settings.view', $ap, true) && in_array('settings.update', $ap, true), implode(',', $ap));
echo '[INFO] settings.* granted to roles other than admin after upgrade: ' . json_encode(q("SELECT r.slug, p.slug ps FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug LIKE 'settings.%' AND r.slug <> 'admin'")) . "\n";

server_start();
$s = $adm->get('rolewarden/settings');
$SEEN[] = ['upg settings', $s->status, $s->body];
check('A04', 'Admin signed in before the upgrade opens Settings at the next request, without logging out', $s->status === 200 && str_contains($s->body, 'name="default_role"'), "HTTP {$s->status} " . substr(trim(preg_replace("/\s+/", " ", strip_tags(clean_html($s->body)))), 0, 600));
$u = $adm->get('rolewarden/users');
check('A05', 'Same admin now sees the Settings menu entry', nav_settings($u->body), 'no settings link in nav');
server_stop();

$mig = spark('migrate:rollback', '-b', '3', '-f');
$post = acl_state();
check('A06', 'Rollback of the upgrade batch (-b 3): acl_* tables and migrations identical to before the upgrade', str_starts_with($mig, 'rc=0') && $post === $preUpgrade, 'diff: ' . substr(json_encode(array_values(array_diff(explode("\n", $post), explode("\n", $preUpgrade)))), 0, 400));
check('A07', 'After rollback no settings.* permission remains', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'settings.%'") === 0, 'residue');
server_start();
$s = $adm->get('rolewarden/settings');
$SEEN[] = ['rolled back settings', $s->status, $s->body];
check('A08', 'After rollback the same admin is refused on Settings at the next request', $s->status !== 200 && ! str_contains($s->body, 'name="default_role"'), "HTTP {$s->status}");
server_stop();
$mig = spark('migrate', '-n', 'RoleWarden');
check('A09', 'Migration re-applies after the rollback', str_starts_with($mig, 'rc=0') && in_array('settings.update', role_perms('admin'), true), substr($mig, 0, 200));

// ======================================================================= B. FRESH INSTALL
echo "\n== B. Fresh install of 86f6935 per README\n";
reset_db();
readme_install();
$ap = role_perms('admin');
check('B01', 'Fresh install: settings.view + settings.update exist and are granted to admin', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug IN ('settings.view','settings.update')") === 2 && in_array('settings.view', $ap, true) && in_array('settings.update', $ap, true), implode(',', $ap));
$mig = spark('migrate:rollback', '-b', '2', '-f');
$left = q("SELECT table_name t FROM information_schema.tables WHERE table_schema='rolewarden_test' AND table_name LIKE 'acl\\_%'");
$sett = (int) q1('SELECT COUNT(*) FROM settings');
check('B02', 'Fresh install: rollback to Shield\'s batch removes every RoleWarden table and migration row; settings table empty', str_starts_with($mig, 'rc=0') && $left === [] && (int) q1("SELECT COUNT(*) FROM migrations WHERE namespace='RoleWarden'") === 0 && $sett === 0, json_encode($left) . " settings=$sett");
spark('migrate', '-n', 'RoleWarden');
spark('db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder');
$F = make_fixtures();
$U = $F['u'];
$role = $F['role'];
$perm = $F['perm'];
$pw = $F['pw'];
add_role('m1-viewer', ['users.view', 'settings.view']);
$viewerEmail = 'viewer@m1.test';
add_user('m1viewer', $viewerEmail, 'm1-viewer', $pw);
add_role('m1-root', [], 1); // a second, non-system super admin role

server_start();

// ======================================================================= C. PERMISSIONS AND MENU
echo "\n== C. settings.view / settings.update\n";
$adm = new Client('m1-admin');
$adm->login($U['admin2']['email'], $pw);
$lim = new Client('m1-limited');
$lim->login($U['limited']['email'], $pw);
$vw = new Client('m1-viewer');
$vw->login($viewerEmail, $pw);
$chi = new Client('m1-child');
$chi->login($U['child']['email'], $pw);
$anon = new Client('m1-anon');

$a = $adm->get('rolewarden/users');
check('C01', 'Admin: Settings in the menu', nav_settings($a->body), 'missing');
$l = $lim->get('rolewarden/users');
$c = $chi->get('rolewarden/roles');
check('C02', 'Without settings.view (users.view only; roles.view + users.view): no Settings menu entry', $l->status === 200 && $c->status === 200 && ! nav_settings($l->body) && ! nav_settings($c->body), "lim {$l->status} child {$c->status}");
$l = $lim->get('rolewarden/settings');
$SEEN[] = ['lim settings', $l->status, $l->body];
check('C03', 'Without settings.view GET /rolewarden/settings is refused', $l->status !== 200 && ! str_contains($l->body, 'default_role'), "HTTP {$l->status} -> {$l->location}");
echo "[INFO] refusal of C03: HTTP {$l->status} -> {$l->location}\n";
$an = $anon->get('rolewarden/settings');
check('C04', 'Anonymous GET /rolewarden/settings goes to login', in_array($an->status, [302, 303], true) && str_contains($an->location, 'login'), "HTTP {$an->status} -> {$an->location}");
$v = $vw->get('rolewarden/users');
check('C05', 'settings.view without settings.update: Settings in the menu', nav_settings($v->body), 'missing');
$v = $vw->get('rolewarden/settings');
$SEEN[] = ['viewer settings', $v->status, $v->body];
$hasSubmit = (bool) preg_match('/type="submit"|Save settings/', preg_replace('/<header.*?<\/header>/s', '', $v->body));
check('C06', 'settings.view only: page opens, value shown, no Save button rendered', $v->status === 200 && ! $hasSubmit && str_contains($v->body, 'Default role for new users'), "HTTP {$v->status} submit=" . var_export($hasSubmit, true));
echo '[INFO] viewer page: select disabled=' . var_export((bool) preg_match('/<select[^>]*name="default_role"[^>]*disabled|<select[^>]*disabled[^>]*name="default_role"/', $v->body), true) . ', POST form present=' . var_export(str_contains($v->body, 'method="post" action="http://localhost:8070/index.php/rolewarden/settings"'), true) . "\n";
$st0 = stored();
$vw->post('rolewarden/settings', ['default_role' => 'user'], 'rolewarden/settings');
check('C07', 'settings.view only: POST refused (not by CSRF), nothing stored', stored() === $st0 && ! csrf_refused($vw) && ! (in_array($vw->status, [302, 303], true) && str_ends_with($vw->location, 'rolewarden/settings')), "HTTP {$vw->status} -> {$vw->location}");
echo "[INFO] refusal of C07: HTTP {$vw->status} -> {$vw->location}\n";
$lim->post('rolewarden/settings', ['default_role' => 'user'], 'rolewarden/users');
check('C08', 'No settings permission at all: POST refused, nothing stored', stored() === $st0 && ! csrf_refused($lim) && ! (in_array($lim->status, [302, 303], true) && str_ends_with($lim->location, 'rolewarden/settings')), "HTTP {$lim->status} -> {$lim->location}");

// ======================================================================= D. SETTINGS SCREEN
echo "\n== D. Settings screen\n";
$s = $adm->get('rolewarden/settings');
$opts = options_default($s->body);
$nonSuper = array_column(q('SELECT slug FROM acl_roles WHERE is_super_admin=0 ORDER BY slug'), 'slug');
$super = array_column(q('SELECT slug FROM acl_roles WHERE is_super_admin=1'), 'slug');
check('D01', 'Section "Roles" with the default-role row and a <select>', (bool) preg_match('#<h2>Roles</h2>#', $s->body) && preg_match('#<select[^>]*name="default_role"#', $s->body) === 1, 'missing section or select');
check('D02', 'Options: "None" plus every non-super-admin role; no super admin role (super-admin, m1-root) offered', $opts !== [] && $opts[0] === '' && str_contains($s->body, '>None</option>') && array_intersect($super, $opts) === [] && array_values(array_diff($nonSuper, $opts)) === [], 'opts=' . json_encode($opts) . ' super=' . json_encode($super));
[$lc, $by] = facts($s->body);
check('D03', 'Before any save: Last changed "Never", value None', $lc === 'Never' && selected_default($s->body) === '', "lc=$lc by=$by sel=" . selected_default($s->body));

set_default($adm, 'user');
action_toast('D04', 'Save default role = user', $adm, 'success');
$s = $adm->get('rolewarden/settings');
[$lc, $by] = facts($s->body);
check('D05', 'Re-read: user selected; "Last changed" is a date, "By" is the admin', selected_default($s->body) === 'user' && $lc !== 'Never' && $lc !== '?' && $by === $U['admin2']['username'], 'sel=' . selected_default($s->body) . " lc=$lc by=$by");
echo '[INFO] settings rows: ' . json_encode(q('SELECT class, `key`, value, type, context FROM settings')) . "\n";
$st0 = stored();

foreach ([['D06', 'super-admin'], ['D07', 'm1-root'], ['D08', 'zz-no-such-role'], ['D09', (string) $role('admin')]] as [$id, $val]) {
    set_default($adm, $val);
    $st = $adm->status;
    $pg = after($adm);
    $SEEN[] = ["post $val", $pg->status, $pg->body];
    $still = stored() === $st0 && selected_default($pg->body) === 'user';
    if ($id === 'D09') {
        echo "[INFO] POST default_role=<admin role id> (an id, not a slug): HTTP $st, toasts=" . json_encode(static_toasts($pg->body)) . ', unchanged=' . var_export($still, true) . "\n";
        set_default($adm, 'user');
        $st0 = stored();
        continue;
    }
    check($id, "POST default_role=$val rejected with an error toast, value unchanged", in_array($st, [302, 303], true) && toast_ok(static_toasts($pg->body), 'error') && $still, "HTTP $st toasts=" . json_encode(static_toasts($pg->body)) . ' sel=' . selected_default($pg->body));
}
$adm->req('POST', 'rolewarden/settings', ['default_role' => 'v1-limited']);
check('D10', 'POST without CSRF token refused, value unchanged', (csrf_refused($adm) || $adm->status === 403) && stored() === $st0, "HTTP {$adm->status}");
$adm->req('POST', 'rolewarden/settings', ['default_role' => 'v1-limited', 'csrf_test_name' => str_repeat('0', 32)]);
check('D11', 'POST with a forged CSRF token refused, value unchanged', (csrf_refused($adm) || $adm->status === 403) && stored() === $st0, "HTTP {$adm->status}");
$adm->post('rolewarden/settings', [], 'rolewarden/settings');
$pg = after($adm);
echo '[INFO] POST without default_role: toasts=' . json_encode(static_toasts($pg->body)) . ' selected=' . selected_default($pg->body) . "\n";
set_default($adm, 'user');

// ======================================================================= E. TOASTS ACROSS THE PANEL
echo "\n== E. Toasts on the existing screens\n";
$sp = $adm->get('rolewarden/settings');
check('E01', 'Served stack: div.rw-toasts with aria-live="polite"; close button aria-label="Dismiss" only in the JS template', (bool) preg_match('/<div class="rw-toasts" aria-live="polite"/', $sp->body) && str_contains($sp->body, 'aria-label="Dismiss"'), 'stack markup missing');
$adm->post('rolewarden/roles', ['slug' => 'm1-temp', 'name' => 'M1 Temp', 'description' => 'temporary', 'parent_id' => ''], 'rolewarden/roles/create');
action_toast('E02', 'Create role', $adm, 'success');
$tmp = $role('m1-temp');
$adm->post('rolewarden/roles/' . $tmp, ['name' => 'M1 Temp 2', 'description' => 'temporary', 'parent_id' => ''], 'rolewarden/roles/' . $tmp . '/edit');
action_toast('E03', 'Update role', $adm, 'success');
$adm->post('rolewarden/roles/' . $role('admin') . '/delete', [], 'rolewarden/roles');
action_toast('E04', 'Delete a system role (refused)', $adm, 'error');
$adm->post('rolewarden/roles', ['slug' => '', 'name' => '', 'description' => '', 'parent_id' => ''], 'rolewarden/roles/create');
$pg = after($adm);
check('E05', 'Role form validation: "Error:" stays on the form page, no toast', static_toasts($pg->body) === [] && js_toasts($pg->body) === [] && str_contains($pg->body, 'Error:') && str_contains($pg->body, 'field is required'), 'toasts=' . json_encode(static_toasts($pg->body)));
$adm->post('rolewarden/users', ['username' => 'm1form', 'email' => 'not-an-email', 'password' => 'x'], 'rolewarden/users/create');
$pg = after($adm);
check('E06', 'User form validation: errors stay on the form page, no toast', static_toasts($pg->body) === [] && js_toasts($pg->body) === [] && str_contains($pg->body, 'Error:'), 'toasts=' . json_encode(static_toasts($pg->body)) . " HTTP {$pg->status}");
$sid = $U['subject']['id'];
$adm->post("rolewarden/users/$sid/roles", ['role_id' => $role('v1-limited')], "rolewarden/users/$sid");
action_toast('E07', 'Assign a role to a user', $adm, 'success');
$adm->post("rolewarden/users/$sid/roles/" . $role('v1-limited') . '/revoke', [], "rolewarden/users/$sid");
action_toast('E08', 'Revoke a role from a user', $adm, 'success');
$aid = $U['admin2']['id'];
$adm->post("rolewarden/users/$aid/roles/" . $role('admin') . '/revoke', [], "rolewarden/users/$aid");
action_toast('E09', 'Revoke own role holding roles.assign (refused, A2)', $adm, 'error');
$adm->post("rolewarden/users/$sid/deactivate", [], "rolewarden/users/$sid");
action_toast('E10', 'Deactivate a user', $adm, 'success');
q('UPDATE users SET active=1 WHERE id=?', [$sid]);

$C = $role('v1-child');
$adm->post("rolewarden/roles/$C/permissions", ['permission_id' => $perm('users.create'), 'granted' => 1], "rolewarden/roles/$C", $AJAX);
$j = json_decode($adm->body, true);
echo "[INFO] matrix save ok: HTTP {$adm->status} " . json_encode(array_diff_key((array) $j, ['csrfHash' => 1])) . "\n";
check('E11', 'Matrix save succeeds as JSON ok:true', $adm->status === 200 && ($j['ok'] ?? null) === true, $adm->body);
$adm->post("rolewarden/roles/$C/permissions", ['permission_id' => $perm('roles.view'), 'granted' => 0], "rolewarden/roles/$C", $AJAX);
$j = json_decode($adm->body, true);
check('E12', 'Matrix, inherited cell revoked: JSON error whose message says what to do and names the origin role', $adm->status >= 400 && ($j['ok'] ?? null) === false && str_contains((string) ($j['message'] ?? ''), 'V1 Parent'), $adm->body);
echo "[INFO] E12 message: " . ($j['message'] ?? '') . "\n";
$adm->post("rolewarden/roles/$C/permissions", ['permission_id' => 99999, 'granted' => 1], "rolewarden/roles/$C", $AJAX);
$j = json_decode($adm->body, true);
check('E13', 'Matrix, unknown permission: JSON error with a non-empty message, no SQL text', $adm->status >= 400 && ($j['ok'] ?? null) === false && trim((string) ($j['message'] ?? '')) !== '' && ! leaks_sql($adm->body), $adm->body);
$m404 = (string) ($j['message'] ?? '');
$adm->post("rolewarden/users/$sid/permissions", ['permission_id' => 99999, 'granted' => 1], "rolewarden/users/$sid", $AJAX);
$j = json_decode($adm->body, true);
check('E14', 'User overrides, unknown permission: JSON error with a non-empty message', $adm->status >= 400 && ($j['ok'] ?? null) === false && trim((string) ($j['message'] ?? '')) !== '', $adm->body);
echo "[INFO] 404 messages: role matrix='$m404' user overrides='" . ($j['message'] ?? '') . "'\n";
$adm->req('POST', "rolewarden/roles/$C/permissions", ['permission_id' => $perm('users.delete'), 'granted' => 1, 'csrf_test_name' => str_repeat('0', 32)], $AJAX);
echo "[INFO] matrix save with forged CSRF: HTTP {$adm->status} body=" . substr(trim(preg_replace('/\s+/', ' ', strip_tags($adm->body))), 0, 200) . "\n";

// ======================================================================= F. DEFAULT ROLE
echo "\n== F. Default role for new users\n";
$regPw = 'Tq8#vLw2!Rz5pXk';

set_default($adm, 'v1-limited');
$r = register('m1reg1', 'reg1@m1.test', $regPw);
check('F01', 'Default v1-limited: user registered through Shield /register gets exactly v1-limited', user_exists('reg1@m1.test') && roles_of('reg1@m1.test') === ['v1-limited'], "HTTP {$r->status} -> {$r->location} roles=" . json_encode(roles_of('reg1@m1.test')));
panel_create($adm, 'm1panel1', 'panel1@m1.test', $regPw);
action_toast('F02a', 'Create user from the panel', $adm, 'success');
check('F02', 'Default v1-limited: user created from the panel gets exactly v1-limited', roles_of('panel1@m1.test') === ['v1-limited'], json_encode(roles_of('panel1@m1.test')));

set_default($adm, '');
$pg = $adm->get('rolewarden/settings');
check('F03', 'Default set back to None and re-read as None', selected_default($pg->body) === '', 'sel=' . selected_default($pg->body));
register('m1reg2', 'reg2@m1.test', $regPw);
panel_create($adm, 'm1panel2', 'panel2@m1.test', $regPw);
check('F04', 'Default None: registered and panel-created users get no role', user_exists('reg2@m1.test') && user_exists('panel2@m1.test') && roles_of('reg2@m1.test') === [] && roles_of('panel2@m1.test') === [], json_encode([roles_of('reg2@m1.test'), roles_of('panel2@m1.test')]));

set_default($adm, 'm1-temp');
$pg = $adm->get('rolewarden/settings');
check('F05', 'Default set to the custom role m1-temp', selected_default($pg->body) === 'm1-temp', 'sel=' . selected_default($pg->body));
$adm->post('rolewarden/roles/' . $tmp . '/delete', [], 'rolewarden/roles');
$delStatus = $adm->status;
$pgDel = after($adm);
$soft = q("SHOW COLUMNS FROM acl_roles LIKE 'deleted_at'") !== [];
$gone = (int) q1('SELECT COUNT(*) FROM acl_roles WHERE slug=?' . ($soft ? ' AND deleted_at IS NULL' : ''), ['m1-temp']) === 0;
check('F06', 'The chosen default role can then be deleted from the panel', $gone && toast_ok(static_toasts($pgDel->body), 'success'), "HTTP $delStatus toasts=" . json_encode(static_toasts($pgDel->body)));
if (! $gone) {
    q('DELETE FROM acl_roles WHERE slug=?', ['m1-temp']);
}
$r = register('m1reg3', 'reg3@m1.test', $regPw);
$regOk = in_array($r->status, [302, 303], true) && ! str_contains($r->location, 'register') && ! leaks_sql($r->body);
panel_create($adm, 'm1panel3', 'panel3@m1.test', $regPw);
$pcSt = $adm->status;
$pg = after($adm);
check('F07', 'Deleted default role: Shield registration still succeeds and gives no role', $regOk && user_exists('reg3@m1.test') && roles_of('reg3@m1.test') === [], "HTTP {$r->status} -> {$r->location} roles=" . json_encode(roles_of('reg3@m1.test')));
check('F08', 'Deleted default role: panel creation still succeeds (success toast) and gives no role', user_exists('panel3@m1.test') && roles_of('panel3@m1.test') === [] && toast_ok(static_toasts($pg->body), 'success'), "HTTP $pcSt toasts=" . json_encode(static_toasts($pg->body)) . ' roles=' . json_encode(roles_of('panel3@m1.test')));
$pg = $adm->get('rolewarden/settings');
$SEEN[] = ['settings after delete', $pg->status, $pg->body];
check('F09', 'Settings page still opens after the default role was deleted', $pg->status === 200, "HTTP {$pg->status}");
echo '[INFO] Settings after the default role was deleted: selected=' . selected_default($pg->body) . ', toasts=' . json_encode(static_toasts($pg->body)) . ', stored=' . stored() . "\n";

// verify-v1.php D04 now fails because the nav links /rolewarden/settings: check the rest of its pattern alone.
$deferred = [];
foreach (['rolewarden/users', 'rolewarden/users/' . $U['subject']['id'], 'rolewarden/roles', 'rolewarden/settings'] as $p) {
    $b = clean_html($adm->get($p)->body);
    $SEEN[] = ["deferred $p", $adm->status, $adm->body];
    if (preg_match('/Send reset link|Sign out everywhere|Export CSV|Invite user|Session|Remember|Lockout|Two-factor|2FA|Audit log|Reset/i', strip_tags($b), $m)) {
        $deferred[] = "$p: {$m[0]}";
    }
}
check('G05', 'Deferred controls (reset link, sign out everywhere, CSV, invite, V2/V3/v1.5 settings rows, Reset section) not rendered on users, user, roles, settings', $deferred === [], implode('; ', $deferred));

server_stop();

// ======================================================================= G. ERRORS AND LOGS
echo "\n== G. No PHP or SQL error text, clean logs\n";
$bad = [];
foreach ($SEEN as [$what, $st, $body]) {
    if (leaks_sql($body) || preg_match('/(<b>(Warning|Notice|Deprecated|Fatal error)<\/b>:|A PHP Error was encountered|ErrorException|Uncaught )/', clean_html($body))) {
        $bad[] = "$what ($st)";
    }
}
check('G01', 'No SQL/PHP error text on ' . count($SEEN) . ' captured pages', $bad === [], implode('; ', $bad));
$green = [];
foreach ($SEEN as [$what, $st, $body]) {
    if (preg_match('/class="[^"]*(rw-flash--(?!error)\w+|rw-toast--success[^"]*granted)/', $body, $m)) {
        $green[] = "$what: {$m[1]}";
    }
}
check('G04', 'No non-error flash box and no granted-coloured success toast on the captured pages', $green === [], implode('; ', $green));
$lines = [];
foreach (glob("$APP/writable/logs/*.log") as $f) {
    foreach (file($f) as $ln) {
        if (preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $ln)) {
            $lines[] = trim($ln);
        }
    }
}
$csrf = array_filter($lines, fn ($l) => str_contains($l, 'SecurityException') || str_contains($l, 'The action you requested is not allowed'));
$other = array_values(array_diff($lines, $csrf));
echo '[INFO] app log: ' . count($csrf) . ' CSRF refusals (provoked), other entries >= warning: ' . count($other) . "\n";
check('G02', 'App log: no warning/notice/deprecation/error besides the provoked CSRF refusals', $other === [], implode("\n            ", array_slice($other, 0, 10)));
$srv = (string) @file_get_contents(__DIR__ . '/verify-v1-m1.server.log');
check('G03', 'Built-in server stderr: no PHP Warning/Notice/Deprecated/Fatal', ! preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/', $srv), 'see server log');

foreach (glob(sys_get_temp_dir() . '/rwv1-m1-*-' . getmypid() . '.jar') as $j) {
    @unlink($j);
}
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
printf("\nRESULT: %d checks, %d PASS, %d FAIL\n", count($GLOBALS['results']), $pass, count($GLOBALS['results']) - $pass);

