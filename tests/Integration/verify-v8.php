<?php
/**
 * V8 "Demo e documentazione" black-box suite (Collaudatore ad Hoc, Claude), commit f06a29d and the
 * three zips build/rolewarden-{app,module,demo}-1.0.0-rc.v8.zip. Assertions come only from
 * docs/SPEC.md ("App demo", decisions of 2026-10-10; "Requisiti di pubblicazione"; "Pannello admin"),
 * docs/BRIEF-v1.0.md (V8 and the definition of done), demo/README.md, README.md, documentation/,
 * CHANGELOG.md, THIRD-PARTY-LICENSES.md and the authorization contracts. No implementation file read.
 * HTTP towards php -S, mysqli towards rolewarden_test on 127.0.0.1:3317 only, spark as a buyer runs it.
 *
 * Usage: php verify-v8.php <work> <mod-without-demo> <mod-with-demo> <demo> <zip-app> <zip-module> <zip-demo> <app-test> [phases]
 *   phases: comma list of P,A,B,C,D,E,F,G (default all)
 * Credentials only from RW_DB_USERNAME / RW_DB_PASSWORD; the application gets them as process variables.
 */
declare(strict_types=1);

require __DIR__ . '/verify-v8.lib.php';

[$_, $WORK, $MOD, $MODFULL, $DEMO, $ZAPP, $ZMOD, $ZDEMO, $APPTEST] = $argv + array_fill(0, 9, null);
$PHASES = explode(',', $argv[9] ?? 'P,A,B,C,D,E,F,G');
foreach ([$WORK, $MOD, $MODFULL, $DEMO, $ZAPP, $ZMOD, $ZDEMO, $APPTEST] as $d) if (! $d || ! is_dir($d)) exit("missing dir $d\n");
$WORK = str_replace('\\', '/', $WORK);
if (tables() !== []) exit("ABORT: rolewarden_test is not empty at start\n");
if ((mailpit('GET', 'messages')['messages_count'] ?? -1) !== 0) exit("ABORT: Mailpit not empty at start\n");

const DPW = 'Demo-Pass-8437-Qx';          // demo.password used by the suite (test value, not a credential)
const OPW = 'Owner-Lantern-5521';         // password of the real accounts written by the suite
const AJAX = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
const MAILENV8 = "email.protocol = smtp\nemail.SMTPHost = 127.0.0.1\nemail.SMTPPort = 1026\nemail.SMTPCrypto = ''\nemail.fromEmail = noreply@rolewarden.test\nemail.fromName = 'RoleWarden Test'\n";
const DEMO_EMAILS = ['reader@example.com', 'author@example.com', 'approver@example.com'];
const DEMO_SLUGS = ['demo-reader', 'demo-author', 'demo-approver'];
const DOC_PERMS = ['documents.view', 'documents.create', 'documents.update', 'documents.approve', 'documents.delete'];

$DBENV = ['database.default.username' => getenv('RW_DB_USERNAME'), 'database.default.password' => getenv('RW_DB_PASSWORD'), 'logger.threshold' => '9'];
$SEEN = [];
$SERVERLOGS = [];
$APPS = [];
$INDUCED = [];   // log patterns of failures the suite causes on purpose

function seen(Client $c, string $tag): string { $GLOBALS['SEEN'][] = [$tag, $c->status, $c->url, $c->body]; return $c->body; }
function G(Client $c, string $u, string $tag = ''): string { $c->get($u); return seen($c, $tag ?: "GET $u"); }
function P(Client $c, string $u, array $d, string $tag = '', array $h = []): string { $c->req('POST', $u, $d, $h); return seen($c, $tag ?: "POST $u"); }
function sp8(string $app, array $args, array $env = []): string { return spark($app, $args, $GLOBALS['DBENV'] + $env); }
function rc(string $out): int { return (int) (preg_match('/^rc=(-?\d+)/', $out, $m) ? $m[1] : -99); }
function short(string $s, int $n = 400): string { $s = trim(preg_replace('/\s+/', ' ', $s)); return strlen($s) > $n ? substr($s, 0, $n) . '...' : $s; }
function phperr(string $s): bool { return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|ErrorException|Uncaught|TypeError|ArgumentCountError)/', $s); }

/** Copy of the test application with the module (and the demo when $mod contains demo/). */
function app_copy(string $name, string $mod, bool $wireDemo, string $envName = 'development', string $extraEnv = ''): string
{
    global $WORK, $APPTEST, $APPS;
    $T = "$WORK/$name";
    $cp = proc_open(['robocopy', $APPTEST, $T, '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', 'builds', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp);
    stream_get_contents($pp[1]); stream_get_contents($pp[2]);
    if (proc_close($cp) >= 8) throw new RuntimeException('copy failed');
    foreach (['cache', 'session', 'logs', 'uploads', 'debugbar', 'tmp'] as $d) @mkdir("$T/writable/$d", 0777, true);
    $j = "$T/vendor/rolewarden/codeigniter4-rolewarden";
    @mkdir(dirname($j), 0777, true);
    if (is_dir($j)) @rmdir($j);
    junction($j, $mod);
    if (! $wireDemo) unwire_demo($T);
    write_env($T, $envName, $extraEnv);
    $APPS[] = $T;
    return $T;
}
function unwire_demo(string $T): void
{
    $a = file_get_contents("$T/app/Config/Autoload.php");
    $a = preg_replace("/^\s*'RoleWardenDemo'\s*=>.*\n/m", '', $a);
    file_put_contents("$T/app/Config/Autoload.php", $a);
    $r = file_get_contents("$T/app/Config/Routes.php");
    $r = preg_replace("/^.*RoleWardenDemo.*\n/m", '', $r);
    file_put_contents("$T/app/Config/Routes.php", $r);
}
function write_env(string $T, string $envName, string $extra = ''): void
{
    file_put_contents("$T/.env", "CI_ENVIRONMENT = $envName\napp.baseURL = 'http://127.0.0.1:8071/'\ndatabase.default.hostname = 127.0.0.1\ndatabase.default.port = 3317\ndatabase.default.database = rolewarden_test\ndatabase.default.DBDriver = MySQLi\nlogger.threshold = 9\nrolewarden.signInRate = 1000\n" . MAILENV8 . $extra);
    $e = file_get_contents("$T/.env");
    foreach (['email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026'] as $l) if (! str_contains($e, $l)) exit("ABORT: mail not on Mailpit\n");
}
function serve8(string $app, string $tag, array $env = []): Client
{
    global $WORK, $DBENV;
    $GLOBALS['SERVERLOGS'][] = "$WORK/server-$tag.log";
    server_start("$app/public", '', "$WORK/server-$tag.log", $DBENV + $env);
    return new Client('http://127.0.0.1:' . PORT7 . '/', $tag);
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
/** The real accounts every lifecycle phase starts with. */
function real_accounts(): array
{
    return [
        'owner' => make_owner('owner@v8.test', OPW, 'v8owner', 'super-admin'),
        'admin' => make_owner('realadmin@v8.test', OPW, 'v8admin', 'admin'),
        'real' => make_owner('realuser@v8.test', OPW, 'v8real', 'user'),
        'off' => make_owner('realoff@v8.test', OPW, 'v8off', 'user', 0),
    ];
}
function c8(string $name): Client { return new Client('http://127.0.0.1:' . PORT7 . '/', $name); }
function login8(Client $c, string $email, string $pw, bool $remember = false): bool
{
    $h = G($c, 'login', "login form $email");
    $f = form_with($h, 'password');
    if (! $f) return false;
    $v = array_replace($f['fields'], ['email' => $email, 'password' => $pw]);
    if ($remember) $v['remember'] = 'on'; else unset($v['remember']);
    P($c, 'login', $v, "login $email");
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, '/login');
}
function demo_ids(): array { $o = []; foreach (DEMO_EMAILS as $e) { $u = uid($e); if ($u) $o[$e] = $u; } return $o; }
function role_id(string $slug): int { return (int) q1('SELECT id FROM acl_roles WHERE slug=?', [$slug]); }
function perm_id(string $slug): int { return (int) q1('SELECT id FROM acl_permissions WHERE slug=?', [$slug]); }
function docs(): array { return tables() && in_array('demo_documents', tables(), true) ? q('SELECT * FROM demo_documents ORDER BY id') : []; }
function tok(Client $c, string $from = 'demo'): string { return csrf_of(G($c, $from, "token from $from")); }
/** POST to a demo endpoint with a fresh CSRF token taken from $from. */
function act(Client $c, string $url, array $data = [], string $from = 'demo', string $tag = ''): string
{
    $t = tok($c, $from);
    return P($c, $url, ['csrf_test_name' => $t] + $data, $tag ?: "POST $url");
}
/** What the /demo page offers to the signed-in user. */
function view_demo(Client $c, string $tag = 'demo'): array
{
    $h = G($c, 'demo', $tag);
    $dec = html_entity_decode($h, ENT_QUOTES | ENT_HTML5);
    $o = ['status' => $c->status, 'new' => (bool) preg_match('#href="[^"]*/demo/create"#', $h), 'edit' => [], 'submit' => [], 'approve' => [], 'delete' => [], 'showcase' => [], 'who' => '', 'perms' => null, 'html' => $h];
    preg_match_all('#href="[^"]*/demo/(\d+)/edit"#', $h, $m); $o['edit'] = array_map('intval', $m[1]);
    preg_match_all('#action="[^"]*/demo/(\d+)/(submit|approve|delete)"#', $h, $m, PREG_SET_ORDER);
    foreach ($m as $x) $o[$x[2]][] = (int) $x[1];
    preg_match_all('#formaction="[^"]*/demo/showcase/(\d+)"#', $dec, $m); $o['showcase'] = array_map('intval', $m[1]);
    if (preg_match('#class="rw-who"[^>]*>\s*<span>([^<]*)</span>#', $h, $m)) $o['who'] = trim(html_entity_decode($m[1]));
    if (preg_match('#<dd class="rw-fig-sm">(\d+)</dd>#', $h, $m)) $o['perms'] = (int) $m[1];
    foreach (['edit', 'submit', 'approve', 'delete', 'showcase'] as $k) { $o[$k] = array_values(array_unique($o[$k])); sort($o[$k]); }
    return $o;
}
function ids_where(callable $f): array { $o = array_values(array_map(fn ($d) => (int) $d['id'], array_filter(docs(), $f))); sort($o); return $o; }
/** POST to a panel endpoint with a token read just before from $page (the token changes after every POST). */
function panelp(Client $c, string $page, string $url, array $data, string $tag, bool $ajax = true): string
{
    $t = csrf_of(G($c, $page, "token $page"));
    return P($c, $url, ['csrf_test_name' => $t] + $data, $tag, $ajax ? AJAX : []);
}
function no500(Client $c): bool { return $c->status < 500 && ! leaks_sql($c->body) && ! phperr(text_of($c->body)); }

/**
 * Classifies every difference between the state before the installation ($a) and after the
 * removal ($b): residues of the demo (defects), changes the spec does not attribute to the demo
 * (the real accounts' own activity), and rows written by real users about demo objects (ambiguous).
 */
function classify(array $a, array $b, array $demo, array $real): array
{
    $isDemo = function (string $t, array $r) use ($demo): bool {
        $j = json_encode($r);
        foreach (array_merge($demo['emails'], $demo['usernames'], DEMO_SLUGS, ['RoleWardenDemo', 'demo_documents', 'documents.']) as $needle) if ($needle !== '' && stripos($j, $needle) !== false) return true;
        foreach (['user_id', 'actor_id', 'owner_id'] as $k) if (isset($r[$k]) && in_array((int) $r[$k], $demo['uids'], true)) return true;
        if (isset($r['role_id']) && in_array((int) $r['role_id'], $demo['rids'], true)) return true;
        if (isset($r['permission_id']) && in_array((int) $r['permission_id'], $demo['pids'], true)) return true;
        if ($t === 'users' && in_array((int) $r['id'], $demo['uids'], true)) return true;
        if ($t === 'acl_roles' && in_array((int) $r['id'], $demo['rids'], true)) return true;
        if ($t === 'acl_permissions' && in_array((int) $r['id'], $demo['pids'], true)) return true;
        if (($r['context'] ?? null) && preg_match('/user:(\d+)/', (string) $r['context'], $m) && in_array((int) $m[1], $demo['uids'], true)) return true;
        if ($t === 'acl_activity_log') {
            $st = (string) ($r['subject_type'] ?? '');
            $sid = (int) ($r['subject_id'] ?? 0);
            if ($st === 'user' && in_array($sid, $demo['uids'], true)) return true;
            if ($st === 'role' && in_array($sid, $demo['rids'], true)) return true;
            if ($st === 'permission' && in_array($sid, $demo['pids'], true)) return true;
        }
        return false;
    };
    $actorDemo = fn (array $r) => in_array((int) ($r['actor_id'] ?? 0), $demo['uids'], true) || in_array((string) ($r['actor_label'] ?? ''), $demo['usernames'], true);
    $out = ['residue' => [], 'allowed' => [], 'ambiguous' => [], 'lost' => []];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $t) {
        if (! isset($b[$t])) { $out['lost'][] = "table $t missing after removal"; continue; }
        if (! isset($a[$t])) { $out['residue'][] = "table $t left behind (" . count($b[$t]['rows']) . ' rows)'; continue; }
        if ($a[$t]['create'] !== $b[$t]['create']) $out['residue'][] = "structure of $t changed";
        if ($a[$t]['ai'] !== $b[$t]['ai']) $out['allowed'][] = "AUTO_INCREMENT of $t {$a[$t]['ai']} -> {$b[$t]['ai']} (counter only)";
        $ka = []; foreach ($a[$t]['rows'] as $r) $ka[json_encode($r)] = $r;
        $kb = []; foreach ($b[$t]['rows'] as $r) $kb[json_encode($r)] = $r;
        $gone = array_diff_key($ka, $kb);
        $new = array_diff_key($kb, $ka);
        // a row that kept its id but changed: compare without the columns real activity touches
        $volatile = ['last_active', 'updated_at', 'last_used_at', 'last_seen_at'];
        foreach ($gone as $kg => $rg) {
            if (! isset($rg['id'])) continue;
            foreach ($new as $kn => $rn) if (($rn['id'] ?? null) === $rg['id']) {
                $vol = $t === 'auth_identities' ? array_merge($volatile, ['secret2']) : $volatile; // Shield rehashes a password at sign-in
                $x = array_diff_key($rg, array_flip($vol)); $y = array_diff_key($rn, array_flip($vol));
                $owner = (int) ($rg['user_id'] ?? ($t === 'users' ? $rg['id'] : 0));
                if ($x === $y && in_array($owner, $real, true)) { $out['allowed'][] = "$t id {$rg['id']}: only " . implode('/', array_keys(array_diff_assoc($rn, $rg))) . ' changed (real account activity)'; unset($gone[$kg], $new[$kn]); }
                break;
            }
        }
        foreach ($gone as $r) $out['lost'][] = "$t row removed or changed: " . substr(json_encode($r), 0, 260);
        foreach ($new as $r) {
            $s = "$t: " . substr(json_encode($r), 0, 260);
            if ($t === 'acl_activity_log') {
                if ($actorDemo($r)) $out['residue'][] = $s;
                elseif ($isDemo($t, $r)) $out['ambiguous'][] = $s;
                else $out['allowed'][] = $s . ' (real account, nothing about the demo)';
                continue;
            }
            if ($isDemo($t, $r)) { $out['residue'][] = $s; continue; }
            if ($t === 'settings' && ($r['class'] ?? '') === 'RoleWarden\Config\RoleWarden' && ($r['key'] ?? '') === 'activityPrunedOn') { $out['allowed'][] = $s . ' (module housekeeping of the activity log)'; continue; }
            $owner = (int) ($r['user_id'] ?? 0);
            if (in_array($t, ['auth_logins', 'acl_sessions', 'auth_remember_tokens'], true) && in_array($owner, $real, true)) { $out['allowed'][] = $s . ' (real account activity)'; continue; }
            $out['residue'][] = $s . ' (unexplained)';
        }
    }
    return $out;
}
function demo_refs(): array
{
    $ids = demo_ids();
    $users = $ids ? q('SELECT id, username FROM users WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') : [];
    return [
        'uids' => array_values(array_map('intval', $ids)), 'emails' => DEMO_EMAILS, 'usernames' => array_column($users, 'username'),
        'rids' => array_map('intval', array_column(q("SELECT id FROM acl_roles WHERE slug IN ('demo-reader','demo-author','demo-approver')"), 'id')),
        'pids' => array_map('intval', array_column(q("SELECT id FROM acl_permissions WHERE slug LIKE 'documents.%'"), 'id')),
    ];
}
function merge_refs(array $x, array $y): array { foreach ($x as $k => $v) $x[$k] = array_values(array_unique(array_merge($v, $y[$k]))); return $x; }

/* =================================================================================== P: packages */
if (in_array('P', $PHASES, true)) {
echo "\n== P: the three zips opened as a buyer, licences, documentation files\n";
$tree = function (string $top): array {
    $o = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($top, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $o[str_replace('\\', '/', substr($f->getPathname(), strlen($top) + 1))] = md5_file($f->getPathname());
    ksort($o);
    return $o;
};
$zm = $tree("$ZMOD/rolewarden"); $zd = $tree("$ZDEMO/rolewarden-demo");
$appRoot = glob("$ZAPP/rolewarden-app-*")[0];
check('P01', 'Module zip is exactly the module at f06a29d (git archive): same files, same bytes', $zm === $tree($MOD), json_encode(array_keys(array_diff_assoc($zm, $tree($MOD)) + array_diff_assoc($tree($MOD), $zm))));
check('P02', 'Demo zip is exactly demo/ at f06a29d', $zd === $tree($DEMO), json_encode(array_keys(array_diff_assoc($zd, $tree($DEMO)))));
check('P03', 'Complete application: modules/rolewarden is the module zip, modules/rolewarden-demo is the demo zip', $tree("$appRoot/modules/rolewarden") === $zm && $tree("$appRoot/modules/rolewarden-demo") === $zd);
$auto = file_get_contents("$appRoot/app/Config/Autoload.php"); $rts = file_get_contents("$appRoot/app/Config/Routes.php");
check('P04', 'Complete application wires the demo with the two lines of its README (psr4 RoleWardenDemo => modules/rolewarden-demo/src; DemoRoutes::register after the module line)',
    str_contains($auto, "'RoleWardenDemo' => ROOTPATH . 'modules/rolewarden-demo/src',") && (int) strpos($rts, 'DemoRoutes::register($routes);') > (int) strpos($rts, 'RouteRegistrar::register($routes);') && str_contains($rts, '\RoleWardenDemo\Config\DemoRoutes::register($routes);'));
$codeRefs = [];
foreach (array_keys($zm) as $f) if (str_starts_with($f, 'src/') && preg_match('/RoleWardenDemo|demo_documents|demo:install|demo:uninstall|documents\.(view|create|update|approve|delete)|rolewarden-demo/i', (string) file_get_contents("$ZMOD/rolewarden/$f"))) $codeRefs[] = $f;
check('P05', 'Module zip: no file under src/ refers to the demo (namespace, table, commands, permissions, folder)', $codeRefs === [], json_encode($codeRefs));
$docRefs = [];
foreach (array_keys($zm) as $f) if (! str_starts_with($f, 'src/') && preg_match_all('/demo/i', (string) file_get_contents("$ZMOD/rolewarden/$f"), $mm)) $docRefs[] = "$f(" . count($mm[0]) . ')';
info('Mentions of the demo outside src/ (documentation of the demo zip, expected): ' . implode(', ', $docRefs));
foreach (['CHANGELOG.md', 'THIRD-PARTY-LICENSES.md'] as $f) {
    check('P06' . $f[0], "$f in the module zip and in the complete application (modules/rolewarden)", isset($zm[$f]) && is_file("$appRoot/modules/rolewarden/$f"));
    info("$f in the demo zip: " . (isset($zd[$f]) ? 'yes' : 'no') . "; at the root of the complete application: " . (is_file("$appRoot/$f") ? 'yes' : 'no'));
}
$rootReadme = (string) @file_get_contents("$appRoot/README.md");
$missingRefs = [];
foreach (['documentation/index.html', 'CHANGELOG.md', 'THIRD-PARTY-LICENSES.md'] as $ref) if (str_contains($rootReadme, "`$ref`") && ! is_file("$appRoot/$ref")) $missingRefs[] = $ref;
check('P06d', 'Complete application: the README at its root points only to files that exist from there (documentation, changelog, licences)', $missingRefs === [], 'README at the root names ' . json_encode($missingRefs) . ', which are only under modules/rolewarden/');
// licences against vendor/
$lic = (string) file_get_contents("$ZMOD/rolewarden/THIRD-PARTY-LICENSES.md");
$inst = json_decode((string) file_get_contents("$appRoot/vendor/composer/installed.json"), true);
$pk = $inst['packages'] ?? $inst;
$wrong = [];
foreach ($pk as $p) {
    $v = ltrim($p['version'], 'v');
    if (! preg_match('/\[' . preg_quote($p['name'], '/') . '\][^\n]*\|\s*' . preg_quote($v, '/') . '\s*\|/', $lic)) {
        preg_match('/\[' . preg_quote($p['name'], '/') . '\][^|]*\|\s*([^|]*)\|/', $lic, $m);
        $wrong[] = $p['name'] . " shipped $v, listed " . (isset($m[1]) ? trim($m[1]) : 'not listed');
    }
}
check('P07', 'THIRD-PARTY-LICENSES.md lists every package of vendor/ in the complete application with the version shipped', $wrong === [], implode('; ', $wrong));
check('P07b', 'No package listed in THIRD-PARTY-LICENSES.md as included is missing from vendor/', ! array_filter(['codeigniter4/framework', 'codeigniter4/shield', 'codeigniter4/settings', 'codeigniter4/translations', 'laminas/laminas-escaper', 'psr/log'], fn ($n) => ! is_dir("$appRoot/vendor/$n")));
$noLic = [];
foreach (glob("$appRoot/vendor/*/*", GLOB_ONLYDIR) as $d) if (! glob("$d/{LICENSE,LICENSE.*,LICENCE*,COPYING*,license*}", GLOB_BRACE)) $noLic[] = basename(dirname($d)) . '/' . basename($d);
check('P08', 'Every package in vendor/ carries its licence file', $noLic === [], json_encode($noLic));
if (is_dir("$appRoot/vendor/composer")) amb('P08c', "Composer's runtime autoloader (vendor/composer/ClassLoader.php, InstalledVersions.php, MIT, licence in vendor/composer/LICENSE) is shipped in the complete application but not named in THIRD-PARTY-LICENSES.md", 'Whether the generated autoloader counts as a third-party dependency to list is not stated; its licence file is beside it.');
$alp = (string) file_get_contents("$ZMOD/rolewarden/src/Assets/js/vendor/alpine.min.js");
check('P09a', 'Alpine.js shipped is 3.17.4 as listed, with its MIT licence beside it (LICENSE-alpine.md)', str_contains($alp, 'version:"3.17.4"') && str_contains((string) @file_get_contents("$ZMOD/rolewarden/src/Assets/js/vendor/LICENSE-alpine.md"), 'MIT') && str_contains($lic, '3.17.4'));
check('P09b', 'IBM Plex fonts with the SIL OFL 1.1 text beside them (src/Assets/fonts/LICENSE.txt)', (bool) glob("$ZMOD/rolewarden/src/Assets/fonts/*.woff2") && str_contains((string) @file_get_contents("$ZMOD/rolewarden/src/Assets/fonts/LICENSE.txt"), 'SIL Open Font License') && str_contains((string) file_get_contents("$ZMOD/rolewarden/src/Assets/fonts/LICENSE.txt"), 'IBM'));
check('P09c', 'Tailwind CSS notice kept in the compiled panel.css with the listed version 4.3.3', str_starts_with((string) file_get_contents("$ZMOD/rolewarden/src/Assets/css/panel.css"), '/*! tailwindcss v4.3.3 | MIT License'));
if (preg_match('/IBM Plex\]\([^)]*\) Sans, Serif, Mono \|\s*\|/', $lic)) amb('P09d', 'THIRD-PARTY-LICENSES.md leaves the version of IBM Plex empty', 'Every other row gives a version; the brief asks for "versioni corrette". The fonts carry no version marker the buyer could check.');
// secrets, personal data
$hits = [];
foreach (["$ZMOD", "$ZDEMO", "$ZAPP"] as $top) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($top, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile()) continue;
        $p = str_replace('\\', '/', $f->getPathname());
        $b = basename($p);
        if ($b === '.env' || preg_match('/\.(pem|key|p12|pfx)$/i', $b) || str_starts_with($b, 'id_rsa')) { $hits[] = "file $p"; continue; }
        if ($f->getSize() > 3000000 || str_contains($p, '/_dictionary.txt')) continue;
        $s = (string) file_get_contents($p);
        if (preg_match('/(fabio|gulotta|tullio69|scolibro|qrart|C:\\\\Users\\\\|E:\\\\progetti|\/e\/progetti|rolewarden_test|:3317|BEGIN [A-Z ]*PRIVATE KEY|ghp_[A-Za-z0-9]{20}|AKIA[0-9A-Z]{16}|hex2bin:[0-9a-f]{64})/i', $s, $m) && ! (str_contains($p, '/vendor/') && str_starts_with(strtolower($m[1]), 'hex2bin:') && str_ends_with($p, '.md'))) $hits[] = substr($p, strlen($top)) . ': ' . $m[1];
        if (! str_contains($p, '/vendor/') && ! str_ends_with($p, 'composer.lock') && preg_match('/[a-z0-9._-]+@(gmail|yahoo|hotmail|outlook|libero|icloud)\.[a-z]+/i', $s, $m)) $hits[] = substr($p, strlen($top)) . ': ' . $m[0];
        if (preg_match('/^\s*(database\.default\.password|encryption\.key|email\.SMTPPass|demo\.password)\s*=\s*\S+/m', $s, $m) && ! str_ends_with($p, '/env') && ! str_contains($p, '/documentation/')) $hits[] = substr($p, strlen($top)) . ': ' . $m[0];
    }
}
check('P10', 'No credential, key, .env, private key or personal path/URL in the three zips', $hits === [], json_encode(array_slice($hits, 0, 20)));
info('composer.json homepage of the module: ' . (json_decode((string) file_get_contents("$ZMOD/rolewarden/composer.json"), true)['homepage'] ?? 'none') . ' (product site, not personal)');
// documentation offline: files, links, anchors
foreach (["module zip" => "$ZMOD/rolewarden/documentation", "complete application" => "$appRoot/modules/rolewarden/documentation"] as $where => $dd) {
    $bad = []; $pages = glob("$dd/*.html");
    foreach ($pages as $pg) {
        $h = (string) file_get_contents($pg);
        preg_match_all('/<(?:a|link|script|img)\b[^>]*(?:href|src)="([^"]+)"/i', $h, $m);
        foreach ($m[1] as $u) {
            if (preg_match('#^(https?:|mailto:)#', $u)) continue;
            [$file, $frag] = array_pad(explode('#', $u, 2), 2, '');
            $target = $file === '' ? $pg : realpath(dirname($pg) . '/' . $file);
            if (! $target || ! is_file($target)) { $bad[] = basename($pg) . " -> $u (missing)"; continue; }
            if ($frag !== '' && ! preg_match('/\bid="' . preg_quote($frag, '/') . '"/', (string) file_get_contents($target))) $bad[] = basename($pg) . " -> $u (no anchor)";
        }
        if (! preg_match('#<link rel="stylesheet" href="\.\./src/Assets/css/panel\.css">#', $h) || (basename($pg) !== 'design-guide.html' && ! str_contains($h, 'href="docs.css"'))) $bad[] = basename($pg) . ' without its stylesheets';
    }
    check('P11' . $where[0], "Documentation in the $where: " . count($pages) . ' pages open offline, every local link, stylesheet and anchor resolves', count($pages) >= 8 && $bad === [], json_encode($bad));
}
$all = ''; foreach (glob("$ZMOD/rolewarden/documentation/*.html") as $pg) $all .= text_of((string) file_get_contents($pg)) . ' ';
$topics = ['installation' => 'Installation', 'configuration' => 'Configuration', 'API reference' => 'API reference', 'view customisation' => 'Replacing a view', 'migration from Shield' => "Migrating from Shield", 'troubleshooting' => 'Troubleshooting'];
check('P12', 'Documentation covers installation, configuration, API reference, view customisation, migration from Shield, troubleshooting', ! array_filter($topics, fn ($t) => ! str_contains($all, $t)));
// demo language files: keys and placeholders
$en = include "$DEMO/src/Language/en/Demo.php"; $it = include "$DEMO/src/Language/it/Demo.php";
$flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flat($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
$fe = $flat($en); $fi = $flat($it);
check('P13', 'Demo language files: same keys in en and it (' . count($fe) . ')', array_keys($fe) === array_keys($fi), json_encode([array_diff(array_keys($fe), array_keys($fi)), array_diff(array_keys($fi), array_keys($fe))]));
$ph = []; foreach ($fe as $k => $v) { preg_match_all('/\{[^}]*\}|%[sd]/', $v, $a); preg_match_all('/\{[^}]*\}|%[sd]/', $fi[$k] ?? '', $b); if ($a[0] != $b[0]) $ph[] = $k; }
check('P13b', 'Demo language files: same placeholders in every line', $ph === [], json_encode($ph));
$same = array_keys(array_filter($fe, fn ($v, $k) => ($fi[$k] ?? '') === $v, ARRAY_FILTER_USE_BOTH));
info('Demo lines identical in en and it: ' . json_encode($same));
}

/* =================================================================================== A: install and remove */
if (in_array('A', $PHASES, true)) {
echo "\n== A: demo:install and demo:uninstall on a copy of the test application (module + demo, development)\n";
$A = app_copy('appA', $MODFULL, true);
check('A00', 'Fixture: Settings, Shield, RoleWarden migrated in separate batches and seeded (README commands)', base_install($A));
$R = real_accounts();
$realIds = array_values($R);
$S0 = fullstate();
$o = sp8($A, ['demo:uninstall']);
info('demo:uninstall before any install: ' . short($o));
check('A01', 'Removal without installation: harmless (no PHP error) and the database stays identical, counters included', ! phperr($o) && statediff($S0, fullstate(), true) === [], short($o) . ' ' . json_encode(statediff($S0, fullstate(), true)));
$o = sp8($A, ['demo:install'], ['demo.password' => DPW]);
info('demo:install output: ' . short($o, 700));
check('A02', 'demo:install is one command and succeeds (exit code 0, no PHP error)', rc($o) === 0 && ! phperr($o), short($o));
check('A02b', 'With demo.password set, the password is not printed', ! str_contains($o, DPW), 'password printed');
$S1 = fullstate();
$dids = demo_ids();
check('A03', 'Schema approved: one table demo_documents (id, title varchar(160), body text, status varchar(16), owner_id FK users CASCADE, approved_by FK users SET NULL, approved_at, created_at, updated_at; indexes on status and owner_id), no other table',
    array_values(array_diff(array_keys($S1), array_keys($S0))) === ['demo_documents'] && (bool) preg_match('/`title` varchar\(160\)/', $S1['demo_documents']['create']) && (bool) preg_match('/`body` text/', $S1['demo_documents']['create']) && (bool) preg_match('/`status` varchar\(16\)/', $S1['demo_documents']['create'])
    && (bool) preg_match('/FOREIGN KEY \(`owner_id`\) REFERENCES `users` \(`id`\) ON DELETE CASCADE/', $S1['demo_documents']['create']) && (bool) preg_match('/FOREIGN KEY \(`approved_by`\) REFERENCES `users` \(`id`\) ON DELETE SET NULL/', $S1['demo_documents']['create'])
    && (bool) preg_match('/KEY `[^`]*` \(`status`\)/', $S1['demo_documents']['create']) && (bool) preg_match('/KEY `[^`]*` \(`owner_id`\)/', $S1['demo_documents']['create']) && str_contains($S1['demo_documents']['create'], '`approved_at`') && str_contains($S1['demo_documents']['create'], '`created_at`') && str_contains($S1['demo_documents']['create'], '`updated_at`'),
    $S1['demo_documents']['create'] ?? 'no table');
$perms = q("SELECT slug, area, is_system FROM acl_permissions WHERE slug LIKE 'documents.%' ORDER BY slug");
check('A04', 'Five permissions in area documents: view, create, update, approve, delete (not system)', array_column($perms, 'slug') === ['documents.approve', 'documents.create', 'documents.delete', 'documents.update', 'documents.view'] && ! array_filter($perms, fn ($p) => $p['area'] !== 'documents' || (int) $p['is_system'] !== 0), json_encode($perms));
$rp = function (string $slug): array { $o = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id JOIN acl_roles r ON r.id=rp.role_id WHERE r.slug=? ORDER BY p.slug', [$slug]), 'slug'); return $o; };
$par = fn (string $slug) => q1('SELECT p.slug FROM acl_roles r LEFT JOIN acl_roles p ON p.id=r.parent_id WHERE r.slug=?', [$slug]);
$names = array_column(q("SELECT slug, name FROM acl_roles WHERE slug LIKE 'demo-%'"), 'name', 'slug');
check('A05', 'Three roles in a chain: demo-reader (Reader: view) <- demo-author (Author: + create, update) <- demo-approver (Approver: + approve, delete)',
    $names === ['demo-approver' => 'Approver', 'demo-author' => 'Author', 'demo-reader' => 'Reader'] && $par('demo-reader') === null && $par('demo-author') === 'demo-reader' && $par('demo-approver') === 'demo-author'
    && $rp('demo-reader') === ['documents.view'] && $rp('demo-author') === ['documents.create', 'documents.update'] && $rp('demo-approver') === ['documents.approve', 'documents.delete']
    && ! q("SELECT id FROM acl_roles WHERE slug LIKE 'demo-%' AND (is_system<>0 OR is_super_admin<>0)"),
    json_encode([$names, $rp('demo-reader'), $rp('demo-author'), $rp('demo-approver'), $par('demo-author'), $par('demo-approver')]));
$ur = q("SELECT i.secret email, r.slug, u.active FROM auth_identities i JOIN users u ON u.id=i.user_id LEFT JOIN acl_user_roles ur ON ur.user_id=u.id LEFT JOIN acl_roles r ON r.id=ur.role_id WHERE i.secret IN ('reader@example.com','author@example.com','approver@example.com') ORDER BY i.secret");
check('A06', 'Accounts reader@, author@, approver@example.com, active, each holding exactly its role', array_map(fn ($r) => $r['email'] . '=' . $r['slug'] . '/' . $r['active'], $ur) === ['approver@example.com=demo-approver/1', 'author@example.com=demo-author/1', 'reader@example.com=demo-reader/1'], json_encode($ur));
$d0 = docs();
check('A07', 'Sample documents from the seed (owned by demo accounts, statuses draft/submitted/approved)', count($d0) >= 3 && ! array_filter($d0, fn ($d) => ! in_array((int) $d['owner_id'], array_values($dids), true)) && count(array_unique(array_column($d0, 'status'))) === 3, json_encode(array_map(fn ($d) => [$d['id'], $d['status'], $d['owner_id']], $d0)));
$sc = q("SELECT class, `key`, value, context FROM settings WHERE class LIKE 'RoleWardenDemo%'");
$scu = isset($sc[0]) ? @unserialize($sc[0]['value']) : null;
check('A08', 'Settings Demo.showcaseUsers lists exactly the three demo accounts', count($sc) === 1 && $sc[0]['key'] === 'showcaseUsers' && is_array($scu) && array_map('intval', $scu) == array_values($dids), json_encode($sc));
check('A08b', 'Migration row of the demo in its own namespace RoleWardenDemo', (int) q1("SELECT COUNT(*) FROM migrations WHERE namespace='RoleWardenDemo'") === 1);
$o2 = sp8($A, ['demo:install'], ['demo.password' => 'Other-Pass-0000']);
info('Second demo:install: ' . short($o2));
check('A09', 'Repeated installation refused without damage: database identical (counters included), no password printed', statediff($S1, fullstate(), true) === [] && ! preg_match('/Password, shown only now/i', $o2) && ! phperr($o2), short($o2) . json_encode(statediff($S1, fullstate(), true)));
check('A09b', 'Repeated installation exits with a non-zero code or says it is already installed', rc($o2) !== 0 || (bool) preg_match('/already/i', $o2), short($o2));

$c = serve8($A, 'A');
foreach (DEMO_EMAILS as $e) { $x = c8("a-$e"); check('A10-' . $e[0] . $e[1], "Demo account $e signs in with demo.password", login8($x, $e, DPW)); }
$x = c8('a-wrong');
check('A10w', 'A wrong password is refused for a demo account', ! login8($x, 'reader@example.com', 'Wrong-Pass-1234'));
$own = c8('owner');
check('A11', 'Owner (real super admin) signs in', login8($own, 'owner@v8.test', OPW));
$h = G($own, 'rolewarden/roles', 'owner roles');
$t = text_of($h);
check('A12', 'Panel roles list shows the chain: Author inherits from Reader, Approver from Author', (bool) preg_match('/Reader Reads the demo documents\. none/', $t) && (bool) preg_match('/Author [^0-9]*? Reader \d/', $t) && (bool) preg_match('/Approver [^0-9]*? Author \d/', $t), short($t, 900));
$h = G($own, 'rolewarden/roles/' . role_id('demo-approver'), 'approver role');
check('A12b', 'Approver role page: parent Author, its own two permissions plus three inherited', str_contains(text_of($h), 'Parent role') && str_contains(text_of($h), 'Author') && (bool) preg_match('/2 own \+ 3 inherited/', $t), short(text_of($h), 500));

// ---- a real use of the demo, then the removal
echo "\n-- A: real use (sign-ins, remember-me, documents, language, theme mode, showcase, panel changes, sign-outs, failed sign-ins)\n";
$rd = c8('rd'); $au = c8('au'); $ap = c8('ap');
login8($rd, 'reader@example.com', DPW); login8($au, 'author@example.com', DPW, true); login8($ap, 'approver@example.com', DPW);
check('A13a', 'Author signed in with "remember me": a remember token exists', (int) q1('SELECT COUNT(*) FROM auth_remember_tokens WHERE user_id=?', [$dids['author@example.com']]) >= 1);
act($au, 'demo', ['title' => 'A-flow draft', 'body' => 'Body of the A flow'], 'demo/create', 'A author create');
$nid = (int) q1("SELECT id FROM demo_documents WHERE title='A-flow draft'");
act($au, "demo/$nid", ['title' => 'A-flow draft v2', 'body' => 'Edited'], "demo/$nid/edit", 'A author update');
act($au, "demo/$nid/submit", [], 'demo', 'A author submit');
act($ap, "demo/$nid/approve", [], 'demo', 'A approver approve');
act($ap, 'demo', ['title' => 'A-flow approver doc', 'body' => 'x'], 'demo/create', 'A approver create');
$del = (int) q1("SELECT id FROM demo_documents WHERE title='A-flow approver doc'");
act($ap, "demo/$del/delete", [], 'demo', 'A approver delete');
check('A13b', 'Documents created, edited, submitted, approved and deleted during the use', q1('SELECT status FROM demo_documents WHERE id=?', [$nid]) === 'approved' && ! q('SELECT id FROM demo_documents WHERE id=?', [$del]));
$lf = form_with(G($au, 'rolewarden/profile', 'au profile'), 'language');
if ($lf) P($au, $lf['action'], array_replace($lf['fields'], ['language' => 'it']), 'au language it');
$mf = null; foreach (forms(G($au, 'rolewarden/profile', 'au profile 2')) as $f) if (str_ends_with($f['action'], 'appearance/mode')) $mf = $f;
if ($mf) P($au, $mf['action'], $mf['fields'] + ['mode' => 'dark'], 'au mode dark');
check('A13c', 'Author chose Italian and the dark mode (per-user settings exist)', (int) q1("SELECT COUNT(*) FROM settings WHERE context=?", ['user:' . $dids['author@example.com']]) >= 2, json_encode(q("SELECT class, `key`, value, context FROM settings WHERE context IS NOT NULL")));
// failed sign-ins on a demo account
for ($i = 0; $i < 2; $i++) { $x = c8("fail$i"); login8($x, 'reader@example.com', 'Wrong-Pass-' . $i . 'abc'); }
// panel changes on demo objects by the real owner
$rr = 'rolewarden/roles/' . role_id('demo-reader');
panelp($own, $rr, "$rr/permissions", ['permission_id' => perm_id('documents.create'), 'granted' => 1], 'owner grant create to Reader');
panelp($own, $rr, "$rr/permissions", ['permission_id' => perm_id('documents.create'), 'granted' => 0], 'owner revoke create from Reader');
$ua = 'rolewarden/users/' . $dids['approver@example.com'];
panelp($own, $ua, "$ua/permissions", ['permission_id' => perm_id('documents.delete'), 'granted' => 0], 'owner deny delete to approver');
$ur = 'rolewarden/users/' . $R['real'];
panelp($own, $ur, "$ur/roles", ['role_id' => role_id('demo-author')], 'owner gives demo-author to the real user', false);
panelp($own, $ur, "$ur/permissions", ['permission_id' => perm_id('documents.view'), 'granted' => 1], 'owner grants documents.view override to the real user');
$rs = 'rolewarden/roles/' . role_id('user');
panelp($own, $rs, "$rs/permissions", ['permission_id' => perm_id('documents.view'), 'granted' => 1], 'owner grants documents.view to the system role user');
check('A13f', 'Panel changes on demo objects saved (override on the approver, demo role and documents.view given to real accounts)', (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=?', [$dids['approver@example.com']]) === 1 && (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$R['real'], role_id('demo-author')]) === 1 && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [role_id('user'), perm_id('documents.view')]) === 1);
$real = c8('real');
login8($real, 'realuser@v8.test', OPW);
act($real, 'demo', ['title' => 'Written by a real user', 'body' => 'r'], 'demo/create', 'real user creates a document');
check('A13d', 'A real user given the Author role writes a document', (int) q1("SELECT COUNT(*) FROM demo_documents WHERE title='Written by a real user' AND owner_id=?", [$R['real']]) === 1);
// showcase: restart with demo.showcase on, switch accounts
server_stop();
write_env($A, 'development', "demo.showcase = true\n");
$c = serve8($A, 'A2');
$v = view_demo($rd, 'A reader with showcase');
if ($v['showcase']) act($rd, 'demo/showcase/' . $dids['approver@example.com'], [], 'demo', 'A showcase reader->approver');
$v2 = view_demo($rd, 'A after switch');
check('A13e', 'Showcase on: the reader switched to the approver', $v2['who'] === q1('SELECT username FROM users WHERE id=?', [$dids['approver@example.com']]), $v2['who']);
// one session revoked from the panel, sign-outs
$sess = q1('SELECT id FROM acl_sessions WHERE user_id=? ORDER BY id LIMIT 1', [$dids['author@example.com']]);
G($own, 'rolewarden/users/' . $dids['approver@example.com'], 'approver page');
foreach (forms($own->body) as $f) if (preg_match('#/sessions/\d+/revoke$#', $f['action'])) { P($own, $f['action'], $f['fields'], 'owner revokes an approver session'); break; }
G($ap, 'logout', 'approver logout'); G($rd, 'logout', 'reader (now approver) logout');
$S2u = fullstate();
$refs = demo_refs();
info('Used state: ' . count($S2u['acl_activity_log']['rows']) . ' activity rows, ' . count($S2u['auth_logins']['rows']) . ' sign-in rows, ' . count($S2u['acl_sessions']['rows']) . ' sessions, ' . count($S2u['auth_remember_tokens']['rows']) . ' remember tokens, ' . count($S2u['settings']['rows']) . ' settings rows, ' . count(docs()) . ' documents');
info('Activity rows about the demo before removal: ' . json_encode(array_map(fn ($r) => [$r['actor_label'], $r['action'], $r['subject_type'], $r['subject_label']], $S2u['acl_activity_log']['rows'])));
server_stop();
$o = sp8($A, ['demo:uninstall']);
info('demo:uninstall output: ' . short($o, 600));
check('A14', 'demo:uninstall is one command and succeeds (exit code 0, no PHP error)', rc($o) === 0 && ! phperr($o), short($o));
$S3 = fullstate();
$cl = classify($S0, $S3, $refs, $realIds);
foreach ($cl['allowed'] as $l) info('Left by real accounts (not the demo): ' . $l);
check('A15', 'After a real use, removal leaves no residue of the demo in any table (tables, structure, rows)', $cl['residue'] === [], json_encode(array_slice($cl['residue'], 0, 12)));
check('A15b', 'Removal took away nothing that existed before the installation (real accounts, roles, permissions, settings intact)', $cl['lost'] === [], json_encode(array_slice($cl['lost'], 0, 12)));
if ($cl['ambiguous']) amb('A15c', 'Activity-log rows written by the real owner about demo objects survive the removal (' . count($cl['ambiguous']) . ')', 'SPEC: removal takes away "account con le loro sessioni, i loro accessi e le loro voci di log ... nient\'altro". Entries whose actor is a real administrator but whose subject or details are demo roles, permissions or accounts are neither clearly "their" entries nor clearly "nient\'altro". Rows: ' . json_encode(array_slice($cl['ambiguous'], 0, 6)));
check('A15d', 'The real user who was given the Author role still exists, with its own role, no longer the demo one', (int) q1('SELECT COUNT(*) FROM users WHERE id=?', [$R['real']]) === 1 && array_column(q('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$R['real']]), 'slug') === ['user']);
check('A15e', 'No session file, remember token or sign-in record of a demo account left in the database', ! q('SELECT id FROM acl_sessions WHERE user_id IN (' . implode(',', $refs['uids']) . ')') && ! q('SELECT id FROM auth_remember_tokens WHERE user_id IN (' . implode(',', $refs['uids']) . ')') && ! q("SELECT id FROM auth_logins WHERE identifier IN ('reader@example.com','author@example.com','approver@example.com') OR user_id IN (" . implode(',', $refs['uids']) . ')'));
// after removal, wiring still in place
$c = serve8($A, 'A3');
$ow = c8('owner-after'); login8($ow, 'owner@v8.test', OPW);
G($ow, 'demo', 'demo after uninstall (dev)');
info("GET /demo after removal, wiring lines still present, development: {$ow->status} " . short(text_of($ow->body), 160));
$devLeak = leaks_sql($ow->body);
$INDUCED[] = 'demo_documents';
server_stop();
write_env($A, 'production');
$c = serve8($A, 'A4');
$ow = c8('owner-after-p'); login8($ow, 'owner@v8.test', OPW);
G($ow, 'demo', 'demo after uninstall (prod)');
check('A16', 'After removal with the two wiring lines still in place (production): /demo shows no SQL or driver detail', ! leaks_sql($ow->body) && ! phperr(text_of($ow->body)), "{$ow->status} " . short(text_of($ow->body), 200));
if ($devLeak) amb('A16d', 'After removal with the wiring lines still in place, /demo in development shows the framework exception with the SQL error', 'The README says to delete the folder and the two lines after demo:uninstall; whether the window in between is a "percorso" covered by "nessun errore SQL a schermo in nessun ambiente" is not stated. Production shows nothing (A16).');
server_stop();
write_env($A, 'development');
// reinstall, uninstall again
$o = sp8($A, ['demo:install'], ['demo.password' => DPW]);
$refs2 = demo_refs();
check('A17', 'Installation again after a removal works (exit 0, accounts and documents back)', rc($o) === 0 && count(demo_ids()) === 3 && count(docs()) >= 3, short($o));
$o = sp8($A, ['demo:uninstall']);
$S4 = fullstate();
$cl2 = classify($S3, $S4, merge_refs($refs, $refs2), $realIds);
check('A17b', 'And the removal again leaves no residue and takes nothing (compared with the state after the first removal)', $cl2['residue'] === [] && $cl2['lost'] === [], json_encode($cl2));

// migrate --all
echo "\n-- A: php spark migrate --all, then demo:install / demo:uninstall\n";
$o = sp8($A, ['migrate', '--all']);
info('migrate --all: ' . short($o, 300));
$hasTable = in_array('demo_documents', tables(), true);
info('After migrate --all the demo table exists: ' . ($hasTable ? 'yes' : 'no') . '; demo migration rows: ' . q1("SELECT COUNT(*) FROM migrations WHERE namespace='RoleWardenDemo'"));
$o = sp8($A, ['demo:uninstall']);
check('A18', 'Removal right after migrate --all (demo never installed) brings the database back as before migrate --all', statediff($S4, fullstate()) === [] && ! phperr($o), short($o) . json_encode(statediff($S4, fullstate())));
sp8($A, ['migrate', '--all']);
$o = sp8($A, ['demo:install'], ['demo.password' => DPW]);
check('A19', 'demo:install after migrate --all succeeds: accounts, roles, permissions and documents created', rc($o) === 0 && count(demo_ids()) === 3 && count(docs()) >= 3 && count(q("SELECT id FROM acl_roles WHERE slug LIKE 'demo-%'")) === 3, short($o));
$x = c8('x19'); $c = serve8($A, 'A5');
check('A19b', 'After migrate --all + demo:install the author signs in and sees the documents', login8($x, 'author@example.com', DPW) && view_demo($x)['status'] === 200);
server_stop();
$o = sp8($A, ['demo:uninstall']);
check('A19c', 'And demo:uninstall leaves the database as before', statediff($S4, fullstate()) === [], json_encode(statediff($S4, fullstate())));

// generated password
$o = sp8($A, ['demo:install']);
preg_match('/Password[^:\n]*:\s*(\S+)/', $o, $m);
$gen = $m[1] ?? '';
check('A20', 'Without demo.password a password is generated and printed once', rc($o) === 0 && strlen($gen) >= 12, short($o));
info('Generated password: ' . strlen($gen) . ' characters, ' . (preg_match('/^[0-9a-f]+$/', $gen) ? 'hexadecimal' : 'mixed'));
$c = serve8($A, 'A6');
$okAll = true; foreach (DEMO_EMAILS as $e) { $x = c8("g-$e"); $okAll = $okAll && login8($x, $e, $gen); }
check('A20b', 'The generated password signs in the three demo accounts', $okAll);
server_stop();
$o3 = sp8($A, ['demo:install']);
check('A20c', 'A second installation does not print a password again', ! str_contains($o3, $gen) && ! preg_match('/shown only now/i', $o3), short($o3));
sp8($A, ['demo:uninstall']);
// demo.password from .env
write_env($A, 'development', 'demo.password = ' . DPW . "\n");
$o = sp8($A, ['demo:install']);
$c = serve8($A, 'A7');
$x = c8('envpw');
check('A21', 'demo.password written in .env (not a process variable) is the accounts\' password', rc($o) === 0 && login8($x, 'author@example.com', DPW) && ! str_contains($o, DPW), short($o));
server_stop();
sp8($A, ['demo:uninstall']);
write_env($A, 'development');
check('A21b', 'Back to the state before the demo (counters apart)', statediff($S4, fullstate()) === [], json_encode(statediff($S4, fullstate())));

// conflicts
echo "\n-- A: conflicts with existing accounts, roles, permissions\n";
$conf = [
    'email' => fn () => make_owner('reader@example.com', OPW, 'realreader', 'user'),
    'username' => fn () => make_owner('someone@v8.test', OPW, 'demo-author', 'user'),
    'role-slug' => fn () => q("INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES ('demo-approver','Approvers of the app','Real role of the application',NULL,0,0,NOW(),NOW())"),
    'role-name' => fn () => q("INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES ('readers','Reader','Real role of the application',NULL,0,0,NOW(),NOW())"),
    'permission' => fn () => q("INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES ('documents.view','documents','Real permission of the application',0,NOW(),NOW())"),
];
foreach ($conf as $k => $make) {
    $make();
    $before = fullstate();
    $o = sp8($A, ['demo:install'], ['demo.password' => DPW]);
    $after = fullstate();
    $installed = count(q("SELECT id FROM acl_roles WHERE slug IN ('demo-reader','demo-author')")) > 0 && in_array('demo_documents', tables(), true);
    info("conflict $k: demo:install rc=" . rc($o) . ' ' . short($o, 250));
    if (in_array($k, ['email', 'username', 'role-slug'], true)) {
        check("A22-$k", "Conflict ($k): installation refused without damage (database identical, counters included) and with a message", statediff($before, $after, true) === [] && trim(preg_replace('/^rc=\d+|CodeIgniter v[^\n]*/m', '', $o)) !== '' && ! phperr($o), short($o) . ' ' . json_encode(array_slice(statediff($before, $after, true), 0, 6)));
    } else {
        // a role with the same name only, or a permission the application already has
        if ($installed) {
            $o2 = sp8($A, ['demo:uninstall']);
            $kept = $k === 'role-name' ? (int) q1("SELECT COUNT(*) FROM acl_roles WHERE slug='readers' AND deleted_at IS NULL") === 1 : (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug='documents.view' AND description='Real permission of the application'") === 1;
            check("A22-$k", "Existing $k of the application: the demo installed, and its removal did not take the application's own record", $kept, short($o) . ' / ' . short($o2) . ' ' . json_encode(statediff($before, fullstate())));
        } else {
            check("A22-$k", "Existing $k of the application: installation refused without damage", statediff($before, $after, true) === [] && ! phperr($o), short($o));
        }
    }
    if ($k === 'email') check('A22-email-b', 'The real account with the demo e-mail was not taken over (password, roles, showcase list untouched)', $after['users'] === $before['users'] && $after['auth_identities'] === $before['auth_identities'] && ! q("SELECT id FROM settings WHERE class LIKE 'RoleWardenDemo%'"));
    if ($k === 'username') {
        $left = statediff($before, $after, true);
        info('After the refused installation with the username taken: ' . count($left) . ' differences, e.g. ' . json_encode(array_slice($left, 0, 3)));
        $o3 = sp8($A, ['demo:install'], ['demo.password' => DPW]);
        info('demo:install again, username still taken: ' . short($o3, 250));
        $o4 = sp8($A, ['demo:uninstall']);
        check('A22-username-b', 'demo:uninstall after that failed installation does not delete the real account that owns the username demo-author', (int) q1("SELECT COUNT(*) FROM users WHERE username='demo-author'") === 1 && (int) q1("SELECT COUNT(*) FROM auth_identities WHERE secret='someone@v8.test'") === 1, short($o4));
        check('A22-username-c', 'And it removes what the failed installation left', ! q("SELECT id FROM acl_permissions WHERE slug LIKE 'documents.%'") && ! q("SELECT id FROM acl_roles WHERE slug LIKE 'demo-%'") && ! in_array('demo_documents', tables(), true), json_encode(statediff($before, fullstate(), true)));
    }
    // clean up the conflicting record and any partial demo
    if (in_array('demo_documents', tables(), true) || q("SELECT id FROM acl_roles WHERE slug IN ('demo-reader','demo-author')")) sp8($A, ['demo:uninstall']);
    q("DELETE FROM auth_identities WHERE secret IN ('reader@example.com','someone@v8.test')");
    q("DELETE FROM acl_user_roles WHERE user_id IN (SELECT id FROM users WHERE username IN ('realreader','demo-author'))");
    q("DELETE FROM users WHERE username IN ('realreader','demo-author')");
    q("DELETE FROM acl_roles WHERE slug IN ('demo-approver','readers') AND description='Real role of the application'");
    q("DELETE FROM acl_permissions WHERE slug='documents.view' AND description='Real permission of the application'");
}
check('A23', 'After the conflict cases the database is back as before them (counters apart)', statediff($S4, fullstate()) === [], json_encode(array_slice(statediff($S4, fullstate()), 0, 8)));
$LOGS_A = logs_of($A);
}

/* =================================================================================== B: permissions at work */
if (in_array('B', $PHASES, true)) {
echo "\n== B: permissions at work in the demo (development)\n";
$B = app_copy('appB', $MODFULL, true);
base_install($B); $R = real_accounts();
sp8($B, ['demo:install'], ['demo.password' => DPW]);
$ids = demo_ids();
$c = serve8($B, 'B');
$rd = c8('B-reader'); $au = c8('B-author'); $ap = c8('B-approver'); $own = c8('B-owner'); $gu = c8('B-guest');
login8($rd, 'reader@example.com', DPW); login8($au, 'author@example.com', DPW); login8($ap, 'approver@example.com', DPW); login8($own, 'owner@v8.test', OPW);
$vr = view_demo($rd); $va = view_demo($au); $vp = view_demo($ap);
$mine = fn (string $e) => ids_where(fn ($d) => (int) $d['owner_id'] === $ids[$e]);
$mineDraft = fn (string $e) => ids_where(fn ($d) => (int) $d['owner_id'] === $ids[$e] && $d['status'] === 'draft');
$all = ids_where(fn ($d) => true);
$submitted = ids_where(fn ($d) => $d['status'] === 'submitted');
check('B01', 'Reader: every document listed, no button at all (no New, Edit, Submit, Approve, Delete); 1 permission', $vr['status'] === 200 && ! $vr['new'] && ! $vr['edit'] && ! $vr['submit'] && ! $vr['approve'] && ! $vr['delete'] && $vr['perms'] === 1 && count(array_filter(docs(), fn ($d) => str_contains($vr['html'], htmlspecialchars($d['title'], ENT_QUOTES)))) === count($all), json_encode(array_diff_key($vr, ['html' => 1])));
check('B02', 'Author: New; Edit only on own documents; Submit only on own drafts; no Approve, no Delete; 3 permissions', $va['new'] && $va['edit'] === $mine('author@example.com') && $va['submit'] === $mineDraft('author@example.com') && ! $va['approve'] && ! $va['delete'] && $va['perms'] === 3, json_encode(array_diff_key($va, ['html' => 1])) . ' own=' . json_encode($mine('author@example.com')));
check('B03', 'Approver: New; Approve only on submitted; Delete on every document; Edit only on own; 5 permissions', $vp['new'] && $vp['approve'] === $submitted && $vp['delete'] === $all && $vp['edit'] === $mine('approver@example.com') && $vp['submit'] === $mineDraft('approver@example.com') && $vp['perms'] === 5, json_encode(array_diff_key($vp, ['html' => 1])));
$vo = view_demo($own, 'owner demo');
info('Super admin (holds no demo role): New=' . json_encode($vo['new']) . ' edit=' . json_encode($vo['edit']) . ' approve=' . json_encode($vo['approve']) . ' delete=' . json_encode($vo['delete']) . ' permissions figure=' . json_encode($vo['perms']));
// documents to play with
act($ap, 'demo', ['title' => 'B approver draft', 'body' => 'x'], 'demo/create', 'B approver creates a draft');
$apDraft = (int) q1("SELECT id FROM demo_documents WHERE title='B approver draft'");
act($au, 'demo', ['title' => 'B author draft', 'body' => 'y'], 'demo/create', 'B author creates a draft');
$auDraft = (int) q1("SELECT id FROM demo_documents WHERE title='B author draft'");
$auSub = ids_where(fn ($d) => (int) $d['owner_id'] === $ids['author@example.com'] && $d['status'] === 'submitted')[0] ?? 0;
$approved = ids_where(fn ($d) => $d['status'] === 'approved')[0] ?? 0;
$apOwn = ids_where(fn ($d) => (int) $d['owner_id'] === $ids['approver@example.com'])[0] ?? 0;
check('B04', 'Fixture: an approver draft, an author draft, an author submitted document, an approved document', $apDraft && $auDraft && $auSub && $approved && $apOwn);
$refused = function (string $id, string $label, Client $c, string $method, string $url, array $data = [], string $from = 'demo') {
    $before = docs();
    if ($method === 'GET') G($c, $url, "$id $url"); else act($c, $url, $data, $from, "$id $url");
    $after = docs();
    check($id, $label, $after === $before && $c->status < 500 && ! leaks_sql($c->body) && ! ($method === 'GET' && $c->status === 200 && form_with($c->body, 'title')), "HTTP {$c->status} {$c->location} " . short(text_of($c->body), 160));
};
$refused('B05', 'Reader: GET demo/create refused (no form)', $rd, 'GET', 'demo/create');
$refused('B06', 'Reader: hand-made POST demo (create) with a valid CSRF token refused, nothing written', $rd, 'POST', 'demo', ['title' => 'reader forged', 'body' => 'x']);
$refused('B07', "Author: hand-made update of the approver's document refused", $au, 'POST', "demo/$apOwn", ['title' => 'author forged', 'body' => 'x']);
$refused('B07b', "Author: GET the edit form of the approver's document refused", $au, 'GET', "demo/$apOwn/edit");
$refused('B08', "Author: submit of the approver's draft refused", $au, 'POST', "demo/$apDraft/submit");
$refused('B09', 'Author: approve without documents.approve refused', $au, 'POST', "demo/$auSub/approve");
$refused('B10', 'Author: delete without documents.delete refused', $au, 'POST', "demo/$auDraft/delete");
$refused('B11', 'Approver: approve of a draft refused', $ap, 'POST', "demo/$auDraft/approve");
info('Message: ' . short(text_of(G($ap, 'demo')), 1) . json_encode(preg_match_all('/&quot;message&quot;&#x3A;&quot;(.*?)&quot;/', $ap->body, $mm) ? array_map('html_entity_decode', $mm[1]) : []));
$refused('B12', 'Author: submit of a document that is not a draft (own submitted) refused', $au, 'POST', "demo/$auSub/submit");
$refused('B12b', 'Author: submit of an approved document refused', $au, 'POST', "demo/$approved/submit");
$refused('B13', 'Approver: approve of an already approved document refused', $ap, 'POST', "demo/$approved/approve");
$refused('B14', "Approver: update of the author's document refused (documents.update covers only one's own)", $ap, 'POST', "demo/$auDraft", ['title' => 'approver forged', 'body' => 'x']);
$refused('B15', 'Reader: submit, approve and delete forged by hand refused', $rd, 'POST', "demo/$auDraft/submit");
$refused('B15b', 'Reader: approve forged by hand refused', $rd, 'POST', "demo/$auSub/approve");
$refused('B15c', 'Reader: delete forged by hand refused', $rd, 'POST', "demo/$auDraft/delete");
$before = docs(); act($own, "demo/$auDraft", ['title' => 'super admin forged', 'body' => 'x'], 'demo', 'owner updates author doc');
info("Super admin editing an author's document: HTTP {$own->status}; changed=" . json_encode(docs() !== $before));
if (docs() !== $before) amb('B16', "The super admin can edit another user's document", 'SPEC: documents.update "solo i propri: la proprieta\' la controlla il codice della demo". Whether the super admin is exempt from ownership is not stated.');
foreach (['submit', 'approve', 'delete'] as $a) { G($gu, 'login'); $before = docs(); P($gu, "demo/$auDraft/$a", ['csrf_test_name' => csrf_of($gu->body)], "guest $a"); check("B17-$a", "Guest: POST $a refused (sent to sign in), nothing changed", docs() === $before && in_array($gu->status, [302, 303, 403], true) && ! leaks_sql($gu->body), "HTTP {$gu->status} {$gu->location}"); }
G($gu, 'demo', 'guest demo'); check('B17b', 'Guest: /demo sends to the sign-in page', in_array($gu->status, [302, 303], true) && str_contains($gu->location, 'login'), "{$gu->status} {$gu->location}");
foreach (['GET' => ['demo/99999/edit'], 'POST' => ['demo/99999', 'demo/99999/submit', 'demo/99999/approve', 'demo/99999/delete', 'demo/0/delete']] as $m => $urls) foreach ($urls as $u) {
    if ($m === 'GET') G($ap, $u, "nonexistent $u"); else act($ap, $u, ['title' => 'x', 'body' => 'y'], 'demo', "nonexistent $u");
    check('B18-' . substr(md5($u), 0, 4), "Nonexistent document ($m $u): no 500, no SQL", no500($ap) && $ap->status !== 200, "HTTP {$ap->status}");
}
foreach (['demo/abc/edit', 'demo/1.5/edit', "demo/1'/edit", 'demo/-1/edit'] as $u) { G($ap, $u, "odd $u"); check('B18-' . substr(md5($u), 0, 4), "Malformed id ($u): no 500, no SQL", no500($ap), "HTTP {$ap->status}"); }

echo "\n-- B: the normal path\n";
act($au, 'demo', ['title' => 'Quarterly report', 'body' => 'Numbers'], 'demo/create', 'author creates');
$q = q("SELECT * FROM demo_documents WHERE title='Quarterly report'")[0] ?? [];
check('B19', 'Author creates a document: a draft owned by the author', ($q['status'] ?? '') === 'draft' && (int) ($q['owner_id'] ?? 0) === $ids['author@example.com'] && $q['body'] === 'Numbers', json_encode($q));
$qid = (int) ($q['id'] ?? 0);
act($au, "demo/$qid", ['title' => 'Quarterly report v2', 'body' => 'More numbers'], "demo/$qid/edit", 'author edits');
check('B20', 'Author edits the own draft', q1('SELECT title FROM demo_documents WHERE id=?', [$qid]) === 'Quarterly report v2');
act($au, "demo/$qid/submit", [], 'demo', 'author submits');
check('B21', 'Author submits the own draft: status submitted', q1('SELECT status FROM demo_documents WHERE id=?', [$qid]) === 'submitted');
act($ap, "demo/$qid/approve", [], 'demo', 'approver approves');
$q = q('SELECT * FROM demo_documents WHERE id=?', [$qid])[0];
check('B22', 'Approver approves: status approved, approved_by the approver, approved_at set', $q['status'] === 'approved' && (int) $q['approved_by'] === $ids['approver@example.com'] && $q['approved_at'] !== null, json_encode($q));
$h = G($au, "demo/$qid/edit", 'author edit approved');
check('B23a', 'Editing an approved document warns that it goes back to draft', str_contains(text_of($h), 'back to draft'), short(text_of($h), 200));
act($au, "demo/$qid", ['title' => 'Quarterly report v3', 'body' => 'Changed after approval'], "demo/$qid/edit", 'author edits approved');
$q = q('SELECT * FROM demo_documents WHERE id=?', [$qid])[0];
check('B23', 'Saving an approved document puts it back to draft, as the form says', $q['status'] === 'draft', json_encode($q));
info('After going back to draft: approved_by=' . json_encode($q['approved_by']) . ' approved_at=' . json_encode($q['approved_at']));
act($ap, "demo/$qid/delete", [], 'demo', 'approver deletes');
check('B24', 'Approver deletes any document', ! q('SELECT id FROM demo_documents WHERE id=?', [$qid]));

echo "\n-- B: CSRF, escaping, validation\n";
$before = docs();
P($au, 'demo', ['title' => 'no token', 'body' => 'x'], 'author create without csrf');
check('B25', 'Create without CSRF token refused, nothing written', docs() === $before && $au->status !== 200 && ! leaks_sql($au->body), "HTTP {$au->status}");
P($au, 'demo', ['title' => 'bad token', 'body' => 'x', 'csrf_test_name' => str_repeat('a', 32)], 'author create wrong csrf');
check('B25b', 'Create with a wrong CSRF token refused', docs() === $before, "HTTP {$au->status}");
P($ap, "demo/$auDraft/delete", [], 'approver delete without csrf');
check('B25c', 'Delete without CSRF token refused, document still there', (bool) q('SELECT id FROM demo_documents WHERE id=?', [$auDraft]), "HTTP {$ap->status}");
P($au, "demo/$auDraft/submit", ['csrf_test_name' => 'x'], 'author submit wrong csrf');
check('B25d', 'Submit with a wrong CSRF token refused', q1('SELECT status FROM demo_documents WHERE id=?', [$auDraft]) === 'draft');
$forms = forms(G($ap, 'demo'));
check('B26', 'Every form of the demo page carries a CSRF field', $forms !== [] && ! array_filter($forms, fn ($f) => $f['method'] === 'post' && ! array_key_exists('csrf_test_name', $f['fields'])));
$xt = '<script>alert(8)</script><b>t8</b> "Q\'8 x");alert(9);("';
$xb = '"><img src=x onerror=alert(8)> <i>i8</i>';
act($au, 'demo', ['title' => $xt, 'body' => $xb], 'demo/create', 'author creates markup');
$xid = (int) q1('SELECT id FROM demo_documents WHERE title=?', [$xt]);
check('B27', 'Markup in title and text stored as typed', $xid > 0 && q1('SELECT body FROM demo_documents WHERE id=?', [$xid]) === $xb);
$pages = [G($rd, 'demo', 'reader markup'), G($au, "demo/$xid/edit", 'author edit markup'), G($ap, 'demo', 'approver markup')];
$raw = array_filter($pages, fn ($h) => str_contains($h, '<script>alert(8)') || str_contains($h, '<b>t8</b>') || str_contains($h, '<img src=x') || str_contains($h, '<i>i8</i>'));
check('B28', 'Title and text escaped on the list (reader, approver) and in the edit form', $raw === [] && str_contains($pages[0], '&lt;script&gt;alert(8)'), 'raw markup found');
$xp = dom($pages[2]); $conf = '';
foreach ($xp->query('//form[contains(@action,"/demo/' . $xid . '/delete")]') as $f) $conf = $f->getAttribute('onsubmit');
$jsOk = preg_match('/^return confirm\((".*")\)$/s', $conf, $m) && json_decode($m[1]) !== null && str_contains((string) json_decode($m[1]), $xt);
check('B29', 'Delete confirmation: the title reaches the JavaScript as one string literal (no break-out)', (bool) $jsOk, $conf);
$before = docs();
foreach (['empty title' => ['title' => '', 'body' => 'x'], 'blank title' => ['title' => '   ', 'body' => 'x'], '161 characters' => ['title' => str_repeat('t', 161), 'body' => 'x'], 'body of 70000 characters' => ['title' => 'Long body', 'body' => str_repeat('b', 70000)], 'missing fields' => []] as $k => $d) {
    act($au, 'demo', $d, 'demo/create', "create $k");
    $made = docs() !== $before;
    check('B30-' . substr(md5($k), 0, 3), "Create with $k: no 500, no SQL detail" . ($k === 'body of 70000 characters' ? '' : ', nothing written'), no500($au) && ($k === 'body of 70000 characters' || ! $made), "HTTP {$au->status} made=" . json_encode($made) . ' ' . short(text_of($au->body), 160));
    if ($made && $k === 'body of 70000 characters') { info('A 70000-character text was ' . (strlen((string) q1("SELECT body FROM demo_documents WHERE title='Long body'")) === 70000 ? 'stored whole' : 'stored truncated')); q("DELETE FROM demo_documents WHERE title='Long body'"); }
    $before = docs();
}
act($au, 'demo', ['title' => str_repeat('é', 160), 'body' => 'Unicode 😀'], 'demo/create', 'create 160 accented');
check('B31', 'A title of exactly 160 characters (accented) and an emoji in the text are kept whole', (int) q1('SELECT COUNT(*) FROM demo_documents WHERE title=? AND body=?', [str_repeat('é', 160), 'Unicode 😀']) === 1);

echo "\n-- B: a role or an override changed in the panel applies at the next request\n";
$rid = role_id('demo-reader');
panelp($own, "rolewarden/roles/$rid", "rolewarden/roles/$rid/permissions", ['permission_id' => perm_id('documents.create'), 'granted' => 1], 'grant create to Reader');
check('B32a', 'Panel: documents.create granted to the Reader role', str_contains($own->body, '"ok"') && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$rid, perm_id('documents.create')]) === 1, short($own->body));
$v = view_demo($rd, 'reader after grant');
act($rd, 'demo', ['title' => 'Reader now writes', 'body' => 'x'], 'demo/create', 'reader creates after grant');
check('B32', "The reader's next request shows New document and the creation succeeds, without signing in again", $v['new'] && (int) q1("SELECT COUNT(*) FROM demo_documents WHERE title='Reader now writes'") === 1);
panelp($own, "rolewarden/roles/$rid", "rolewarden/roles/$rid/permissions", ['permission_id' => perm_id('documents.create'), 'granted' => 0], 'revoke create from Reader');
$v = view_demo($rd, 'reader after revoke');
$before = docs(); act($rd, 'demo', ['title' => 'Reader again', 'body' => 'x'], 'demo', 'reader creates after revoke');
check('B33', 'Revoked in the panel: the next request hides New document and refuses the creation', ! $v['new'] && docs() === $before);
$v = view_demo($ap);
check('B33b', 'Inheritance: the approver (Reader <- Author <- Approver) still has its five permissions', $v['perms'] === 5 && $v['new']);
$uidAp = $ids['approver@example.com'];
panelp($own, "rolewarden/users/$uidAp", "rolewarden/users/$uidAp/permissions", ['permission_id' => perm_id('documents.delete'), 'granted' => 0], 'deny delete to approver');
$v = view_demo($ap, 'approver denied delete');
$before = docs(); act($ap, "demo/$auDraft/delete", [], 'demo', 'approver delete after deny');
check('B34', 'Per-user denial of documents.delete: no Delete button for the approver and the forged delete refused', ! $v['delete'] && docs() === $before && $v['perms'] === 4, json_encode(array_diff_key($v, ['html' => 1])));
panelp($own, "rolewarden/users/$uidAp", "rolewarden/users/$uidAp/permissions", ['permission_id' => perm_id('documents.delete')], 'clear deny');
$v = view_demo($ap, 'approver override cleared');
check('B34b', 'Override cleared: Delete back at the next request', $v['delete'] !== []);
$uidAu = $ids['author@example.com'];
panelp($own, "rolewarden/users/$uidAu", "rolewarden/users/$uidAu/permissions", ['permission_id' => perm_id('documents.approve'), 'granted' => 1], 'grant approve to author');
$v = view_demo($au, 'author granted approve');
$sub = ids_where(fn ($d) => $d['status'] === 'submitted');
if ($sub) act($au, "demo/{$sub[0]}/approve", [], 'demo', 'author approves with override');
check('B35', 'Per-user grant of documents.approve: the author sees Approve on submitted documents and approves', $v['approve'] === $sub && $sub && q1('SELECT status FROM demo_documents WHERE id=?', [$sub[0]]) === 'approved' && (int) q1('SELECT approved_by FROM demo_documents WHERE id=?', [$sub[0]]) === $uidAu, json_encode([$v['approve'], $sub]));
panelp($own, "rolewarden/users/$uidAu", "rolewarden/users/$uidAu/permissions", ['permission_id' => perm_id('documents.approve')], 'clear grant');
panelp($own, "rolewarden/users/$uidAu", "rolewarden/users/$uidAu/deactivate", [], 'deactivate author', false);
check('B36a', 'Fixture: the author deactivated from the panel', (int) q1('SELECT active FROM users WHERE id=?', [$uidAu]) === 0);
$before = docs();
G($au, 'demo', 'deactivated author demo'); $st = $au->status;
act($au, 'demo', ['title' => 'deactivated writes', 'body' => 'x'], 'demo', 'deactivated author create');
check('B36', 'A demo account deactivated in the panel is refused at its next request', docs() === $before && $st !== 200, "GET demo -> $st");
$logsB = logs_of($B);
server_stop();
$LOGS_B = $logsB;
}

/* =================================================================================== C: showcase */
if (in_array('C', $PHASES, true)) {
echo "\n== C: showcase mode\n";
$C = app_copy('appC', $MODFULL, true);
base_install($C); $R = real_accounts();
sp8($C, ['demo:install'], ['demo.password' => DPW]);
$ids = demo_ids();
$rolesSnap = fn () => [q('SELECT * FROM acl_user_roles ORDER BY user_id, role_id'), q('SELECT * FROM acl_roles ORDER BY id'), q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id'), q('SELECT * FROM acl_user_permissions ORDER BY user_id'), q("SELECT value FROM settings WHERE class LIKE 'RoleWardenDemo%'")];
$uname = fn (int $id) => (string) q1('SELECT username FROM users WHERE id=?', [$id]);
// off by default
$c = serve8($C, 'C-off');
$rd = c8('C-r'); login8($rd, 'reader@example.com', DPW);
$v = view_demo($rd, 'showcase default');
check('C01', 'Showcase off by default: no account bar', $v['showcase'] === [] && ! str_contains($v['html'], '/demo/showcase/'));
act($rd, 'demo/showcase/' . $ids['approver@example.com'], [], 'demo', 'switch while off');
$v = view_demo($rd, 'after switch while off');
check('C02', 'Showcase off: a hand-made switch request with a valid CSRF token is refused, the session stays the reader', $v['who'] === $uname($ids['reader@example.com']) && ! leaks_sql($rd->body), $v['who']);
server_stop();
// on, development, from .env
write_env($C, 'development', "demo.showcase = true\n");
$c = serve8($C, 'C-on');
$rd = c8('C-r2'); login8($rd, 'reader@example.com', DPW);
$v = view_demo($rd, 'showcase on');
$want = array_values($ids); sort($want);
check('C03', 'demo.showcase = true: the bar lists exactly the three demo accounts', $v['showcase'] === $want, json_encode($v['showcase']));
$snap = $rolesSnap();
act($rd, 'demo/showcase/' . $ids['approver@example.com'], [], 'demo', 'switch to approver');
$v = view_demo($rd, 'now approver');
check('C04', 'One click: the session becomes the approver, without any password', $v['who'] === $uname($ids['approver@example.com']) && $v['perms'] === 5 && $v['delete'] !== [], $v['who']);
act($rd, 'demo/showcase/' . $ids['author@example.com'], [], 'demo', 'switch to author');
$v = view_demo($rd, 'now author');
check('C04b', 'And again to the author', $v['who'] === $uname($ids['author@example.com']) && $v['perms'] === 3);
check('C05', 'No role, permission, assignment or showcase list changed by the switches', $rolesSnap() === $snap);
$cur = $uname($ids['author@example.com']);
foreach (['real super admin' => $R['owner'], 'real user' => $R['real'], 'deactivated real user' => $R['off'], 'nonexistent id 99999' => 99999, 'id 0' => 0] as $k => $target) {
    act($rd, "demo/showcase/$target", [], 'demo', "switch to $k");
    $v = view_demo($rd, "after switch to $k");
    check('C06-' . substr(md5($k), 0, 3), "Switch to the $k refused: the session stays the demo author", $v['who'] === $cur && ! leaks_sql($rd->body) && $rd->status < 500, "now {$v['who']}");
}
foreach (['abc', '-1', '1.5'] as $t) { act($rd, "demo/showcase/$t", [], 'demo', "switch $t"); check('C06-' . $t, "Malformed target ($t): no 500, session unchanged", $rd->status < 500 && view_demo($rd)['who'] === $cur); }
q('UPDATE users SET active=0 WHERE id=?', [$ids['reader@example.com']]);
act($rd, 'demo/showcase/' . $ids['reader@example.com'], [], 'demo', 'switch to deactivated demo reader');
$v = view_demo($rd, 'after switch to deactivated');
check('C07', 'Switch to a deactivated demo account refused', $v['who'] === $cur, "now {$v['who']}");
q('UPDATE users SET active=1 WHERE id=?', [$ids['reader@example.com']]);
P($rd, 'demo/showcase/' . $ids['approver@example.com'], [], 'switch without csrf');
check('C08', 'Switch without CSRF token refused', view_demo($rd)['who'] === $cur);
P($rd, 'demo/showcase/' . $ids['approver@example.com'], ['csrf_test_name' => str_repeat('0', 32)], 'switch wrong csrf');
check('C08b', 'Switch with a wrong CSRF token refused', view_demo($rd)['who'] === $cur);
G($rd, 'demo/showcase/' . $ids['approver@example.com'], 'switch by GET');
check('C08c', 'Switch by GET does nothing', view_demo($rd)['who'] === $cur, "GET status {$rd->status}");
$gu = c8('C-guest'); $tg = csrf_of(G($gu, 'login'));
P($gu, 'demo/showcase/' . $ids['approver@example.com'], ['csrf_test_name' => $tg], 'guest switch');
G($gu, 'demo', 'guest after switch');
check('C09', 'Guest: switch refused, still not signed in', in_array($gu->status, [302, 303], true) && str_contains($gu->location, 'login'), "{$gu->status} {$gu->location}");
$ow = c8('C-owner'); login8($ow, 'owner@v8.test', OPW);
$vo = view_demo($ow, 'owner showcase');
info('Showcase bar for a signed-in real super admin: ' . json_encode($vo['showcase']));
check('C10', 'No role changed after all the attempts', $rolesSnap() === $snap);
$logs = q("SELECT actor_label, action, subject_label FROM acl_activity_log ORDER BY id");
info('Activity log after the switches: ' . json_encode(array_slice($logs, -6)));
server_stop();
// production with the flag on
write_env($C, 'production', "demo.showcase = true\n");
$c = serve8($C, 'C-prod');
$rd = c8('C-r3'); login8($rd, 'reader@example.com', DPW);
$v = view_demo($rd, 'prod showcase');
check('C11', 'CI_ENVIRONMENT = production with demo.showcase = true: no bar', $v['status'] === 200 && $v['showcase'] === [] && ! str_contains($v['html'], '/demo/showcase/'), json_encode($v['showcase']));
act($rd, 'demo/showcase/' . $ids['approver@example.com'], [], 'demo', 'prod switch');
$v = view_demo($rd, 'prod after switch');
check('C12', 'Production: the switch request is refused, the session stays the reader', $v['who'] === $uname($ids['reader@example.com']) && ! leaks_sql($rd->body), $v['who']);
server_stop();
// environment "demo": Boot/demo.php copied from production.php, as the demo README says
copy("$C/app/Config/Boot/production.php", "$C/app/Config/Boot/demo.php");
write_env($C, 'demo', "demo.showcase = true\n");
$c = serve8($C, 'C-demoenv');
$rd = c8('C-r4'); login8($rd, 'reader@example.com', DPW);
$v = view_demo($rd, 'demo env');
check('C13', 'CI_ENVIRONMENT = demo (boot copied from production): the bar is there and no debug toolbar', $v['showcase'] === $want && ! preg_match('/debugbar|kint|DEBUG-VIEW/i', $v['html']), json_encode($v['showcase']));
act($rd, 'demo/showcase/' . $ids['approver@example.com'], [], 'demo', 'demo env switch');
check('C14', 'Environment demo: the switch works', view_demo($rd)['who'] === $uname($ids['approver@example.com']));
$h = G($rd, 'demo/abc/edit', 'demo env 404');
check('C15', "Environment demo: an unknown address shows no error detail (no route, path or trace)", $rd->status === 404 && ! preg_match("/Can't find a route|SYSTEMPATH|APPPATH|ROOTPATH|\.php|Stack|trace/i", text_of($h)), "{$rd->status} " . short(text_of($h), 200));
q('RENAME TABLE demo_documents TO demo_documents_v8hidden');
$INDUCED[] = 'demo_documents';
$h = G($rd, 'demo', 'demo env db error');
q('RENAME TABLE demo_documents_v8hidden TO demo_documents');
check('C16', 'Environment demo: a database failure shows a generic error page, no SQL, no trace', $rd->status >= 400 && ! leaks_sql($h) && ! preg_match('/SYSTEMPATH|APPPATH|Stack|trace|\.php/i', text_of($h)), "{$rd->status} " . short(text_of($h), 200));
server_stop();
$LOGS_C = logs_of($C);
}

/* =================================================================================== D: independence */
if (in_array('D', $PHASES, true)) {
echo "\n== D: the module without the demo, and the demo wired into a clean application\n";
$D = app_copy('appD', $MOD, false);
base_install($D); $R = real_accounts();
$c = serve8($D, 'D');
$ow = c8('D-owner');
check('D01', 'Module alone (no demo folder, no demo lines): owner signs in and the panel answers', login8($ow, 'owner@v8.test', OPW) && G($ow, 'rolewarden/users') !== '' && $ow->status === 200);
G($ow, 'demo', 'D demo');
check('D02', 'Module alone: /demo does not exist (404), no error', $ow->status === 404 && ! leaks_sql($ow->body), "{$ow->status}");
foreach (['rolewarden/roles', 'rolewarden/permissions', 'rolewarden/settings', 'rolewarden/activity', 'rolewarden/appearance', 'rolewarden/profile'] as $u) { G($ow, $u); $okD[] = $ow->status === 200 && ! leaks_sql($ow->body); }
check('D03', 'Module alone: every panel screen answers 200', ! in_array(false, $okD, true));
server_stop();
// demo zip + the two README lines in a clean application
$S0 = fullstate();
$dz = "$D/modules/rolewarden-demo";
copytree($DEMO, $dz);
$auto = file_get_contents("$D/app/Config/Autoload.php");
$auto = preg_replace('/(public \$psr4 = \[\s*\n)/', "$1        'RoleWardenDemo' => ROOTPATH . 'modules/rolewarden-demo/src',\n", $auto, 1, $n1);
file_put_contents("$D/app/Config/Autoload.php", $auto);
$rt = file_get_contents("$D/app/Config/Routes.php");
$rt = str_replace("\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);\n", "\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);\n\\RoleWardenDemo\\Config\\DemoRoutes::register(\$routes);\n", $rt, $n2);
file_put_contents("$D/app/Config/Routes.php", $rt);
check('D04', 'Clean application: demo copied to modules/rolewarden-demo and the two README lines added', $n1 === 1 && $n2 === 1);
$o = sp8($D, ['demo:install'], ['demo.password' => DPW]);
check('D05', 'demo:install in the clean application succeeds', rc($o) === 0, short($o));
$c = serve8($D, 'D2');
$au = c8('D-au');
check('D06', 'The author signs in and /demo lists the documents with New document', login8($au, 'author@example.com', DPW) && ($v = view_demo($au))['status'] === 200 && $v['new']);
server_stop();
$refs = demo_refs();
$o = sp8($D, ['demo:uninstall']);
$cl = classify($S0, fullstate(), $refs, array_values($R));
check('D07', 'demo:uninstall in the clean application: no residue, nothing lost', rc($o) === 0 && $cl['residue'] === [] && $cl['lost'] === [], json_encode($cl));
$LOGS_D = logs_of($D);
}

/* =================================================================================== E: complete application zip */
if (in_array('E', $PHASES, true)) {
echo "\n== E: complete application (zip), served with CodeIgniter's rewrite.php, document root public/\n";
empty_db();
$appRoot = glob("$ZAPP/rolewarden-app-*")[0];
$E = "$WORK/appE"; copytree($appRoot, $E); $APPS[] = $E;
$router = "$E/vendor/codeigniter4/framework/system/rewrite.php";
$serveZ = function (string $tag) use ($E, $router): Client {
    global $WORK, $SRVZ;
    server_stop();
    $e = getenv(); unset($e['RW_DB_USERNAME'], $e['RW_DB_PASSWORD']);
    $e['logger.threshold'] = '9';
    $log = "$WORK/server-$tag.log"; $GLOBALS['SERVERLOGS'][] = $log;
    $p = proc_open(['php', '-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-S', '127.0.0.1:' . PORT7, '-t', "$E/public", $router], [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, "$E/public", $e, ['bypass_shell' => true]);
    $GLOBALS['SRV7'] = $p; $GLOBALS['SRV7_PIDS'][] = proc_get_status($p)['pid'];
    for ($i = 0; $i < 40; $i++) { usleep(250000); $s = @fsockopen('127.0.0.1', PORT7); if ($s) { fclose($s); break; } }
    info('zip server pid=' . proc_get_status($p)['pid']);
    return c8($tag);
};
$c = $serveZ('E1');
G($c, 'demo', 'zip demo before install');
check('E01', 'Complete application before the installer: /demo does not answer (not 200), no 500, no SQL', $c->status !== 200 && no500($c), "{$c->status} {$c->location}");
G($c, '', 'zip home'); check('E01b', 'Home still leads to the installer', str_contains($c->location, 'rolewarden/install'), $c->location);
// installer, as V7
G($c, 'rolewarden/install', 'install first');
$code = trim((string) @file_get_contents("$E/writable/rolewarden/install-code.txt"));
$ip = function (array $v, string $tag) use ($c) { $h = G($c, 'rolewarden/install'); $t = csrf_of($h) ?: csrf_of(G($c, 'login')); return P($c, 'rolewarden/install', ['csrf_test_name' => $t] + $v, $tag); };
$ip(['step' => 'code', 'code' => $code], 'code');
$ip(['step' => 'database', 'baseURL' => 'http://127.0.0.1:8071/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'database');
$ip(['step' => 'migrate'], 'migrate');
$ip(['step' => 'admin', 'username' => 'zipadmin', 'email' => 'zipadmin@v8.test', 'password' => OPW, 'password_confirm' => OPW], 'admin');
$installed = is_file("$E/writable/rolewarden/installed.lock") && uid('zipadmin@v8.test');
check('E02', 'Installer V7 unchanged on the v8 zip: from zero to a first super admin, lock file written', (bool) $installed);
// the .env written by the installer now holds the database settings: point mail at Mailpit too
file_put_contents("$E/.env", file_get_contents("$E/.env") . "\n" . MAILENV8);
$c = $serveZ('E2');
$adm = c8('E-adm');
check('E03', 'The first super admin signs in', login8($adm, 'zipadmin@v8.test', OPW));
G($adm, 'demo', 'zip demo after installer');
check('E04', 'After the installer, before demo:install: /demo does not answer (not 200), no 500', $adm->status !== 200 && no500($adm), "{$adm->status}");
server_stop();
$S0 = fullstate();
$o = spark($E, ['demo:install'], ['demo.password' => DPW]);
check('E05', 'demo:install in the complete application (database settings from the installer\'s .env)', rc($o) === 0 && count(demo_ids()) === 3, short($o));
$c = $serveZ('E3');
$au = c8('E-au');
check('E06', 'The author signs in and /demo answers with the documents', login8($au, 'author@example.com', DPW) && ($v = view_demo($au))['status'] === 200 && $v['new'] && $v['showcase'] === []);
act($au, 'demo', ['title' => 'zip doc', 'body' => 'z'], 'demo/create', 'zip author creates');
check('E06b', 'The author creates a document in the complete application', (int) q1("SELECT COUNT(*) FROM demo_documents WHERE title='zip doc'") === 1);
server_stop();
$refs = demo_refs();
$o = spark($E, ['demo:uninstall']);
$cl = classify($S0, fullstate(), $refs, [uid('zipadmin@v8.test')]);
check('E07', 'demo:uninstall in the complete application: no residue, nothing lost', rc($o) === 0 && $cl['residue'] === [] && $cl['lost'] === [], json_encode($cl));
$LOGS_E = logs_of($E);
}

/* =================================================================================== F: documentation examples */
if (in_array('F', $PHASES, true)) {
echo "\n== F: every example of documentation/ tried on a copy of the test application (module alone)\n";
$F = app_copy('appF', $MOD, false);
base_install($F); $R = real_accounts();
// api.html: migration with own permissions
@mkdir("$F/app/Database/Migrations", 0777, true);
file_put_contents("$F/app/Database/Migrations/2026-10-10-120000_AddInvoicePermissions.php", <<<'PHP'
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvoicePermissions extends Migration
{
    private const PERMISSIONS = [
        'invoices.view' => 'See invoices',
        'invoices.approve' => 'Approve invoices',
    ];

    public function up(): void
    {
        $table = config('RoleWarden')->table('permissions');
        $now = date('Y-m-d H:i:s');

        foreach (self::PERMISSIONS as $slug => $description) {
            $this->db->table($table)->insert([
                'slug' => $slug,
                'area' => strstr($slug, '.', true),
                'description' => $description,
                'is_system' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table(config('RoleWarden')->table('permissions'))
            ->whereIn('slug', array_keys(self::PERMISSIONS))->delete();
    }
}
PHP);
// reports.view is used by the routes example; added by the same pattern
$o = sp8($F, ['migrate']);
check('F01', 'api.html migration "AddInvoicePermissions" copied as is: php spark migrate creates invoices.view and invoices.approve', rc($o) === 0 && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug IN ('invoices.view','invoices.approve') AND area='invoices' AND is_system=0") === 2, short($o));
$o = sp8($F, ['migrate:rollback']);
check('F01b', 'Its down() removes them (migrate:rollback of that batch)', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'invoices.%'") === 0, short($o));
sp8($F, ['migrate']);
q("INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES ('reports.view','reports','See reports',0,NOW(),NOW())");
foreach (['inv-viewer' => ['invoices.view'], 'inv-approver' => ['invoices.view', 'invoices.approve'], 'rep-viewer' => ['reports.view']] as $slug => $ps) {
    q("INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,NOW(),NOW())", [$slug, ucfirst($slug), 'docs test']);
    foreach ($ps as $p) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [role_id($slug), perm_id($p)]);
}
$u1 = make_owner('viewer@v8.test', OPW, 'v8viewer', 'inv-viewer');
$u3 = make_owner('approver2@v8.test', OPW, 'v8approver', 'inv-approver');
$u4 = make_owner('reports@v8.test', OPW, 'v8reports', 'rep-viewer');
$u2 = $R['real'];
// routes, controllers and views exactly as the documentation writes them
$rt = file_get_contents("$F/app/Config/Routes.php");
$rt .= <<<'PHP'

// verify-v8: examples of documentation/api.html, as written
$routes->get('invoices', 'Invoices::index', ['filter' => 'can:invoices.view']);
$routes->post('invoices/(:num)/approve', 'Invoices::approve/$1', ['filter' => 'can:invoices.approve']);

// Several permissions: any one of them lets the request through.
$routes->get('reports', 'Reports::index', ['filter' => 'can:reports.view,invoices.view']);
$routes->get('v8/user', 'V8Docs::user');
$routes->get('v8/helpers', 'V8Docs::helpers');
PHP;
file_put_contents("$F/app/Config/Routes.php", $rt);
file_put_contents("$F/app/Controllers/Invoices.php", "<?php\n\nnamespace App\\Controllers;\n\nclass Invoices extends BaseController\n{\n    protected \$helpers = ['rolewarden'];\n\n    public function index(): string\n    {\n        return view('invoices_v8');\n    }\n\n    public function approve(int \$id): string\n    {\n        return 'approved ' . \$id;\n    }\n}\n");
file_put_contents("$F/app/Controllers/Reports.php", "<?php\n\nnamespace App\\Controllers;\n\nclass Reports extends BaseController\n{\n    public function index(): string\n    {\n        return 'reports page';\n    }\n}\n");
file_put_contents("$F/app/Views/invoices_v8.php", "<p>invoices page</p>\n<form method=\"post\" action=\"/invoices/1/approve\"><?= csrf_field() ?>\n<?php if (can('invoices.approve')) : ?>\n  <button type=\"submit\">Approve</button>\n<?php endif ?>\n</form>\n<p>any=<?= can_any(['invoices.approve', 'reports.view']) ? 'yes' : 'no' ?> all=<?= can_all(['invoices.view', 'invoices.approve']) ? 'yes' : 'no' ?> perms=<?= esc(implode(',', permissions())) ?></p>\n");
file_put_contents("$F/app/Controllers/V8Docs.php", <<<'PHP'
<?php

namespace App\Controllers;

class V8Docs extends BaseController
{
    public function user()
    {
        $u = auth()->user();
        if ($u === null) {
            return $this->response->setJSON(['guest' => true]);
        }

        return $this->response->setJSON([
            'can' => auth()->user()->can('invoices.view'),
            'canEither' => auth()->user()->can('invoices.view', 'reports.view'),
            'hasPermission' => auth()->user()->hasPermission('invoices.view'),
            'canApprove' => auth()->user()->can('invoices.approve'),
            'inGroup' => auth()->user()->inGroup('inv-viewer'),
            'groups' => auth()->user()->getGroups(),
        ]);
    }

    public function helpers()
    {
        helper('rolewarden');

        return $this->response->setJSON(['can' => can('invoices.view'), 'any' => can_any(['invoices.view']), 'all' => can_all(['invoices.view']), 'permissions' => permissions()]);
    }
}
PHP);
@mkdir("$F/app/Commands", 0777, true);
file_put_contents("$F/app/Commands/V8Docs.php", <<<'PHP'
<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class V8Docs extends BaseCommand
{
    protected $group = 'v8';
    protected $name = 'v8:docs';

    public function run(array $params)
    {
        [$u1, $u2, $u3, $rid] = array_map('intval', $params);
        $rolewarden = service('rolewarden');
        $o = [];
        $o['can'] = [$rolewarden->can($u1, 'invoices.approve'), $rolewarden->can($u3, 'invoices.approve')];
        $o['canAny'] = $rolewarden->canAny($u1, ['invoices.view', 'reports.view']);
        $o['canAll'] = [$rolewarden->canAll($u1, ['invoices.view', 'invoices.approve']), $rolewarden->canAll($u3, ['invoices.view', 'invoices.approve'])];
        $o['permissions'] = $rolewarden->permissions($u3);
        try { $rolewarden->authorize($u1, 'invoices.approve'); $o['authorizeDenied'] = 'no exception'; } catch (\Throwable $e) { $o['authorizeDenied'] = get_class($e); }
        try { $rolewarden->authorize($u3, 'invoices.approve'); $o['authorizeGranted'] = 'ok'; } catch (\Throwable $e) { $o['authorizeGranted'] = get_class($e); }
        foreach (['invoices', 'invoices.*', 'Invoices.View'] as $bad) {
            try { $rolewarden->can($u1, $bad); $o['bad'][$bad] = 'accepted'; } catch (\Throwable $e) { $o['bad'][$bad] = get_class($e); }
        }
        $o['beforeAssign'] = $rolewarden->can($u2, 'invoices.view');
        try { \RoleWarden\Models\UserRoles::assign($u2, $rid); $o['staticAssign'] = 'ok'; } catch (\Throwable $e) { $o['staticAssign'] = get_class($e) . ': ' . $e->getMessage(); }
        if ($o['staticAssign'] !== 'ok') { model(\RoleWarden\Models\UserRoles::class)->assign($u2, $rid); }
        $o['afterAssign'] = $rolewarden->can($u2, 'invoices.view');
        $rolewarden->forgetUser($u2);
        $rolewarden->forgetRole($rid);
        $o['forgetOk'] = true;
        echo 'V8JSON' . json_encode($o) . "\n";
    }
}
PHP);
// events
$ev = file_get_contents("$F/app/Config/Events.php");
$ev .= "\n// verify-v8: the documented events\nEvents::on('rolewarden.activity', static function (array \$entry): void { @file_put_contents(WRITEPATH . 'v8-activity.jsonl', json_encode(\$entry) . \"\\n\", FILE_APPEND); });\nEvents::on('rolewarden.mail', static function (array \$message) { @file_put_contents(WRITEPATH . 'v8-mail.jsonl', json_encode(array_keys(\$message)) . \"\\n\", FILE_APPEND); });\n";
file_put_contents("$F/app/Config/Events.php", $ev);
// views.html: overrides, language lines, a new language, a theme of one's own
@mkdir("$F/app/Views/overrides/RoleWarden/Views/layouts", 0777, true);
file_put_contents("$F/app/Views/overrides/RoleWarden/Views/layouts/panel.php", "<!-- V8-LAYOUT-OVERRIDE -->\n" . file_get_contents("$MOD/src/Views/layouts/panel.php"));
@mkdir("$F/app/Views/overrides/RoleWarden/Views/auth", 0777, true);
file_put_contents("$F/app/Views/overrides/RoleWarden/Views/auth/login.php", "<!-- V8-LOGIN-OVERRIDE -->\n" . file_get_contents("$MOD/src/Views/auth/login.php"));
@mkdir("$F/app/Language/en", 0777, true);
file_put_contents("$F/app/Language/en/RoleWarden.php", "<?php\n\nreturn [\n    'panel' => [\n        'nav' => [\n            'users' => 'People',\n        ],\n    ],\n];\n");
@mkdir("$F/app/Language/fr", 0777, true);
$frArr = include "$MOD/src/Language/en/RoleWarden.php";
$frArr['panel']['nav']['users'] = 'Utilisateurs';
unset($frArr['panel']['nav']['roles']);
file_put_contents("$F/app/Language/fr/RoleWarden.php", "<?php\n\nreturn " . var_export($frArr, true) . ";\n");
file_put_contents("$F/app/Config/RoleWarden.php", "<?php\n\nnamespace Config;\n\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public array \$extraThemes = ['ocean' => 'Ocean'];\n    public ?string \$themeStylesheet = '/css/rolewarden-theme.css';\n}\n");
@mkdir("$F/public/css", 0777, true);
file_put_contents("$F/public/css/rolewarden-theme.css", ":root[data-rw-theme=\"ocean\"] {\n  --l-accent: #0b6e8a;\n  --d-accent: #5cc3dd;\n}\n");
write_env($F, 'development', "rolewarden.lockAttempts = 8\n");

$c = serve8($F, 'F');
$v1 = c8('F-viewer'); $v2 = c8('F-none'); $v3 = c8('F-approver'); $v4 = c8('F-reports'); $gu = c8('F-guest'); $ow = c8('F-owner');
login8($v1, 'viewer@v8.test', OPW); login8($v2, 'realuser@v8.test', OPW); login8($v3, 'approver2@v8.test', OPW); login8($v4, 'reports@v8.test', OPW); login8($ow, 'owner@v8.test', OPW);
$h = G($v1, 'invoices', 'viewer invoices');
check('F02', "Route filter can:invoices.view: a user holding it gets the page", $v1->status === 200 && str_contains($h, 'invoices page'), "{$v1->status}");
G($v2, 'invoices', 'none invoices');
$loc = $v2->location; $after = $loc ? G($v2, abs_url($v2->url, $loc), 'after deny') : '';
check('F03', "Route filter: a signed-in user without it is redirected (Shield's permissionDeniedRedirect) with an error message", in_array($v2->status, [200, 302, 303], true) && $loc !== '' && ! str_contains($h = $v2->body, 'invoices page'), "status redirect to $loc");
info('Denied message shown after the redirect: ' . short(text_of($after), 160));
G($gu, 'invoices', 'guest invoices');
check('F03b', 'Route filter alone (no session filter): a guest does not get the page', $gu->status !== 200 || ! str_contains($gu->body, 'invoices page'), "{$gu->status} {$gu->location}");
info("Guest on a can-only route: {$gu->status} -> {$gu->location} (documentation: put Shield's session filter before it)");
G($v4, 'reports', 'reports-only user on reports'); $a = $v4->status === 200 && str_contains($v4->body, 'reports page');
G($v1, 'reports', 'invoices user on reports'); $b = $v1->status === 200;
G($v2, 'reports', 'none on reports'); $cc = $v2->status !== 200;
check('F04', "can:reports.view,invoices.view: any one of the permissions lets the request through, none refuses", $a && $b && $cc);
$t = csrf_of(G($v1, 'invoices')); P($v1, 'invoices/1/approve', ['csrf_test_name' => $t], 'viewer approves'); $a = $v1->status !== 200;
$t = csrf_of(G($v3, 'invoices')); P($v3, 'invoices/1/approve', ['csrf_test_name' => $t], 'approver approves'); $b = $v3->status === 200 && str_contains($v3->body, 'approved 1');
check('F05', 'POST route with can:invoices.approve: refused without, accepted with', $a && $b, "viewer {$v1->status}, approver {$v3->status}");
$h1 = G($v1, 'invoices'); $h3 = G($v3, 'invoices');
check('F06', 'View helper example: the Approve button only for who holds invoices.approve', ! str_contains($h1, '>Approve</button>') && str_contains($h3, '>Approve</button>'));
check('F06b', 'can_any(), can_all(), permissions() (sorted) answer as documented', str_contains($h1, 'any=no all=no perms=invoices.view') && str_contains($h3, 'any=yes all=yes perms=invoices.approve,invoices.view'), short(text_of($h1)) . ' / ' . short(text_of($h3)));
$j = json_decode(G($gu, 'v8/helpers', 'guest helpers'), true);
check('F06c', 'Helpers for a guest: everything denied, no permission', $j === ['can' => false, 'any' => false, 'all' => false, 'permissions' => []], json_encode($j));
$j = json_decode(G($v1, 'v8/user', 'viewer user'), true);
check('F07', "Shield's entity: can('invoices.view'), can(a, b), hasPermission() answered from the database", ($j['can'] ?? null) === true && ($j['canEither'] ?? null) === true && ($j['hasPermission'] ?? null) === true && ($j['canApprove'] ?? null) === false, json_encode($j));
check('F07b', 'inGroup() and getGroups() read the user\'s roles', ($j['inGroup'] ?? null) === true && in_array('inv-viewer', $j['groups'] ?? [], true), json_encode($j));
$j = json_decode(G($v4, 'v8/user', 'reports user'), true);
check('F07c', "can('invoices.view', 'reports.view') is true when any one is granted", ($j['canEither'] ?? null) === true && ($j['can'] ?? null) === false, json_encode($j));
$h = G($ow, 'rolewarden/users', 'owner users F');
check('F08', 'View override of layouts/panel.php under app/Views/overrides/RoleWarden/Views/ is used', str_contains($h, 'V8-LAYOUT-OVERRIDE'));
$h = G($gu, 'login', 'login override');
check('F08b', 'View override of auth/login.php is used for the sign-in page', str_contains($h, 'V8-LOGIN-OVERRIDE'));
$h = G($ow, 'rolewarden/users', 'owner nav');
check('F09', "Language line override (app/Language/en/RoleWarden.php, panel.nav.users => 'People') replaces that line only", str_contains(text_of($h), 'People') && str_contains(text_of($h), 'Roles') && ! preg_match('/\b01Users\b/', text_of($h)), short(text_of($h), 200));
$lf = form_with(G($ow, 'rolewarden/profile', 'profile F'), 'language');
$opts = []; foreach (dom($ow->body)->query('//select[@name="language"]/option') as $op) $opts[$op->getAttribute('value')] = trim($op->textContent);
check('F10', 'A language added as app/Language/fr/RoleWarden.php appears at once in the profile, named in its own language', isset($opts['fr']) && preg_match('/fran[cç]ais/iu', $opts['fr']), json_encode($opts));
if ($lf) P($ow, $lf['action'], array_replace($lf['fields'], ['language' => 'fr']), 'owner french');
$h = G($ow, 'rolewarden/users', 'owner users fr');
check('F10b', 'Chosen: the panel uses the French file (lang="fr", Utilisateurs) and lines left out fall back to English (Roles)', str_contains($h, 'lang="fr"') && str_contains(text_of($h), 'Utilisateurs') && str_contains(text_of($h), 'Roles'), short(text_of($h), 200));
$lf = form_with(G($ow, 'rolewarden/profile', 'profile F2'), 'language');
if ($lf) P($ow, $lf['action'], array_replace($lf['fields'], ['language' => 'en']), 'owner english');
$h = G($ow, 'rolewarden/appearance', 'appearance');
check('F11', "extraThemes ['ocean' => 'Ocean'] is offered on the Appearance screen", str_contains(text_of($h), 'Ocean') && str_contains($h, 'ocean'));
$hd = html_entity_decode($h, ENT_QUOTES | ENT_HTML5);
preg_match_all('#<link[^>]+href="([^"]+)"#', $hd, $lk);
check('F11b', 'themeStylesheet is loaded by the panel after its own stylesheet', (bool) preg_match('#href="[^"]*/css/rolewarden-theme\.css"#', $hd) && strpos($hd, 'rolewarden-theme.css') > strpos($hd, 'panel.css'), json_encode($lk[1]));
$css = c8('F-css'); $css->get('css/rolewarden-theme.css'); $pc = c8('F-pcss'); $pc->get('rolewarden/assets/css/panel.css');
check('F12', 'Troubleshooting: the panel stylesheet is served at /rolewarden/assets/css/panel.css (200, text/css)', $pc->status === 200 && str_contains($pc->headers['content-type'] ?? '', 'text/css'), "{$pc->status} " . ($pc->headers['content-type'] ?? ''));
$h = G($ow, 'rolewarden/settings', 'settings');
$sel = []; $xps = dom($h); foreach ($xps->query('//select') as $sx) foreach ($xps->query('.//option[@selected]', $sx) as $op) $sel[$sx->getAttribute('name')] = $op->getAttribute('value');
$lockField = array_filter($sel, fn ($v, $k) => stripos($k, 'attempt') !== false || stripos($k, 'lock') !== false, ARRAY_FILTER_USE_BOTH);
info('Settings selects: ' . json_encode($sel));
check('F13', "configuration.html: rolewarden.lockAttempts = 8 in .env is the value the Settings screen starts from", in_array('8', $lockField, true) || (bool) preg_match('/name="[^"]*attempt[^"]*"[^>]*value="8"/i', $h), json_encode($lockField));
// password change of a user by the owner -> mail event keys
$uidV = $u1;
$hh = G($ow, "rolewarden/users/$uidV/edit", 'edit viewer');
$ef = form_with($hh, 'password');
if ($ef) { $vals = array_replace($ef['fields'], ['password' => 'Changed-Lantern-7781']); foreach ($ef['types'] as $k => $tp) if (str_contains($k, 'confirm')) $vals[$k] = 'Changed-Lantern-7781'; P($ow, $ef['action'], $vals, 'owner sets viewer password'); }
usleep(1500000);
$mk = array_filter(array_map('json_decode', file("$F/writable/v8-mail.jsonl", FILE_IGNORE_NEW_LINES) ?: []));
$keys = $mk ? (array) reset($mk) : [];
check('F14', 'rolewarden.mail fires with the message keys the documentation lists (to, subject, view, data, locale)', $keys && ! array_diff(['to', 'subject', 'view', 'data', 'locale'], $keys), json_encode($keys));
panelp($ow, 'rolewarden/roles/' . role_id('inv-viewer'), 'rolewarden/roles/' . role_id('inv-viewer') . '/permissions', ['permission_id' => perm_id('reports.view'), 'granted' => 1], 'owner grants reports.view to inv-viewer');
$ak = array_values(array_filter(array_map(fn ($l) => json_decode($l, true), file("$F/writable/v8-activity.jsonl", FILE_IGNORE_NEW_LINES) ?: [])));
$withDetails = array_values(array_filter($ak, fn ($e) => ($e['details'] ?? null) !== null));
$first = $withDetails[0] ?? ($ak[0] ?? []);
$nulls = array_values(array_unique(array_map(fn ($e) => $e['action'], array_filter($ak, fn ($e) => ($e['details'] ?? null) === null))));
if ($nulls) info('rolewarden.activity entries whose details are null (no JSON): ' . json_encode($nulls));
check('F15', "rolewarden.activity fires with the row: 'action' like role.updated / user.updated, 'details' as JSON", isset($first['action'], $first['details']) && preg_match('/^[a-z]+\.[a-z_]+$/', (string) $first['action']) && json_decode((string) $first['details']) !== null, json_encode($first));
server_stop();
$o = sp8($F, ['v8:docs', (string) $u1, (string) $u2, (string) $u3, (string) role_id('inv-viewer')]);
$j = preg_match('/V8JSON(\{.*\})/', $o, $m) ? json_decode($m[1], true) : null;
info('service(\'rolewarden\') outside a request: ' . json_encode($j));
check('F16', "service('rolewarden'): can(), canAny(), canAll(), permissions() for any user, outside a request", $j && $j['can'] === [false, true] && $j['canAny'] === true && $j['canAll'] === [false, true] && $j['permissions'] === ['invoices.approve', 'invoices.view'], short($o));
check('F17', 'authorize() throws RoleWarden\Authorization\AuthorizationException when denied and passes when granted', ($j['authorizeDenied'] ?? '') === 'RoleWarden\Authorization\AuthorizationException' && ($j['authorizeGranted'] ?? '') === 'ok', json_encode($j));
check('F18', 'A slug not in area.action form throws InvalidArgumentException (no wildcards in checks)', ($j['bad']['invoices'] ?? '') === 'InvalidArgumentException' && ($j['bad']['invoices.*'] ?? '') === 'InvalidArgumentException', json_encode($j['bad'] ?? null));
info('Upper-case slug Invoices.View: ' . ($j['bad']['Invoices.View'] ?? '?'));
check('F19', 'UserRoles assign() (called on the model instance) takes effect at once; forgetUser() and forgetRole() exist', ($j['beforeAssign'] ?? null) === false && ($j['afterAssign'] ?? null) === true && ($j['forgetOk'] ?? false) === true, json_encode($j));
check('F19b', 'api.html writes "RoleWarden\Models\UserRoles::assign($userId, $roleId)": copied as written, the call works', ($j['staticAssign'] ?? '') === 'ok', $j['staticAssign'] ?? 'no result');
// installation.html: first super admin by hand
$p = proc_open(['php', 'spark', 'shield:user', 'create', '-n', 'admin', '-e', 'you@example.com'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, $F, array_merge(array_diff_key(getenv(), ['RW_DB_USERNAME' => 1, 'RW_DB_PASSWORD' => 1]), $DBENV), ['bypass_shell' => true]);
fwrite($pp[0], "Byhand-Lantern-4410\nByhand-Lantern-4410\n"); fclose($pp[0]);
$out = stream_get_contents($pp[1]) . stream_get_contents($pp[2]); proc_close($p);
$p = proc_open(['php', 'spark', 'shield:user', 'activate', '-e', 'you@example.com'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, $F, array_merge(array_diff_key(getenv(), ['RW_DB_USERNAME' => 1, 'RW_DB_PASSWORD' => 1]), $DBENV), ['bypass_shell' => true]);
fwrite($pp[0], "y\n"); fclose($pp[0]);
$o2 = stream_get_contents($pp[1]) . stream_get_contents($pp[2]); proc_close($p);
info('shield:user activate asks for a confirmation, answered y: ' . short($o2, 200));
q("INSERT INTO acl_user_roles (user_id, role_id) SELECT i.user_id, r.id FROM auth_identities i JOIN acl_roles r ON r.slug = 'super-admin' WHERE i.type = 'email_password' AND i.secret = 'you@example.com'");
$c = serve8($F, 'F2');
$yu = c8('F-you');
check('F20', 'installation.html, super admin by hand: shield:user create + activate + the INSERT given, then the panel opens', login8($yu, 'you@example.com', 'Byhand-Lantern-4410') && G($yu, 'rolewarden/roles') !== '' && $yu->status === 200, short(redact($out)) . ' / ' . short($o2));
server_stop();
// configuration.html: tablePrefix in .env, before the migrations
empty_db();
write_env($F, 'development', "rolewarden.tablePrefix = 'myapp_'\n");
foreach ([['migrate', '-n', 'CodeIgniter\Settings'], ['migrate', '-n', 'CodeIgniter\Shield'], ['migrate', '-n', 'RoleWarden']] as $a) sp8($F, $a);
$tb = tables();
check('F21', "rolewarden.tablePrefix = 'myapp_' in .env: the module's tables are created with that prefix", in_array('myapp_roles', $tb, true) && in_array('myapp_permissions', $tb, true) && ! array_filter($tb, fn ($t) => str_starts_with($t, 'acl_')), json_encode($tb));
write_env($F, 'development');
$LOGS_F = logs_of($F);
}

/* =================================================================================== G: the demo in Italian */
if (in_array('G', $PHASES, true)) {
echo "\n== G: the demo in Italian\n";
$Gp = app_copy('appG', $MODFULL, true, 'development', "demo.showcase = true\n");
base_install($Gp); real_accounts();
sp8($Gp, ['demo:install'], ['demo.password' => DPW]);
$ids = demo_ids();
$c = serve8($Gp, 'G');
$en = include "$DEMO/src/Language/en/Demo.php"; $it = include "$DEMO/src/Language/it/Demo.php";
$flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flat($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
$fe = $flat($en); $fi = $flat($it);
$au = c8('G-au'); login8($au, 'author@example.com', DPW);
$lf = form_with(G($au, 'rolewarden/profile'), 'language');
P($au, $lf['action'], array_replace($lf['fields'], ['language' => 'it']), 'author italian');
act($au, 'demo', ['title' => 'Bozza di prova', 'body' => 'Testo'], 'demo/create', 'it create');
$after = G($au, 'demo', 'it demo after create');
preg_match_all('/&quot;message&quot;&#x3A;&quot;(.*?)&quot;/', $after, $mm);
$msgs = array_map(fn ($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5), $mm[1]);
$pages = ['demo' => $after, 'demo/create' => G($au, 'demo/create', 'it create form')];
$did = (int) q1("SELECT id FROM demo_documents WHERE title='Bozza di prova'");
$pages["demo/$did/edit"] = G($au, "demo/$did/edit", 'it edit');
$ap = c8('G-ap'); login8($ap, 'approver@example.com', DPW);
$lf = form_with(G($ap, 'rolewarden/profile'), 'language');
P($ap, $lf['action'], array_replace($lf['fields'], ['language' => 'it']), 'approver italian');
$pages['demo (approver)'] = G($ap, 'demo', 'it approver demo');
$seed = []; foreach (docs() as $d) { $seed[] = $d['title']; $seed[] = $d['body']; }
$seed = array_merge($seed, ['Reader', 'Author', 'Approver'], array_column(q('SELECT username FROM users'), 'username'));
$eng = [];
foreach ($pages as $u => $h) {
    $t = text_of($h);
    $attrs = ''; foreach (dom($h)->query('//@aria-label|//@title|//@placeholder|//@onsubmit') as $at) $attrs .= ' ' . $at->nodeValue;
    $tt = ' ' . str_replace($seed, ' ', $t . ' ' . html_entity_decode($attrs, ENT_QUOTES | ENT_HTML5)) . ' ';
    foreach ($fe as $k => $v) if (($fi[$k] ?? '') !== $v && strlen($v) > 3 && str_contains($tt, preg_replace('/\{\d+\}.*$/', '', $v))) $eng[] = "$u: $v";
    foreach (['Edit', 'Delete', 'Save', 'Cancel', 'Dismiss', 'Documents', 'Admin panel', 'Approved', 'Submitted', 'Draft', 'Title', 'Owner', 'Status', 'Actions', 'This cannot be undone', 'you', 'Sign out', 'Profile', 'Light', 'Dark', 'System', 'Saved', 'Error:', 'Warning:'] as $w) if (preg_match('/(?<![\p{L}])' . preg_quote($w, '/') . '(?![\p{L}])/u', $tt)) $eng[] = "$u: $w";
    if (! str_contains($h, 'lang="it"')) $eng[] = "$u: html lang not it";
}
$eng = array_values(array_unique($eng));
check('G01', 'Demo in Italian (author and approver who chose Italian): no English interface text left on list, create, edit pages, attributes included', $eng === [], json_encode($eng));
check('G02', 'Toast after the creation in Italian', in_array($fi['created'], $msgs, true), json_encode($msgs));
info('Italian demo list text: ' . short(text_of($pages['demo (approver)']), 600));
amb('G03', 'Seeded data stay in English in the Italian demo: role names (Reader, Author, Approver, also on the showcase buttons), permission descriptions and the four sample documents', 'Same question as V7 A1 (seeded names and descriptions of roles and permissions), still open; the sample documents of the demo seed are the same kind of data. Not counted as defects.');
server_stop();
$LOGS_G = logs_of($Gp);
}

/* =================================================================================== L: logs and publication */
echo "\n== L: PHP errors and warnings, application logs at threshold 9, every response\n";
$leak = []; $err = [];
foreach ($SEEN as [$tag, $st, $url, $body]) {
    if ($st >= 500 && ! str_contains($tag, 'db error') && ! str_contains($tag, 'after uninstall')) $err[] = "$tag ($st)";
    if (leaks_sql($body) && ! str_contains($tag, '(dev)')) $leak[] = "$tag ($st)";
}
check('L01', 'No HTTP 500 in the demo paths (apart from the induced database failure)', $err === [], json_encode(array_slice($err, 0, 10)));
check('L02', 'No SQL or driver detail in any response (development pages after removal judged apart, A16d)', $leak === [], json_encode(array_slice($leak, 0, 10)));
$srv = [];
foreach (array_unique($SERVERLOGS) as $f) foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $l) if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal|Parse)|Warning:|Deprecated:/', $l)) $srv[] = basename($f) . ': ' . $l;
check('L03', 'No PHP warning, notice or deprecation in the server output (error_reporting=-1)', $srv === [], json_encode(array_slice($srv, 0, 8)));
foreach (['A' => $LOGS_A ?? null, 'B' => $LOGS_B ?? null, 'C' => $LOGS_C ?? null, 'D' => $LOGS_D ?? null, 'E' => $LOGS_E ?? null, 'F' => $LOGS_F ?? null, 'G' => $LOGS_G ?? null] as $k => $lines) {
    if ($lines === null) continue;
    file_put_contents("$WORK/verify-v8.applog-$k.log", implode("\n", $lines) . "\n");
    $bad = bad_log_lines($lines);
    $ind = array_filter($bad, fn ($l) => str_contains($l, 'demo_documents') || preg_match('/Table .*doesn.t exist|demo_documents_v8hidden/', $l) || (in_array($k, ['B', 'C'], true) && (str_contains($l, 'SecurityException: The action you requested is not allowed') || str_contains($l, 'disallowed characters'))));
    $other = array_values(array_diff($bad, $ind));
    info("log $k: " . count($lines) . ' lines, ' . count($bad) . ' at WARNING or above, ' . count($ind) . ' from the failures the suite caused (table renamed, CSRF refusals asked for in B and C)');
    check("L04$k", "Application log ($k) at threshold 9: no warning or error other than the induced ones", $other === [], json_encode(array_slice(array_map(fn ($l) => substr($l, 0, 220), $other), 0, 6)));
}
$mp = mailpit('GET', 'messages?limit=500');
$badMail = []; foreach (($mp['messages'] ?? []) as $m) foreach (array_merge($m['To'] ?? [], $m['Cc'] ?? [], $m['Bcc'] ?? []) as $a) if (! preg_match('/@(example\.com|[a-z0-9.-]+\.test)$/i', $a['Address'])) $badMail[] = $a['Address'];
check('L05', 'Every message caught by Mailpit went to @example.com or *.test addresses (' . ($mp['messages_count'] ?? 0) . ' messages)', $badMail === [], json_encode($badMail));

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "\nTOTAL: $pass PASS, $fail FAIL, " . count($GLOBALS['ambiguities']) . " AMB\n";
foreach ($GLOBALS['results'] as $r) if (! $r[2]) echo "FAILED: {$r[0]} {$r[1]}\n";
exit($fail ? 1 : 0);
