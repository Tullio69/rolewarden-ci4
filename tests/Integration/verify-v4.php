<?php
/**
 * V4 "Email di sicurezza": independent black-box verification (Collaudatore ad Hoc, Claude).
 * Assertions come only from docs/SPEC.md (V4 decisions, Sistema di notifiche, Modello dati),
 * docs/BRIEF-v1.0.md (V4, definition of done) and README ("Security emails", "Updating the
 * module", "Sign-in rules"). Form fields and actions are read from the served HTML; email
 * content only from the messages captured by Mailpit (http://localhost:8026, SMTP 127.0.0.1:1026).
 * Started by verify-v4.run.py:  php verify-v4.php <app> <module-3805640> <module-2028ea2> v4 <env>
 * Only rolewarden_test on 127.0.0.1:3317; credentials only from RW_DB_* process variables.
 */
declare(strict_types=1);
require __DIR__ . '/verify-v3.lib.php';
require __DIR__ . '/verify-v4.recheck.php';

$ENVN = $argv[5] ?? 'development';
$WORKDIR = dirname($APP);
const MP = 'http://localhost:8026/api/v1/';
const UA_CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
const UA_CHROME_WIN_NEXT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.6668.58 Safari/537.36';
const UA_CHROME_LINUX = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
const UA_FF_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';
const UA_SAFARI_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15';
const UA_EDGE_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.2739.42';
const UA_MARKUP = 'Opera/9.80 (X11; <b>RWV4</b>; <img src=x onerror=alert(1)>) Presto/2.12.388 Version/12.16';

function info(string $s): void { echo "[INFO] $s\n"; }

// ---------- Mailpit ----------
function mp(string $method, string $path, bool $json = true) {
    $ch = curl_init(MP . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10]);
    $b = curl_exec($ch); $s = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($s !== 200) { throw new RuntimeException("Mailpit $method $path HTTP $s"); }
    return $json ? json_decode((string) $b, true) : (string) $b;
}
function mp_clear(): void { mp('DELETE', 'messages'); }
function rcpts(array $m): array {
    return array_map(fn ($a) => strtolower($a['Address']), array_merge($m['To'] ?? [], $m['Cc'] ?? [], $m['Bcc'] ?? []));
}
/** All captured messages, after letting shutdown-time sends finish. */
function mails(float $wait = 1.5): array { usleep((int) ($wait * 1e6)); return mp('GET', 'messages?limit=500')['messages'] ?? []; }
function to_addr(array $ms, string $a): array { return array_values(array_filter($ms, fn ($m) => in_array(strtolower($a), rcpts($m), true))); }
function full(array $m): array { $f = mp('GET', 'message/' . $m['ID']); $f['Raw'] = mp('GET', 'message/' . $m['ID'] . '/raw', false); return $f; }
function show(string $tag, array $f): void {
    recheck_mail($tag, $f);
    $txt = trim(preg_replace('/\s+/', ' ', (string) ($f['Text'] ?? '')));
    info("$tag From=" . ($f['From']['Name'] ?? '') . ' <' . ($f['From']['Address'] ?? '') . '> To=' . json_encode(array_column($f['To'] ?? [], 'Address')) . ' Subject=' . json_encode($f['Subject'] ?? '') . ' HTML=' . (($f['HTML'] ?? '') === '' ? 'none' : strlen($f['HTML']) . 'B') . ' Text=' . json_encode(mb_substr($txt, 0, 700)));
}
/** No secret appears in the decoded text, decoded HTML or raw source. */
function secret_free(array $f, array $secrets): bool {
    foreach ($secrets as $s) {
        if ($s === '' || $s === null) continue;
        foreach (['Text', 'HTML', 'Raw', 'Subject'] as $k) if (str_contains((string) ($f[$k] ?? ''), $s)) return false;
    }
    return true;
}
function has_today(string $t): bool {
    foreach ([-86400, 0, 86400] as $d) {
        $ts = time() + $d;
        foreach (['Y-m-d', 'j F Y', 'F j, Y', 'M j, Y', 'j M Y', 'd/m/Y', 'd.m.Y', 'D, j M Y', 'd M Y'] as $fmt) if (str_contains($t, date($fmt, $ts))) return true;
    }
    return false;
}

// ---------- HTTP helpers ----------
$NC = 0;
function login_ua(string $name, string $email, string $pw, string $ua, bool $raw = false): Client {
    global $NC; $NC++;
    $c = new Client('v4-' . $name . '-' . $NC);
    $c->req('GET', 'login', null, ['User-Agent: ' . $ua]);
    $f = matching(forms($c->body), 'Login'); unset($f['fields']['remember']);
    $c->req('POST', $f['action'], array_replace($f['fields'], ['email' => $email, 'password' => $pw]), ['User-Agent: ' . $ua]);
    $GLOBALS['SEEN'][] = ['login ' . $email, $c->status, $c->body];
    return $c;
}
function ok_login(Client $c): bool { return in_array($c->status, [302, 303], true) && ! str_contains($c->location, '/login'); }
function alerts4(string $h): string {
    if (trim($h) === '') return '';
    $d = new DOMDocument(); @$d->loadHTML($h); $xp = new DOMXPath($d); $t = [];
    foreach ($xp->query('//*[@role="alert"]') as $n) $t[] = trim(preg_replace('/\s+/', ' ', $n->textContent));
    return implode(' | ', $t);
}
/** One failed attempt; returns the alert text shown afterwards. */
function fail_once(string $email, string $pw = 'wrong-V4-pass', string $ua = UA_CHROME_WIN): string {
    $c = login_ua('fail', $email, $pw, $ua);
    if (ok_login($c)) return 'SIGNED-IN';
    $h = in_array($c->status, [302, 303], true) ? follow($c) : $c->body;
    return alerts4($h);
}
function locked_msg(string $m): bool { return (bool) preg_match('/(too many|try again|locked|wait)/i', $m); }
function tables(): array { $t = array_map(fn ($r) => array_values($r)[0], q('SHOW TABLES')); sort($t); return $t; }
function hash_of(int $uid): string { return (string) q1("SELECT secret2 FROM auth_identities WHERE user_id=? AND type='email_password'", [$uid]); }
function history(int $uid, string $email, string $ip, string $ua, int $success, string $when = '-1 day'): void {
    q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', [$ip, $ua, 'email_password', $email, $uid, date('Y-m-d H:i:s', strtotime($when)), $success]);
}
/** Collect application log lines, archive them for the report and start fresh. */
function logs_take(string $label): array {
    global $APP, $WORKDIR; $lines = [];
    foreach (glob("$APP/writable/logs/*.log") as $f) { $lines = array_merge($lines, file($f, FILE_IGNORE_NEW_LINES)); unlink($f); }
    file_put_contents("$WORKDIR/verify-v4.phase-$label.log", implode("\n", $lines) . "\n", FILE_APPEND);
    return $lines;
}
function bad_lines(array $lines, array $allow = []): array {
    $bad = [];
    foreach ($lines as $l) {
        if (! preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)) continue;
        if (str_contains($l, 'SecurityException') || str_contains($l, 'The action you requested is not allowed')) continue;
        foreach ($allow as $re) if (preg_match($re, $l)) continue 2;
        $bad[] = $l;
    }
    return $bad;
}
function restart(string $bind): void { server_stop(); $GLOBALS['RW_BIND'] = $bind; server_start(); }
function sh(array $cmd): array {
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); return [proc_close($p), trim($o)];
}
function mailpit_up(): bool {
    for ($i = 0; $i < 60; $i++) { try { mp('GET', 'messages'); return true; } catch (Throwable $e) { usleep(500000); } }
    return false;
}
function events_file(): string { global $APP; return "$APP/writable/rw-v4-mail-events.jsonl"; }
function events(): array {
    $out = [];
    foreach (@file(events_file(), FILE_IGNORE_NEW_LINES) ?: [] as $l) { $a = json_decode($l, true); $out[] = is_array($a) ? ($a[0] ?? $a) : $l; }
    return $out;
}
function effective_email(): array {
    // config:check refuses to print in production; Config\Email does not depend on the environment.
    global $APP; $env = ci_env(); $env['CI_ENVIRONMENT'] = 'development';
    $p = proc_open(['php', 'spark', 'config:check', 'Email'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $APP, $env, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); proc_close($p);
    $r = [];
    foreach (['protocol', 'SMTPHost', 'SMTPPort', 'SMTPCrypto', 'fromEmail'] as $k) {
        $r[$k] = preg_match('/public ' . $k . ' -> (?:string \(\d+\) "([^"]*)"|integer (\d+))/', $o, $m) ? ($m[1] !== '' ? $m[1] : ($m[2] ?? '')) : (preg_match('/\["' . $k . '"\]=>\s*(?:string\(\d+\) "([^"]*)"|int\((\d+)\))/', $o, $m) ? ($m[1] !== '' ? $m[1] : ($m[2] ?? '')) : '?');
    }
    if (in_array('?', $r, true)) info('config:check output head: ' . json_encode(substr(redact($o), 0, 400)));
    return $r;
}
function set_env_line(string $key, string $value): void {
    global $APP; $e = file_get_contents("$APP/.env");
    $e = preg_match('/^' . preg_quote($key, '/') . ' = .*$/m', $e) ? preg_replace('/^' . preg_quote($key, '/') . ' = .*$/m', $key . ' = ' . $value, $e) : $e . "$key = $value\n";
    file_put_contents("$APP/.env", $e);
    mail_guard();
}

$ALLOW_EXPECTED = [];
try {
    mail_guard();
    echo "== 1 Upgrade from 3805640, rollback, fresh install ==\n";
    reset_db(); use_module($MOD_OLD); readme_install();
    $eff = effective_email();
    info('Effective Config\\Email (spark config:check, selected keys): ' . json_encode($eff));
    check('S00', 'Effective mail settings point at Mailpit (smtp 127.0.0.1:1026) before any HTTP request', $eff['protocol'] === 'smtp' && $eff['SMTPHost'] === '127.0.0.1' && $eff['SMTPPort'] === '1026', json_encode($eff));
    if ($eff['protocol'] !== 'smtp' || $eff['SMTPHost'] !== '127.0.0.1' || $eff['SMTPPort'] !== '1026') { fwrite(STDERR, "ABORT: effective mail settings not on Mailpit\n"); exit(3); }
    mp_clear();
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    $before = acl_state(); $tBefore = tables(); $batch = (int) q1('SELECT MAX(batch) FROM migrations');
    $GLOBALS['RW_BIND'] = '127.0.0.1';
    server_start();
    $adm = login2('up-admin', $U['admin2']['email'], $pw);
    // Sensitivity on the baseline (INFO only): login field as array, and login without CSRF token.
    $b0 = new Client('v4-base-arr'); $b0->get('login'); $f = matching(forms($b0->body), 'Login'); unset($f['fields']['remember']);
    $b0->req('POST', $f['action'], array_replace($f['fields'], ['email' => ['x@v4.test'], 'password' => 'y'])); $st0 = $b0->status; $b0->get('login');
    info("Baseline 3805640: login with email[]: POST HTTP $st0, next GET /login HTTP {$b0->status}");
    $b1 = new Client('v4-base-csrf'); $b1->get('login'); $f = matching(forms($b1->body), 'Login'); unset($f['fields']['remember'], $f['fields']['csrf_test_name']);
    $b1->req('POST', $f['action'], array_replace($f['fields'], ['email' => $U['child']['email'], 'password' => $pw]));
    info('Baseline 3805640: login POST without CSRF token: HTTP ' . $b1->status . ' signed_in=' . json_encode(ok_login($b1)));
    logs_take('baseline-sensitivity');
    check('U01', 'V3 baseline: no security.alerts permission', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug='security.alerts'") === 0);
    server_stop(); use_module($MOD_NEW); server_start();
    $h = page($adm, 'rolewarden/users');
    check('U02', 'Folder replaced, before migrate: signed-in panel still usable', $adm->status === 200 && ! leaks_sql($h), 'HTTP ' . $adm->status);
    $w = login_ua('window', $U['subject']['email'], $pw, UA_FF_LINUX);
    check('U02b', 'Folder replaced, before migrate: sign-in from a new device works (no 500, no SQL)', ok_login($w) && ! leaks_sql($w->body), 'HTTP ' . $w->status . ' ' . $w->location);
    info('Messages captured in the pre-migrate window: ' . count(mails()));
    spark('migrate', '-n', 'RoleWarden');
    check('U03', 'Upgrade grants security.alerts to admin', in_array('security.alerts', role_perms('admin'), true));
    $holders = array_column(q("SELECT r.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug='security.alerts' ORDER BY r.slug"), 'slug');
    check('U03b', 'Only admin gets security.alerts from the data migration', $holders === ['admin'], json_encode($holders));
    check('U03c', 'Upgrade adds no table (SPEC Modello dati lists none for V4)', tables() === $tBefore, json_encode(array_values(array_diff(tables(), $tBefore))));
    check('U03d', 'Upgraded admin panel works', page($adm, 'rolewarden/users') !== '' && $adm->status === 200);
    server_stop(); spark('migrate:rollback', '-b', (string) $batch, '-f');
    check('U04', 'Rollback restores ACL, migrations and tables exactly', acl_state() === $before && tables() === $tBefore);
    use_module($MOD_OLD); server_start();
    check('U05', 'Restored 3805640 folder works after rollback', page($adm, 'rolewarden/users') !== '' && $adm->status === 200);
    server_stop(); use_module($MOD_NEW); spark('migrate', '-n', 'RoleWarden');
    check('U06', 'Upgrade applies again after rollback', in_array('security.alerts', role_perms('admin'), true));
    reset_db(); readme_install();
    check('U07', 'Fresh install grants security.alerts to admin', in_array('security.alerts', role_perms('admin'), true));
    $rb = (int) q1("SELECT MIN(batch) FROM migrations WHERE namespace='RoleWarden'") - 1;
    spark('migrate:rollback', '-b', (string) $rb, '-f');
    check('U08', 'Fresh install rolls back cleanly (no acl_ table left)', q("SHOW TABLES LIKE 'acl\\_%'") === []);
    spark('migrate', '-n', 'RoleWarden'); spark('db:seed', 'RoleWarden\\Database\\Seeds\\RoleWardenSeeder');
    logs_take('upgrade');
    mp_clear();

    echo "== Fixtures ==\n";
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    $cols = q('SHOW COLUMNS FROM acl_user_permissions');
    info('acl_user_permissions columns: ' . json_encode(array_map(fn ($c) => $c['Field'] . ':' . $c['Type'], $cols)));
    $flag = null; foreach ($cols as $c) if (! in_array($c['Field'], ['id', 'user_id', 'permission_id', 'created_at', 'updated_at'], true)) $flag = $c['Field'];
    $mk = function (string $key, ?string $role) use ($pw) { return ['id' => add_user('V4' . $key, $key . '@v4.test', $role, $pw), 'email' => $key . '@v4.test']; };
    $N = $mk('newdev', 'user'); $Q = $mk('peruser', 'user'); $R = $mk('onlyfailed', 'user'); $S = $mk('dbhistory', 'user');
    $P = $mk('pwchange', 'user'); $K = $mk('lock', 'user'); $K2 = $mk('lockfive', 'user'); $K3 = $mk('lockreset', 'user');
    $T = [$mk('late1', 'user'), $mk('late2', 'user'), $mk('late3', 'user'), $mk('late4', 'user')];
    $X = [$mk('ext1', 'user'), $mk('ext2', 'user'), $mk('ext3', 'user'), $mk('ext4', 'user')];
    $G = $mk('ovrgrant', 'user'); $D = $mk('ovrdeny', 'admin');
    $permId = (int) q1("SELECT id FROM acl_permissions WHERE slug='security.alerts'");
    if ($flag !== null) {
        q("INSERT INTO acl_user_permissions (user_id, permission_id, `$flag`) VALUES (?,?,?)", [$G['id'], $permId, 1]);
        q("INSERT INTO acl_user_permissions (user_id, permission_id, `$flag`) VALUES (?,?,?)", [$D['id'], $permId, 0]);
    }
    q('INSERT INTO users (username, active, created_at, updated_at) VALUES (?,1,NOW(),NOW())', ['<i>V4esc</i>']);
    $E = ['id' => (int) db()->insert_id, 'email' => 'esc@v4.test'];
    q("INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,'email_password',?,?,0,NOW(),NOW())", [$E['id'], $E['email'], password_hash($pw, PASSWORD_DEFAULT)]);
    q('INSERT INTO acl_user_roles (user_id, role_id) SELECT ?, id FROM acl_roles WHERE slug=?', [$E['id'], 'user']);
    // Users that need a known sign-in history (declared DB inserts into Shield's auth_logins).
    foreach (array_merge($T, $X, [$E, $P]) as $u) history($u['id'], $u['email'], '127.0.0.1', 'fixture', 1);
    server_start();
    $adm = login2('admin', $U['admin2']['email'], $pw);
    settings($adm, ['sign_in_rate' => '100', 'lock_attempts' => '3', 'lock_minutes' => '1']); cache_clear();
    $d0 = defaults($adm);
    info('Sign-in settings now: ' . json_encode(array_intersect_key($d0, array_flip(['lock_attempts', 'lock_minutes', 'sign_in_rate']))));
    mp_clear();

    echo "== 2 New device or IP ==\n";
    $c = login_ua('n01', $N['email'], $pw, UA_CHROME_WIN); $ms = mails();
    check('N01', 'Very first sign-in: succeeds, no email', ok_login($c) && to_addr($ms, $N['email']) === [], count($ms) . ' msgs');
    $ip0 = (string) q1('SELECT ip_address FROM auth_logins WHERE user_id=? AND success=1 ORDER BY id DESC LIMIT 1', [$N['id']]);
    info('Recorded IP of the first sign-in: ' . $ip0);
    $c = login_ua('n02', $N['email'], $pw, UA_CHROME_WIN); $ms = mails();
    check('N02', 'Same device and IP again: no email', ok_login($c) && to_addr($ms, $N['email']) === []);
    $c = login_ua('n03', $N['email'], $pw, UA_CHROME_WIN_NEXT); $ms = mails();
    check('N03', 'Same browser and system, other browser version, same IP: no email (device = browser and system)', ok_login($c) && to_addr($ms, $N['email']) === [], count(to_addr($ms, $N['email'])) . ' email(s)');
    mp_clear();
    $c = login_ua('n04', $N['email'], $pw, UA_FF_LINUX); $ms = mails();
    $mine = to_addr($ms, $N['email']);
    check('N04', 'New device (Firefox on Linux), known IP: exactly one email to the user', ok_login($c) && count($mine) === 1 && count($ms) === 1, count($mine) . ' to user, ' . count($ms) . ' total');
    if ($mine) {
        $f = full($mine[0]); show('new-device', $f); $txt = $f['Text'] . ' ' . strip_tags((string) $f['HTML']);
        check('C01', 'New sign-in email: recipient is only the user', array_column($f['To'], 'Address') === [$N['email']] && ($f['Cc'] ?? []) === [] && ($f['Bcc'] ?? []) === []);
        check('C02', 'Sender is the host Config\\Email from (noreply@rolewarden.test / RoleWarden Test)', $f['From']['Address'] === 'noreply@rolewarden.test' && $f['From']['Name'] === 'RoleWarden Test', json_encode($f['From']));
        check('C03', 'Subject present, about a sign-in', trim($f['Subject']) !== '' && preg_match('/sign|log|access|device/i', $f['Subject']), $f['Subject']);
        check('C04', 'Text names the device (browser and system)', str_contains($txt, 'Firefox') && str_contains($txt, 'Linux'));
        check('C05', 'Text names the address', str_contains($txt, $ip0));
        check('C06', 'Text carries the date', has_today($txt));
        check('C07', 'No password or hash in the email', secret_free($f, [$pw, hash_of($N['id'])]));
    }
    mp_clear();
    $c = login_ua('n05', $N['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('N05', 'That device again: no email', ok_login($c) && to_addr($ms, $N['email']) === []);
    $c = login_ua('n06', $N['email'], $pw, UA_CHROME_LINUX); $ms = mails();
    check('N06', 'Known browser on a system never paired with it (Chrome on Linux): one email', ok_login($c) && count(to_addr($ms, $N['email'])) === 1, count(to_addr($ms, $N['email'])) . ' email(s)');
    mp_clear();
    $c = login_ua('n07', $N['email'], $pw, UA_EDGE_WIN); $ms = mails();
    info('Edge on Windows after Chrome on Windows (Edge UA also contains "Chrome/"): ' . count(to_addr($ms, $N['email'])) . ' email(s)');
    mp_clear();
    $c = login_ua('n08', $N['email'], 'wrong-V4-pass', UA_SAFARI_MAC); $ms = mails();
    check('N08', 'Failed sign-in from a new device: no email', ! ok_login($c) && to_addr($ms, $N['email']) === []);
    $c = login_ua('n09', $N['email'], $pw, UA_SAFARI_MAC); $ms = mails();
    check('N09', 'Success from a device seen only in a failed attempt: one email', ok_login($c) && count(to_addr($ms, $N['email'])) === 1, count(to_addr($ms, $N['email'])) . ' email(s)');
    if ($m = to_addr($ms, $N['email'])) { $f = full($m[0]); check('C04b', 'Text names Safari and macOS/Mac', str_contains($f['Text'] . $f['HTML'], 'Safari') && preg_match('/mac/i', $f['Text'] . $f['HTML'])); }

    echo "-- new IP over HTTP: server rebound to [::1] --\n";
    restart('[::1]'); mp_clear();
    $c = login_ua('n10', $N['email'], $pw, UA_CHROME_WIN); $ms = mails();
    $ip1 = (string) q1('SELECT ip_address FROM auth_logins WHERE user_id=? ORDER BY id DESC LIMIT 1', [$N['id']]);
    info("Recorded IP now: $ip1 (was $ip0)");
    $mine = to_addr($ms, $N['email']);
    check('N10', 'New IP with a known device: exactly one email', $ip1 !== $ip0 && ok_login($c) && count($mine) === 1, "ip=$ip1 " . count($mine) . ' email(s)');
    if ($mine) { $f = full($mine[0]); show('new-ip', $f); check('C05b', 'Text names the new address', str_contains($f['Text'] . strip_tags((string) $f['HTML']), $ip1)); check('C06b', 'Text carries the date', has_today($f['Text'] . strip_tags((string) $f['HTML']))); }
    mp_clear();
    $c = login_ua('n11', $N['email'], $pw, UA_CHROME_WIN); $ms = mails();
    check('N11', 'Same new IP and device again: no email', ok_login($c) && to_addr($ms, $N['email']) === []);
    $c = login_ua('n12', $N['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('N12', 'IP seen and device seen (never together): no email', ok_login($c) && to_addr($ms, $N['email']) === [], count(to_addr($ms, $N['email'])) . ' email(s)');
    // Per-user history (declared DB insert): Q signed in once before, from another IP and device.
    history($Q['id'], $Q['email'], '10.20.30.40', UA_SAFARI_MAC, 1);
    $c = login_ua('n13', $Q['email'], $pw, UA_CHROME_WIN); $ms = mails();
    check('N13', 'History is per user: device and IP used by another user are new for this one', ok_login($c) && count(to_addr($ms, $Q['email'])) === 1);
    history($R['id'], $R['email'], '::1', UA_CHROME_WIN, 0, '-2 hours'); history($R['id'], $R['email'], '10.9.9.9', UA_SAFARI_MAC, 0, '-1 hour');
    $c = login_ua('n14', $R['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('N14', 'Only failed attempts before: the first successful sign-in sends nothing', ok_login($c) && to_addr($ms, $R['email']) === []);
    history($S['id'], $S['email'], '::1', UA_FF_LINUX, 1, '-3 days');
    $c = login_ua('n15', $S['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('N15', 'Device and IP known from auth_logins history (DB insert): no email', ok_login($c) && to_addr($ms, $S['email']) === []);
    $c = login_ua('n16', $S['email'], $pw, UA_SAFARI_MAC); $ms = mails();
    check('N16', 'Same user, known IP, new device (history from DB insert): one email', ok_login($c) && count(to_addr($ms, $S['email'])) === 1);
    restart('127.0.0.1'); mp_clear();
    $c = login_ua('n17', $S['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('N17', 'Known device, IP never used by this user (127.0.0.1 vs history ::1): one email', ok_login($c) && count(to_addr($ms, $S['email'])) === 1);
    // Escaping: user name and user agent with markup.
    mp_clear();
    $c = login_ua('esc', $E['email'], $pw, UA_MARKUP); $ms = mails();
    $mine = to_addr($ms, $E['email']);
    check('N18', 'Markup user agent from a new device: one email', ok_login($c) && count($mine) === 1);
    if ($mine) {
        $f = full($mine[0]); show('markup', $f); $html = (string) $f['HTML'];
        info('HTML part present: ' . ($html === '' ? 'no' : 'yes') . '; escaped name present: ' . (str_contains($html, '&lt;i&gt;V4esc') ? 'yes' : 'no') . '; name in text: ' . (str_contains($f['Text'], 'V4esc') ? 'yes' : 'no'));
        check('C08', 'HTML part carries no raw markup from the user name or user agent', ! str_contains($html, '<i>V4esc') && ! str_contains($html, '<b>RWV4') && ! str_contains($html, '<img src=x') && ! str_contains($f['Subject'], '<i>'));
    }

    echo "== 3 Password changed ==\n";
    $p = login_ua('p', $P['email'], $pw, UA_CHROME_WIN); mails(); mp_clear();
    $h0 = hash_of($P['id']); $new1 = 'V4-' . bin2hex(random_bytes(5)) . '-Bb2!'; $new2 = 'V4-' . bin2hex(random_bytes(5)) . '-Cc3!';
    $prof = fn (array $v) => (function () use ($p, $v) { $f = matching(forms(page($p, 'rolewarden/profile')), 'Change password'); submit($p, $f, $v); return in_array($p->status, [302, 303], true) ? follow($p) : $p->body; })();
    $prof(['current_password' => 'not-the-password', 'password' => $new1, 'password_confirm' => $new1]); $ms = mails();
    check('P01', 'Wrong current password: nothing changes, no email', hash_of($P['id']) === $h0 && $ms === [], count($ms) . ' msgs');
    $prof(['current_password' => $pw, 'password' => 'abc', 'password_confirm' => 'abc']); $ms = mails();
    check('P02', 'Weak new password: refused, no email', hash_of($P['id']) === $h0 && $ms === []);
    $prof(['current_password' => $pw, 'password' => 'password', 'password_confirm' => 'password']); $ms = mails();
    check('P02b', 'Dictionary new password: refused, no email', hash_of($P['id']) === $h0 && $ms === []);
    $prof(['current_password' => $pw, 'password' => $new1, 'password_confirm' => $new1 . 'x']); $ms = mails();
    check('P03', 'Mismatched confirmation: refused, no email', hash_of($P['id']) === $h0 && $ms === []);
    $f = matching(forms(page($p, 'rolewarden/profile')), 'Change password'); unset($f['fields']['csrf_test_name']);
    submit($p, $f, ['current_password' => $pw, 'password' => $new1, 'password_confirm' => $new1]); $ms = mails();
    check('P04', 'Profile change without CSRF token: refused, no email', hash_of($P['id']) === $h0 && $ms === [] && $p->status !== 500, 'HTTP ' . $p->status);
    $prof(['current_password' => $pw, 'password' => $new1, 'password_confirm' => $new1]); $ms = mails();
    $mine = to_addr($ms, $P['email']);
    check('P05', 'Correct change from the profile: exactly one email, to the user', hash_of($P['id']) !== $h0 && count($mine) === 1 && count($ms) === 1, count($ms) . ' msgs');
    if ($mine) {
        $f = full($mine[0]); show('password-profile', $f);
        check('C09', 'Password email: To the user, From the host sender, subject about the password', array_column($f['To'], 'Address') === [$P['email']] && $f['From']['Address'] === 'noreply@rolewarden.test' && preg_match('/password/i', $f['Subject']));
        check('C10', 'Password email carries neither old/new password nor either hash', secret_free($f, [$pw, $new1, $h0, hash_of($P['id'])]));
        info('Password email mentions date: ' . (has_today($f['Text'] . strip_tags((string) $f['HTML'])) ? 'yes' : 'no') . ', address: ' . (str_contains($f['Text'] . $f['HTML'], '127.0.0.1') ? 'yes' : 'no'));
    }
    mp_clear();
    $editUrl = 'rolewarden/users/' . $P['id'] . '/edit';
    $h1 = hash_of($P['id']);
    $f = matching(forms(page($adm, $editUrl)), 'Save'); submit($adm, $f, ['password' => 'abc']); $st = $adm->status; $pg = after($adm); $ms = mails();
    $weakTaken = hash_of($P['id']) !== $h1;
    info('Admin sets "abc": HTTP ' . $st . ', password ' . ($weakTaken ? 'CHANGED' : 'unchanged') . ', ' . count($ms) . ' email(s) to ' . json_encode(array_merge(...array_map('rcpts', $ms ?: [[]]))) . ', field error shown: ' . (field_error($pg->body, 'password') ? 'yes' : 'no') . ', toasts ' . json_encode(static_toasts($pg->body)));
    check('P06', 'Admin-set weak password: an email is sent if and only if the password actually changed', $weakTaken ? count(to_addr($ms, $P['email'])) === 1 && count($ms) === 1 : $ms === []);
    mp_clear(); $h1 = hash_of($P['id']);
    $f = matching(forms(page($adm, $editUrl)), 'Save'); unset($f['fields']['csrf_test_name']); submit($adm, $f, ['password' => $new2]); $ms = mails();
    check('P07', 'Admin set without CSRF token: refused, no email', hash_of($P['id']) === $h1 && $ms === [] && $adm->status !== 500, 'HTTP ' . $adm->status);
    mp_clear(); $f = matching(forms(page($adm, $editUrl)), 'Save'); submit($adm, $f, ['username' => 'V4pwrenamed']); $ms = mails();
    check('P08', 'Admin edit without a password: no password email', hash_of($P['id']) === $h1 && $ms === [] && (string) q1('SELECT username FROM users WHERE id=?', [$P['id']]) === 'V4pwrenamed');
    mp_clear(); $f = matching(forms(page($adm, $editUrl)), 'Save'); submit($adm, $f, ['password' => $new2]); $ms = mails();
    $mine = to_addr($ms, $P['email']);
    check('P09', 'Admin sets a new password: exactly one email, to the user (not to the admin)', hash_of($P['id']) !== $h1 && count($mine) === 1 && count($ms) === 1 && to_addr($ms, $U['admin2']['email']) === [], count($ms) . ' msgs');
    if ($mine) { $f = full($mine[0]); show('password-admin', $f); check('C11', 'Admin-set password email carries neither password nor hash', secret_free($f, [$new1, $new2, $h1, hash_of($P['id'])])); }

    echo "== 4 Too many attempts ==\n";
    $alertees = [$U['owner']['email'], $U['admin2']['email'], $G['email']];
    $never = [$U['limited']['email'], $U['subject']['email'], $U['child']['email'], $U['disabled']['email'], $D['email'], $N['email']];
    info('Expected security.alerts holders: super admin (owner), admin2, positive override; not: users.view-only, user, child, disabled admin, admin with negative override' . ($flag === null ? ' (override columns not found!)' : " (override column `$flag`)"));
    mp_clear();
    $m1 = fail_once($K['email']); $m2 = fail_once($K['email']); $ms = mails();
    check('L01', 'Below the threshold (2 of 3 failures): no email at all', $ms === [], count($ms) . ' msgs');
    $m3 = fail_once($K['email']); $at3 = count(mails());
    $c = login_ua('l4', $K['email'], $pw, UA_CHROME_WIN); $blocked = ! ok_login($c); $m4 = alerts4(in_array($c->status, [302, 303], true) ? follow($c) : $c->body);
    info("Lock messages: 1=[$m1] 2=[$m2] 3=[$m3] 4(correct password)=[$m4]; messages after 3rd failure: $at3");
    check('L02', 'Configured threshold (3) blocks the next attempt even with the right password', $blocked && locked_msg($m4), $m4);
    fail_once($K['email']); fail_once($K['email']); $ms = mails();
    check('L03', 'User receives exactly one lock email', count(to_addr($ms, $K['email'])) === 1, count(to_addr($ms, $K['email'])) . ' email(s)');
    foreach ($alertees as $i => $a) check('L04-' . $i, "Holder of security.alerts receives exactly one lock alert ($a)", count(to_addr($ms, $a)) === 1, count(to_addr($ms, $a)) . ' email(s)');
    $wrong = []; foreach ($never as $a) if (to_addr($ms, $a)) $wrong[] = $a;
    check('L05', 'Nobody without security.alerts receives it (incl. disabled admin, negative override)', $wrong === [], json_encode($wrong));
    $rc = []; foreach ($ms as $m) $rc = array_merge($rc, rcpts($m));
    check('L06', 'One alert per lock, not per attempt (6 attempts, one per recipient)', count($rc) === count(array_unique($rc)) && count(array_unique($rc)) === 1 + count($alertees), json_encode(array_count_values($rc)));
    if ($m = to_addr($ms, $K['email'])) { $f = full($m[0]); show('lock-user', $f); check('C12', 'Lock email to the user: From the host sender, no password typed', $f['From']['Address'] === 'noreply@rolewarden.test' && secret_free($f, ['wrong-V4-pass', $pw, hash_of($K['id'])])); }
    if ($m = to_addr($ms, $U['admin2']['email'])) { $f = full($m[0]); show('lock-admin', $f); info('Admin alert names the locked email: ' . (str_contains($f['Text'] . $f['HTML'], $K['email']) ? 'yes' : 'no') . '; date: ' . (has_today($f['Text'] . strip_tags((string) $f['HTML'])) ? 'yes' : 'no') . '; address: ' . (str_contains($f['Text'] . $f['HTML'], '127.0.0.1') ? 'yes' : 'no')); check('C13', 'Admin alert carries no typed password', secret_free($f, ['wrong-V4-pass', $pw])); }
    mp_clear();
    q('UPDATE auth_logins SET date=DATE_SUB(date, INTERVAL 3 MINUTE) WHERE identifier=?', [$K['email']]);
    $n = 0; do { $msg = fail_once($K['email']); $n++; } while (! locked_msg($msg) && $n < 4);
    fail_once($K['email']); $ms = mails();
    info("Second lock after expiry reached after $n more attempt(s)");
    check('L07', 'After the lock expires, a new lock sends a new alert, once per recipient', count(to_addr($ms, $K['email'])) === 1 && count(to_addr($ms, $U['admin2']['email'])) === 1 && count($ms) >= 1, json_encode(array_count_values(array_merge(...array_map('rcpts', $ms ?: [[]])))));
    mp_clear();
    $ghost = 'ghost@v4.test';
    for ($i = 0; $i < 5; $i++) fail_once($ghost);
    $ms = mails();
    check('L08', 'Unregistered email: nothing sent to it', to_addr($ms, $ghost) === []);
    $ok = true; foreach ($alertees as $a) $ok = $ok && count(to_addr($ms, $a)) === 1;
    check('L09', 'Unregistered email: each security.alerts holder gets exactly one alert', $ok, json_encode(array_count_values(array_merge(...array_map('rcpts', $ms ?: [[]])))));
    $wrong = []; foreach ($never as $a) if (to_addr($ms, $a)) $wrong[] = $a;
    check('L10', 'Unregistered email: nobody without the permission receives it', $wrong === [], json_encode($wrong));
    mp_clear();
    $amp = 'x&y' . random_int(10, 99) . '@v4.test';
    for ($i = 0; $i < 4; $i++) fail_once($amp);
    $ms = mails();
    $rowsAmp = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE identifier=?', [$amp]);
    if ($m = to_addr($ms, $U['admin2']['email'])) {
        $f = full($m[0]); $html = (string) $f['HTML'];
        info('Alert for "' . $amp . '": HTML ' . ($html === '' ? 'none' : (str_contains($html, htmlspecialchars($amp)) ? 'contains escaped form' : 'no escaped form')) . '; raw form in HTML: ' . (str_contains($html, $amp) ? 'yes' : 'no'));
        check('C14', 'Typed email with & is escaped in the HTML alert', $html === '' || ! str_contains($html, $amp));
    } else info("No alert for $amp (auth_logins rows: $rowsAmp)");
    // A different threshold set from Settings.
    settings($adm, ['lock_attempts' => '5']); cache_clear(); mp_clear();
    for ($i = 0; $i < 3; $i++) fail_once($K2['email']);
    $ms3 = mails();
    fail_once($K2['email']); $ms4 = mails();
    check('L11', 'Threshold 5: no alert after 3 or 4 failures', $ms3 === [] && $ms4 === [], count($ms3) . '/' . count($ms4));
    $m5 = fail_once($K2['email']);
    $c = login_ua('l5', $K2['email'], $pw, UA_CHROME_WIN); $m6 = alerts4(in_array($c->status, [302, 303], true) ? follow($c) : $c->body); $ms = mails();
    check('L12', 'Threshold 5 blocks the sixth attempt', ! ok_login($c) && locked_msg($m6), $m6);
    check('L13', 'Threshold 5: the alert fires at the new threshold, once to the user and each holder', count(to_addr($ms, $K2['email'])) === 1 && count(to_addr($ms, $U['admin2']['email'])) === 1 && count(to_addr($ms, $U['owner']['email'])) === 1, json_encode(array_count_values(array_merge(...array_map('rcpts', $ms ?: [[]])))));
    settings($adm, ['lock_attempts' => '3']); cache_clear(); mp_clear();
    fail_once($K3['email']); fail_once($K3['email']);
    $okc = login_ua('l-reset', $K3['email'], $pw, UA_CHROME_WIN); mails(); mp_clear();
    fail_once($K3['email']); fail_once($K3['email']); $ms = mails();
    check('L14', 'Failures counted since the last success: 2 + success + 2 at threshold 3 sends no alert', ok_login($okc) && $ms === [], count($ms) . ' msgs');
    settings($adm, ['lock_attempts' => '20']); cache_clear();
    logs_take('main');

    echo "== 6 Sending after the response ==\n";
    mp_clear();
    $t = microtime(true); $c = login_ua('m0', $T[0]['email'], $pw, UA_FF_LINUX); $t0 = microtime(true) - $t; $ms = mails();
    info(sprintf('Baseline new-device sign-in with Mailpit up: %.2fs, HTTP %d, %d email(s)', $t0, $c->status, count(to_addr($ms, $T[0]['email']))));
    [$rc1, $o1] = sh(['docker', 'stop', '-t', '2', 'rolewarden-mail']);
    info("docker stop rolewarden-mail rc=$rc1 $o1");
    if ($rc1 !== 0) {
        echo "[BLOCKED] M01-M03/M05: Docker could not stop Mailpit; SMTP failure injection was not exercised.\n";
    } else {
    $stall = null;
    try {
        $t = microtime(true); $c = login_ua('m1', $T[1]['email'], $pw, UA_FF_LINUX); $t1 = microtime(true) - $t;
        $lb = $c->body; $ok1 = ok_login($c); $h = page($c, 'rolewarden/profile');
        check('M01', 'Mail server down: sign-in answers normally (redirect, then page 200), no error on screen', $ok1 && $c->status === 200 && ! preg_match('/(SMTP|Exception|fsockopen|Unable to send|Failed to authenticate)/i', clean_html($h . $lb)), sprintf('%.2fs HTTP %d', $t1, $c->status));
        info(sprintf('Sign-in with Mailpit stopped (connection refused): %.2fs', $t1));
        $code = '$s=@stream_socket_server("tcp://127.0.0.1:1026",$e,$es);if(!$s){fwrite(STDERR,"bind failed: $es\n");exit(1);}echo "listening\n";$h=[];$end=time()+45;while(time()<$end){$x=@stream_socket_accept($s,1);if($x)$h[]=$x;}';
        $stall = proc_open(['php', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $sp, null, null, ['bypass_shell' => true]);
        $first = fgets($sp[1]);
        if (trim((string) $first) !== 'listening') throw new RuntimeException('SMTP stall listener did not bind; test cannot continue');
        info('Stalling SMTP listener on 127.0.0.1:1026 (accepts, never greets; sends nothing anywhere): ' . trim((string) $first));
        $t = microtime(true); $c = login_ua('m2', $T[2]['email'], $pw, UA_FF_LINUX); $t2 = microtime(true) - $t;
        $lb = $c->body; $ok2 = ok_login($c); $h = page($c, 'rolewarden/profile');
        check('M02', 'Mail server hanging: sign-in answers normally, no error on screen', $ok2 && $c->status === 200 && ! preg_match('/(SMTP|Exception|fsockopen|Unable to send)/i', clean_html($h . $lb)), sprintf('%.2fs HTTP %d', $t2, $c->status));
        info(sprintf('Sign-in with a hanging SMTP server (host SMTPTimeout 5s): %.2fs', $t2));
        $GLOBALS['T_STALL'] = $t2;
    } finally {
        if ($stall) { proc_terminate($stall); proc_close($stall); info('Stalling listener closed'); }
        [$rc2, $o2] = sh(['docker', 'start', 'rolewarden-mail']);
        info("docker start rolewarden-mail rc=$rc2 $o2");
        if (! mailpit_up()) { throw new RuntimeException('Mailpit did not come back'); }
    }
    $lines = logs_take('smtp-down');
    $crit = array_values(array_filter($lines, fn ($l) => preg_match('/^(CRITICAL|ALERT|EMERGENCY) /', $l) || preg_match('/Uncaught/', $l)));
    info('Log lines at WARNING or above while the mail server was down: ' . json_encode(array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l))), JSON_UNESCAPED_SLASHES));
    check('M03', 'Mail server down/hanging: at most a handled send error in the log, no unhandled exception', $crit === [], json_encode($crit));
    $late = $GLOBALS['T_STALL'] ?? 0;
    info(sprintf('Response time with hanging SMTP %.2fs vs baseline %.2fs', $late, $t0));
    info('fastcgi_finish_request available to the php -S server: no (CLI built-in server SAPI)');
    if ($late < $t0 + 2) check('M05', 'Response does not wait for the SMTP server (hanging server adds < 2s)', true);
    else amb('M05', 'Response waits for a hanging SMTP server on the built-in server', sprintf('%.2fs vs %.2fs baseline; php -S has no fastcgi_finish_request, which SPEC names as the way the response leaves first', $late, $t0));
    }
    mp_clear();
    $c = login_ua('m4', $T[3]['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('M04', 'Mailpit available: the next new-device email is delivered', ok_login($c) && count(to_addr($ms, $T[3]['email'])) === 1);

    echo "== 7 Extension and override ==\n";
    $ev = events();
    $views = []; foreach ($ev as $e) if (is_array($e) && isset($e['view'])) $views[$e['view']] = true;
    $last = end($ev);
    info('rolewarden.mail payloads recorded: ' . count($ev) . '; keys of last: ' . json_encode(is_array($last) ? array_keys($last) : gettype($last)) . '; views: ' . json_encode(array_keys($views)));
    check('X01', 'rolewarden.mail fires with to, subject, view and data (README)', is_array($last) && count(array_intersect(['to', 'subject', 'view', 'data'], array_keys($last))) === 4, json_encode($last));
    $leak = []; foreach ($ev as $e) foreach ([$pw, $new1, $new2] as $s) if (str_contains(json_encode($e), $s)) $leak[] = $s;
    check('X02', 'No plain password in any rolewarden.mail payload', $leak === []);
    $hashes = array_filter(array_map(fn ($r) => $r['secret2'], q("SELECT secret2 FROM auth_identities WHERE type='email_password'")));
    $hl = 0; foreach ($ev as $e) foreach ($hashes as $hh) if (str_contains(json_encode($e, JSON_UNESCAPED_SLASHES), $hh)) $hl++;
    info("Password hashes found in rolewarden.mail payload data: $hl");
    touch("$APP/writable/rw-v4-block-mail"); mp_clear(); $n0 = count(events());
    $c = login_ua('x1', $X[0]['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('X03', 'A listener returning false takes the message over: nothing is sent', ok_login($c) && $ms === [] && count(events()) === $n0 + 1, count($ms) . ' msgs, events +' . (count(events()) - $n0));
    unlink("$APP/writable/rw-v4-block-mail");
    $c = login_ua('x2', $X[1]['email'], $pw, UA_FF_LINUX); $ms = mails();
    check('X04', 'Without that listener verdict the same email is sent again', ok_login($c) && count(to_addr($ms, $X[1]['email'])) === 1);
    // Overrides: names learnt from the documented payload `view`.
    $made = [];
    foreach (array_keys($views) as $v) {
        $rel = str_replace('\\', '/', ltrim($v, '\\'));
        if (! str_contains($rel, '/')) $rel = 'RoleWarden/Views/emails/' . $rel; // bare name: the README folder
        if (! str_starts_with($rel, 'RoleWarden/Views/emails/')) { info("View outside RoleWarden/Views/emails: $v"); continue; }
        $path = "$APP/app/Views/overrides/$rel" . (str_ends_with($rel, '.php') ? '' : '.php');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'RWV4-OVERRIDE ' . basename($rel) . "\n");
        $made[] = $path;
    }
    info('Override files created: ' . json_encode(array_map(fn ($p) => substr($p, strlen($APP) + 1), $made)));
    cache_clear(); mp_clear();
    $c = login_ua('x3', $X[2]['email'], $pw, UA_SAFARI_MAC); $ms = mails();
    $body = ($m = to_addr($ms, $X[2]['email'])) ? full($m[0]) : ['Text' => '', 'HTML' => ''];
    check('X05', 'Override of the new sign-in template in app/Views/overrides/RoleWarden/Views/emails/ is used', str_contains($body['Text'] . $body['HTML'], 'RWV4-OVERRIDE'), json_encode(mb_substr($body['Text'] . $body['HTML'], 0, 200)));
    $f = matching(forms(page($adm, 'rolewarden/users/' . $X[3]['id'] . '/edit')), 'Save'); submit($adm, $f, ['password' => $new2]); $ms = mails();
    $body = ($m = to_addr($ms, $X[3]['email'])) ? full($m[0]) : ['Text' => '', 'HTML' => ''];
    check('X06', 'Override of the password template is used', str_contains($body['Text'] . $body['HTML'], 'RWV4-OVERRIDE'));
    settings($adm, ['lock_attempts' => '3']); cache_clear(); mp_clear();
    for ($i = 0; $i < 4; $i++) fail_once('ovr-lock@v4.test');
    $ms = mails(); $body = ($m = to_addr($ms, $U['admin2']['email'])) ? full($m[0]) : ['Text' => '', 'HTML' => ''];
    check('X07', 'Override of the lock alert template is used', str_contains($body['Text'] . $body['HTML'], 'RWV4-OVERRIDE'));
    settings($adm, ['lock_attempts' => '20']); cache_clear();
    foreach ($made as $p) unlink($p);
    logs_take('extension');
    // Host without fromEmail.
    set_env_line('email.fromEmail', "''");
    $eff = effective_email(); info('Effective fromEmail now: ' . json_encode($eff['fromEmail']));
    restart('127.0.0.1'); mp_clear();
    $c = login_ua('x8', $X[0]['email'], $pw, UA_SAFARI_MAC); $pages = [];
    foreach (['rolewarden/users', 'rolewarden/profile', 'rolewarden/settings', 'rolewarden/activity'] as $u) { page($adm, $u); $pages[$u] = $adm->status; }
    $f = matching(forms(page($adm, 'rolewarden/users/' . $X[0]['id'] . '/edit')), 'Save'); submit($adm, $f, ['password' => $new1]); $afterSet = after($adm);
    $ms = mails(2.0);
    check('X08', 'No fromEmail: sign-in and admin password change still work', ok_login($c) && toast_ok(static_toasts($afterSet->body), 'success'), 'HTTP ' . $c->status);
    check('X09', 'No fromEmail: panel pages answer 200', array_unique(array_values($pages)) === [200], json_encode($pages));
    check('X10', 'No fromEmail: nothing is sent', $ms === [], count($ms) . ' msgs');
    $lines = logs_take('no-from');
    $warn = array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)));
    info('Log lines at WARNING or above without fromEmail: ' . json_encode($warn, JSON_UNESCAPED_SLASHES));
    check('X11', 'No fromEmail: a warning is logged (README), nothing worse', count(array_filter($warn, fn ($l) => str_starts_with($l, 'WARNING'))) >= 1 && ! array_filter($warn, fn ($l) => preg_match('/^(ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)));
    set_env_line('email.fromEmail', 'noreply@rolewarden.test');
    restart('127.0.0.1');

    echo "== 9 Publication ==\n";
    $a = new Client('v4-csrf-login'); $a->get('login'); $f = matching(forms($a->body), 'Login'); unset($f['fields']['csrf_test_name'], $f['fields']['remember']);
    $rows = (int) q1('SELECT COUNT(*) FROM auth_logins'); $a->req('POST', $f['action'], array_replace($f['fields'], ['email' => $N['email'], 'password' => $pw])); $rows2 = (int) q1('SELECT COUNT(*) FROM auth_logins');
    info('Login POST without CSRF token: HTTP ' . $a->status . ' location=' . $a->location . ' auth_logins +' . ($rows2 - $rows) . ' csrf_refused=' . json_encode(csrf_refused($a)) . ' signed_in=' . json_encode(ok_login($a)));
    if (! ok_login($a) && $rows2 === $rows && $a->status !== 500) check('Z01', 'Login POST without CSRF token is refused and not recorded', true);
    else amb('Z01', 'Login POST without CSRF token is accepted', 'HTTP ' . $a->status . ', signed in=' . json_encode(ok_login($a)) . '; Shield route, host global csrf filter off in the test app (also on 3805640)');
    $prior = stored(); $f = matching(forms(page($adm, 'rolewarden/settings')), 'Save settings'); unset($f['fields']['csrf_test_name']); submit($adm, $f, ['lock_attempts' => '4']);
    check('Z02', 'Settings POST without CSRF token is refused', stored() === $prior && $adm->status !== 500, 'HTTP ' . $adm->status);
    recheck_d1();
    $arr = [['email' => ['x@v4.test'], 'password' => 'y'], ['email' => ['a' => ['b' => 'x@v4.test']], 'password' => 'y'], ['email' => $N['email'], 'password' => [$pw]], ['email' => ['x'], 'password' => ['y']], ['email' => [$N['email']], 'password' => $pw]];
    foreach ($arr as $i => $vals) {
        $a = new Client('v4-arr-' . $i); $a->get('login'); $f = matching(forms($a->body), 'Login'); unset($f['fields']['remember']);
        $a->req('POST', $f['action'], array_replace($f['fields'], $vals));
        $b = in_array($a->status, [302, 303], true) ? follow($a) : $a->body;
        if (is_array($vals['password'])) { amb('Z03-' . $i, 'Login with password as array (email ' . (is_array($vals['email']) ? 'array' : 'string') . ')', 'HTTP ' . $a->status . '; raised inside Shield (see log: ValidationRules::max_byte TypeError), not by a module file'); continue; }
        check('Z03-' . $i, 'Login with array field(s) ' . json_encode(array_map(fn ($v) => is_array($v) ? 'array' : 'string', $vals)) . ': no 500, no PHP/SQL error, not signed in', $a->status !== 500 && ! ok_login($a) && ! leaks_sql($b) && ! preg_match('/(Array to string|TypeError|ErrorException|must be of type)/', clean_html($b)), 'HTTP ' . $a->status);
    }
    $ms = mails(); info('Messages after the array/CSRF posts: ' . count($ms));
    $leak = []; foreach ($GLOBALS['SEEN'] as [$u, $s, $b]) if (leaks_sql((string) $b) || $s === 500) $leak[] = "$u HTTP $s";
    check('Z04', 'No SQL error text and no HTTP 500 in any page seen (' . count($GLOBALS['SEEN']) . ')', $leak === [], json_encode(array_slice($leak, 0, 10)));
    $bad = bad_lines(logs_take('publication'));
    $all = []; foreach (['upgrade', 'main', 'extension', 'publication'] as $ph) if (is_file($pf = "$WORKDIR/verify-v4.phase-$ph.log")) $all = array_merge($all, bad_lines(file($pf, FILE_IGNORE_NEW_LINES), ['/ValidationRules::max_byte\(\)/']));
    check('Z05', 'Application log at threshold 9: no warning or error outside the deliberate SMTP-down and no-fromEmail phases', $all === [], implode("\n", array_slice($all, 0, 15)));
} finally {
    server_stop();
    foreach (glob("$APP/app/Views/overrides/RoleWarden/Views/emails/*") ?: [] as $p) @unlink($p);
    @unlink("$APP/writable/rw-v4-block-mail");
    if (is_file(events_file())) copy(events_file(), "$WORKDIR/verify-v4.mail-events.json");
}
$srv = @file("$WORKDIR/verify-v4.regression.v4.server.log", FILE_IGNORE_NEW_LINES) ?: [];
$php = array_values(array_filter($srv, fn ($l) => preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|Stack trace/i', $l)));
check('Z06', 'PHP built-in server log (error_reporting=-1): no PHP warning, notice, deprecation or fatal', $php === [], implode("\n", array_slice($php, 0, 10)));
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
echo 'V4 RESULT ' . count($GLOBALS['results']) . ' checks ' . $pass . ' PASS ' . (count($GLOBALS['results']) - $pass) . " FAIL\n";
exit($pass === count($GLOBALS['results']) ? 0 : 1);
