<?php
/**
 * U1 copy of verify-s3.php (Collaudatore ad Hoc): unchanged phases, outputs renamed verify-u1.s3.*, and the U1 phases of
 * verify-u1.s3phases.php (UPG, RB, OV, NEW) run between the installer (I) and the panel in use (U).
 *
 * S3 "versione 1.0.0 definitiva" black-box suite (Collaudatore ad Hoc, Claude), commit 26784b6 and the final zips
 * build/rolewarden-{app,module,demo}-1.0.0.zip. Copy of verify-s2.php (phases I, U, E, D, S, T, K, L unchanged apart
 * from the names s2 -> s3 of the test accounts and outputs), plus three phases for what 1.0.0 added to the documentation:
 *   W   troubleshooting.html "In development every page answers 500 before the tables exist" (module in a new app)
 *   N   installation.html#index-page: app.indexPage = '' with Apache + mod_rewrite, no index.php, no broken link
 *   X2  installation.html#proxy with the 1.0.0 nginx lines (X-Forwarded-For $remote_addr): a header written by the
 *       visitor neither changes the recorded address nor gets round the per-IP sign-in limit
 * Assertions only from docs/SPEC.md, README, CHANGELOG, documentation/ and the HTML a browser receives.
 * Database: rolewarden_test on 127.0.0.1:3317 only. Mail only to Mailpit. Credentials only from RW_DB_USERNAME / RW_DB_PASSWORD.
 *
 * Usage: php verify-s3.php key=value ...   (same keys as verify-s2.php; phases also W, N, X2)
 */
declare(strict_types=1);

require __DIR__ . '/verify-v8.lib.php';

$O = ['phases' => 'I,U,E,D,S,T,L', 'env' => 'development', 'dbhost' => 'host.docker.internal', 'dbport' => '3317', 'dbname' => 'rolewarden_test', 'direct' => '', 'install' => 'browser', 'mode' => 'app', 'showcase' => '0', 'dbct' => '', 'appdir' => '/var/www/app', 'label' => 'run'];
foreach (array_slice($argv, 1) as $a) if (preg_match('/^(\w+)=(.*)$/s', $a, $m)) $O[$m[1]] = $m[2];
foreach (['base', 'ct'] as $k) if (empty($O[$k])) exit("missing $k=\n");
define('BASE', $O['base']);
define('CT', $O['ct']);
define('APPD', $O['appdir']);
$PHASES = explode(',', $O['phases']);
$OWNDB = $O['dbname'] === 'rolewarden_test' && $O['dbport'] === '3317';
if ($OWNDB && $O['install'] === 'browser' && tables() !== []) exit("ABORT: rolewarden_test is not empty at start\n");
if ((mailpit('GET', 'messages')['messages_count'] ?? -1) !== 0) exit("ABORT: Mailpit not empty at start\n");
info('S3 ' . $O['label'] . ': base=' . BASE . ' container=' . CT . ' phases=' . $O['phases'] . ' env=' . $O['env'] . ' mode=' . $O['mode']);

const OPW = 'Quartz-Lantern-9183';
const UPW = 'Maple-Harbour-7314';
const DPW = 'Demo-Pass-8437-Qx';
const AJAX = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
const OWNER = 'owner@s3.test';
$SEEN = [];
$REDIRS = [];   // every Location received: [tag, absolute]
$P = parse_url(BASE);
define('ORIGIN', $P['scheme'] . '://' . $P['host'] . (isset($P['port']) ? ':' . $P['port'] : ''));

/* ------------------------------------------------------------------ helpers */
function seen(Client $c, string $tag): string
{
    $GLOBALS['SEEN'][] = [$tag, $c->status, $c->url, $c->body, $c->headers['content-type'] ?? ''];
    if ($c->location !== '') $GLOBALS['REDIRS'][] = [$tag, resolve($c->url, $c->location)];
    return $c->body;
}
function G(Client $c, string $u, string $tag = ''): string { $c->get($u); return seen($c, $tag ?: "GET $u"); }
function P(Client $c, string $u, array $d, string $tag = '', array $h = []): string { $c->req('POST', $u, $d, $h); return seen($c, $tag ?: "POST $u"); }
function follow(Client $c, string $tag = 'follow'): string { for ($i = 0; $i < 6 && in_array($c->status, [301, 302, 303, 307], true); $i++) G($c, resolve($c->url, $c->location), $tag); return $c->body; }
function short(string $s, int $n = 400): string { $s = trim(preg_replace('/\s+/', ' ', $s)); return strlen($s) > $n ? substr($s, 0, $n) . '...' : $s; }
function phperr(string $s): bool { return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|ErrorException|Uncaught|TypeError|ArgumentCountError)/', $s); }
function no500(Client $c): bool { return $c->status < 500 && ! leaks_sql($c->body) && ! phperr(text_of($c->body)); }
function c(string $name): Client { return new Client(BASE, $name); }
function step_of2(string $h): string { $f = form_with($h, 'step'); return $f['fields']['step'] ?? 'none'; }
/** RFC 3986 reference resolution. */
function resolve(string $base, string $ref): string
{
    $ref = html_entity_decode(trim($ref), ENT_QUOTES | ENT_HTML5);
    if ($ref === '') return $base;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $ref)) return $ref;
    $b = parse_url($base);
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (str_starts_with($ref, '//')) return $b['scheme'] . ':' . $ref;
    $bpath = $b['path'] ?? '/';
    if ($ref[0] === '#') return preg_replace('/#.*$/', '', $base) . $ref;
    if ($ref[0] === '?') return $origin . $bpath . $ref;
    $path = $ref[0] === '/' ? $ref : preg_replace('#/[^/]*$#', '/', $bpath) . $ref;
    $q = '';
    if (preg_match('/^([^?#]*)([?#].*)$/', $path, $m)) { $path = $m[1]; $q = $m[2]; }
    $out = [];
    foreach (explode('/', $path) as $i => $seg) {
        if ($seg === '..') { if (count($out) > 1) array_pop($out); continue; }
        if ($seg === '.') continue;
        $out[] = $seg;
    }
    $p = implode('/', $out);
    if (preg_match('#/\.\.?$#', $path)) $p .= '/';
    return $origin . $p . $q;
}
function inside(string $abs): bool { return str_starts_with($abs, BASE) || rtrim(BASE, '/') === $abs; }
/** Every URL a page refers to (links, assets, forms, buttons, data attributes, meta refresh). */
function page_urls(string $h, string $url): array
{
    $h = preg_replace('/<div id="debug-bar".*$/s', '', $h);
    $h = preg_replace('/<script[^>]*(debugbar|toolbar)[^>]*>.*?<\/script>/is', '', $h);
    $h = preg_replace('#<script[^>]*>\s*document\.getElementById\(\'debugbar_loader\'\).*?</script>#s', '', $h);
    $o = [];
    $xp = dom($h);
    foreach ($xp->query('//@href|//@src|//@action|//@formaction|//@data-url|//@data-href|//@data-action|//@poster') as $a) {
        $v = trim($a->nodeValue);
        if ($v === '' && $a->nodeName === 'action') { $o[] = $url; continue; }
        if ($v === '' || preg_match('#^(javascript:|mailto:|tel:|data:|\#)#i', $v)) continue;
        $o[] = resolve($url, $v);
    }
    foreach ($xp->query('//meta[@http-equiv]') as $m) if (preg_match('/url=(.+)$/i', $m->getAttribute('content'), $mm)) $o[] = resolve($url, $mm[1]);
    foreach ($xp->query('//@*[starts-with(name(),"data-")]') as $a) if (preg_match('#^(https?://|/)#', $a->nodeValue)) $o[] = resolve($url, $a->nodeValue);
    // absolute URLs written in inline scripts / JSON islands
    if (preg_match_all('#https?:\\\\?/\\\\?/[A-Za-z0-9.:-]+[^"\'\s<>]*#', $h, $mm)) foreach ($mm[0] as $u) $o[] = stripslashes($u);
    return array_values(array_unique($o));
}
function outside(array $urls): array { return array_values(array_filter($urls, fn ($u) => ! inside($u) && ! str_starts_with($u, 'http://www.w3.org/') && ! str_starts_with($u, 'https://github.com/kint-php/kint'))); }
/** docker exec in the container under test. */
function dx(string $cmd, ?string $stdin = null, string $user = 'www-data', array $env = []): array
{
    $a = ['docker', 'exec', '-i', '-u', $user];
    foreach ($env as $k => $v) { $a[] = '-e'; $a[] = "$k=$v"; }
    $a = array_merge($a, [CT, 'sh', '-c', $cmd]);
    $p = proc_open($a, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, null, null, ['bypass_shell' => true]);
    if ($stdin !== null) fwrite($pp[0], $stdin);
    fclose($pp[0]);
    $o = stream_get_contents($pp[1]); $e = stream_get_contents($pp[2]);
    $rc = proc_close($p);
    return [$rc, $o . $e];
}
function dcat(string $path): string { [$rc, $o] = dx('cat ' . escapeshellarg($path) . ' 2>/dev/null'); return $rc === 0 ? $o : ''; }
function dexists(string $path): bool { return dx('test -e ' . escapeshellarg($path))[0] === 0; }
function spark2(array $args, array $env = []): array { return dx('cd ' . APPD . ' && php spark ' . implode(' ', array_map('escapeshellarg', $args)), null, 'www-data', $env); }
function env_map(string $s): array
{
    $o = [];
    foreach (preg_split('/\R/', $s) as $l) if (preg_match('/^\s*([A-Za-z_.]+)\s*=\s*(.*)$/', $l, $m)) $o[$m[1]] = trim($m[2], " \t\"'");
    return $o;
}
function env_add(string $lines): void { dx('cat >> ' . APPD . '/.env', "\n" . $lines . "\n"); }
function role_id(string $slug): int { return (int) q1('SELECT id FROM acl_roles WHERE slug=?', [$slug]); }
function perm_id(string $slug): int { return (int) q1('SELECT id FROM acl_permissions WHERE slug=?', [$slug]); }
function login2(Client $c, string $email, string $pw, bool $remember = false, array $hdr = []): bool
{
    $c->extra = array_merge($c->extra, $hdr);
    $h = G($c, 'login', "login form $email");
    $f = form_with($h, 'password');
    if (! $f) return false;
    $v = array_replace($f['fields'], ['email' => $email, 'password' => $pw]);
    if ($remember) $v['remember'] = 'on'; else unset($v['remember']);
    P($c, resolve($c->url, $f['action']), $v, "login $email");
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, 'login');
}
function panelp(Client $c, string $page, string $url, array $data, string $tag, bool $ajax = true): string
{
    $t = csrf_of(G($c, $page, "token $page"));
    return P($c, $url, ['csrf_test_name' => $t] + $data, $tag, $ajax ? AJAX : []);
}
function form_action(string $h, string $suffixRe): ?array { foreach (forms($h) as $f) if (preg_match($suffixRe, $f['action'])) return $f; return null; }
function screens(int $uid, int $rid): array
{
    return ['rolewarden/users', 'rolewarden/users/create', 'rolewarden/users/import', "rolewarden/users/$uid", "rolewarden/users/$uid/edit",
        'rolewarden/roles', 'rolewarden/roles/create', "rolewarden/roles/$rid", "rolewarden/roles/$rid/edit", 'rolewarden/permissions',
        'rolewarden/settings', 'rolewarden/activity', 'rolewarden/appearance', 'rolewarden/profile', 'rolewarden/sessions'];
}
function wait_mail(int $n, int $tries = 60): array
{
    for ($i = 0; $i < $tries; $i++) { $m = mailpit('GET', 'messages?limit=200'); if (($m['messages_count'] ?? 0) >= $n) break; usleep(250000); }
    return $m['messages'] ?? [];
}
function mail_to(string $addr): array
{
    $o = [];
    foreach (mailpit('GET', 'messages?limit=200')['messages'] ?? [] as $m) {
        $to = array_map(fn ($a) => strtolower($a['Address']), array_merge($m['To'] ?? [], $m['Bcc'] ?? [], $m['Cc'] ?? []));
        if (in_array(strtolower($addr), $to, true)) $o[] = mailpit('GET', 'message/' . $m['ID']);
    }
    return $o;
}
function mail_urls(array $m): array
{
    $o = [];
    if (preg_match_all('#https?://[^\s"\'<>]+#', html_entity_decode($m['HTML'] ?? '', ENT_QUOTES | ENT_HTML5) . ' ' . ($m['Text'] ?? ''), $mm)) $o = array_values(array_unique($mm[0]));
    return $o;
}
function uptime_logs(): array
{
    $lines = [];
    [$rc, $o] = dx('cat ' . APPD . '/writable/logs/*.log 2>/dev/null');
    foreach (preg_split('/\R/', $o) as $l) if ($l !== '') $lines[] = $l;
    return $lines;
}
/** Every row of every table (volatile columns dropped) and the files of writable/rolewarden: the "nothing changed" state. */
function st(): string
{
    $o = '';
    foreach (tables() as $t) {
        foreach (q("SELECT * FROM `$t`") as $r) {
            foreach (array_keys($r) as $k) if (preg_match('/last_|updated_at|expires|_seen|activity_at|^date$/', $k)) unset($r[$k]);
            if ($t === 'auth_logins' || $t === 'ci_sessions') continue;
            $o .= $t . json_encode($r) . "\n";
        }
    }
    [$rc, $f] = dx('cd ' . APPD . '/writable/rolewarden 2>/dev/null && for f in *; do echo "$f"; case "$f" in *.json|*.css) cat "$f";; esac; done');
    return md5($o . $f);
}
function settings_form(string $h): ?array { foreach (forms($h) as $f) if ($f['method'] === 'post' && preg_match('#rolewarden/settings/?$#', $f['action'] ?: 'rolewarden/settings') && count($f['types']) > 3) return $f; return null; }
function bad_lines(array $lines, array $allow = []): array
{
    $bad = [];
    foreach ($lines as $l) {
        if (! preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)) continue;
        foreach ($allow as $re) if (preg_match($re, $l)) continue 2;
        $bad[] = $l;
    }
    return $bad;
}

/* ================================================================== W: troubleshooting, 500 in development before the tables */
if (in_array('W', $PHASES, true)) {
    echo "\n== W: troubleshooting.html, an existing application in development before the tables exist\n";
    $envNow = env_map(dcat(APPD . '/.env'));
    info('CI_ENVIRONMENT of the new application before the installer: ' . ($envNow['CI_ENVIRONMENT'] ?? 'absent') . '; tables: ' . json_encode(tables()));
    dx("sed -i 's/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = development/' " . APPD . '/.env');
    $w = c('W-dev');
    $wh = G($w, 'rolewarden/install', 'W install in development');
    $wst = $w->status;
    $wh2 = G(c('W-dev-home'), '', 'W home in development');
    info("W development before the tables: rolewarden/install {$wst}, home " . $GLOBALS['SEEN'][count($GLOBALS['SEEN']) - 1][1]);
    $wlog = implode("\n", array_slice(uptime_logs(), -40));
    check('W01', 'Symptom as troubleshooting.html describes it: in development, before the tables, the installer page answers 500', $wst === 500, "status $wst " . short(text_of($wh), 200));
    info('W reason in writable/logs: ' . short(implode(' | ', preg_grep('/settings|doesn\'t exist|Table/i', explode("\n", $wlog))), 400));
    dx("sed -i 's/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = production/' " . APPD . '/.env');
    $w2 = c('W-prod');
    $wh3 = G($w2, 'rolewarden/install', 'W install in production');
    check('W02', 'The remedy of the entry: in production the installer opens (200, with its form)', $w2->status === 200 && form_with($wh3, 'step') !== null, "status {$w2->status} " . short(text_of($wh3), 200));
    // the development 500 of the probe is the documented symptom, not part of the run's no-500 check (L04)
    foreach ($GLOBALS['SEEN'] as $i => $sv) if (str_starts_with($sv[0], 'W ') && str_contains($sv[0], 'development')) $GLOBALS['SEEN'][$i][0] = 'z down (W probe) ' . $sv[0];
    // the probe's log lines (missing settings table) are the documented symptom: copy them aside and empty the logs
    file_put_contents(__DIR__ . '/verify-u1.s3.' . $O['label'] . '.w-applog.txt', implode("\n", uptime_logs()) . "\n");
    dx('rm -f ' . APPD . '/writable/logs/*.log');
    // the install code written by the probe stays: phase I reads it from the file, as a buyer would
}

/* ================================================================== I: installer through the browser */
$UID = 0; $RID = 0;
if (in_array('I', $PHASES, true) && $O['install'] === 'browser') {
    echo "\n== I: installer (" . BASE . ")\n";
    $c = c('I');
    G($c, '', 'home before install');
    $loc = resolve($c->url, $c->location);
    if ($O['mode'] === 'module') info("home of the buyer's own application before the installer: {$c->status} (the home page is the application's, I01 applies to the complete application only)");
    else check('I01', 'Home before installation leads to the installer, inside the address of the application', in_array($c->status, [302, 303], true) && $loc === BASE . 'rolewarden/install', "{$c->status} $loc");
    $h = G($c, 'rolewarden/install', 'install first');
    check('I02', 'Installer answers 200 with the requirements all met on this server', $c->status === 200 && ! preg_match('#<b>(?!ok)[^<]*</b>#', $h) && str_contains($h, 'PHP 8.3'), short(text_of($h), 600));
    info('requirements: ' . short(implode(' | ', array_map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n->textContent)), iterator_to_array(dom($h)->query('//ul[contains(@class,"rw-install-checks")]/li')))), 600));
    $assets = [];
    foreach (page_urls($h, $c->url) as $u) if (preg_match('/\.(css|js)(\?|$)/', $u)) { $t = c('asset'); G($t, $u, "asset $u"); $assets[] = "$u {$t->status} " . ($t->headers['content-type'] ?? ''); }
    check('I03', 'Installer stylesheet loads from inside the application address', $assets !== [] && ! array_filter($assets, fn ($a) => ! preg_match('/ 200 text\/css/', $a) || ! inside(explode(' ', $a)[0])), json_encode($assets));
    $code = trim(dcat(APPD . '/writable/rolewarden/install-code.txt'));
    check('I04', 'Setup code written in writable/rolewarden/install-code.txt', strlen($code) >= 8, 'len ' . strlen($code));
    $ip = function (array $v, string $tag) use ($c): string {
        $h = G($c, 'rolewarden/install', "install before $tag");
        $f = form_with($h, 'step');
        $act = $f ? resolve($c->url, $f['action']) : BASE . 'rolewarden/install';
        return P($c, $act, ['csrf_test_name' => csrf_of($h)] + $v, $tag);
    };
    $h = $ip(['step' => 'code', 'code' => $code], 'code');
    $f = form_with($h, 'hostname');
    if (! $f && $O['mode'] === 'module') {
        $GLOBALS['ENVMAP'] = env_map(dcat(APPD . '/.env'));
        check('I05m', 'Module in a connected application: the database step is skipped (installation.html#migrations)', step_of2($h) === 'migrate', short(text_of($h), 300));
    } else {
        $GLOBALS['PREFILL'] = $f['fields']['baseURL'] ?? '';
        check('I05', 'Right code opens the database step; the proposed address is the one the installer was opened at', $f !== null && ($f['fields']['baseURL'] ?? '') === ($O['expectbase'] ?? BASE), 'proposed ' . ($f['fields']['baseURL'] ?? 'none') . ' expected ' . ($O['expectbase'] ?? BASE));
        $h = $ip(['step' => 'database', 'baseURL' => $O['submitbase'] ?? ($f['fields']['baseURL'] ?? BASE), 'hostname' => $O['dbhost'], 'port' => $O['dbport'], 'database' => $O['dbname'], 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'database');
        $env = env_map(dcat(APPD . '/.env'));
        $GLOBALS['ENVMAP'] = $env;
        check('I06', '.env written by the installer with app.baseURL = proposed address and the database settings', ($env['app.baseURL'] ?? '') === ($O['submitbase'] ?? ($f['fields']['baseURL'] ?? '?')) && ($env['database.default.hostname'] ?? '') === $O['dbhost'] && ($env['database.default.database'] ?? '') === $O['dbname'], json_encode(array_intersect_key($env, array_flip(['app.baseURL', 'database.default.hostname', 'database.default.port', 'CI_ENVIRONMENT']))));
        check('I06b', 'CI_ENVIRONMENT after the installer is the expected one (' . $O['env'] . ')', ($env['CI_ENVIRONMENT'] ?? '') === $O['env'], $env['CI_ENVIRONMENT'] ?? 'absent');
        check('I06c', 'Database step page without SQL or driver detail', ! leaks_sql($h) && $c->status < 500, short(text_of($h), 300));
    }
    $h = $ip(['step' => 'migrate'], 'migrate');
    check('I07', 'Tables step: Settings, Shield and RoleWarden tables and the system roles', $OWNDB ? (in_array('acl_roles', tables(), true) && role_id('super-admin') > 0) : (! leaks_sql($h) && $c->status === 200), short(text_of($h), 300));
    $h = $ip(['step' => 'admin', 'username' => 's3owner', 'email' => OWNER, 'password' => OPW, 'password_confirm' => OPW], 'admin');
    check('I08', 'First super admin created; lock written; setup code deleted', dexists(APPD . '/writable/rolewarden/installed.lock') && ! dexists(APPD . '/writable/rolewarden/install-code.txt') && (! $OWNDB || q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [uid(OWNER)]) === 'super-admin'), short(text_of($h), 300));
    $links = outside(page_urls($h, $c->url));
    check('I09', 'Installer pages: no link, form or redirect outside ' . BASE, $links === [] && ! array_filter($GLOBALS['REDIRS'], fn ($r) => ! inside($r[1])), json_encode([$links, array_values(array_filter($GLOBALS['REDIRS'], fn ($r) => ! inside($r[1])))]));
    $after = G(c('I-after'), 'rolewarden/install', 'install after');
    check('I10', 'Installer refuses to run again (Already installed, no form)', str_contains(text_of($after), 'Already installed') && forms($after) === [], short(text_of($after), 200));
}
if (($O['todev'] ?? '') === '1') {
    dx("sed -i 's/^CI_ENVIRONMENT = production/CI_ENVIRONMENT = development/' " . APPD . '/.env');
    info('after the installer: CI_ENVIRONMENT switched to development for the rest of the run (error_reporting -1)');
}
if ($O['install'] === 'browser' || $O['mode'] === 'module') {
    // mail to Mailpit and every log line, before any request that could send
    $mh = $O['mailhost'] ?? 'host.docker.internal';
    env_add("email.protocol = smtp\nemail.SMTPHost = $mh\nemail.SMTPPort = " . ($O['mailport'] ?? '1026') . "\nemail.SMTPCrypto = ''\nemail.fromEmail = noreply@s3.test\nemail.fromName = 'RoleWarden S3'\nlogger.threshold = 9\nrolewarden.signInRate = " . ($O['rate'] ?? '100') . "\ndemo.password = " . DPW . ($O['showcase'] === '1' ? "\ndemo.showcase = true" : '') . ($O['extraenv'] ?? ''));
}
if ($O['mode'] === 'module' && $O['install'] === 'spark') {
    // installation.html#migrations: "the first super admin is a Shield user given the super-admin role once"
    q("INSERT INTO acl_user_roles (user_id, role_id) SELECT i.user_id, r.id FROM auth_identities i JOIN acl_roles r ON r.slug = 'super-admin' WHERE i.type = 'email_password' AND i.secret = ?", [OWNER]);
    check('M01', 'Module by hand: migrations in three steps, seed, shield:user create/activate and the documented INSERT give a super admin', in_array('acl_roles', tables(), true) && (int) q1('SELECT active FROM users WHERE id=?', [uid(OWNER)]) === 1 && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [uid(OWNER)]) === 'super-admin', json_encode(q('SELECT namespace, batch FROM migrations GROUP BY namespace, batch')));
}
if ($OWNDB && uid(OWNER)) { $UID = uid(OWNER); $RID = role_id('admin'); }

require __DIR__ . '/verify-u1.s3phases.php';

/* ================================================================== U: the panel in use */
if (in_array('U', $PHASES, true)) {
    echo "\n== U: panel in use\n";
    $own = c('owner');
    check('U01', 'The super admin signs in; Shield redirects inside the application', login2($own, OWNER, OPW) && inside(resolve($own->url, $own->location)), "{$own->status} {$own->location}");
    $bad = []; $out = [];
    foreach (screens($UID, $RID) as $s) {
        $h = G($own, $s, "screen $s");
        if ($own->status !== 200 || ! no500($own)) $bad[] = "$s {$own->status} " . short(text_of($h), 120);
        foreach (outside(page_urls($h, $own->url)) as $u) $out[] = "$s -> $u";
    }
    check('U02', 'Every panel screen answers 200 with no SQL, PHP error or warning on screen', $bad === [], json_encode($bad));
    check('U03', 'No link, asset, form or script URL of the 15 screens points outside ' . BASE, $out === [], json_encode(array_slice($out, 0, 15)));
    $h = G($own, 'rolewarden/users', 'users for assets');
    $as = [];
    foreach (page_urls($h, $own->url) as $u) if (preg_match('/\.(css|js|woff2)(\?|$)/', $u) || str_contains($u, '/assets/')) { $t = c('asset'); G($t, $u, "asset $u"); $as[] = "$u {$t->status} " . ($t->headers['content-type'] ?? ''); }
    $css = c('css'); $cssb = '';
    foreach ($as as $a) if (str_contains($a, '.css')) $cssb = G($css, explode(' ', $a)[0], 'panel css');
    $fonts = [];
    if (preg_match_all('/url\(([^)]+)\)/', $cssb, $mm)) foreach (array_unique($mm[1]) as $u) { $u = trim($u, '\'" '); if (str_starts_with($u, 'data:')) continue; $abs = resolve($css->url, $u); $t = c('font'); G($t, $abs, "font $abs"); $fonts[] = "$abs {$t->status}"; }
    check('U04', 'Panel CSS, JS and the fonts referenced by the CSS load (200) from inside the address', $as !== [] && ! array_filter($as, fn ($a) => ! str_contains($a, ' 200 ') || ! inside(explode(' ', $a)[0])) && ! array_filter($fonts, fn ($a) => ! str_ends_with($a, ' 200') || ! inside(explode(' ', $a)[0])), json_encode([$as, $fonts]));
    // a user, a role, matrix, assignment, override
    $hc = G($own, 'rolewarden/users/create', 'create user form');
    $fc = form_with($hc, 'email');
    $v = array_replace($fc['fields'] ?? [], ['username' => 's2user', 'email' => 'user1@s3.test', 'password' => UPW]);
    foreach ($fc['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
    P($own, resolve($own->url, $fc['action'] ?? ''), $v, 'create user');
    $U1 = uid('user1@s3.test');
    check('U05', 'Users: a user created from the panel (Shield user, active)', $U1 > 0 && (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 1 && inside(resolve($own->url, $own->location)), "{$own->status} {$own->location}");
    $hr = G($own, 'rolewarden/roles/create', 'create role form');
    $fr = form_with($hr, 'name');
    info('role form fields: ' . json_encode(array_keys($fr['types'] ?? [])));
    $v = array_replace($fr['fields'] ?? [], ['name' => 'S2 Editors', 'description' => 'Role made by S2']);
    if (array_key_exists('slug', $fr['types'] ?? [])) $v['slug'] = 's2-editors';
    P($own, resolve($own->url, $fr['action'] ?? ''), $v, 'create role');
    $R1 = (int) q1("SELECT id FROM acl_roles WHERE name='S2 Editors' AND deleted_at IS NULL");
    check('U06', 'Roles: a role created from the panel', $R1 > 0 && inside(resolve($own->url, $own->location)), "{$own->status} {$own->location}");
    panelp($own, "rolewarden/roles/$R1", "rolewarden/roles/$R1/permissions", ['permission_id' => perm_id('users.view'), 'granted' => 1], 'matrix grant users.view');
    $mok = $own->status < 400 && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$R1, perm_id('users.view')]) === 1;
    panelp($own, "rolewarden/roles/$R1", "rolewarden/roles/$R1/permissions", ['permission_id' => perm_id('roles.view'), 'granted' => 1], 'matrix grant roles.view');
    check('U07', 'Matrix: granting a permission to the role from its page is saved', $mok && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=?', [$R1]) === 2, "{$own->status} " . short($own->body, 200));
    $hu = G($own, "rolewarden/users/$U1", 'user page');
    $fa = form_action($hu, '#/users/' . $U1 . '/roles$#');
    if ($fa) { $sel = array_keys(array_filter($fa['types'], fn ($t) => $t === 'select'))[0] ?? 'role_id'; P($own, resolve($own->url, $fa['action']), array_replace($fa['fields'], [$sel => $R1]), 'assign role'); }
    check('U08', 'Users: the role is assigned to the user from the user page', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$U1, $R1]) === 1, $fa ? "{$own->status}" : 'no roles form');
    $us = c('user1');
    $ul = login2($us, 'user1@s3.test', UPW);
    G($us, 'rolewarden/users', 'user1 users (role)');
    $s1 = $us->status;
    panelp($own, "rolewarden/users/$U1", "rolewarden/users/$U1/permissions", ['permission_id' => perm_id('users.view'), 'granted' => 0], 'override deny users.view');
    $ovr = (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$U1, perm_id('users.view')]);
    G($us, 'rolewarden/users', 'user1 users (denied)');
    $s2 = $us->status;
    panelp($own, "rolewarden/users/$U1", "rolewarden/users/$U1/permissions", ['permission_id' => perm_id('users.view')], 'override cleared');
    G($us, 'rolewarden/users', 'user1 users (cleared)');
    $s3 = $us->status;
    check('U09', 'Override: the role grants the screen (200), a negative override denies it, clearing it restores it', $ul && $s1 === 200 && $ovr === 1 && $s2 !== 200 && $s3 === 200, "login=$ul role=$s1 override_rows=$ovr denied=$s2 cleared=$s3");
    $hu = G($own, "rolewarden/users/$U1", 'user page 2');
    $fd = form_action($hu, '#/users/' . $U1 . '/deactivate$#');
    if ($fd) P($own, resolve($own->url, $fd['action']), $fd['fields'], 'deactivate');
    $deact = (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 0;
    G($us, 'rolewarden/users', 'user1 after deactivation');
    $dst = $us->status;
    G($us, 'rolewarden/profile', 'user1 profile after deactivation');
    info('deactivated user: rolewarden/users ' . $dst . ', own profile ' . $us->status);
    $hu = G($own, "rolewarden/users/$U1", 'user page 3');
    $fe = form_action($hu, '#/users/' . $U1 . '/(activate|reactivate)$#');
    if ($fe) P($own, resolve($own->url, $fe['action']), $fe['fields'], 'reactivate');
    $back = c('user1-back'); $bl = login2($back, 'user1@s3.test', UPW); G($back, 'rolewarden/users', 'user1 after reactivation');
    check('U10', 'Users: deactivating denies the user a screen the role grants, at the next request; reactivating restores it', $fd && $deact && $dst !== 200 && $fe && (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 1 && $bl && $back->status === 200, "deact=$deact next=$dst reactivate-form=" . ($fe ? 'y' : 'n') . " after={$back->status}");
    // settings: change one value with the screen's own form
    $hs = G($own, 'rolewarden/settings', 'settings');
    $fs = settings_form($hs);
    info('settings fields: ' . json_encode($fs['types'] ?? []));
    $before = q("SELECT class, `key`, value FROM settings ORDER BY id");
    $changed = '';
    if ($fs) {
        $vals = $fs['fields'];
        $selects = array_keys(array_filter($fs['types'], fn ($t) => $t === 'select'));
        usort($selects, fn ($a, $b) => (int) ! preg_match('/retention|lifetime/i', $a) <=> (int) ! preg_match('/retention|lifetime/i', $b));
        foreach ($selects as $k) {
            $opts = []; foreach (dom($fs['html'])->query('//select[@name="' . $k . '"]/option') as $op) $opts[] = $op->getAttribute('value');
            foreach ($opts as $op) if ($op !== (string) ($vals[$k] ?? '')) { $vals[$k] = $op; $changed = "$k=$op"; break 2; }
        }
        P($own, resolve($own->url, $fs['action']), $vals, 'settings save');
    }
    $after = q("SELECT class, `key`, value FROM settings ORDER BY id");
    $flash = G($own, resolve($own->url, $own->location ?: 'rolewarden/settings'), 'settings after save');
    info('settings after save: ' . short(html_entity_decode(preg_match('/rwToasts\(([^)]*)\)/', $flash, $tm) ? $tm[1] : ''), 200));
    check('U11', 'Settings: a value changed with the Settings form is stored; redirect inside the application', $fs && $before !== $after && inside(resolve($own->url, $own->location ?: $own->url)), "changed $changed status {$own->status} {$own->location}");
    $ha = G($own, 'rolewarden/activity', 'activity');
    check('U12', 'Activity: the log lists what was just done (role created, role assigned)', $own->status === 200 && str_contains($ha, 'S2 Editors') && str_contains($ha, 's2user'), short(text_of($ha), 300));
    // language switch in the top bar (field back), then back to English
    $hp = G($own, 'rolewarden/roles?x=1', 'roles with query');
    $lf = null; foreach (forms($hp) as $f) if (str_contains($f['action'], 'profile/language') && array_key_exists('back', $f['types'])) $lf = $f;
    $back = $lf['fields']['back'] ?? '';
    if ($lf) P($own, resolve($own->url, $lf['action']), array_replace($lf['fields'], ['language' => 'it']), 'switch to it');
    $loc = resolve($own->url, $own->location);
    $hit = G($own, $loc, 'after switch');
    check('U13', 'Language switch: back to the same page inside the application, now in Italian', $lf && in_array($loc, [BASE . 'rolewarden/roles?x=1', BASE . 'index.php/rolewarden/roles?x=1'], true) && preg_match('/<html[^>]*lang="it"/', $hit) === 1, "back=$back location=$loc");
    if ($lf) { $lf2 = null; foreach (forms($hit) as $f) if (str_contains($f['action'], 'profile/language')) $lf2 = $f; if ($lf2) P($own, resolve($own->url, $lf2['action']), array_replace($lf2['fields'], ['language' => 'en']), 'switch to en'); }
    $GLOBALS['U1'] = $U1; $GLOBALS['R1'] = $R1;
    $GLOBALS['OWNC'] = $own;
}

/* ================================================================== E: security emails */
if (in_array('E', $PHASES, true)) {
    echo "\n== E: security emails\n";
    $own = $GLOBALS['OWNC'] ?? c('owner-e');
    if (! isset($GLOBALS['OWNC'])) login2($own, OWNER, OPW);
    mailpit('DELETE', 'messages');
    // password changed from the profile
    $u = c('user1-pw'); $u->extra = ['User-Agent: S2-Agent-A'];
    login2($u, 'user1@s3.test', UPW);
    $hp = G($u, 'rolewarden/profile', 'profile');
    $fp = form_with($hp, 'current_password');
    $NPW = UPW . '-2';
    if ($fp) P($u, resolve($u->url, $fp['action']), array_replace($fp['fields'], ['current_password' => UPW, 'password' => $NPW, 'password_confirm' => $NPW]), 'change password');
    wait_mail(1);
    $m1 = mail_to('user1@s3.test');
    check('E01', 'Password changed from the profile: an email to the user', $fp && count($m1) >= 1, $fp ? json_encode(array_column($m1, 'Subject')) : 'no password form');
    // new device: same account from another browser
    $u2 = c('user1-dev');
    login2($u2, 'user1@s3.test', $NPW, false, ['User-Agent: Mozilla/5.0 (X11; Linux x86_64) S2-Agent-B Firefox/140.0']);
    wait_mail(2);
    $m2 = mail_to('user1@s3.test');
    check('E02', 'Sign-in from a device never used: an email to the user', count($m2) >= 2, json_encode(array_column($m2, 'Subject')));
    // lock after failed sign-ins: second user
    $hc = G($own, 'rolewarden/users/create', 'create user2');
    $fc = form_with($hc, 'email');
    $v = array_replace($fc['fields'] ?? [], ['username' => 's2lock', 'email' => 'lock@s3.test', 'password' => UPW]);
    foreach ($fc['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
    P($own, resolve($own->url, $fc['action'] ?? ''), $v, 'create lock user');
    $n0 = count(mailpit('GET', 'messages?limit=200')['messages'] ?? []);
    for ($i = 0; $i < 6; $i++) { $f = c("fail$i"); login2($f, 'lock@s3.test', 'Wrong-Pass-' . $i); }
    wait_mail($n0 + 2);
    $ml = mail_to('lock@s3.test');
    $mo = mail_to(OWNER);
    check('E03', 'Lock after failed sign-ins: an email to the user and an alert to a holder of security.alerts', count($ml) >= 1 && count($mo) >= 1, json_encode([array_column($ml, 'Subject'), array_column($mo, 'Subject')]));
    $all = mailpit('GET', 'messages?limit=200')['messages'] ?? [];
    $urls = []; $bodies = [];
    foreach ($all as $m) { $f = mailpit('GET', 'message/' . $m['ID']); $bodies[] = $f; foreach (mail_urls($f) as $x) $urls[] = $x; }
    $GLOBALS['MAILS'] = $bodies;
    info('links in the emails: ' . json_encode(array_values(array_unique($urls))));
    check('E04', 'Every link in the security emails points inside ' . BASE, $urls !== [] && ! array_filter($urls, fn ($x) => ! inside($x)), json_encode(array_values(array_unique($urls))));
    check('E05', 'No SQL or PHP error text in any email', ! array_filter($bodies, fn ($f) => leaks_sql(($f['HTML'] ?? '') . ($f['Text'] ?? '')) || phperr($f['Text'] ?? '')));
    $GLOBALS['NPW'] = $NPW;
}

/* ================================================================== D: demo */
if (in_array('D', $PHASES, true)) {
    echo "\n== D: demo app\n";
    [$rc, $o] = spark2(['demo:install']);
    check('D01', 'php spark demo:install in the application (exit 0)', $rc === 0, short($o));
    $rd = c('reader'); $lok = login2($rd, 'reader@example.com', DPW);
    $h = G($rd, 'demo', 'demo reader');
    $out = outside(page_urls($h, $rd->url));
    check('D02', 'The demo answers to the reader, links and forms inside the application', $lok && $rd->status === 200 && no500($rd) && $out === [], json_encode([$rd->status, $out]));
    $au = c('author'); login2($au, 'author@example.com', DPW);
    $hc = G($au, 'demo/create', 'demo create');
    $f = form_with($hc, 'title');
    if ($f) P($au, resolve($au->url, $f['action']), array_replace($f['fields'], ['title' => 'S2 document', 'body' => 'Written in S2']), 'demo create post');
    check('D03', 'The author writes a document; redirect inside the application', $f && (int) q1("SELECT COUNT(*) FROM demo_documents WHERE title='S2 document'") === 1 && inside(resolve($au->url, $au->location)), "{$au->status} {$au->location}");
    if ($O['showcase'] === '1') {
        $h = G($rd, 'demo', 'demo showcase');
        // the showcase bar is one form whose buttons carry the target in formaction
        $sw = null;
        foreach (forms($h) as $ff) if (preg_match_all('/formaction="([^"]*showcase[^"]*)"[^>]*>\s*([^<]+)</', $ff['html'], $bm, PREG_SET_ORDER)) {
            foreach ($bm as $b) if (str_contains($b[2], 'Approver') || str_contains($b[2], 'Approvatore')) $sw = ['action' => html_entity_decode($b[1], ENT_QUOTES | ENT_HTML5), 'fields' => $ff['fields']];
        }
        if ($sw) P($rd, resolve($rd->url, $sw['action']), $sw['fields'], 'showcase switch');
        $loc = resolve($rd->url, $rd->location);
        $h2 = G($rd, $loc, 'after showcase');
        check('D04', 'Showcase bar: a switch keeps the visitor inside the application and changes the account', $sw && inside($loc) && $rd->status === 200 && outside(page_urls($h2, $rd->url)) === [] && h_user($h2) !== h_user($h), "form=" . ($sw['action'] ?? 'none') . " loc=$loc users " . h_user($h) . ' -> ' . h_user($h2));
    }
    [$rc, $o] = spark2(['demo:uninstall']);
    check('D05', 'php spark demo:uninstall (exit 0): demo table and accounts gone', $rc === 0 && ! in_array('demo_documents', tables(), true) && ! uid('reader@example.com'), short($o));
}
function h_user(string $h): string { $xp = dom($h); foreach ($xp->query('//*[contains(@class,"rw-who")]//span') as $n) return trim(preg_replace('/\s+/', ' ', $n->textContent)); return md5(text_of($h)); }

/* ================================================================== S: CSRF, escaping, password hashes, SQL on screen */
if (in_array('S', $PHASES, true)) {
    echo "\n== S: CSRF, escaping, password hashes, no SQL on screen\n";
    $own = c('owner-s'); login2($own, OWNER, OPW);
    $U1 = $GLOBALS['U1'] ?? 0; $R1 = $GLOBALS['R1'] ?? $RID;
    // every POST form on every page seen so far, plus the demo pages and the sign-in pages
    [$rc] = spark2(['demo:install']);
    $rd = c('reader-s'); login2($rd, 'author@example.com', DPW); G($rd, 'demo', 'demo'); G($rd, 'demo/create', 'demo create');
    $docId = (int) q1("SELECT id FROM demo_documents WHERE status='draft' ORDER BY id LIMIT 1");
    if ($docId) { G($rd, "demo/$docId", 'demo doc'); G($rd, "demo/$docId/edit", 'demo doc edit'); }
    foreach (array_merge(screens($U1 ?: $UID, $R1), ['rolewarden/users/' . $UID, 'rolewarden/roles/' . role_id('user')]) as $s) G($own, $s, "S screen $s");
    G(c('anon-s'), 'login', 'login anon'); G(c('anon-s2'), 'register', 'register anon'); G(c('anon-s3'), 'login/magic-link', 'magic link anon');
    $postForms = []; $noToken = [];
    foreach ($GLOBALS['SEEN'] as [$tag, $st, $url, $body]) {
        if ($st !== 200 || ! str_contains((string) $body, '<form')) continue;
        foreach (forms($body) as $f) {
            if ($f['method'] !== 'post') continue;
            $abs = resolve($url, $f['action']);
            $key = preg_replace('#/\d+(/|$)#', '/{id}$1', parse_url($abs, PHP_URL_PATH) ?? '');
            $postForms[$key] ??= [$abs, $f, $tag];
            if (! array_key_exists('csrf_test_name', $f['fields'])) $noToken[] = "$key ($tag)";
        }
    }
    info('distinct POST forms seen: ' . count($postForms) . ' ' . json_encode(array_keys($postForms)));
    check('S01', 'Every POST form on every page seen carries the CSRF token field', $noToken === [] && count($postForms) >= 15, json_encode(array_values(array_unique($noToken))));
    $refusedBad = [];
    foreach ($postForms as $key => [$abs, $f, $tag]) {
        if (str_contains($key, '/logout')) continue;
        $client = str_contains($key, '/demo') ? $rd : (preg_match('#/(login|register)#', $key) ? c('anon-csrf') : $own);
        $st0 = st();
        $vals = $f['fields']; unset($vals['csrf_test_name']);
        foreach ($f['types'] as $k => $t) if (str_starts_with($t, 'button:') && ! isset($vals[$k])) $vals[$k] = substr($t, 7);
        P($client, $abs, $vals, "no token $key");
        $st1 = st();
        $codes[] = "$key:{$client->status}";
        if ($st0 !== $st1) $refusedBad[] = "$key -> {$client->status} changed the state";
        P($client, $abs, ['csrf_test_name' => str_repeat('0', 32)] + $vals, "bad token $key");
        if (st() !== $st1) $refusedBad[] = "$key (wrong token) changed the state";
    }
    info('answers without token: ' . implode(' ', $codes ?? []));
    foreach ([["rolewarden/roles/$R1/permissions", ['permission_id' => perm_id('users.delete'), 'granted' => 1]], ["rolewarden/users/$U1/permissions", ['permission_id' => perm_id('users.delete'), 'granted' => 0]], ["rolewarden/users/$U1/roles", ['role_id' => role_id('admin')]]] as [$u, $d]) {
        $st0 = st(); P($own, $u, $d, "no token ajax $u", AJAX); if (st() !== $st0) $refusedBad[] = "$u (ajax, no token) changed the database";
    }
    check('S02', 'Every POST form submitted without a token, or with a wrong one, is refused and changes nothing (' . count($postForms) . ' forms, plus the AJAX endpoints)', $refusedBad === [], json_encode($refusedBad));
    // escaping: markup in what an admin writes, shown back everywhere
    $X1 = '<script>alert("s2x")</script>'; $X2 = '"><img src=x onerror=alert(22)>'; $X3 = "'><svg onload=alert(33)>";
    $hr = G($own, 'rolewarden/roles/create', 'xss role form'); $fr = form_with($hr, 'name');
    $v = array_replace($fr['fields'] ?? [], ['name' => 'S2 ' . $X1, 'description' => 'D ' . $X2 . $X3]);
    if (array_key_exists('slug', $fr['types'] ?? [])) $v['slug'] = 's2-xss';
    P($own, resolve($own->url, $fr['action'] ?? ''), $v, 'xss role');
    $RX = (int) q1("SELECT id FROM acl_roles WHERE name LIKE 'S2 <script>%'");
    if ($RX && $U1) { $hu = G($own, "rolewarden/users/$U1", 'xss user page'); $fa = form_action($hu, '#/users/' . $U1 . '/roles$#'); if ($fa) { $sel = array_keys(array_filter($fa['types'], fn ($t) => $t === 'select'))[0] ?? 'role_id'; P($own, resolve($own->url, $fa['action']), array_replace($fa['fields'], [$sel => $RX]), 'xss assign'); } }
    $he = G($own, "rolewarden/users/$U1/edit", 'xss user edit'); $fe = form_with($he, 'username');
    if ($fe) P($own, resolve($own->url, $fe['action']), array_replace($fe['fields'], ['username' => 's2user' . $X3]), 'xss username');
    $au = c('author-x'); login2($au, 'author@example.com', DPW); $hc = G($au, 'demo/create', 'xss demo'); $fd = form_with($hc, 'title');
    if ($fd) P($au, resolve($au->url, $fd['action']), array_replace($fd['fields'], ['title' => 'T ' . $X1, 'body' => 'B ' . $X2]), 'xss doc');
    $raw = [];
    $pages = array_merge(screens($U1, $RX ?: $R1), ['rolewarden/roles/' . $RX, 'rolewarden/users/' . $U1, 'rolewarden/activity', 'rolewarden/permissions']);
    foreach (['en', 'it'] as $lang) {
        $cl = c("xss-$lang"); $cl->extra = ["Accept-Language: $lang"]; login2($cl, OWNER, OPW);
        foreach ($pages as $s) { $h = G($cl, $s, "xss $lang $s"); foreach ([$X1, '<img src=x onerror', '<svg onload'] as $x) if (str_contains($h, $x)) $raw[] = "$lang $s: $x"; }
    }
    foreach (['demo', 'demo/' . (int) q1("SELECT id FROM demo_documents WHERE title LIKE 'T <script>%'")] as $s) { $h = G($au, $s, "xss $s"); foreach ([$X1, '<img src=x onerror'] as $x) if (str_contains($h, $x)) $raw[] = "$s: $x"; }
    check('S03', 'Markup written in role name and description, username and a demo document is escaped on every page (en, it) and in the activity log', $RX > 0 && $raw === [] && (! $fe || str_contains(q1('SELECT username FROM users WHERE id=?', [$U1]) ?? '', '<svg') || true), json_encode([$RX, $raw, q1('SELECT username FROM users WHERE id=?', [$U1])]));
    info('username after the markup edit: ' . json_encode(q1('SELECT username FROM users WHERE id=?', [$U1])) . ' (Shield may refuse it)');
    // password hashes: Shield's default (PASSWORD_DEFAULT, bcrypt) for every account, whatever created it
    $auth = dcat(APPD . '/app/Config/Auth.php');
    preg_match('/public\s+string\s+\$hashAlgorithm\s*=\s*([^;]+);/', $auth, $ha); preg_match('/public\s+int\s+\$hashCost\s*=\s*(\d+)/', $auth, $hc2);
    $rows = q("SELECT u.username, i.secret, i.secret2 FROM auth_identities i JOIN users u ON u.id=i.user_id WHERE i.type='email_password'");
    $hbad = [];
    foreach ($rows as $r) { $inf = password_get_info((string) $r['secret2']); if ($inf['algo'] !== PASSWORD_BCRYPT || ($inf['options']['cost'] ?? 0) !== (int) ($hc2[1] ?? 12)) $hbad[] = $r['secret'] . ' ' . $inf['algoName'] . ' ' . json_encode($inf['options']); }
    $known = ['s3owner' => OPW, 'reader' => DPW];
    $ver = password_verify(OPW, (string) q1("SELECT secret2 FROM auth_identities WHERE secret=?", [OWNER])) && password_verify(DPW, (string) q1("SELECT secret2 FROM auth_identities WHERE secret='reader@example.com'"));
    check('S04', 'Every stored password is a Shield hash (Config\Auth: ' . trim($ha[1] ?? '?') . ', cost ' . ($hc2[1] ?? '?') . '), installer, panel, profile and demo accounts alike', $rows !== [] && $hbad === [] && $ver && str_contains($ha[1] ?? '', 'PASSWORD_DEFAULT'), json_encode([$hbad, count($rows), $ver]));
    // odd input on every route: no SQL text, no PHP error on screen
    $fz = ['rolewarden/users?q=%27%22%5C', 'rolewarden/users?page=-1', 'rolewarden/users?page=abc', 'rolewarden/users?page=99999999999999999999', 'rolewarden/users?sort=id%3BDROP', 'rolewarden/users?q[]=x', 'rolewarden/users?role=%27', 'rolewarden/users?status=%27',
        'rolewarden/users/0', 'rolewarden/users/abc', 'rolewarden/users/99999999', 'rolewarden/users/1%27', 'rolewarden/users/-1/edit', 'rolewarden/users/99999999/edit', 'rolewarden/roles/0', 'rolewarden/roles/abc', 'rolewarden/roles/99999999', 'rolewarden/roles/99999999/edit', "rolewarden/roles/1'", 'rolewarden/activity?page=%27', 'rolewarden/activity?type=%27&user=%27&from=x&to=y', 'rolewarden/activity?page[]=1', 'rolewarden/permissions?q=%27', 'rolewarden/sessions?page=%27', 'rolewarden/users/import/sample?x=%27', 'demo/0', 'demo/abc', 'demo/99999999', 'demo/1%27/edit', 'rolewarden/assets/css/..%2f..%2f.env', 'rolewarden/assets/..%2f..%2fConfig/RoleWarden.php', 'rolewarden/assets/css/nothing.css'];
    $leak = []; $five = [];
    foreach ($fz as $u) { $cl = str_starts_with($u, 'demo') ? $au : $own; $h = G($cl, $u, "fuzz $u"); if (leaks_sql($h) || phperr(text_of($h)) || str_contains($h, 'encryption.key') || str_contains($h, 'database.default')) $leak[] = "$u {$cl->status} " . short(text_of($h), 160); if ($cl->status >= 500) $five[] = "$u {$cl->status}"; }
    foreach ([["rolewarden/roles/$R1/permissions", ['permission_id' => "1'", 'granted' => 'x']], ["rolewarden/users/$U1/permissions", ['permission_id' => '99999999', 'granted' => 1]], ["rolewarden/users/$U1/roles", ['role_id' => "0 OR 1=1"]], ['rolewarden/users/99999999/deactivate', []], ['rolewarden/roles/99999999/delete', []], ['rolewarden/settings', ['sessionLifetime' => "'", 'lockAttempts' => '-5']], ['rolewarden/profile/language', ['language' => "'"]]] as [$u, $d]) {
        $h = panelp($own, 'rolewarden/profile', $u, $d, "fuzz post $u", false); if (leaks_sql($h) || phperr(text_of($h))) $leak[] = "POST $u {$own->status} " . short(text_of($h), 160); if ($own->status >= 500) $five[] = "POST $u {$own->status}";
    }
    check('S05', 'Odd input on ' . (count($fz) + 7) . ' routes (quotes, huge or negative ids, arrays, path traversal on assets): no SQL text, PHP error or setting on screen', $leak === [], json_encode($leak));
    check('S05b', 'Odd input never makes the application answer 500', $five === [], json_encode($five));
    spark2(['demo:uninstall']);
}

/* ================================================================== T: Italian panel without English text */
if (in_array('T', $PHASES, true)) {
    echo "\n== T: Italian panel, every string from the language files\n";
    $own = c('owner-t'); login2($own, OWNER, OPW);
    $h = G($own, 'rolewarden/profile', 'profile t'); $lf = null; foreach (forms($h) as $f) if (str_contains($f['action'], 'profile/language')) $lf = $f;
    if ($lf) P($own, resolve($own->url, $lf['action']), array_replace($lf['fields'], ['language' => 'it']), 'it');
    [$rc] = spark2(['demo:install']);
    $dirs = ['modules/rolewarden/src/Language', 'modules/rolewarden-demo/src/Language'];
    if ($O['mode'] === 'module') $dirs = ['vendor/rolewarden/codeigniter4-rolewarden/src/Language'];
    $flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flat($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
    $EN = []; $IT = [];
    foreach ($dirs as $d) foreach (['en' => 'EN', 'it' => 'IT'] as $l => $var) {
        [$rc, $ls] = dx('ls ' . APPD . "/$d/$l/ 2>/dev/null");
        foreach (array_filter(preg_split('/\s+/', trim($ls))) as $file) {
            [$rc, $j] = dx('php', '<?php echo json_encode(require ' . var_export(APPD . "/$d/$l/$file", true) . ');');
            $$var += $flat((array) json_decode($j, true), basename($file, '.php') . '.');
        }
    }
    $words = fn (string $s): array => array_unique(array_map('mb_strtolower', preg_split('/[^\p{L}]+/u', preg_replace('/\{[^}]*\}|:[a-z]+/', ' ', $s), -1, PREG_SPLIT_NO_EMPTY)));
    $wEN = []; foreach ($EN as $v) foreach ($words($v) as $w) $wEN[$w] = true;
    $wIT = []; foreach ($IT as $v) foreach ($words($v) as $w) $wIT[$w] = true;
    $only = array_filter(array_keys($wEN), fn ($w) => ! isset($wIT[$w]) && mb_strlen($w) >= 3);
    $only = array_flip($only);
    info(sprintf('language files: %d English and %d Italian strings; %d words used only in English', count($EN), count($IT), count($only)));
    // an action done while the panel is in Italian, for the activity log
    if (isset($GLOBALS['R1'])) panelp($own, 'rolewarden/roles/' . $GLOBALS['R1'], 'rolewarden/roles/' . $GLOBALS['R1'] . '/permissions', ['permission_id' => perm_id('activity.view'), 'granted' => 1], 'it grant');
    $pages = array_merge(array_values(array_filter(screens($GLOBALS['U1'] ?? $UID, $GLOBALS['R1'] ?? $RID), fn ($x) => $x !== 'rolewarden/activity')), ['rolewarden/users/' . $UID, 'rolewarden/roles/' . role_id('super-admin'), 'rolewarden/roles/' . role_id('user'), 'rolewarden/users?q=zzz']);
    $hits = [];
    $docs = q('SELECT title, body FROM demo_documents');
    $dataWords = []; foreach (array_merge(q('SELECT username AS t FROM users'), q("SELECT secret AS t FROM auth_identities WHERE type='email_password'"), array_map(fn ($d) => ['t' => $d['title'] . ' ' . $d['body']], $docs), q("SELECT name AS t FROM acl_roles WHERE is_system=0")) as $r) foreach ($words((string) $r['t']) as $w) $dataWords[$w] = true;
    $scan = function (Client $cl, string $s) use (&$hits, $only, $words, $dataWords) {
        $h = G($cl, $s, "it $s");
        $xp = dom($h);
        foreach ($xp->query('//script|//style|//code|//*[contains(@class,"rw-mono")]|//*[contains(@class,"slug")]') as $n) $n->parentNode->removeChild($n);
        $txt = [];
        $b = $xp->query('//body')->item(0); $txt[] = $b ? $b->textContent : '';
        foreach ($xp->query('//@title|//@aria-label|//@placeholder|//@alt|//input[@type="submit" or @type="button"]/@value|//title') as $a) $txt[] = $a->nodeValue ?? $a->textContent;
        $all = preg_replace('/\b[a-z0-9-]+\.[a-z0-9.-]+\b|\S+@\S+/u', ' ', implode(' ', $txt));
        $found = [];
        foreach ($words($all) as $w) if (isset($only[$w]) && ! isset($dataWords[$w])) $found[] = $w;
        if ($found) foreach (array_unique($found) as $w) $hits[$s][$w] = preg_match('/.{0,60}\b' . preg_quote($w, '/') . '\b.{0,40}/iu', $all, $cx) ? trim(preg_replace('/\s+/', ' ', $cx[0])) : '';
        return $h;
    };
    foreach ($pages as $s) $scan($own, $s);
    $rdc = c('reader-t'); login2($rdc, 'reader@example.com', DPW);
    $hp = G($rdc, 'rolewarden/profile', 'reader profile'); $lf = null; foreach (forms($hp) as $f) if (str_contains($f['action'], 'profile/language')) $lf = $f;
    if ($lf) P($rdc, resolve($rdc->url, $lf['action']), array_replace($lf['fields'], ['language' => 'it']), 'reader it');
    $scan($rdc, 'demo');
    $an = c('anon-it'); $an->extra = ['Accept-Language: it-IT,it;q=0.9'];
    foreach (['login', 'rolewarden/install'] as $s) $scan($an, $s);
    // entries written while the reader of the log works in Italian: grant (above) and a Settings change
    $hs = G($own, 'rolewarden/settings', 'it settings'); $fs = settings_form($hs);
    if ($fs) { $vals = $fs['fields']; $vals['lock_minutes'] = (string) ((int) ($vals['lock_minutes'] ?? 15) + 1); P($own, resolve($own->url, $fs['action']), $vals, 'it settings save'); }
    $ha = G($own, 'rolewarden/activity', 'it activity newest');
    $rows = []; foreach (dom($ha)->query('//table//tbody/tr') as $i => $tr) { if ($i > 7) break; $t = trim(preg_replace('/\s+/u', ' ', $tr->textContent)); if (str_contains($t, 's3owner')) $rows[] = $t; }
    info('newest entries of the Italian owner: ' . json_encode($rows, JSON_UNESCAPED_UNICODE) . ' raw first row: ' . json_encode(substr($ha, (int) strpos($ha, '<tbody'), 1500)));
    // the details column reads "key: old → new"; the keys (lock_minutes, permission, username) are reported apart as an ambiguity
    $keys = []; $rw = [];
    foreach ($rows as $r) {
        if (preg_match_all('/(?<=\s)([a-z][a-z_]*):\s/u', $r, $km)) foreach ($km[1] as $k) $keys[$k] = true;
        $r2 = preg_replace(['/(?<=\s)[a-z][a-z_]*:\s/u', '/[a-z0-9_-]+\.[a-z0-9._-]+/u'], ' ', $r);
        foreach ($words($r2) as $w) if (isset($only[$w]) && ! isset($dataWords[$w])) $rw[] = $w;
    }
    if (array_filter(array_keys($keys), fn ($k) => isset($only[$k]) || str_contains($k, '_'))) amb('TA2', 'Italian activity log, details column: the field keys of an entry stay as written in the code (key: old → new), in English', implode(', ', array_keys($keys)));
    check('T04', 'Activity log entries written while the panel is in Italian (role permission, Settings, user): no English-only word outside the field keys', $rows !== [] && $rw === [], json_encode([$rows, array_values(array_unique($rw))], JSON_UNESCAPED_UNICODE));
    // a column heading that is exactly the action part of permission slugs is reported apart (ambiguity, not decided here)
    $acts = array_flip(array_map(fn ($r) => $r['a'], q("SELECT DISTINCT SUBSTRING_INDEX(slug, '.', -1) AS a FROM acl_permissions")));
    $ambH = [];
    foreach ($hits as $pg => $ws) foreach ($ws as $w => $cx) if ($cx === $w && isset($acts[$w])) { $ambH[$w] = true; unset($hits[$pg][$w]); }
    $hits = array_filter($hits);
    if ($ambH) amb('TA1', 'Italian permission matrix: the action columns are headed by the action part of the slug, in English', implode(', ', array_keys($ambH)));
    check('T01', 'Italian panel, demo and sign-in pages: no word that exists only in the English language files (text and title/aria-label/placeholder/alt)', $hits === [], json_encode($hits, JSON_UNESCAPED_UNICODE));
    $cands = [];
    foreach ($GLOBALS['SEEN'] as [$tag, $st, $url, $body]) {
        if (! str_starts_with($tag, 'it ') || $st !== 200) continue;
        $t = ' ' . text_of((string) $body) . ' ';
        foreach ($docs as $d) $t = str_replace([trim(preg_replace('/\s+/u', ' ', $d['title'])), trim(preg_replace('/\s+/u', ' ', $d['body']))], ' ', $t);
        if (str_contains($tag, 'activity')) continue;
        foreach (['Save', 'Cancel', 'Delete', 'Edit', 'Search', 'Next', 'Previous', 'Back', 'Settings', 'Sign out', 'Users', 'Roles', 'Permissions', 'Activity', 'Sessions', 'Profile', 'Name', 'Description', 'Created', 'Updated', 'Never', 'None', 'Yes', 'Loading', 'Error', 'Close', 'Show', 'Hide', 'Remember me', 'Forgot', 'Submit', 'Approve', 'Documents', 'Owner', 'Status', 'Draft', 'Approved', 'Title', ' the ', ' and ', ' of ', ' with ', ' your '] as $w)
            if (preg_match('/.{0,50}(?<![\p{L}.-])' . preg_quote(trim($w), '/') . '(?![\p{L}.-]).{0,30}/u', $t, $cx)) $cands[$tag][trim($w)] = trim($cx[0]);
    }
    check('T02', 'Italian pages: none of 40 common English interface words appears', $cands === [], json_encode($cands));
    // Italian security email
    if (isset($GLOBALS['NPW'])) {
        $ui = c('user1-it'); login2($ui, 'user1@s3.test', $GLOBALS['NPW']);
        $hp = G($ui, 'rolewarden/profile', 'user1 profile'); $lf = null; foreach (forms($hp) as $f) if (str_contains($f['action'], 'profile/language')) $lf = $f;
        if ($lf) P($ui, resolve($ui->url, $lf['action']), array_replace($lf['fields'], ['language' => 'it']), 'user1 it');
        mailpit('DELETE', 'messages');
        $fp = form_with(G($ui, 'rolewarden/profile', 'user1 profile it'), 'current_password');
        if ($fp) P($ui, resolve($ui->url, $fp['action']), array_replace($fp['fields'], ['current_password' => $GLOBALS['NPW'], 'password' => $GLOBALS['NPW'] . 'x', 'password_confirm' => $GLOBALS['NPW'] . 'x']), 'user1 pw it');
        wait_mail(1);
        $m = mail_to('user1@s3.test');
        $mw = []; foreach ($m as $f) foreach ($words(($f['Subject'] ?? '') . ' ' . preg_replace('/\b[a-z0-9-]+\.[a-z0-9.-]+\b|\S+@\S+|https?:\S+/u', ' ', $f['Text'] ?? '')) as $w) if (isset($only[$w]) && ! isset($dataWords[$w])) $mw[] = $w;
        check('T03', 'Security email to an Italian reader: in Italian, no English-only word', $m !== [] && $mw === [], json_encode([array_column($m, 'Subject'), array_values(array_unique($mw))], JSON_UNESCAPED_UNICODE));
        $GLOBALS['NPW'] .= 'x';
    }
    spark2(['demo:uninstall']);
}

/* ================================================================== K: devices CodeIgniter cannot recognise (D1 recheck) */
if (in_array('K', $PHASES, true)) {
    echo "\n== K: sessions and security emails with a User-Agent without a recognisable system, and an empty one (en, it)\n";
    // CodeIgniter's own fallbacks of its UserAgent class (framework, English), never to be shown as such
    $ciWords = ['Unknown Platform', 'Unknown Browser', 'Unknown Mobile', 'Unknown Robot', 'Unidentified User Agent'];
    $own = c('owner-k'); login2($own, OWNER, OPW);
    $users = [];
    foreach (['en' => 'k-en@s3.test', 'it' => 'k-it@s3.test'] as $lang => $mail) {
        $hc = G($own, 'rolewarden/users/create', "create k $lang"); $fc = form_with($hc, 'email');
        $v = array_replace($fc['fields'] ?? [], ['username' => 's2k' . $lang, 'email' => $mail, 'password' => UPW]);
        foreach ($fc['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
        P($own, resolve($own->url, $fc['action'] ?? ''), $v, "create k $lang");
        $users[$lang] = uid($mail);
        // the account's language, then a first sign-in from a normal browser (no email on the very first one)
        $u = c("k-$lang-first"); login2($u, $mail, UPW, false, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36']);
        $hp = G($u, 'rolewarden/profile', "k $lang profile"); $lf = null; foreach (forms($hp) as $f) if (str_contains($f['action'], 'profile/language')) $lf = $f;
        if ($lf) P($u, resolve($u->url, $lf['action']), array_replace($lf['fields'], ['language' => $lang]), "k $lang language");
    }
    check('K00', 'Two accounts, English and Italian, signed in once from a normal browser', ! in_array(0, $users, true) && count($users) === 2, json_encode($users));
    $agents = ['no system' => 'User-Agent: S2-Agent-A', 'empty' => 'User-Agent;'];
    $seenTxt = [];
    foreach (['en', 'it'] as $lang) {
        $mail = "k-$lang@s3.test";
        foreach ($agents as $name => $hdr) {
            mailpit('DELETE', 'messages');
            $u = c("k-$lang-$name");
            $okl = login2($u, $mail, UPW, false, [$hdr]);
            wait_mail(1);
            $m = mail_to($mail);
            $txt = implode("\n", array_map(fn ($f) => ($f['Subject'] ?? '') . "\n" . ($f['Text'] ?? '') . "\n" . html_entity_decode(strip_tags($f['HTML'] ?? ''), ENT_QUOTES | ENT_HTML5), $m));
            preg_match('/^\h*(Device|Dispositivo)\h*:\h*(.*)$/mi', $txt, $dm);
            info("K $lang, User-Agent $name: email " . json_encode(array_column($m, 'Subject'), JSON_UNESCAPED_UNICODE) . ' device line: ' . json_encode($dm[0] ?? '(none)', JSON_UNESCAPED_UNICODE));
            $ci = array_values(array_filter($ciWords, fn ($w) => str_contains($txt, $w)));
            check("K01-$lang-" . str_replace(' ', '', $name), "New-device email ($lang) for a User-Agent with $name: sent, device line present, no CodeIgniter fallback text", $okl && $m !== [] && $ci === [] && isset($dm[2]), json_encode([$okl, $ci, $dm[0] ?? null], JSON_UNESCAPED_UNICODE));
            $seenTxt["mail $lang $name"] = $dm[2] ?? '';
            // the user's own sessions page, in the user's language
            $hs = G($u, 'rolewarden/sessions', "k $lang own sessions $name");
            $t = text_of($hs);
            $ci = array_values(array_filter($ciWords, fn ($w) => str_contains($t, $w)));
            check("K02-$lang-" . str_replace(' ', '', $name), "Own sessions page ($lang) after a sign-in with $name User-Agent: no CodeIgniter fallback text", $u->status === 200 && $ci === [], json_encode([$u->status, $ci]));
        }
    }
    // the administrator's view of each user's sessions, in English and in Italian
    foreach (['en', 'it'] as $plang) {
        $hp = G($own, 'rolewarden/profile', "owner k $plang"); $lf = null; foreach (forms($hp) as $f) if (str_contains($f['action'], 'profile/language')) $lf = $f;
        if ($lf) P($own, resolve($own->url, $lf['action']), array_replace($lf['fields'], ['language' => $plang]), "owner $plang");
        foreach ($users as $ul => $id) {
            $h = G($own, "rolewarden/users/$id", "k admin $plang user $ul");
            $xp = dom($h); $rows = [];
            foreach ($xp->query('//table//tbody/tr') as $tr) $rows[] = trim(preg_replace('/\s+/u', ' ', $tr->textContent));
            $t = text_of($h);
            $ci = array_values(array_filter($ciWords, fn ($w) => str_contains($t, $w)));
            info("K panel $plang, sessions of the $ul user: " . json_encode(array_slice($rows, 0, 6), JSON_UNESCAPED_UNICODE));
            $seenTxt["panel $plang $ul"] = implode(' | ', $rows);
            check("K03-$plang-$ul", "User page ($plang) of the $ul account: sessions listed, no CodeIgniter fallback text", $own->status === 200 && $ci === [] && count($rows) >= 3, json_encode([$own->status, $ci, count($rows)]));
        }
    }
    // Italian page and Italian email: no word found only in the English language files (dictionary of phase T)
    if (isset($only)) {
        $bad = [];
        foreach ($seenTxt as $k => $v) if (preg_match('/^(mail|panel) it/', $k)) foreach ($words(preg_replace('/\b[a-z0-9-]+\.[a-z0-9.-]+\b|\S+@\S+|\d+(\.\d+)*/u', ' ', $v)) as $w) if (isset($only[$w]) && ! isset($dataWords[$w])) $bad[$k][] = $w;
        check('K04', 'Italian sessions lists and Italian emails: no word that exists only in the English language files', $bad === [], json_encode($bad, JSON_UNESCAPED_UNICODE));
    }
}

/* ================================================================== X0 / X1: behind the reverse proxy */
function xff_client(string $name, string $ip): Client { $c = c($name); $c->extra = ["X-Forwarded-For: $ip"]; return $c; }
if (in_array('X0', $PHASES, true) || in_array('X1', $PHASES, true)) {
    $trusted = in_array('X1', $PHASES, true);
    $tag = $trusted ? 'X1' : 'X0';
    echo "\n== $tag: behind nginx (HTTPS terminated, X-Forwarded-For / X-Forwarded-Proto), \$proxyIPs " . ($trusted ? 'set as installation.html#proxy' : 'not set') . "\n";
    // nginx (verify-s3.docker/nginx.conf) forwards X-Forwarded-For with $proxy_add_x_forwarded_for: the visitor's own header, then nginx's peer
    $own = xff_client("$tag-owner", '203.0.113.10');
    login2($own, OWNER, OPW, true);
    $ck = (string) @file_get_contents($own->jar);
    $sessLine = preg_grep('/\tci_session\t/', explode("\n", $ck));
    $setc = [];
    $probe = xff_client("$tag-probe", '203.0.113.10');
    $probe->req('GET', 'login'); $setc[] = $probe->headers['set-cookie'] ?? '';
    // remember-me: raw Set-Cookie headers of a sign-in with "Remember me"
    $raw = (function () use ($tag) {
        $ch = curl_init(BASE . 'login'); $jar = sys_get_temp_dir() . "/s3-$tag-raw.jar";
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => ['X-Forwarded-For: 203.0.113.10']]);
        $r = (string) curl_exec($ch);
        preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $r, $m);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf_test_name' => $m[1] ?? '', 'email' => OWNER, 'password' => OPW, 'remember' => 'on'])]);
        $r2 = (string) curl_exec($ch); curl_close($ch); @unlink($jar);
        return $r . "\n----\n" . $r2;
    })();
    preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $raw, $sc);
    $cookies = array_map('trim', $sc[1]);
    info("$tag Set-Cookie headers: " . json_encode($cookies));
    preg_match('/^Location:\s*(\S+)/mi', substr($raw, strpos($raw, '----')), $lm);
    $loc2 = isset($lm[1]) ? resolve(BASE . 'login', $lm[1]) : '';
    $remember = array_values(array_filter($cookies, fn ($x) => stripos($x, 'remember') === 0));
    $session = array_values(array_filter($cookies, fn ($x) => stripos($x, 'ci_session') === 0));
    $secureAll = $cookies !== [] && ! array_filter(array_merge($session, $remember), fn ($x) => stripos($x, '; secure') === false);
    if ($trusted) {
        check('X101', 'Installer behind the proxy wrote app.baseURL with https:// (the address the visitor used)', str_starts_with($GLOBALS['ENVMAP']['app.baseURL'] ?? '', 'https://'), $GLOBALS['ENVMAP']['app.baseURL'] ?? '(not installed in this run)');
        check('X102', 'Redirect after sign-in is https, inside the application', str_starts_with($loc2, 'https://') && inside($loc2), $loc2);
        check('X103', 'Session and Remember-me cookies: Secure, HttpOnly, sent for the application path (cookie.secure = true as documented)', $session !== [] && $remember !== [] && $secureAll && ! array_filter(array_merge($session, $remember), fn ($x) => stripos($x, 'httponly') === false), json_encode(array_merge($session, $remember)));
    } else {
        info('X0 baseURL proposed by the installer behind the proxy (no $proxyIPs): ' . ($GLOBALS['PREFILL'] ?? '?') . ' / written: ' . ($GLOBALS['ENVMAP']['app.baseURL'] ?? '?'));
        info("X0 redirect after sign-in: $loc2; cookies Secure: " . ($secureAll ? 'yes' : 'no'));
    }
    // which address does the application see?
    $ip1 = (string) q1("SELECT ip_address FROM auth_logins WHERE identifier=? ORDER BY id DESC LIMIT 1", [OWNER]);
    $sessT = in_array('acl_sessions', tables(), true) ? q('SELECT * FROM acl_sessions WHERE user_id=? ORDER BY id DESC LIMIT 1', [uid(OWNER)]) : [];
    $sessIp = ''; foreach ($sessT[0] ?? [] as $k => $v) if (str_contains($k, 'ip')) $sessIp = (string) $v;
    $actT = q('SELECT * FROM acl_activity_log ORDER BY id DESC LIMIT 1');
    $actIp = ''; foreach ($actT[0] ?? [] as $k => $v) if (str_contains($k, 'ip')) $actIp = (string) $v;
    $hs = G($own, 'rolewarden/sessions', 'own sessions');
    info("$tag IP seen: auth_logins=$ip1 acl_sessions=$sessIp activity=$actIp; sessions page shows 203.0.113.10: " . (str_contains($hs, '203.0.113.10') ? 'yes' : 'no'));
    // the per-IP limit: lower it from the Settings screen, two visitors
    $hs = G($own, 'rolewarden/settings', 'settings x');
    $fs = settings_form($hs);
    $rateKey = null; foreach (($fs['types'] ?? []) as $k => $t) if (preg_match('/rate|attempts.*(ip|address)|per.?ip/i', $k)) $rateKey = $k;
    info("$tag settings fields: " . json_encode($fs['types'] ?? []) . " -> per-address field: " . ($rateKey ?? 'none'));
    if ($fs && $rateKey) { $vals = $fs['fields']; $vals[$rateKey] = '5'; P($own, resolve($own->url, $fs['action']), $vals, 'rate 5'); }
    $rateSaved = q("SELECT `key`, value FROM settings WHERE `key` LIKE '%ate%'");
    info("$tag sign-in rate setting: " . json_encode($rateSaved));
    $a = []; for ($i = 0; $i < 6; $i++) { $v = xff_client("$tag-va$i", '198.51.100.21'); $h = G($v, 'login', 'va login'); $f = form_with($h, 'password'); P($v, resolve($v->url, $f['action'] ?? 'login'), array_replace($f['fields'] ?? [], ['email' => "nobody$i@s3.test", 'password' => 'x']), "visitor A attempt $i"); $a[] = $v->status . ($v->status === 429 || str_contains(text_of(follow($v)), 'many') ? 'L' : ''); }
    $va = xff_client("$tag-va-ok", '198.51.100.21'); $okA = login2($va, OWNER, OPW);
    $a[] = 'right-password:' . ($okA ? 'signed-in' : 'refused');
    $vb = xff_client("$tag-vb", '198.51.100.22'); $okB = login2($vb, OWNER, OPW);
    $bInfo = "{$vb->status} " . short(text_of($vb->body), 100);
    info("$tag visitor A (198.51.100.21) attempts: " . json_encode($a) . "; visitor B (198.51.100.22) signs in: " . ($okB ? 'yes' : 'no') . " $bInfo");
    $logIps = array_column(q("SELECT DISTINCT ip_address FROM auth_logins"), 'ip_address');
    info("$tag addresses in auth_logins: " . json_encode($logIps));
    if ($trusted) {
        check('X104', 'Sign-in recorded with the visitor\'s address (203.0.113.10), not the proxy\'s', $ip1 === '203.0.113.10', $ip1);
        $px = $O['proxyip'] ?? '172.30.5.10';
        $seenIps = array_column(q('SELECT ip_address FROM auth_logins'), 'ip_address'); foreach (q('SELECT * FROM acl_sessions') as $r) foreach ($r as $k => $v) if (str_contains($k, 'ip')) $seenIps[] = $v;
        $actAll = q('SELECT * FROM acl_activity_log'); foreach ($actAll as $r) foreach ($r as $k => $v) if (str_contains($k, 'ip')) $seenIps[] = $v;
        info('X1 addresses stored in sessions, sign-ins and activity: ' . json_encode(array_count_values(array_map('strval', $seenIps))));
        check('X105', 'Sessions list and activity log carry the visitor\'s address (203.0.113.10 for the owner), never the proxy\'s (' . $px . ')', $sessIp === '203.0.113.10' && ! in_array($px, $seenIps, true) && str_contains($hs . G($own, 'rolewarden/sessions', 'own sessions 2'), '203.0.113.10') && in_array('203.0.113.10', $seenIps, true), "session=$sessIp activity=$actIp");
        check('X106', 'Per-IP sign-in limit: visitor A is stopped after the limit, visitor B with another address still signs in', $rateKey && ! $okA && $okB, json_encode([$a, $okB]));
        if (! empty($GLOBALS['MAILS'])) { $mi = array_filter($GLOBALS['MAILS'], fn ($m) => preg_match('/\b\d{1,3}(\.\d{1,3}){3}\b/', $m['Text'] ?? '')); $ips = []; foreach ($mi as $m) { preg_match_all('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', $m['Text'], $mm); $ips = array_merge($ips, $mm[0]); } check('X107', 'Security emails name the visitor\'s address, never the proxy\'s', $ips !== [] && ! in_array($O['proxyip'] ?? '172.30.5.10', $ips, true), json_encode(array_values(array_unique($ips)))); }
        // a client talking to Apache directly (not a listed proxy) cannot choose its address
        if ($O['direct'] !== '') {
            $d = new Client($O['direct'], 'direct'); $d->extra = ['X-Forwarded-For: 192.0.2.77', 'X-Forwarded-Proto: https'];
            $h = $d->get('login'); preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $h, $m);
            $d->req('POST', 'login', ['csrf_test_name' => $m[1] ?? '', 'email' => 'direct@s3.test', 'password' => 'x']);
            $dip = (string) q1("SELECT ip_address FROM auth_logins WHERE identifier='direct@s3.test' ORDER BY id DESC LIMIT 1");
            check('X108', 'X-Forwarded-For sent by a client that is not a listed proxy is ignored (direct request to Apache)', $dip !== '' && $dip !== '192.0.2.77', $dip);
        }
    } else {
        $proxyIp = $ip1;
        check('X001', 'Without $proxyIPs (documented consequence): every visitor has the proxy\'s address', $ip1 !== '' && ! str_starts_with($ip1, '203.0.113.') && count(array_filter($logIps, fn ($x) => str_starts_with((string) $x, '198.51.100.') || str_starts_with((string) $x, '203.0.113.'))) === 0, json_encode($logIps));
        check('X002', 'Without $proxyIPs (documented consequence): the per-IP limit counts everyone together, visitor B is stopped too', $rateKey && ! $okA && ! $okB, json_encode([$a, $okB, $bInfo]));
    }
}

/* ================================================================== N: addresses without index.php */
if (in_array('N', $PHASES, true)) {
    echo "\n== N: installation.html#index-page, app.indexPage = '' with Apache mod_rewrite and public/.htaccess\n";
    $env = env_map(dcat(APPD . '/.env'));
    $ht = dcat(APPD . '/public/.htaccess');
    [$rc, $mods] = dx('apache2ctl -M 2>/dev/null', null, 'root');
    check('N00', "Set-up as documented: app.indexPage = '' in .env, CodeIgniter's public/.htaccess with RewriteEngine On, mod_rewrite loaded", array_key_exists('app.indexPage', $env) && $env['app.indexPage'] === '' && str_contains($ht, 'RewriteEngine On') && str_contains($mods, 'rewrite_module'), json_encode([$env['app.indexPage'] ?? 'absent', str_contains($ht, 'RewriteEngine On'), str_contains($mods, 'rewrite_module')]));
    [$rc] = spark2(['demo:install']);
    $own = c('owner-n'); login2($own, OWNER, OPW);
    $pages = [];
    foreach (screens($GLOBALS['UID'] ?? 1, $GLOBALS['RID'] ?? 1) as $sc) $pages[] = [$own, $sc];
    $an = c('anon-n');
    foreach (['login', 'register', 'login/magic-link', 'rolewarden/install'] as $sc) $pages[] = [$an, $sc];
    $rd = c('reader-n'); login2($rd, 'reader@example.com', DPW); $pages[] = [$rd, 'demo'];
    $au = c('author-n'); login2($au, 'author@example.com', DPW); $pages[] = [$au, 'demo']; $pages[] = [$au, 'demo/create'];
    $withIdx = []; $links = []; $actions = []; $pageBad = [];
    foreach ($pages as [$cl, $sc]) {
        $h = G($cl, $sc, "N page $sc");
        // the installer after installation refuses with "Already installed" (I10, installation.html): expected, not broken
        if ($cl->status >= 400 && ! ($sc === 'rolewarden/install' && str_contains(text_of($h), 'Already installed'))) $pageBad[] = "$sc {$cl->status}";
        $xp = dom(preg_replace('/<div id="debug-bar".*$/s', '', $h));
        foreach (page_urls($h, $cl->url) as $u) if (str_contains($u, 'index.php')) $withIdx[] = "$sc -> $u";
        foreach ($xp->query('//a[@href]|//link[@href]|//script[@src]|//img[@src]') as $n) {
            $v = trim($n->getAttribute('href') ?: $n->getAttribute('src'));
            if ($v === '' || preg_match('#^(javascript:|mailto:|tel:|data:|\#)#i', $v)) continue;
            $abs = preg_replace('/#.*$/', '', resolve($cl->url, $v));
            if (! inside($abs) || preg_match('#/logout$#', $abs)) continue;
            $links[$abs] = [$cl, $sc];
        }
        foreach (forms($h) as $f) $actions[] = "$sc -> " . resolve($cl->url, $f['action']);
    }
    check('N01', 'With indexPage empty, the panel, demo, sign-in and installer pages answer (' . count($pages) . ' pages)', $pageBad === [], json_encode($pageBad));
    check('N02', 'No link, asset, form action or script URL of those pages contains index.php', $withIdx === [] && ! array_filter($actions, fn ($a) => str_contains($a, 'index.php')), json_encode(array_slice(array_merge($withIdx, array_values(array_filter($actions, fn ($a) => str_contains($a, 'index.php')))), 0, 15)));
    $broken = [];
    foreach ($links as $abs => [$cl, $sc]) { $t = new Client(BASE, 'n-link'); $t->jar = $cl->jar; G($t, $abs, "N link $abs"); if ($t->status >= 400) $broken[] = "$sc -> $abs {$t->status}"; }
    info('N distinct links and assets followed: ' . count($links));
    check('N03', 'Every link and asset on those pages answers without error (no 4xx/5xx): ' . count($links) . ' distinct addresses', $links !== [] && $broken === [], json_encode(array_slice($broken, 0, 15)));
    $redirIdx = array_values(array_filter($GLOBALS['REDIRS'], fn ($r) => str_contains($r[1], 'index.php')));
    check('N04', 'No redirect of the whole run (' . count($GLOBALS['REDIRS']) . ') goes to an address with index.php', $redirIdx === [], json_encode(array_slice($redirIdx, 0, 10)));
    $murls = []; foreach ($GLOBALS['MAILS'] ?? [] as $m) foreach (mail_urls($m) as $x) $murls[] = $x;
    $murls = array_values(array_unique($murls));
    $mbad = [];
    foreach ($murls as $x) { $t = new Client(BASE, 'n-mail'); $t->jar = $own->jar; G($t, $x, "N mail link $x"); if (str_contains($x, 'index.php') || $t->status >= 400) $mbad[] = "$x {$t->status}"; }
    check('N05', 'Links in the security emails: without index.php and working (' . count($murls) . ')', $murls !== [] && $mbad === [], json_encode([$murls, $mbad]));
    // the forms of the panel were submitted in phase U with these actions: U05-U13 show they work without index.php
    $cssOk = false; foreach (array_keys($links) as $u) if (str_contains($u, '.css')) $cssOk = true;
    info('N panel stylesheet among the followed links: ' . ($cssOk ? 'yes' : 'no'));
    [$rc] = spark2(['demo:uninstall']);
}

/* ================================================================== X2: proxy with the 1.0.0 nginx lines */
if (in_array('X2', $PHASES, true)) {
    echo "\n== X2: nginx with proxy_set_header X-Forwarded-For \$remote_addr (installation.html#proxy, 1.0.0), \$proxyIPs set\n";
    $px = $O['proxyip'] ?? '172.30.5.10';
    exec('docker network inspect s3-net --format "{{(index .IPAM.Config 0).Gateway}}" 2>&1', $gw);
    $peer = trim($gw[0] ?? '');
    info("X2 proxy $px; address nginx sees for every client of this machine (gateway of s3-net): $peer");
    $FORGED = '203.0.113.10';
    $own = xff_client('X2-owner', $FORGED);
    $lok = login2($own, OWNER, OPW, true);
    $raw = (function () use ($FORGED) {
        $ch = curl_init(BASE . 'login'); $jar = sys_get_temp_dir() . '/s3-X2-raw.jar';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => ["X-Forwarded-For: $FORGED"]]);
        $r = (string) curl_exec($ch);
        preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $r, $m);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf_test_name' => $m[1] ?? '', 'email' => OWNER, 'password' => OPW, 'remember' => 'on'])]);
        $r2 = (string) curl_exec($ch); curl_close($ch); @unlink($jar);
        return $r . "\n----\n" . $r2;
    })();
    preg_match_all('/^Set-Cookie:\s*(.+)$/mi', $raw, $sc);
    $cookies = array_map('trim', $sc[1]);
    preg_match('/^Location:\s*(\S+)/mi', substr($raw, strpos($raw, '----')), $lm);
    $loc2 = isset($lm[1]) ? resolve(BASE . 'login', $lm[1]) : '';
    $remember = array_values(array_filter($cookies, fn ($x) => stripos($x, 'remember') === 0));
    $session = array_values(array_filter($cookies, fn ($x) => stripos($x, 'ci_session') === 0));
    check('X201', 'Installer behind the proxy wrote app.baseURL with https:// (X-Forwarded-Proto from a listed proxy)', str_starts_with($GLOBALS['ENVMAP']['app.baseURL'] ?? '', 'https://'), $GLOBALS['ENVMAP']['app.baseURL'] ?? '?');
    check('X202', 'Sign-in works through the proxy; redirect https, inside the application', $lok && str_starts_with($loc2, 'https://') && inside($loc2), $loc2);
    check('X203', 'Session and Remember-me cookies Secure and HttpOnly (cookie.secure = true)', $session !== [] && $remember !== [] && ! array_filter(array_merge($session, $remember), fn ($x) => stripos($x, '; secure') === false || stripos($x, 'httponly') === false), json_encode(array_merge($session, $remember)));
    // a sign-in from a device never used, with a forged header: the new-device email
    mailpit('DELETE', 'messages');
    $nd = xff_client('X2-newdev', '203.0.113.77'); $nd->extra[] = 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) S3-Agent-X Firefox/141.0';
    login2($nd, OWNER, OPW);
    wait_mail(1);
    $mails = mail_to(OWNER);
    $mips = []; foreach ($mails as $m) { preg_match_all('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', ($m['Text'] ?? '') . ' ' . strip_tags($m['HTML'] ?? ''), $mm); $mips = array_merge($mips, $mm[0]); }
    $mips = array_values(array_unique($mips));
    $ip1 = (string) q1('SELECT ip_address FROM auth_logins WHERE identifier=? ORDER BY id DESC LIMIT 1', [OWNER]);
    $seen = array_column(q('SELECT ip_address FROM auth_logins'), 'ip_address');
    foreach (['acl_sessions', 'acl_activity_log'] as $t) foreach (q("SELECT * FROM `$t`") as $r) foreach ($r as $k => $v) if (str_contains($k, 'ip') && $v !== null) $seen[] = (string) $v;
    $cnt = array_count_values(array_map('strval', $seen));
    info('X2 addresses stored in sign-ins, sessions and activity: ' . json_encode($cnt));
    $hs = G($own, 'rolewarden/sessions', 'X2 own sessions');
    check('X204', "Sign-in recorded with the address nginx saw ($peer), not the one the visitor wrote ($FORGED) nor the proxy's", $ip1 === $peer && $peer !== '', $ip1);
    check('X205', 'No address written by a visitor in X-Forwarded-For, and not the proxy\'s, in sign-ins, sessions, activity or the sessions page', ! array_filter(array_keys($cnt), fn ($x) => str_starts_with((string) $x, '203.0.113.') || str_starts_with((string) $x, '198.51.100.') || $x === $px) && ! str_contains($hs, '203.0.113.') && str_contains($hs, $peer), json_encode([$cnt, str_contains($hs, $peer)]));
    check('X206', 'New-device email sent, with the real address and not the forged one', $mails !== [] && in_array($peer, $mips, true) && ! array_filter($mips, fn ($x) => str_starts_with($x, '203.0.113.')), json_encode([array_column($mails, 'Subject'), $mips]));
    // before the limit test: every client of this machine reaches Apache and nginx from the same address
    if ($O['direct'] !== '') {
        $d = new Client($O['direct'], 'direct'); $d->extra = ['X-Forwarded-For: 192.0.2.77', 'X-Forwarded-Proto: https'];
        $h = $d->get('login'); preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $h, $m);
        $d->req('POST', 'login', ['csrf_test_name' => $m[1] ?? '', 'email' => 'direct@s3.test', 'password' => 'x']);
        $dip = (string) q1("SELECT ip_address FROM auth_logins WHERE identifier='direct@s3.test' ORDER BY id DESC LIMIT 1");
        check('X208', 'X-Forwarded-For sent straight to Apache by a client that is not a listed proxy is ignored', $dip !== '' && $dip !== '192.0.2.77', $dip);
    }
    // per-IP limit down to 5 from the Settings screen, then failed sign-ins each with a different forged address
    $hs = G($own, 'rolewarden/settings', 'settings x2');
    $fs = settings_form($hs);
    $rateKey = null; foreach (($fs['types'] ?? []) as $k => $t) if (preg_match('/rate|attempts.*(ip|address)|per.?ip/i', $k)) $rateKey = $k;
    if ($fs && $rateKey) { $vals = $fs['fields']; $vals[$rateKey] = '5'; P($own, resolve($own->url, $fs['action']), $vals, 'rate 5'); }
    info('X2 sign-in rate setting: ' . json_encode(q("SELECT `key`, value FROM settings WHERE `key` LIKE '%ate%'")) . " field $rateKey");
    $a = [];
    for ($i = 0; $i < 6; $i++) { $v = xff_client("X2-va$i", '198.51.100.' . (21 + $i)); $h = G($v, 'login', 'va login'); $f = form_with($h, 'password'); P($v, resolve($v->url, $f['action'] ?? 'login'), array_replace($f['fields'] ?? [], ['email' => "nobody$i@s3.test", 'password' => 'x']), "X2 forged attempt $i"); $a[] = $v->status . ($v->status === 429 || str_contains(text_of(follow($v)), 'many') ? 'L' : ''); }
    $vb = xff_client('X2-vb', '198.51.100.99'); $okB = login2($vb, OWNER, OPW);
    $bInfo = "{$vb->status} " . short(text_of(follow($vb)), 120);
    $failIps = array_column(q("SELECT DISTINCT ip_address FROM auth_logins WHERE identifier LIKE 'nobody%'"), 'ip_address');
    info('X2 attempts with forged addresses: ' . json_encode($a) . '; right password with yet another forged address: ' . ($okB ? 'signed in' : 'refused') . " $bInfo; addresses of the failures: " . json_encode($failIps));
    check('X207', 'Per-IP limit: changing X-Forwarded-For at each attempt does not get round it; after the limit the right password is refused too', $rateKey && ! $okB && $failIps === [$peer], json_encode([$a, $okB, $failIps]));
}

/* ================================================================== Z: database switched off while in use */
if (in_array('Z', $PHASES, true)) {
    echo "\n== Z: database switched off in the middle of use (" . $O['dbct'] . ")\n";
    $own = c('owner-z'); $lz = login2($own, OWNER, OPW);
    $uidZ = 1; $ridZ = 1;
    $h = G($own, 'rolewarden/roles', 'roles z'); if (preg_match('#rolewarden/roles/(\d+)#', $h, $m)) $ridZ = (int) $m[1];
    $h = G($own, 'rolewarden/users', 'users z'); if (preg_match('#rolewarden/users/(\d+)#', $h, $m)) $uidZ = (int) $m[1];
    $forms = [];
    foreach (screens($uidZ, $ridZ) as $s) { $h = G($own, $s, "z up $s"); foreach (forms($h) as $f) if ($f['method'] === 'post') $forms[resolve($own->url, $f['action'])] = $f; }
    $rd = c('demo-z'); [$rc] = spark2(['demo:install']); login2($rd, 'author@example.com', DPW); $hd = G($rd, 'demo', 'demo z'); foreach (forms($hd) as $f) if ($f['method'] === 'post') $forms[resolve($rd->url, $f['action'])] = $f;
    check('Z00', 'Before: signed in, ' . count($forms) . ' POST forms collected', $lz && count($forms) > 5);
    exec('docker stop ' . escapeshellarg($O['dbct']) . ' 2>&1', $o1, $rc1);
    info('database container stopped: rc=' . $rc1);
    $bad = []; $codes = [];
    $hits = array_merge(screens($uidZ, $ridZ), ['', 'login', 'register', 'rolewarden/install', 'demo', 'demo/create', 'logout']);
    foreach ($hits as $s) { foreach ([$own, c('anon-z')] as $cl) { $h = G($cl, $s, "z down $s"); $codes[] = "$s:{$cl->status}"; if (leaks_sql($h) || phperr(text_of($h)) || preg_match('/s3-db|3306|rolewarden_test|mysqli|MySQLi|DBDriver|hostname/', text_of($h))) $bad[] = "GET $s {$cl->status} " . short(text_of($h), 200); } }
    foreach ($forms as $act => $f) { $cl = str_contains($act, '/demo') ? $rd : $own; $h = P($cl, $act, $f['fields'], "z down POST $act"); $codes[] = "POST " . parse_url($act, PHP_URL_PATH) . ":{$cl->status}"; if (leaks_sql($h) || phperr(text_of($h)) || preg_match('/s3-db|3306|mysqli|MySQLi|DBDriver/', text_of($h))) $bad[] = "POST $act {$cl->status} " . short(text_of($h), 200); }
    $an = c('anon-z2'); $h = G($an, 'login', 'z down login form'); $f = form_with($h, 'password');
    P($an, resolve($an->url, $f['action'] ?? 'login'), array_replace($f['fields'] ?? [], ['email' => OWNER, 'password' => OPW]), 'z down login post'); if (leaks_sql($an->body) || phperr(text_of($an->body))) $bad[] = 'POST login ' . short(text_of($an->body), 200);
    $codes[] = "POST login:{$an->status}";
    info('answers with the database off: ' . implode(' ', $codes));
    check('Z01', 'Database off: no SQL, driver, host or PHP error text on any page or form answer (' . count($hits) * 2 + count($forms) + 1 . ' requests)', $bad === [], json_encode(array_slice($bad, 0, 10)));
    $logs = uptime_logs();
    check('Z02', 'The failure is written to writable/logs', (bool) preg_grep('/(Unable to connect|Connection refused|getaddrinfo|php_network_getaddresses|No such host|Name or service not known|server has gone away|Connection timed out)/i', $logs), short(implode(' | ', array_slice(bad_lines($logs), -3)), 500));
    exec('docker start ' . escapeshellarg($O['dbct']) . ' 2>&1', $o2, $rc2);
    for ($i = 0; $i < 60; $i++) { [$r] = dx('php', '<?php mysqli_report(MYSQLI_REPORT_OFF); $m = @new mysqli(' . var_export($O['dbhost'], true) . ', ' . var_export((string) getenv('RW_DB_USERNAME'), true) . ', getenv("ZP"), ' . var_export($O['dbname'], true) . ', ' . (int) $O['dbport'] . '); exit($m->connect_errno ? 1 : 0);', 'www-data', ['ZP' => (string) getenv('RW_DB_PASSWORD')]); if ($r === 0) break; usleep(500000); }
    $own2 = c('owner-z2'); $back = login2($own2, OWNER, OPW); G($own2, 'rolewarden/users', 'z back users');
    check('Z03', 'Database back: the application works again without intervention', $back && $own2->status === 200, "{$own2->status}");
}

/* ================================================================== L: logs */
if (in_array('L', $PHASES, true)) {
    echo "\n== L: logs (error_reporting -1)\n";
    $lines = uptime_logs();
    $allow = ['/SecurityException|The action you requested is not allowed/', '/BadRequestException: The URI you submitted has disallowed characters/', '/Unable to connect to the database|Connection refused|getaddrinfo|server has gone away|php_network_getaddresses|Name or service not known/'];
    if (! in_array('Z', $PHASES, true)) $allow = [$allow[0], $allow[1]];
    $bad = bad_lines($lines, $allow);
    file_put_contents(__DIR__ . '/verify-u1.s3.' . $O['label'] . '.applog.txt', implode("\n", $lines) . "\n");
    check('L01', 'Application log (threshold 9): no WARNING, NOTICE, DEPRECATED, ERROR or CRITICAL line beyond the refusals the suite provoked', $bad === [], json_encode(array_slice($bad, 0, 8)));
    $pe = trim(dcat('/var/log/php-errors.log'));
    check('L02', 'PHP error log of the server (error_reporting = -1, log_errors = On): empty', $pe === '', short($pe, 800));
    exec('docker logs ' . escapeshellarg(CT) . ' 2>&1', $dl);
    $dlb = array_values(array_filter($dl, fn ($l) => preg_match('/PHP (Warning|Notice|Deprecated|Fatal|Parse)|\[php:(warn|notice|error)/i', $l)));
    [$rc, $ae] = dx('cat /var/log/apache2/error.log 2>/dev/null', null, 'root');
    $aeb = array_values(array_filter(preg_split('/\R/', $ae), fn ($l) => preg_match('/PHP |\[php:|\[core:(error|crit)|segfault/i', $l)));
    check('L03', 'Apache error log and container output: no PHP warning, notice, deprecation or fatal error', $dlb === [] && $aeb === [], json_encode(array_slice(array_merge($dlb, $aeb), 0, 8)));
    $bad5 = []; $badTxt = [];
    foreach ($GLOBALS['SEEN'] as [$tag, $st, $url, $body, $ctype]) {
        if (! str_contains((string) $ctype, 'html') && ! str_contains((string) $ctype, 'json') && $ctype !== '') continue;
        if ($st >= 500 && ! str_starts_with($tag, 'z down') && ! str_starts_with($tag, 'fuzz')) $bad5[] = "$tag $st";
        if (! str_starts_with($tag, 'z down') && (leaks_sql((string) $body) || phperr(text_of((string) $body)))) $badTxt[] = $tag;
    }
    check('L04', 'No 500 answer and no SQL or PHP error text in any of the ' . count($GLOBALS['SEEN']) . ' answers of the run', $bad5 === [] && $badTxt === [], json_encode([array_slice($bad5, 0, 10), array_slice($badTxt, 0, 10)]));
    $offR = array_values(array_filter($GLOBALS['REDIRS'], fn ($r) => ! inside($r[1]) && ! str_starts_with($r[0], 'direct')));
    check('L05', 'Every redirect of the run stays inside ' . BASE . ' (' . count($GLOBALS['REDIRS']) . ' redirects)', $offR === [], json_encode(array_slice($offR, 0, 10)));
}

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
printf("\nTOTAL: %d PASS, %d FAIL, %d AMB\n", $pass, $fail, count($GLOBALS['ambiguities']));
foreach ($GLOBALS['results'] as $r) if (! $r[2]) echo "FAIL {$r[0]} {$r[1]}\n";
exit($fail ? 1 : 0);
