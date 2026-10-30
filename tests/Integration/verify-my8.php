<?php
/**
 * MY8 (Collaudatore ad Hoc, Claude): does RoleWarden work on MySQL 8? Black-box suite on a temporary MySQL container
 * (rolewarden-my8-84 = mysql:8.4, rolewarden-my8-80 = mysql:8.0, 127.0.0.1:3318, image defaults: strict sql_mode and
 * ONLY_FULL_GROUP_BY untouched) and a copy of ../rolewarden-app-test in the scratchpad, whose vendor/rolewarden junction
 * points at a worktree of the commit under test. Assertions only from docs/SPEC.md, CHANGELOG, documentation/ and the HTML
 * a browser receives; facts checked with direct queries on rolewarden_test.
 *
 * Usage: php verify-my8.php phases=A,RB,B,P,I,X,Z label=84
 *   A   installation by hand (installation.html#migrations): Settings, Shield, RoleWarden in separate batches, seeder,
 *       demo:install, first super admin with shield:user and the documented INSERT
 *   RB  rollback of RoleWarden's batch alone (installation.html "Removing the module"), then applied again
 *   B   browser installer on an empty database, up to the first super admin
 *   P   the panel in use (super admin and a limited user), each step checked with queries
 *   I   integrity: charset/collation, foreign keys and cascades, non-ASCII text
 *   X   production: no SQL on screen (every screen, a broken query path, the database paused)
 *   Z   log scan of writable/logs (SQL errors) for the whole run
 * Needs RW_DB_USERNAME / RW_DB_PASSWORD (harness, root of the temporary server), MY8_APP_USER / MY8_APP_PASS (the
 * application's database user), MY8_APP (the application copy), MY8_CT (container name) in the process environment.
 */
declare(strict_types=1);

require __DIR__ . '/verify-my8.lib.php';

$O = ['phases' => 'A,RB,B,P,I,X,Z', 'label' => 'run'];
foreach (array_slice($argv, 1) as $a) if (preg_match('/^(\w+)=(.*)$/s', $a, $m)) $O[$m[1]] = $m[2];
$PH = explode(',', $O['phases']);
define('APP', str_replace('\\', '/', (string) getenv('MY8_APP')));
define('CT', (string) getenv('MY8_CT'));
define('BASE', 'http://localhost:' . PORT7 . '/');
foreach (['MY8_APP', 'MY8_CT', 'MY8_APP_USER', 'MY8_APP_PASS'] as $v) if (! getenv($v)) exit("Missing $v\n");
if (! is_file(APP . '/spark')) exit("MY8_APP is not an application\n");

const OPW = 'Quartz-Lantern-9183';
const UPW = 'Maple-Harbour-7314';
const DPW = 'Demo-Pass-8437-Qx';
const OWNER = 'owner@my8.test';
// security emails: the application's own Config\Email has a sender and protocol "mail"; the harness sends them to a
// closed local SMTP port so that nothing leaves the machine and the shared Mailpit is never touched
const MAILENV = "email.protocol = smtp\nemail.SMTPHost = 127.0.0.1\nemail.SMTPPort = 9\nemail.SMTPCrypto = ''\nemail.SMTPTimeout = 2\n";
const AJAX = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
$SEEN = [];

info('MY8 ' . $O['label'] . ': server ' . db()->server_info . ' container ' . CT . ' phases ' . $O['phases']);
info('sql_mode=' . q1('SELECT @@GLOBAL.sql_mode') . ' charset=' . q1('SELECT @@character_set_server') . ' collation=' . q1('SELECT @@collation_server'));

/* ------------------------------------------------------------------ helpers */
function seen(Client $c, string $tag): string { $GLOBALS['SEEN'][] = [$tag, $c->status, $c->url, $c->body]; return $c->body; }
function G(Client $c, string $u, string $tag = ''): string { $c->get($u); return seen($c, $tag ?: "GET $u"); }
function P(Client $c, string $u, array $d, string $tag = '', array $h = []): string { $c->req('POST', $u, $d, $h); return seen($c, $tag ?: "POST $u"); }
function F(Client $c, string $tag = 'follow'): string { for ($i = 0; $i < 6 && in_array($c->status, [301, 302, 303, 307], true); $i++) G($c, abs_url($c->url, $c->location), $tag); return $c->body; }
function short(string $s, int $n = 300): string { $s = trim(preg_replace('/\s+/', ' ', $s)); return strlen($s) > $n ? substr($s, 0, $n) . '...' : $s; }
function phperr(string $s): bool { return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|ErrorException|Uncaught|TypeError|ArgumentCountError)/', $s); }
function ok200(Client $c): bool { return $c->status === 200 && ! leaks_sql($c->body) && ! phperr(text_of($c->body)); }
function c(string $n): Client { return new Client(BASE, $n); }
function uid(string $email): int { return (int) q1("SELECT user_id FROM auth_identities WHERE type='email_password' AND secret=?", [$email]); }
function role_id(string $slug): int { return (int) q1('SELECT id FROM acl_roles WHERE slug=? AND deleted_at IS NULL', [$slug]); }
function perm_id(string $slug): int { return (int) q1('SELECT id FROM acl_permissions WHERE slug=?', [$slug]); }
function sp(array $args, ?string $stdin = null): array
{
    $e = getenv();
    unset($e['RW_DB_USERNAME'], $e['RW_DB_PASSWORD'], $e['MY8_APP_USER'], $e['MY8_APP_PASS']);
    $p = proc_open(array_merge(['php', '-d', 'error_reporting=-1', 'spark'], $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, APP, $e, ['bypass_shell' => true]);
    if ($stdin !== null) fwrite($pp[0], $stdin);
    fclose($pp[0]);
    $o = stream_get_contents($pp[1]) . stream_get_contents($pp[2]);
    $rc = proc_close($p);
    return [$rc, redact($o)];
}
function write_env(string $env, bool $db = true, string $extra = ''): void
{
    $s = "CI_ENVIRONMENT = $env\napp.baseURL = '" . BASE . "'\n";
    if ($db) $s .= "database.default.hostname = 127.0.0.1\ndatabase.default.port = " . DBPORT8 . "\ndatabase.default.database = rolewarden_test\ndatabase.default.username = " . getenv('MY8_APP_USER') . "\ndatabase.default.password = " . getenv('MY8_APP_PASS') . "\ndatabase.default.DBDriver = MySQLi\n";
    $s .= "logger.threshold = 9\ndemo.password = " . DPW . "\n" . MAILENV . $extra;
    file_put_contents(APP . '/.env', $s);
}
function set_env_value(string $k, string $v): void
{
    $s = (string) @file_get_contents(APP . '/.env');
    $re = '/^' . preg_quote($k, '/') . '\s*=.*$/m';
    $s = preg_match($re, $s) ? preg_replace($re, "$k = $v", $s) : rtrim($s) . "\n$k = $v\n";
    file_put_contents(APP . '/.env', $s);
}
function clean_writable(): void
{
    // the permission cache lives in writable/cache: emptied whenever the database starts again
    foreach (['rolewarden', 'cache', 'session', 'debugbar'] as $d) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP . "/writable/$d", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) if ($f->getFilename() !== 'index.html' || $f->getPath() !== APP . "/writable/$d") $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}
function login2(Client $c, string $email, string $pw, array $hdr = []): bool
{
    $c->extra = array_merge($c->extra, $hdr);
    $h = G($c, 'login', "login form $email");
    $f = form_with($h, 'password');
    if (! $f) return false;
    $v = array_replace($f['fields'], ['email' => $email, 'password' => $pw]);
    unset($v['remember']);
    P($c, abs_url($c->url, $f['action']), $v, "login $email");
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, 'login');
}
function panelp(Client $c, string $page, string $url, array $data, string $tag, bool $ajax = true): string
{
    $t = csrf_of(G($c, $page, "token $page"));
    return P($c, $url, ['csrf_test_name' => $t] + $data, $tag, $ajax ? AJAX : []);
}
function form_action(string $h, string $re): ?array { foreach (forms($h) as $f) if (preg_match($re, $f['action'])) return $f; return null; }
function submit(Client $c, array $f, array $vals, string $tag): string { return P($c, abs_url($c->url, $f['action'] ?: $c->url), array_replace($f['fields'], $vals), $tag); }
function migrations(): array { return q('SELECT namespace, batch, COUNT(*) n FROM migrations GROUP BY namespace, batch ORDER BY batch, namespace'); }
function sqlerr_lines(): array
{
    return array_values(array_filter(logs_of(APP), fn ($l) => preg_match('/(mysqli_sql_exception|SQLSTATE|DatabaseException|You have an error in your SQL syntax|Illegal mix of collations|ONLY_FULL_GROUP_BY|only_full_group_by|isn\'t in GROUP BY|Incorrect (string|datetime|integer) value|Data too long|Out of range value|doesn\'t have a default value|cannot be null|foreign key constraint fails|Unknown column|Deadlock|Lock wait timeout|Incorrect table definition|Specified key was too long)/i', $l)));
}
function rows_text(string $h, string $xpath = '//table//tbody/tr'): array
{
    $o = []; $xp = dom($h);
    foreach ($xp->query($xpath) as $tr) $o[] = trim(preg_replace('/\s+/u', ' ', $tr->textContent));
    return $o;
}

/* ================================================================== A: installation by hand */
if (in_array('A', $PH, true)) {
    echo "\n== A: installation by hand (installation.html#migrations)\n";
    empty_db();
    clean_writable();
    foreach (glob(APP . '/writable/logs/*.log') ?: [] as $f) unlink($f);
    write_env('development');
    $steps = [];
    foreach ([["migrate", "-n", 'CodeIgniter\Settings'], ["migrate", "-n", 'CodeIgniter\Shield'], ["migrate", "-n", "RoleWarden"], ["db:seed", 'RoleWarden\Database\Seeds\RoleWardenSeeder']] as $i => $args) {
        [$rc, $o] = sp($args);
        $steps[] = [implode(' ', $args), $rc, $o];
        check('A0' . ($i + 1), 'php spark ' . implode(' ', $args) . ' (exit 0, no error in the output)', $rc === 0 && ! preg_match('/(error|exception|SQLSTATE)/i', $o), "rc=$rc " . short($o, 600));
    }
    $mg = migrations();
    info('migrations: ' . json_encode($mg));
    $byNs = []; foreach ($mg as $r) $byNs[$r['namespace']][] = (int) $r['batch'];
    $b = fn ($ns) => $byNs[$ns] ?? [];
    check('A05', 'Three batches: Settings, Shield and RoleWarden each in a batch of its own', count($b('CodeIgniter\Settings')) === 1 && count($b('CodeIgniter\Shield')) === 1 && count($b('RoleWarden')) === 1 && count(array_unique([$b('CodeIgniter\Settings')[0] ?? 0, $b('CodeIgniter\Shield')[0] ?? 0, $b('RoleWarden')[0] ?? 0])) === 3, json_encode($byNs));
    $t = tables();
    $want = ['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles', 'acl_user_permissions', 'acl_sessions', 'acl_activity_log', 'users', 'auth_identities', 'auth_logins', 'settings'];
    check('A06', 'Tables of Settings, Shield and RoleWarden present (SPEC "Modello dati", v1.0 tables)', ! array_diff($want, $t), json_encode(array_values(array_diff($want, $t))) . ' tables=' . json_encode($t));
    $roles = array_column(q('SELECT slug FROM acl_roles WHERE deleted_at IS NULL ORDER BY slug'), 'slug');
    $np = (int) q1('SELECT COUNT(*) FROM acl_permissions');
    check('A07', 'Seeder: system roles (super-admin, admin) and the permissions, admin holding them', in_array('super-admin', $roles, true) && in_array('admin', $roles, true) && $np > 10 && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=?', [role_id('admin')]) > 10, "roles=" . json_encode($roles) . " permissions=$np");
    [$rc, $o] = sp(['demo:install']);
    check('A08', 'php spark demo:install (exit 0): demo table, three accounts, sample documents', $rc === 0 && in_array('demo_documents', tables(), true) && uid('reader@example.com') > 0 && (int) q1('SELECT COUNT(*) FROM demo_documents') > 0, "rc=$rc " . short($o, 600));
    [$rc1, $o1] = sp(['shield:user', 'create', '-n', 'my8owner', '-e', OWNER], OPW . "\n" . OPW . "\n");
    [$rc2, $o2] = sp(['shield:user', 'activate', '-e', OWNER], "y\n");
    q("INSERT INTO acl_user_roles (user_id, role_id) SELECT i.user_id, r.id FROM auth_identities i JOIN acl_roles r ON r.slug = 'super-admin' WHERE i.type = 'email_password' AND i.secret = ?", [OWNER]);
    check('A09', 'First super admin by hand: shield:user create/activate and the documented INSERT', $rc1 === 0 && $rc2 === 0 && (int) q1('SELECT active FROM users WHERE id=?', [uid(OWNER)]) === 1 && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [uid(OWNER)]) === 'super-admin', short($o1 . ' | ' . $o2, 500));
    info('migrations after demo:install: ' . json_encode(migrations()));
    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $own = c('A-owner');
    $lok = login2($own, OWNER, OPW);
    $bad = [];
    foreach (['rolewarden', 'rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/profile', 'rolewarden/sessions'] as $s) { $h = G($own, $s, "A screen $s"); if (! ok200($own)) $bad[] = "$s {$own->status} " . short(text_of($h), 160); }
    check('A10', 'Installed by hand: the super admin signs in and the main screens answer 200 without SQL or PHP errors', $lok && $bad === [], json_encode($bad));
    $rd = c('A-reader'); $rl = login2($rd, 'reader@example.com', DPW); $h = G($rd, 'demo', 'A demo reader');
    check('A11', 'Demo: the reader signs in and the demo answers 200', $rl && ok200($rd), "login=$rl {$rd->status} " . short(text_of($h), 200));
    server_stop();
}

/* ================================================================== RB: rollback of RoleWarden's batch alone */
if (in_array('RB', $PH, true)) {
    echo "\n== RB: rollback of RoleWarden's batch (installation.html, Removing the module)\n";
    $mg = migrations();
    $rwb = 0; $all = [];
    foreach ($mg as $r) { $all[(int) $r['batch']][] = $r['namespace']; if ($r['namespace'] === 'RoleWarden') $rwb = (int) $r['batch']; }
    $later = array_filter($all, fn ($ns, $b) => $b > $rwb, ARRAY_FILTER_USE_BOTH);
    info("RoleWarden batch $rwb; batches after it: " . json_encode($later));
    $shieldBefore = json_encode([q('SELECT id, username, active FROM users ORDER BY id'), q('SELECT user_id, type, secret FROM auth_identities ORDER BY id'), array_values(array_filter(tables(), fn ($t) => preg_match('/^(auth_|users$|settings$)/', $t)))]);
    $usersBefore = (int) q1('SELECT COUNT(*) FROM users');
    [$rc, $o] = sp(['migrate:rollback', '-b', (string) ($rwb - 1), '-f']);
    $t = tables();
    $acl = array_values(array_filter($t, fn ($x) => str_starts_with($x, 'acl_')));
    $shieldAfter = json_encode([q('SELECT id, username, active FROM users ORDER BY id'), q('SELECT user_id, type, secret FROM auth_identities ORDER BY id'), array_values(array_filter(tables(), fn ($t) => preg_match('/^(auth_|users$|settings$)/', $t)))]);
    check('RB1', "migrate:rollback -b " . ($rwb - 1) . " (exit 0) removes every acl_ table", $rc === 0 && $acl === [], "rc=$rc acl=" . json_encode($acl) . ' ' . short($o, 600));
    check('RB2', 'Shield and Settings untouched: tables, users and identities identical', $shieldBefore === $shieldAfter && $usersBefore > 0, "users before $usersBefore");
    check('RB3', 'Migrations table: no RoleWarden row left, Shield and Settings rows kept', ! array_filter(migrations(), fn ($r) => $r['namespace'] === 'RoleWarden') && array_filter(migrations(), fn ($r) => $r['namespace'] === 'CodeIgniter\Shield'), json_encode(migrations()));
    [$rc, $o] = sp(['migrate', '-n', 'RoleWarden']);
    [$rc2, $o2] = sp(['db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder']);
    q("INSERT INTO acl_user_roles (user_id, role_id) SELECT i.user_id, r.id FROM auth_identities i JOIN acl_roles r ON r.slug = 'super-admin' WHERE i.type = 'email_password' AND i.secret = ?", [OWNER]);
    check('RB4', 'migrate -n RoleWarden and the seeder again (exit 0): tables and system roles back', $rc === 0 && $rc2 === 0 && in_array('acl_roles', tables(), true) && role_id('super-admin') > 0 && role_id('admin') > 0, short($o . ' | ' . $o2, 600));
    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $own = c('RB-owner');
    $lok = login2($own, OWNER, OPW);
    G($own, 'rolewarden/roles', 'RB roles');
    check('RB5', 'After applying again the super admin signs in and the panel answers', $lok && ok200($own), "{$own->status}");
    server_stop();
    info('migrations after RB: ' . json_encode(migrations()));
}

require __DIR__ . '/verify-my8.phases.php';

/* ------------------------------------------------------------------ summary */
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "\nTOTAL {$O['label']}: " . count($GLOBALS['results']) . " checks, $pass PASS, $fail FAIL, " . count($GLOBALS['ambiguities']) . " ambiguities\n";
foreach ($GLOBALS['results'] as $r) if (! $r[2]) echo "FAIL {$r[0]} {$r[1]}\n";
