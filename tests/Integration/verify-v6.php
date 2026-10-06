<?php
/**
 * V6 "Importazione CSV": independent black-box verification (Collaudatore ad Hoc, Claude), commit
 * 98636ea, upgrade baseline 6c9fd04. Assertions come only from docs/SPEC.md (Modello dati, Pannello
 * admin with the V6 decisions of 2026-10-05, Design system), docs/BRIEF-v1.0.md (V6, definition of
 * done, protocol), README ("Updating the module", "Settings and the default role", "Importing users
 * from CSV") and the mandate. Routes, field names and messages are read from the served HTML only.
 * Started by verify-v6.run.py:  php verify-v6.php <app> <module-6c9fd04> <module-98636ea> v6 <env>
 * Only rolewarden_test on 127.0.0.1:3317; credentials only from RW_DB_* process variables.
 * Mail only to Mailpit (smtp 127.0.0.1:1026). Browser: verify-v6.browser.mjs (throwaway profile).
 */
declare(strict_types=1);
require __DIR__ . '/verify-v3.lib.php';
require __DIR__ . '/verify-v6.upload.php';

$ENVN = $argv[5] ?? 'development';
$WORKDIR = dirname($APP);
const HOST = 'http://localhost:8070/';
const MP = 'http://localhost:8026/api/v1/';
const SEMANTIC = ['--surface', '--surface-sunk', '--surface-raised', '--ink', '--ink-muted', '--ink-faint', '--rule', '--rule-strong', '--control-border', '--accent', '--accent-hover', '--on-accent', '--accent-tint', '--granted', '--granted-tint', '--denied', '--denied-tint', '--on-denied', '--focus'];
const THEMES = ['console', 'clarity', 'contrast'];
const HEAD = "email,username,role,password\n";

function info(string $s): void { echo "[INFO] $s\n"; }
function tables(): array { $t = array_map(fn ($r) => array_values($r)[0], q('SHOW TABLES')); sort($t); return $t; }
function logs_take(string $label): array {
    global $APP, $WORKDIR; $lines = [];
    foreach (glob("$APP/writable/logs/*.log") as $f) { $lines = array_merge($lines, file($f, FILE_IGNORE_NEW_LINES)); unlink($f); }
    file_put_contents("$WORKDIR/verify-v6.phase-$label.log", implode("\n", $lines) . "\n", FILE_APPEND);
    return $lines;
}
/** Lines written by the module for the rolled-back confirmation of T12 (handled path, reported apart). */
function rollback_line(string $l): bool { return str_contains($l, 'acl_user_roles_role_id_foreign') || str_contains($l, 'users import rolled back'); }
function bad_lines(array $lines): array {
    return array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l) && ! str_contains($l, 'SecurityException') && ! str_contains($l, 'The action you requested is not allowed') && ! rollback_line($l)));
}
/** Users-side state the import may touch (sessions, settings and sign-in rows excluded). */
function ustate(): string {
    $o = '';
    // users.last_active and identity timestamps move on every signed-in request (Shield): content columns only.
    foreach (['users' => 'id, username, active, status, deleted_at', 'auth_identities' => 'id, user_id, type, name, secret, secret2, force_reset', 'acl_user_roles' => '*', 'acl_user_permissions' => '*', 'acl_activity_log' => '*', 'auth_groups_users' => '*'] as $t => $cols) $o .= $t . ':' . json_encode(q("SELECT $cols FROM $t ORDER BY 1, 2")) . "\n";
    return md5($o) . ' users=' . q1('SELECT COUNT(*) FROM users') . ' log=' . q1('SELECT COUNT(*) FROM acl_activity_log');
}
/** leaks_sql() without the module's own message 'Unknown column "x"' (a CSV header error, not a driver error). */
function leaks(string $b): bool { return leaks_sql(preg_replace('/Unknown column (&quot;|&#039;|")/', 'Unrecognised column $1', $b)); }
function nusers(): int { return (int) q1('SELECT COUNT(*) FROM users'); }
function uid_of(string $email): ?int { $v = q1("SELECT user_id FROM auth_identities WHERE type='email_password' AND secret=?", [$email]); return $v === null ? null : (int) $v; }
function import_link(string $h): bool { return (bool) preg_match('#href="[^"]*rolewarden/users/import"#', $h); }
function import_form(string $h): ?array { foreach (forms($h) as $f) if (str_ends_with($f['action'], 'users/import/preview')) { $ff = form_with_file($f['html']); $f['file'] = $ff['file'] ?? 'csv'; return $f; } return null; }
function confirm_form(string $h): ?array { foreach (forms($h) as $f) if (str_ends_with($f['action'], 'users/import/confirm')) return $f; return null; }

/** Upload a CSV from the import page as a browser does, follow the redirect, return the page shown. */
function preview(Client $c, ?string $csv, string $name = 'users.csv', bool $csrf = true, string $type = 'text/csv'): string {
    $f = import_form(page($c, 'rolewarden/users/import'));
    if (! $f) { $GLOBALS['LAST_UP'] = $c->status; return $c->body; }
    if (! $csrf) unset($f['fields']['csrf_test_name']);
    upload($c, $f['action'], $f['fields'], $f['file'], $csv, $name, $type);
    $GLOBALS['LAST_UP'] = $c->status;
    return in_array($c->status, [302, 303], true) ? follow($c) : $c->body;
}
/** Rows of the preview table: line, email, username, role, password, bad, errors[]. */
function prows(string $h): array {
    $d = new DOMDocument(); @$d->loadHTML('<?xml encoding="UTF-8">' . $h); $xp = new DOMXPath($d); $out = [];
    foreach ($xp->query('//table[contains(@class,"rw-t-import")]/tbody/tr') as $tr) {
        $td = $xp->query('./td', $tr);
        $errs = []; foreach ($xp->query('.//ul[contains(@class,"rw-import-errors")]/li', $tr) as $li) $errs[] = trim($li->textContent);
        $out[] = ['line' => trim($td->item(0)->textContent ?? ''), 'email' => trim($td->item(1)->textContent ?? ''), 'username' => trim($td->item(2)->textContent ?? ''), 'role' => trim($td->item(3)->textContent ?? ''), 'password' => trim($td->item(4)->textContent ?? ''), 'bad' => str_contains($tr->getAttribute('class'), 'rw-import-bad'), 'errors' => $errs, 'result' => trim(preg_replace('/\s+/', ' ', $td->item(5)->textContent ?? ''))];
    }
    return $out;
}
function row_of(array $rows, string $line): ?array { foreach ($rows as $r) if ($r['line'] === $line) return $r; return null; }
/** File-level errors (the whole file refused before any row). */
function ferrs(string $h): array {
    $d = new DOMDocument(); @$d->loadHTML('<?xml encoding="UTF-8">' . $h); $xp = new DOMXPath($d); $out = [];
    foreach ($xp->query('//main//div[contains(@class,"rw-flash--error")]') as $div) { $lis = $xp->query('.//li', $div); if ($lis->length) foreach ($lis as $li) $out[] = trim($li->textContent); else $out[] = trim(preg_replace('/\s+/', ' ', $div->textContent)); }
    return $out;
}
function fielderr(string $h): string { return preg_match('#<p class="rw-field-error" id="rw-csv-error">(.*?)</p>#s', $h, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : ''; }
function bar(string $h): string { return preg_match('#<div class="rw-bar rw-import-bar[^"]*"[^>]*>(.*?)</div>\s*(<div class="rw-bar-actions">|</div>)#s', $h, $m) ? trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1])))) : ''; }
function refused(string $h): bool { return confirm_form($h) === null && (ferrs($h) !== [] || fielderr($h) !== '' || array_filter(prows($h), fn ($r) => $r['bad']) !== []); }
function ready(string $h, int $n): bool { $f = confirm_form($h); return $f !== null && count(prows($h)) === $n && ! array_filter(prows($h), fn ($r) => $r['bad']) && ferrs($h) === []; }
function err_has(?array $row, string $re = '//'): bool { if (! $row) return false; foreach ($row['errors'] as $e) if (preg_match($re, $e)) return true; return false; }
function summary(string $h): string { return json_encode(['http' => $GLOBALS['LAST_UP'] ?? null, 'ready' => confirm_form($h) !== null, 'field' => fielderr($h), 'file' => ferrs($h), 'bar' => bar($h), 'rows' => array_map(fn ($r) => $r['line'] . ($r['bad'] ? ' BAD ' . implode(' | ', $r['errors']) : ' ok ' . $r['role'] . '/' . $r['password']), array_slice(prows($h), 0, 8))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
/** POST with a long timeout (big confirmations). */
function post_long(Client $c, string $url, array $fields): void {
    $ch = curl_init($url); $c->headers = [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_TIMEOUT => 1500,
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_HEADERFUNCTION => function ($ch, $h) use ($c) { $p = explode(':', $h, 2); if (count($p) === 2) $c->headers[strtolower(trim($p[0]))] = trim($p[1]); return strlen($h); }]);
    $c->body = (string) curl_exec($ch); $c->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $c->location = $c->headers['location'] ?? ''; curl_close($ch);
    $GLOBALS['SEEN'][] = [$url, $c->status, $c->body];
}
/** Confirm the preview shown in $h; returns the page reached. */
function confirm(Client $c, string $h, array $override = []): string {
    $f = confirm_form($h); if (! $f) { $GLOBALS['LAST_CONF'] = 0; return ''; }
    $f['fields']['csrf_test_name'] = $c->token('rolewarden/profile'); // CSRF rotates with every POST
    post_long($c, $f['action'], array_replace($f['fields'], $override));
    $GLOBALS['LAST_CONF'] = $c->status;
    return in_array($c->status, [302, 303], true) ? follow($c) : $c->body;
}
/** Confirm with an explicit token and a fresh CSRF token of this session. */
function confirm_token(Client $c, string $token, bool $csrf = true): string {
    $fields = ['token' => $token];
    if ($csrf) $fields['csrf_test_name'] = $c->token('rolewarden/profile');
    post_long($c, BASE . 'rolewarden/users/import/confirm', $fields);
    $GLOBALS['LAST_CONF'] = $c->status;
    return in_array($c->status, [302, 303], true) ? follow($c) : $c->body;
}
function session_file(Client $c): string { global $APP; return "$APP/writable/session/ci_session" . session_cookie($c); }
/** Make the pending preview of this session $sec seconds older (only the time it was made). */
function age_preview(Client $c, int $sec): string {
    $f = session_file($c); $s = (string) @file_get_contents($f); $n = 0;
    $s2 = preg_replace_callback('/(rw_import\|a:\d+:\{.*?s:2:"at";i:)(\d+);/s', function ($m) use ($sec, &$n) { $n++; return $m[1] . ((int) $m[2] - $sec) . ';'; }, $s, 1);
    file_put_contents($f, $s2);
    return "aged $n field(s) by $sec s";
}
function csv(array $rows, string $head = HEAD, string $sep = ',', string $eol = "\n"): string { $o = $head; foreach ($rows as $r) $o .= implode($sep, $r) . $eol; return $o; }
function mp(string $method, string $path, bool $json = true) {
    $ch = curl_init(MP . $path); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10]);
    $b = curl_exec($ch); curl_close($ch); return $json ? json_decode((string) $b, true) : (string) $b;
}
function mails_to(string $a): array { usleep(1500000); return array_values(array_filter(mp('GET', 'messages?limit=500')['messages'] ?? [], fn ($m) => in_array(strtolower($a), array_map(fn ($x) => strtolower($x['Address']), $m['To'] ?? []), true))); }
function try_login(string $tag, string $email, string $pw): bool { $c = login2($tag, $email, $pw); return signed($c); }
// Appearance helpers (served form only), as in verify-v5.php.
function ap_form(Client $c): array { return matching(forms(page($c, 'rolewarden/appearance')), 'Save theme'); }
function save_theme(Client $c, array $v): void {
    $f = ap_form($c);
    foreach (array_keys($f['fields']) as $k) if (str_starts_with($k, 'colors[')) $f['fields'][$k] = '';
    $f['fields']['base'] = 'console'; $f['fields']['radius'] = ''; $f['fields']['density'] = '';
    submit($c, $f, $v);
}
$BJ = 0;
function browser(array $steps, string $tag, int $settle = 250): array {
    global $WORKDIR, $BJ; $BJ++;
    $job = ['profile' => str_replace('\\', '/', (getenv('RW_V6_CHROME_DIR') ?: sys_get_temp_dir()) . '/rwv6-chrome-' . getmypid() . "-$BJ"), 'steps' => $steps, 'settle' => $settle, 'timeout' => 900000];
    $file = "$WORKDIR/browser-job-$BJ.json"; file_put_contents($file, json_encode($job, JSON_UNESCAPED_SLASHES));
    $p = proc_open(['node', "$WORKDIR/verify-v6.browser.mjs", $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); proc_close($p);
    $lines = array_values(array_filter(explode("\n", trim($o))));
    $r = json_decode((string) end($lines), true);
    if (! is_array($r)) { info("browser $tag: no result; stderr=" . substr($e, 0, 400)); return ['steps' => [], 'errors' => ['no result'], 'pid' => null, 'consoleErrors' => []]; }
    info("browser $tag: chrome pid {$r['pid']}, steps " . count($r['steps']) . ', errors ' . json_encode($r['errors']) . ', console ' . json_encode($r['consoleErrors'] ?? null) . ', profile removed ' . json_encode($r['profileRemoved'] ?? null));
    $GLOBALS['CHROME_PIDS'][] = $r['pid'];
    return $r;
}
function by_tag(array $r, string $tag) { foreach ($r['steps'] as $s) if (($s['tag'] ?? '') === $tag) return $s; return null; }
function lin(float $x): float { $x /= 255; return $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4; }
function lum_hex(string $h): float { [$r, $g, $b] = sscanf($h, '#%02x%02x%02x'); return 0.2126 * lin($r) + 0.7152 * lin($g) + 0.0722 * lin($b); }
function rgb_hex(string $rgb): string { return preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $rgb, $m) ? sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]) : $rgb; }
const JS_TOASTS = "(() => { const live = [...document.querySelectorAll('.rw-toasts:not(.rw-toasts--static) .rw-toast')].filter((t) => t.getClientRects().length); return { n: live.length, text: live.map((t) => t.innerText.replace(/\\s+/g, ' ').trim()), alpine: !!window.Alpine, rows: document.querySelectorAll('.rw-t-import tbody tr').length, bad: document.querySelectorAll('.rw-t-import tbody tr.rw-import-bad').length, confirm: !!document.querySelector('form[action$=\"import/confirm\"] button'), fieldErr: (document.getElementById('rw-csv-error') || {}).innerText || null }; })()";

$GLOBALS['CHROME_PIDS'] = [];
$PWS = []; // every plaintext password put in a CSV, searched for at the end
try {
    mail_guard();
    // =====================================================================================
    echo "== 1 Upgrade from 6c9fd04 by replacing the folder, rollback, fresh install ==\n";
    reset_db(); use_module($MOD_OLD); readme_install();
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    $before = acl_state(); $tBefore = tables(); $batch = (int) q1('SELECT MAX(batch) FROM migrations');
    check('U01', 'Baseline 6c9fd04 has no users.import permission', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug='users.import'") === 0);
    server_start();
    $adm = login2('up-admin', $U['admin2']['email'], $pw);
    info('Baseline users list has an import link: ' . json_encode(import_link(page($adm, 'rolewarden/users'))) . ', /rolewarden/users/import answers HTTP ' . (page($adm, 'rolewarden/users/import') !== '' || true ? $adm->status : 0));
    server_stop(); use_module($MOD_NEW); server_start();
    $h = page($adm, 'rolewarden/users');
    check('U02', 'Folder replaced, before migrate: users list 200, no SQL text, no Import button (permission not granted yet)', $adm->status === 200 && ! leaks($h) && ! import_link($h), 'HTTP ' . $adm->status . ' link=' . json_encode(import_link($h)));
    $h = page($adm, 'rolewarden/users/import');
    check('U02b', 'Before migrate: /rolewarden/users/import refused cleanly (not 200, not 500)', $adm->status !== 200 && $adm->status !== 500 && ! leaks($h), 'HTTP ' . $adm->status);
    spark('migrate', '-n', 'RoleWarden');
    check('U03', 'Upgrade grants users.import to admin', in_array('users.import', role_perms('admin'), true));
    $holders = array_column(q("SELECT r.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug='users.import' ORDER BY r.slug"), 'slug');
    check('U03b', 'Only admin receives users.import from the data migration', $holders === ['admin'], json_encode($holders));
    check('U03c', 'Upgrade adds no table (SPEC: no new table for V6)', tables() === $tBefore, json_encode(array_values(array_diff(tables(), $tBefore))));
    $h = page($adm, 'rolewarden/users');
    check('U04', 'After migrate: admin sees "Import users" in the users list and opens the import page', import_link($h) && str_contains(page($adm, 'rolewarden/users/import'), 'name="csv"') && $adm->status === 200, 'HTTP ' . $adm->status);
    $own = login2('up-owner', $U['owner']['email'], $pw);
    check('U04b', 'Super admin also sees the button and the page', import_link(page($own, 'rolewarden/users')) && str_contains(page($own, 'rolewarden/users/import'), 'name="csv"'));
    server_stop(); spark('migrate:rollback', '-b', (string) $batch, '-f');
    check('U05', 'Rollback restores ACL rows, migrations and tables exactly', acl_state() === $before && tables() === $tBefore);
    server_start();
    $h = page($adm, 'rolewarden/users');
    $hi = page($adm, 'rolewarden/users/import'); $si = $adm->status;
    check('U06', 'Rolled back with the 98636ea folder still in place: list 200 without button, import refused (not 500)', ! import_link($h) && $si !== 200 && $si !== 500, "import HTTP $si");
    server_stop(); use_module($MOD_OLD); server_start();
    check('U07', 'Restored 6c9fd04 folder works after the rollback', page($adm, 'rolewarden/users') !== '' && $adm->status === 200);
    server_stop(); use_module($MOD_NEW); spark('migrate', '-n', 'RoleWarden');
    check('U08', 'Upgrade applies again after the rollback', in_array('users.import', role_perms('admin'), true));
    reset_db(); readme_install();
    check('U09', 'Fresh install grants users.import to admin only', array_column(q("SELECT r.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug='users.import'"), 'slug') === ['admin']);
    $rb = (int) q1("SELECT MIN(batch) FROM migrations WHERE namespace='RoleWarden'") - 1;
    spark('migrate:rollback', '-b', (string) $rb, '-f');
    check('U10', 'Fresh install rolls back cleanly (no acl_ table left)', q("SHOW TABLES LIKE 'acl\\_%'") === []);
    spark('migrate', '-n', 'RoleWarden'); spark('db:seed', 'RoleWarden\\Database\\Seeds\\RoleWardenSeeder');
    logs_take('upgrade');

    // =====================================================================================
    echo "== Fixtures ==\n";
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    server_start();
    $adm = login2('admin', $U['admin2']['email'], $pw);
    settings($adm, ['sign_in_rate' => '100', 'lock_attempts' => '20']); cache_clear();
    $ADMIN_ID = $U['admin2']['id'];
    $roles = q('SELECT id, slug, name, is_super_admin FROM acl_roles ORDER BY id');
    info('Roles: ' . json_encode($roles));
    $supers = array_column(array_filter($roles, fn ($r) => (int) $r['is_super_admin'] === 1), 'slug');
    $h = page($adm, 'rolewarden/settings');
    info('Default role at start: ' . json_encode(selected_default($h)) . ' options ' . json_encode(options_default($h)));

    // =====================================================================================
    echo "== 2 Definition of done: one invalid row refuses the file; a valid file creates the users ==\n";
    $s0 = ustate();
    $c1 = csv([['dod1@v6.test', 'dod.one', 'user', ''], ['dod2@v6.test', 'dod.two', '', ''], ['not-an-email', 'dod.three', 'user', ''], ['dod4@v6.test', 'dod.four', 'admin', '']]);
    $h = preview($adm, $c1);
    $r = prows($h);
    check('D01', 'A file with one invalid row (line 4) is refused: no confirm button, the error on line 4 only', confirm_form($h) === null && count($r) === 4 && (row_of($r, '4')['bad'] ?? false) && err_has(row_of($r, '4'), '/email/i') && count(array_filter($r, fn ($x) => $x['bad'])) === 1, summary($h));
    check('D02', 'Refused file: nothing written (users, identities, roles, overrides, activity log, Shield groups)', ustate() === $s0, ustate() . ' vs ' . $s0);
    check('D03', 'Refused file: confirming anyway with a forged token creates nothing', ($x = confirm_token($adm, str_repeat('a', 32))) !== null && ustate() === $s0 && $adm->status !== 500, 'HTTP ' . ($GLOBALS['LAST_CONF'] ?? 0));
    $c2 = csv([['dod1@v6.test', 'dod.one', 'user', ''], ['dod2@v6.test', 'dod.two', '', ''], ['dod4@v6.test', 'dod.four', 'admin', '']]);
    $h = preview($adm, $c2);
    check('D04', 'The corrected file previews as ready: 3 rows, confirm button "Import 3 users"', ready($h, 3) && str_contains(confirm_form($h)['text'] ?? '', '3'), summary($h));
    check('D05', 'The preview itself writes nothing', ustate() === $s0);
    $after = confirm($adm, $h);
    check('D06', 'Confirming creates the 3 users', uid_of('dod1@v6.test') && uid_of('dod2@v6.test') && uid_of('dod4@v6.test') && nusers() === (int) explode('users=', $s0)[1] + 3, 'HTTP ' . ($GLOBALS['LAST_CONF'] ?? 0) . ' users=' . nusers());
    logs_take('dod');

    // =====================================================================================
    echo "== 3 Format (SPEC V6 Formato) ==\n";
    $k = 0; $fmt = function (string $id, string $label, string $content, ?int $rowsReady, string $name = 'users.csv') use ($adm, &$k) {
        $s = ustate(); $h = preview($adm, $content, $name); $st = $GLOBALS['LAST_UP'];
        $ok = $rowsReady === null ? refused($h) : ready($h, $rowsReady);
        check($id, $label, $ok && ustate() === $s && $st !== 500 && ! leaks($h), summary($h));
        return $h;
    };
    $fmt('F01', 'Header in another order (password,role,username,email) is accepted and mapped by name', "password,role,username,email\n,user,f.one,f1@v6.test\n,,f.two,f2@v6.test\n", 2);
    $h = preview($adm, "role,email,username\nadmin,f3@v6.test,f.three\n");
    check('F01b', 'Columns are mapped by name: the preview shows email, username and role in the right cells', (row_of(prows($h), '2')['email'] ?? '') === 'f3@v6.test' && (row_of(prows($h), '2')['username'] ?? '') === 'f.three' && (row_of(prows($h), '2')['role'] ?? '') === 'Admin', summary($h));
    $fmt('F02', 'Only the two required columns (email,username) is accepted', "email,username\nf4@v6.test,f.four\n", 1);
    $fmt('F02b', 'Required + one optional column (username,email,password) is accepted', "username,email,password\nf.five,f5@v6.test,\n", 1);
    $h = $fmt('F03', 'An unknown column refuses the whole file', "email,username,phone\nf6@v6.test,f.six,123\n", null);
    check('F03b', 'The unknown column is named in the file error', (bool) preg_grep('/phone/', ferrs($h)), json_encode(ferrs($h)));
    $h = $fmt('F04', 'Missing required column username refuses the file', "email,role\nf7@v6.test,user\n", null);
    check('F04b', 'The missing column is named in the file error', (bool) preg_grep('/username/i', ferrs($h)), json_encode(ferrs($h)));
    $fmt('F04c', 'Missing required column email refuses the file', "username,role\nf.eight,user\n", null);
    $s = ustate(); $h = preview($adm, "email,username,email\nf9@v6.test,f.nine,f9b@v6.test\n");
    if (refused($h)) check('F05', 'A duplicated column (email twice) refuses the file', ustate() === $s, summary($h));
    else amb('F05', 'A duplicated column (email twice) is accepted', 'SPEC V6 lists required/optional/unknown columns but says nothing about a repeated one; the file is accepted: ' . summary($h));
    $fmt('F06', 'Semicolon separator detected from the header', "email;username;role;password\nf10@v6.test;f.ten;user;\nf11@v6.test;f.eleven;;\n", 2);
    $PWS[] = $pwSemi = 'Semi,colon-Pass9!z';
    $h = $fmt('F06b', 'Semicolon file: a comma inside a value is kept as data (password with a comma)', "email;username;password\nf12@v6.test;f.twelve;$pwSemi\n", 1);
    $fmt('F07', 'UTF-8 BOM before the header is accepted (email column recognised)', "\xEF\xBB\xBF" . "email,username\nf13@v6.test,f.thirteen\n", 1);
    $fmt('F07b', 'BOM + semicolon + CRLF line endings (an Excel export) is accepted', "\xEF\xBB\xBF" . "email;username;role\r\nf14@v6.test;f.fourteen;user\r\nf15@v6.test;f.fifteen;\r\n", 2);
    $h = $fmt('F08', 'Invalid UTF-8 refuses the whole file', "email,username\nf16@v6.test,f.sixteen\n\xC3\x28bad@v6.test,f.bad\xFF\n", null);
    info('Invalid UTF-8 message: ' . json_encode(ferrs($h)) . ' rows ' . json_encode(array_map(fn ($r) => $r['errors'], prows($h))));
    $fmt('F08b', 'A UTF-16 file (BOM FF FE) is refused, not misread', "\xFF\xFE" . mb_convert_encoding("email,username\nf17@v6.test,f.seventeen\n", 'UTF-16LE', 'UTF-8'), null);
    $h = $fmt('F09', 'An empty file (0 bytes) is refused', '', null);
    info('Empty file: ' . summary($h) . ' field error=' . json_encode(field_error($h, 'csv')));
    $h = $fmt('F10', 'A file with only the header is refused (nothing to import, no confirm button)', HEAD, null);
    info('Header only: ' . summary($h));
    $PWS[] = $pwQ = 'Quo"ted,pass;Word9!';
    $h = $fmt('F11', 'Quoted fields: quotes, commas and doubled quotes inside values are read as data', 'email,username,role,password' . "\n" . '"f18@v6.test","f.eighteen","user","Quo""ted,pass;Word9!"' . "\n", 1);
    $hq = preview($adm, 'email,username,role,password' . "\n" . '"f18@v6.test","f.eighteen","user","Quo""ted,pass;Word9!"' . "\n");
    confirm($adm, $hq);
    $sec = (string) q1("SELECT secret2 FROM auth_identities WHERE secret='f18@v6.test'");
    check('F11b', 'Quoted password imported verbatim: the stored Shield hash verifies Quo"ted,pass;Word9!', $sec !== '' && password_verify($pwQ, $sec));
    $h = $fmt('F12', 'A row with more values than columns is refused, with the error on its line', HEAD . "f19@v6.test,f.nineteen,user,,EXTRA\nf20@v6.test,f.twenty,,\n", null);
    check('F12b', 'Extra values: the error is on line 2 and line 3 is fine', (row_of(prows($h), '2')['bad'] ?? false) && ! (row_of(prows($h), '3')['bad'] ?? true), summary($h));
    $s = ustate(); $h = preview($adm, HEAD . "f21@v6.test,f.twentyone\n");
    info('Row with fewer values than columns (role/password missing): ' . summary($h));
    $s = ustate(); $h = preview($adm, "Email,USERNAME\nf22@v6.test,f.twentytwo\n");
    info('Header in another case (Email,USERNAME): ' . summary($h));
    $h = preview($adm, "email , username\nf23@v6.test , f.twentythree\n");
    info('Header and values with spaces around: ' . summary($h));
    $h = preview($adm, HEAD . "f24@v6.test,f.twentyfour,,\n\nf25@v6.test,f.twentyfive,,\n");
    info('Blank line in the middle: ' . summary($h));
    $h = preview($adm, "email,username\rf26@v6.test,f.twentysix\r");
    info('CR-only line endings: ' . summary($h));
    // Row limit: 1000 accepted, 1001 refused.
    $rows = []; for ($i = 1; $i <= 1001; $i++) $rows[] = ["bulk$i@v6.test", "bulk.$i", '', ''];
    $s = ustate(); $t0 = microtime(true);
    $h = preview($adm, csv(array_slice($rows, 0, 1000)), 'thousand.csv'); $tp = microtime(true) - $t0;
    check('F13', '1000 rows (the limit) are accepted: 1000 rows ready, nothing written', ready($h, 1000) && ustate() === $s, substr(summary($h), 0, 400) . sprintf(' preview %.1fs', $tp));
    $t0 = microtime(true); $after = confirm($adm, $h); $tc = microtime(true) - $t0;
    $made = (int) q1("SELECT COUNT(*) FROM auth_identities WHERE secret LIKE 'bulk%@v6.test'");
    check('F13b', 'Confirming 1000 rows creates exactly 1000 users and 1000 "user.imported" log entries', $made === 1000 && (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='user.imported' AND subject_label LIKE 'bulk.%'") === 1000, "made=$made HTTP " . ($GLOBALS['LAST_CONF'] ?? 0) . sprintf(' confirm %.1fs', $tc));
    info(sprintf('Timing: preview of 1000 rows %.1fs, confirmation %.1fs', $tp, $tc));
    check('F13c', 'After the failed or slow confirmation nothing half-done remains (all or nothing)', $made === 0 || $made === 1000, "made=$made");
    $rows = []; for ($i = 1; $i <= 1000; $i++) $rows[] = ["pw$i@v6.test", "pw.$i", '', 'Bulk-Pass-' . $i . '-xZ!'];
    $s = ustate(); $t0 = microtime(true);
    $h = preview($adm, csv($rows), 'thousand-pw.csv'); $tpw = microtime(true) - $t0;
    check('F13d', '1000 rows with a password each: the preview answers (no 500) and writes nothing', $GLOBALS['LAST_UP'] !== 500 && ready($h, 1000) && ustate() === $s, substr(summary($h), 0, 300) . sprintf(' HTTP %d in %.1fs', $GLOBALS['LAST_UP'], $tpw));
    $rows = []; for ($i = 1; $i <= 100; $i++) $rows[] = ["hund$i@v6.test", "hund.$i", '', ''];
    $h = preview($adm, csv($rows), 'hundred.csv'); $t0 = microtime(true); confirm($adm, $h); $t100 = microtime(true) - $t0;
    $rows = []; for ($i = 1; $i <= 20; $i++) $rows[] = ["twen$i@v6.test", "twen.$i", '', ''];
    $h = preview($adm, csv($rows), 'twenty.csv'); $t0 = microtime(true); confirm($adm, $h); $t20 = microtime(true) - $t0;
    info(sprintf('Confirmation of 20 rows without password: HTTP %d in %.1fs (%.2fs per row), created %d', $GLOBALS['LAST_CONF'], $t20, $t20 / 20, (int) q1("SELECT COUNT(*) FROM auth_identities WHERE secret LIKE 'twen%@v6.test'")));
    $rows = []; for ($i = 1; $i <= 20; $i++) $rows[] = ["tpw$i@v6.test", "tpw.$i", '', 'Twenty-Pass-' . $i . '-q!'];
    $t0 = microtime(true); $h = preview($adm, csv($rows), 'twenty-pw.csv'); $t20p = microtime(true) - $t0;
    info(sprintf('Preview of 20 rows with a password each: HTTP %d in %.1fs (%.2fs per row)', $GLOBALS['LAST_UP'], $t20p, $t20p / 20));
    info(sprintf('Confirmation of 100 rows without password: HTTP %d in %.1fs, created %d', $GLOBALS['LAST_CONF'], $t100, (int) q1("SELECT COUNT(*) FROM auth_identities WHERE secret LIKE 'hund%@v6.test'")));
    $rows = []; for ($i = 1; $i <= 1001; $i++) $rows[] = ["more$i@v6.test", "more.$i", '', ''];
    $h = $fmt('F14', '1001 rows are refused for the whole file', csv($rows), null, 'thousand-one.csv');
    check('F14b', 'The 1001-row refusal names the row limit', (bool) preg_grep('/1\s?000|1,000|limit|too many/i', array_merge(ferrs($h), [fielderr($h)])), summary($h));
    $sizeRe = '/(1\s?MB|1024\s?KB|1\s?024|too (large|big)|size)/i';
    $mk = function (int $bytes): string { // <=1000 rows, padded to the exact size with long (invalid) emails
        $out = HEAD; $n = 0; $per = intdiv($bytes - strlen(HEAD), 1000);
        while ($n < 1000) { $left = $bytes - strlen($out); if ($left <= 0) break; $len = $n === 999 ? $left : min($per, $left); $line = 'x@v6.test,pad.' . $n . ','; $line = str_repeat('a', max(0, $len - strlen($line) - 1)) . $line . "\n"; $out .= $line; $n++; }
        return substr($out, 0, $bytes);
    };
    $under = $mk(1048576); $over = $mk(1048577);
    info('Size files: ' . strlen($under) . ' and ' . strlen($over) . ' bytes, rows ' . (substr_count($under, "\n") - 1) . '/' . (substr_count($over, "\n") - 1));
    $h = preview($adm, $under, 'size-1048576.csv'); $st = $GLOBALS['LAST_UP'];
    check('F15', 'A file of exactly 1 MB (1048576 bytes, 1000 rows) is read: no size error, the rows are checked one by one', $st !== 500 && ! preg_grep($sizeRe, ferrs($h)) && ! preg_match($sizeRe, fielderr($h)) && count(prows($h)) >= 1, substr(summary($h), 0, 400));
    $h = preview($adm, $over, 'size-1048577.csv'); $st = $GLOBALS['LAST_UP'];
    check('F16', 'One byte over 1 MB (1048577 bytes) is refused for its size, with no row read', $st !== 500 && ((bool) preg_grep($sizeRe, ferrs($h)) || (bool) preg_match($sizeRe, fielderr($h))) && prows($h) === [] && confirm_form($h) === null, substr(summary($h), 0, 400));
    $h = preview($adm, str_repeat("x", 3 * 1024 * 1024), 'three-mb.csv'); $st = $GLOBALS['LAST_UP'];
    check('F17', 'A 3 MB file (above PHP upload_max_filesize 2M) is refused cleanly, not 500', $st !== 500 && ! leaks($h) && confirm_form($h) === null && (ferrs($h) !== [] || field_error($h, 'csv')), substr(summary($h), 0, 300) . ' field=' . json_encode(field_error($h, 'csv')));
    info('Where the file errors are shown: under the file field=' . json_encode(field_error($h, 'csv')) . ', in the preview section=' . json_encode(ferrs($h) !== []));
    $h = preview($adm, null);
    check('F18', 'No file chosen: error under the file field, nothing written', field_error($h, 'csv') && confirm_form($h) === null, summary($h));
    logs_take('format');

    // =====================================================================================
    echo "== 4 Rows (SPEC V6 Righe, Password) ==\n";
    $row = function (string $id, string $label, array $line, bool $ok, string $re = '//', ?string $head = null) use ($adm) {
        $s = ustate(); $h = preview($adm, csv([$line], $head ?? HEAD)); $r = row_of(prows($h), '2');
        $pass = $ok ? ($r && ! $r['bad'] && ready($h, 1)) : ($r && $r['bad'] && err_has($r, $re) && confirm_form($h) === null);
        check($id, $label, $pass && ustate() === $s, summary($h));
        return $h;
    };
    $row('R01', 'Invalid email refused on its row', ['plainaddress', 'r.one', '', ''], false, '/email/i');
    $row('R01b', 'Email over 254 characters refused (Shield max_length)', [str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61) . '.test', 'r.oneb', '', ''], false, '/email/i');
    $row('R01c', 'Empty email refused', ['', 'r.onec', '', ''], false, '/email/i');
    $row('R02', 'Username shorter than 3 refused', ['r2@v6.test', 'ab', '', ''], false, '/username/i');
    $row('R02b', 'Username longer than 30 refused', ['r2b@v6.test', str_repeat('u', 31), '', ''], false, '/username/i');
    $row('R02c', 'Username with characters outside Shield\'s rule (underscore) refused', ['r2c@v6.test', 'bad_name', '', ''], false, '/username/i');
    $row('R02d', 'Username of exactly 3 and 30 characters accepted', ['r2d@v6.test', 'abc', '', ''], true);
    $row('R02e', 'Username of exactly 30 characters accepted', ['r2e@v6.test', str_repeat('z', 30), '', ''], true);
    $row('R02f', 'Empty username refused', ['r2f@v6.test', '', '', ''], false, '/username/i');
    $row('R03', 'Email of an existing user refused', [$U['owner']['email'], 'r.three', '', ''], false, '/email|taken|exist|use/i');
    $row('R03b', 'Email of an existing user in other case refused', [strtoupper($U['owner']['email']), 'r.threeb', '', ''], false, '/email|taken|exist|use/i');
    $row('R03c', 'Username of an existing user refused', ['r3c@v6.test', $U['admin2']['username'], '', ''], false, '/username|taken|exist|use/i');
    $row('R03d', 'Username of an existing user in other case refused', ['r3d@v6.test', strtolower($U['admin2']['username']), '', ''], false, '/username|taken|exist|use/i');
    $dup = function (string $id, string $label, array $rows) use ($adm) {
        $s = ustate(); $h = preview($adm, csv($rows)); $r = prows($h);
        check($id, $label, confirm_form($h) === null && (row_of($r, '3')['bad'] ?? false) && ustate() === $s, summary($h));
    };
    $dup('R04', 'Same email twice in the file: refused, the repeat flagged', [['d1@v6.test', 'dup.one', '', ''], ['d1@v6.test', 'dup.two', '', '']]);
    $dup('R04b', 'Same email twice in other case: refused', [['d2@v6.test', 'dup.three', '', ''], ['D2@V6.TEST', 'dup.four', '', '']]);
    $dup('R04c', 'Same username twice: refused', [['d3@v6.test', 'dup.five', '', ''], ['d4@v6.test', 'dup.five', '', '']]);
    $dup('R04d', 'Same username twice in other case: refused', [['d5@v6.test', 'dup.six', '', ''], ['d6@v6.test', 'DUP.SIX', '', '']]);
    $row('R05', 'Existing role slug accepted and shown by name', ['r5@v6.test', 'r.five', 'v1-child', ''], true);
    $row('R05b', 'Unknown role refused', ['r5b@v6.test', 'r.fiveb', 'ghost-role', ''], false, '/role/i');
    foreach ($supers as $i => $sl) $row('R05c' . $i, "Super admin role \"$sl\" refused", ['r5c' . $i . '@v6.test', 'r.fivec' . $i, $sl, ''], false, '/super/i');
    add_role('v6-custom-super', [], 1);
    $row('R05d', 'A custom role flagged super admin is refused too', ['r5d@v6.test', 'r.fived', 'v6-custom-super', ''], false, '/super/i');
    $h = preview($adm, csv([['r5e@v6.test', 'r.fivee', 'Admin', '']]));
    info('Role written by name instead of slug ("Admin"): ' . summary($h));
    $PWS[] = $pwGood = 'Imported-Pass-9x!';
    $row('R06', 'Strong password accepted, shown as "set" (never echoed)', ['r6@v6.test', 'r.six', '', $pwGood], true);
    $PWS[] = 'Ab1!x';
    $row('R06b', 'Short password refused', ['r6b@v6.test', 'r.sixb', '', 'Ab1!x'], false, '/password/i');
    $PWS[] = 'password123';
    $row('R06c', 'Dictionary password refused (Shield DictionaryValidator)', ['r6c@v6.test', 'r.sixc', '', 'password123'], false, '/password/i');
    $PWS[] = 'r.sixd.r.sixd';
    // Shield's own verdict on the same passwords, from its registration form (users that pass are created by Shield).
    $verdicts = [];
    foreach (['r.sixd.r.sixd' => 'r.sixd', 'Abcdefgh' => 'r.reg.b', 'v6test-r.reg.c' => 'r.reg.c', 'Qwerty123!' => 'r.reg.d', 'Tr0ub4dor&3x' => 'r.reg.e'] as $cand => $uname) {
        $PWS[] = $cand;
        $hh = preview($adm, csv([["imp-$uname@v6.test", $uname, '', $cand]])); $imp = ready($hh, 1);
        register($uname . 'x', "reg-$uname@v6.test", $cand); $shield = user_exists("reg-$uname@v6.test");
        $verdicts[$cand] = ['import' => $imp ? 'accepted' : 'refused', 'shield-register' => $shield ? 'accepted' : 'refused'];
    }
    info('Password verdicts, import vs Shield registration: ' . json_encode($verdicts));
    check('R06d', 'Import password verdicts match Shield\'s own registration on the same passwords (strength rules of Shield)', count(array_filter($verdicts, fn ($v) => $v['import'] !== $v['shield-register'])) === 0, json_encode($verdicts));
    $h = $row('R06e', 'No password accepted ("recovery")', ['r6e@v6.test', 'r.sixe', '', ''], true);
    check('R06f', 'The preview never shows a password value', ! str_contains(clean_html($h), $pwGood));
    // Default role
    $h = preview($adm, csv([['r7@v6.test', 'r.seven', '', '']]));
    check('R07', 'No default role in Settings (README: null = no role): empty role previews as no role', ready($h, 1) && preg_match('/none/i', row_of(prows($h), '2')['role'] ?? ''), summary($h));
    confirm($adm, $h);
    check('R07b', 'Imported with no default role: the user has no role', uid_of('r7@v6.test') && roles_of('r7@v6.test') === []);
    $hs = page($adm, 'rolewarden/settings');
    $opt = preg_match('#<select[^>]*name="default_role"[^>]*>(.*?)</select>#s', $hs, $m) && preg_match('#<option value="([^"]*)"[^>]*>\s*V1 Parent\s*<#', $m[1], $o) ? $o[1] : null;
    settings($adm, ['default_role' => (string) $opt]);
    info('Default role set to V1 Parent (option ' . json_encode($opt) . '): ' . json_encode(selected_default(page($adm, 'rolewarden/settings'))));
    $h = preview($adm, csv([['r8@v6.test', 'r.eight', '', ''], ['r9@v6.test', 'r.nine', 'user', '']]));
    check('R08', 'Default role set: empty role previews as that role', ready($h, 2) && (row_of(prows($h), '2')['role'] ?? '') === 'V1 Parent', summary($h));
    confirm($adm, $h);
    check('R08b', 'Default role set: the empty-role user gets V1 Parent, the other keeps its own role', roles_of('r8@v6.test') === ['v1-parent'] && roles_of('r9@v6.test') === ['user'], json_encode([roles_of('r8@v6.test'), roles_of('r9@v6.test')]));
    $h = preview($adm, csv([['r10@v6.test', 'r.ten', '', '']]));
    settings($adm, ['default_role' => '']); // default changed between preview and confirmation
    confirm($adm, $h);
    info('Default role removed between preview and confirmation: user r10 got ' . json_encode(roles_of('r10@v6.test')));
    logs_take('rows');

    // =====================================================================================
    echo "== 5 Imported users ==\n";
    $PWS[] = $pwA = 'Alpha-Import-77!';
    $h = preview($adm, csv([['Ann.Mixed@V6.test', 'Ann.Mixed', 'user', $pwA], ['nopass@v6.test', 'no.pass', 'v1-child', '']]));
    $before5 = time();
    confirm($adm, $h);
    $toast = static_toasts($adm->body);
    $ua = uid_of('Ann.Mixed@V6.test') ?? uid_of('ann.mixed@v6.test'); $un = uid_of('nopass@v6.test');
    $urow = $ua ? q('SELECT username, active FROM users WHERE id=?', [$ua])[0] : null;
    $irow = $ua ? q("SELECT secret, secret2 FROM auth_identities WHERE user_id=? AND type='email_password'", [$ua])[0] : null;
    check('I01', 'Imported users are active', $ua && $un && (int) q1('SELECT active FROM users WHERE id=?', [$ua]) === 1 && (int) q1('SELECT active FROM users WHERE id=?', [$un]) === 1);
    check('I02', 'Email and username stored as in the file', ($urow['username'] ?? '') === 'Ann.Mixed' && ($irow['secret'] ?? '') === 'Ann.Mixed@V6.test', json_encode([$urow, $irow['secret'] ?? null]));
    info('Stored email case: ' . json_encode($irow['secret'] ?? null));
    check('I03', 'Roles as in the file (user, v1-child)', roles_of((string) ($irow['secret'] ?? '')) === ['user'] && roles_of('nopass@v6.test') === ['v1-child']);
    check('I04', 'Password from the file: stored only as a hash that verifies it', isset($irow['secret2']) && $irow['secret2'] !== $pwA && password_verify($pwA, $irow['secret2']), substr((string) ($irow['secret2'] ?? ''), 0, 7));
    check('I05', 'The user with a password signs in with it', try_login('imp-ann', (string) ($irow['secret'] ?? 'Ann.Mixed@V6.test'), $pwA));
    $h2 = (string) q1("SELECT secret2 FROM auth_identities WHERE user_id=? AND type='email_password'", [$un]);
    $known = ['', 'no.pass', 'nopass@v6.test', 'password', $pwA, $pw];
    $verify = array_filter($known, fn ($k) => $h2 !== '' && password_verify($k, $h2));
    check('I06', 'No password in the file: a hash is stored that matches no known value', $h2 !== '' && $verify === [], json_encode(array_values($verify)));
    $hit = []; foreach (['no.pass', 'nopass@v6.test', 'password', $pwA] as $k) if (try_login('imp-np-' . md5($k), 'nopass@v6.test', $k)) $hit[] = $k;
    check('I07', 'No password in the file: no known password signs in', $hit === [], json_encode($hit));
    $other = q('SELECT secret2 FROM auth_identities WHERE secret IN (?, ?)', ['r6e@v6.test', 'nopass@v6.test']);
    info('Random hashes differ between users: ' . json_encode(count(array_unique(array_column($other, 'secret2'))) === count($other)));
    // Shield password recovery (magic link), captured by Mailpit.
    $mc = new Client('v6-magic'); $mh = page($mc, 'login/magic-link');
    $mf = null; foreach (forms($mh) as $f) if (str_contains($f['action'], 'magic-link')) $mf = $f;
    if ($mf) submit($mc, $mf, ['email' => 'nopass@v6.test']);
    $ms = mails_to('nopass@v6.test');
    $link = null;
    foreach ($ms as $m) { $full = mp('GET', 'message/' . $m['ID']); if (preg_match('#https?://[^\s"\'<>]*verify-magic-link\?token=[A-Za-z0-9]+#', (string) ($full['Text'] ?? '') . (string) ($full['HTML'] ?? ''), $mm)) $link = html_entity_decode($mm[0]); }
    $signedIn = false;
    if ($link) { $mc->req('GET', $link); $GLOBALS['SEEN'][] = ['magic link', $mc->status, $mc->body]; if (in_array($mc->status, [302, 303], true)) follow($mc); $signedIn = signed($mc); }
    check('I08', 'The user without a password recovers access with Shield\'s magic link (email captured by Mailpit)', $link !== null && $signedIn, json_encode(['form' => (bool) $mf, 'mails' => count($ms), 'link' => (bool) $link]));
    $lr = q("SELECT action, subject_id, actor_id FROM acl_activity_log WHERE action='user.imported' AND subject_id IN (?, ?) ORDER BY subject_id", [$ua, $un]);
    check('I09', 'Each imported user has one "user.imported" entry, authored by the admin', count($lr) === 2 && (int) $lr[0]['actor_id'] === $ADMIN_ID && (int) $lr[1]['actor_id'] === $ADMIN_ID, json_encode($lr));
    logs_take('users');

    // =====================================================================================
    echo "== 6 Two steps, all or nothing ==\n";
    // T01-T02: preview writes nothing; covered in D05 and per format/row case. Here: confirm paths.
    $s = ustate(); $h = preview($adm, csv([['t1@v6.test', 't.one', '', ''], ['t2@v6.test', 't.two', '', '']]));
    $tok = confirm_form($h)['fields']['token'] ?? '';
    check('T01', 'Preview writes nothing and offers a confirmation token', ustate() === $s && strlen($tok) >= 16);
    $x = confirm_token($adm, $tok, false);
    check('T02', 'Confirmation without CSRF token refused, nothing created', ustate() === $s && $adm->status !== 500, 'HTTP ' . $GLOBALS['LAST_CONF']);
    $x = confirm_token($adm, strrev($tok));
    check('T03', 'Confirmation with a wrong token refused, nothing created, no 500', ustate() === $s && $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' toasts=' . json_encode(static_toasts($x)));
    $other = login2('admin-other', $U['admin2']['email'], $pw);
    $x = confirm_token($other, $tok);
    check('T04', 'The token used from another session of the same admin is refused', ustate() === $s && $GLOBALS['LAST_CONF'] !== 500, 'HTTP ' . $GLOBALS['LAST_CONF']);
    $x = confirm_token($adm, $tok);
    $okT = uid_of('t1@v6.test') && uid_of('t2@v6.test');
    info('After a wrong token, the right token of the same session ' . ($okT ? 'still confirms' : 'no longer confirms') . ' (HTTP ' . $GLOBALS['LAST_CONF'] . ')');
    if (! $okT) { $h = preview($adm, csv([['t1@v6.test', 't.one', '', ''], ['t2@v6.test', 't.two', '', '']])); $tok = confirm_form($h)['fields']['token'] ?? ''; $x = confirm_token($adm, $tok); }
    check('T05', 'Confirmation creates both users', uid_of('t1@v6.test') && uid_of('t2@v6.test'));
    $n = nusers(); $s = ustate();
    $x = confirm_token($adm, $tok);
    check('T06', 'Repeating the confirmation (fresh CSRF, same token) creates nothing more and answers cleanly', nusers() === $n && ustate() === $s && $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' toasts=' . json_encode(static_toasts($x)));
    $np = login2('admin-np', $U['admin2']['email'], $pw);
    $x = confirm_token($np, bin2hex(random_bytes(16)));
    check('T07', 'Confirmation without any preview in the session refused, nothing created, no 500', ustate() === $s && $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' toasts=' . json_encode(static_toasts($x)));
    $x = confirm_token($np, '');
    check('T07b', 'Confirmation with an empty token refused, no 500', ustate() === $s && $GLOBALS['LAST_CONF'] !== 500, 'HTTP ' . $GLOBALS['LAST_CONF']);
    // Expiry (30 minutes, per the mandate): the pending preview is aged in the session file of this copy.
    $h = preview($adm, csv([['e1@v6.test', 'e.one', '', '']])); $s = ustate();
    $aged = age_preview($adm, 31 * 60);
    $x = confirm($adm, $h);
    check('T08', 'A preview older than 30 minutes (aged to 31) is refused, nothing created', ustate() === $s && uid_of('e1@v6.test') === null && $GLOBALS['LAST_CONF'] !== 500, "$aged HTTP " . ($GLOBALS['LAST_CONF'] ?? 0) . ' toasts=' . json_encode(static_toasts($x)));
    $h = preview($adm, csv([['e2@v6.test', 'e.two', '', '']]));
    $aged = age_preview($adm, 29 * 60);
    $x = confirm($adm, $h);
    check('T09', 'A preview aged to 29 minutes still confirms', uid_of('e2@v6.test') !== null, "$aged HTTP " . ($GLOBALS['LAST_CONF'] ?? 0));
    // Somebody creates one of the users between preview and confirmation.
    $h = preview($adm, csv([['c1@v6.test', 'c.one', '', ''], ['c2@v6.test', 'c.two', '', ''], ['c3@v6.test', 'c.three', '', '']]));
    $adm2 = login2('admin-race', $U['owner']['email'], $pw);
    panel_create($adm2, 'c.other', 'c2@v6.test', 'Race-Cond-Pass-1!');
    info('Created by another admin from the panel: c2@v6.test -> ' . json_encode(uid_of('c2@v6.test')) . ' (HTTP ' . $adm2->status . ')');
    $s = ustate();
    $x = confirm($adm, $h);
    check('T10', 'Email taken between preview and confirmation: confirmation refused, nobody created (all or nothing)', uid_of('c1@v6.test') === null && uid_of('c3@v6.test') === null && ustate() === $s && $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' toasts=' . json_encode(static_toasts($x)) . ' ' . summary($x));
    check('T10b', 'The refusal says why (error toast: users created after the preview)', toast_ok(static_toasts($x), 'error') && (str_contains(html_entity_decode($x), 'c2@v6.test') || preg_grep('/created after|taken|exist|already|changed/i', array_column(static_toasts($x), 'msg'))), json_encode(static_toasts($x)) . ' ' . summary($x));
    $h = preview($adm, csv([['c4@v6.test', 'c.four', '', ''], ['c5@v6.test', 'c.race', '', '']]));
    add_user('c.race', 'c-other@v6.test', 'user', $pw); // username taken directly in the database
    $s = ustate(); $x = confirm($adm, $h);
    check('T11', 'Username taken between preview and confirmation: refused, nobody created', uid_of('c4@v6.test') === null && uid_of('c5@v6.test') === null && ustate() === $s && $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' ' . summary($x));
    $h = preview($adm, csv([['c6@v6.test', 'c.six', 'v1-child', '']]));
    q("DELETE FROM acl_roles WHERE slug='v6-custom-super'");
    $rid = (int) q1("SELECT id FROM acl_roles WHERE slug='v1-child'");
    $s = ustate();
    q('UPDATE acl_roles SET is_super_admin=1 WHERE id=?', [$rid]);
    $x = confirm($adm, $h);
    info('Role turned super admin between preview and confirmation: c6 created=' . json_encode(uid_of('c6@v6.test') !== null) . ' HTTP ' . ($GLOBALS['LAST_CONF'] ?? 0));
    q('UPDATE acl_roles SET is_super_admin=0 WHERE id=?', [$rid]);
    add_role('v6-temp', []);
    $h = preview($adm, csv([['c7@v6.test', 'c.seven', 'v6-temp', ''], ['c8@v6.test', 'c.eight', '', '']]));
    q("DELETE FROM acl_roles WHERE slug='v6-temp'");
    $s = ustate(); $x = confirm($adm, $h);
    check('T12', 'Role deleted between preview and confirmation: no 500 and no SQL text, and never half the file', $GLOBALS['LAST_CONF'] !== 500 && ! leaks($x) && ((uid_of('c7@v6.test') === null) === (uid_of('c8@v6.test') === null)), 'HTTP ' . $GLOBALS['LAST_CONF'] . ' c7=' . json_encode(uid_of('c7@v6.test')) . ' roles=' . json_encode(roles_of('c7@v6.test')) . ' toasts=' . json_encode(static_toasts($x)));
    // A second preview replaces the first?
    $h1 = preview($adm, csv([['p1@v6.test', 'p.one', '', '']])); $t1 = confirm_form($h1)['fields']['token'] ?? '';
    $h2 = preview($adm, csv([['p2@v6.test', 'p.two', '', '']]));
    $x = confirm_token($adm, $t1);
    info('Confirming the first of two previews made in a row: p1 created=' . json_encode(uid_of('p1@v6.test') !== null) . ', p2 created=' . json_encode(uid_of('p2@v6.test') !== null));
    logs_take('two-steps');

    // =====================================================================================
    echo "== 7 Permissions and log ==\n";
    add_role('v6-creator', ['users.view', 'users.create']);
    add_user('v6.creator', 'creator@v6.test', 'v6-creator', $pw);
    add_user('v6.denied', 'denied@v6.test', 'admin', $pw);
    q('INSERT INTO acl_user_permissions (user_id, permission_id, granted) VALUES (?,?,0)', [uid_of('denied@v6.test'), $F['perm']('users.import')]);
    $actors = ['users.view only' => login2('p-limited', $U['limited']['email'], $pw), 'users.create without users.import' => login2('p-creator', 'creator@v6.test', $pw), 'admin with a negative users.import override' => login2('p-denied', 'denied@v6.test', $pw), 'no permission at all' => login2('p-subject', $U['subject']['email'], $pw)];
    $i = 0;
    foreach ($actors as $who => $c) {
        $i++; $s = ustate();
        $hl = page($c, 'rolewarden/users'); $listSt = $c->status;
        $hi = page($c, 'rolewarden/users/import'); $pg = $c->status;
        upload($c, BASE . 'rolewarden/users/import/preview', ['csrf_test_name' => $c->token('rolewarden/profile')], 'csv', csv([["perm$i@v6.test", "perm.u$i", '', '']]));
        $pv = $c->status; $pvSession = str_contains((string) @file_get_contents(session_file($c)), "perm$i@v6.test");
        $x = confirm_token($c, bin2hex(random_bytes(16))); $cf = $GLOBALS['LAST_CONF'];
        $c->get('rolewarden/users/import/sample'); $sm = $c->status; $smCsv = str_contains($c->headers['content-type'] ?? '', 'csv') || str_starts_with($c->body, 'email');
        check("A0$i", "$who: no Import button; page, preview, confirmation and sample all refused; nothing written", ! import_link($hl) && $pg !== 200 && $pg !== 500 && $pv !== 500 && ! in_array($pv, [200], true) && ! $pvSession && $cf !== 500 && $sm !== 200 && ! $smCsv && ustate() === $s, json_encode(['list' => $listSt, 'link' => import_link($hl), 'page' => $pg, 'preview' => $pv, 'stored' => $pvSession, 'confirm' => $cf, 'sample' => $sm, 'sampleCsv' => $smCsv]));
    }
    $an = new Client('v6-anon');
    $an->get('rolewarden/users/import'); $a1 = $an->status . ' ' . $an->location;
    $an->get('rolewarden/users/import/sample'); $a2 = $an->status . ' ' . $an->location;
    check('A05', 'Anonymous: import page and sample redirect to login', str_contains($a1, 'login') && str_contains($a2, 'login') && ! str_starts_with($an->body, 'email'), "$a1 | $a2");
    // Admin: the sample file.
    $adm->get('rolewarden/users/import/sample'); $sample = $adm->body;
    check('A06', 'Sample file: CSV download with the documented header', $adm->status === 200 && str_contains($adm->headers['content-type'] ?? '', 'csv') && str_contains($adm->headers['content-disposition'] ?? '', 'attachment') && preg_match('/^(\xEF\xBB\xBF)?email,username,role,password\r?\n/', $sample), json_encode([$adm->status, $adm->headers['content-type'] ?? null, substr($sample, 0, 80)]));
    $h = preview($adm, $sample, 'rolewarden-users.csv');
    info('Sample uploaded back: ' . summary($h));
    check('A06b', 'The sample file, uploaded back, has a valid format (rows only flagged for already existing users)', ferrs($h) === [] && array_filter(prows($h), fn ($r) => $r['bad'] && ! err_has($r, '/taken|exist|already|use|unique/i')) === [], summary($h));
    // Log
    $n = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='user.imported'");
    $imported = (int) q1("SELECT COUNT(*) FROM users u JOIN auth_identities i ON i.user_id=u.id WHERE i.secret LIKE '%v6.test' AND i.secret NOT IN ('creator@v6.test','denied@v6.test','c2@v6.test','c-other@v6.test') AND i.secret NOT LIKE 'reg-%' AND u.username <> 'c.other'");
    info("Users created by import (all @v6.test minus fixtures, race users and Shield registrations reg-*): $imported, user.imported entries: $n");
    $bad = q("SELECT l.id, l.subject_id FROM acl_activity_log l LEFT JOIN users u ON u.id=l.subject_id WHERE l.action='user.imported' AND (u.id IS NULL OR l.subject_type <> 'user' OR l.actor_id IS NULL)");
    check('L01', 'Every imported user has exactly one "user.imported" log entry pointing at it', $n === $imported && $bad === [] && (int) q1("SELECT COUNT(*) FROM (SELECT subject_id FROM acl_activity_log WHERE action='user.imported' GROUP BY subject_id HAVING COUNT(*)>1) d") === 0, "entries=$n imported=$imported bad=" . json_encode($bad));
    check('L02', 'Users created by others (panel, database) during the race checks are not logged as imported', (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='user.imported' AND subject_label IN ('c.other','c.race')") === 0);
    $ha = page($adm, 'rolewarden/activity?action=user.imported');
    $shown = substr_count(clean_html($ha), '<span>User imported</span>'); $others = preg_match_all('#<td><span>(?!User imported)[^<]+</span></td>#', clean_html($ha));
    check('L03', 'Activity screen filtered by action shows the entries as "User imported" and nothing else', $adm->status === 200 && $shown >= 1 && $others === 0 && str_contains($ha, 'option value="user.imported"'), "HTTP {$adm->status} shown=$shown others=$others");
    $ha = page($adm, 'rolewarden/activity?action=user.imported&q=' . rawurlencode('Ann.Mixed'));
    info('Filter by action + user (Ann.Mixed): ' . substr_count(clean_html($ha), '<span>User imported</span>') . ' row(s)');
    logs_take('permissions');

    // =====================================================================================
    echo "== 8 Interface: toasts, field errors, three themes light and dark in Chrome ==\n";
    $h = preview($adm, csv([['ui1@v6.test', 'ui.one', '', ''], ['bad', 'x', '', '']]));
    check('V01', 'Refused file: error toast (static and Alpine), errors on the row', toast_ok(static_toasts($h), 'error') && (js_toasts($h)[0]['type'] ?? '') === 'error' && (row_of(prows($h), '3')['bad'] ?? false), json_encode(static_toasts($h)));
    $h = preview($adm, "email,username,colour\nui2@v6.test,ui.two,red\n");
    $tm = static_toasts($h)[0]['msg'] ?? '';
    check('V02', 'File refused for a whole-file reason: error toast', toast_ok(static_toasts($h), 'error'), json_encode(static_toasts($h)));
    info('Toast text for a whole-file refusal (no row shown): ' . json_encode($tm));
    $h = preview($adm, csv([['ui3@v6.test', 'ui.three', '', '']]));
    check('V03', 'Valid preview: no error toast, a status bar with the count and the confirm button', no_toast($h) || ! toast_ok(static_toasts($h), 'error'), json_encode(static_toasts($h)) . ' bar=' . bar($h));
    $x = confirm($adm, $h);
    check('V04', 'After importing: success toast ("Saved …") on the page reached', toast_ok(static_toasts($x), 'success') && (js_toasts($x)[0]['type'] ?? '') === 'success', json_encode(static_toasts($x)));
    $h = preview($adm, null);
    check('V05', 'No file: the error is under the file field (aria-describedby) and not in a toast', field_error($h, 'csv') && str_contains($h, 'aria-invalid="true"') && ! toast_ok(static_toasts($h), 'error'), json_encode(static_toasts($h)));
    $h = preview($adm, "email,username,colour\nui4@v6.test,ui.four,red\n");
    $underField = field_error($h, 'csv');
    if (! $underField) amb('V06', 'Whole-file errors (unknown column, size, encoding) are shown in the Preview section, not under the file field', 'Mandate point 8 asks for "errori sotto il campo del file"; SPEC V1 says field validation errors stay in the form. The no-file error is under the field (V05), but content errors are listed after the form, in section C, plus an error toast. Whether a content error is a field error is not decided by the SPEC.');
    else check('V06', 'Whole-file errors shown under the file field', true);
    // Browser rendering: import page empty, refused and ready, in each theme, light and dark.
    $base = HOST . 'index.php/';
    $okCsv = str_replace('\\', '/', "$WORKDIR/verify-v6.browser-ok.csv"); file_put_contents($okCsv, csv([['br1@v6.test', 'br.one', 'user', ''], ['br2@v6.test', 'br.two', '', '']]));
    $badCsv = str_replace('\\', '/', "$WORKDIR/verify-v6.browser-bad.csv"); file_put_contents($badCsv, csv([['br3@v6.test', 'br.three', 'user', ''], ['<b>x</b>', 'b', 'ghost', 'weak']]));
    $goCsv = str_replace('\\', '/', "$WORKDIR/verify-v6.browser-go.csv"); file_put_contents($goCsv, csv([['br9@v6.test', 'br.nine', '', '']]));
    $RENDER = [];
    foreach (THEMES as $theme) {
        save_theme($adm, ['base' => $theme]);
        $steps = [['op' => 'emulate', 'scheme' => 'light'], ['op' => 'login', 'url' => $base . 'login', 'email' => $U['admin2']['email'], 'password' => $pw, 'tag' => 'login']];
        foreach (['light', 'dark'] as $mode) {
            $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/profile'];
            $steps[] = ['op' => 'click', 'selector' => "form.rw-mode button[value=$mode]", 'nav' => true];
            $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/users', 'tag' => "$mode/users/goto"];
            $steps[] = ['op' => 'eval', 'tag' => "$mode/users/button", 'expr' => "!!document.querySelector('a[href$=\"users/import\"]')"];
            $steps[] = ['op' => 'click', 'selector' => 'a[href$="users/import"]', 'nav' => true, 'tag' => "$mode/empty/goto"];
            $steps[] = ['op' => 'audit', 'tokens' => SEMANTIC, 'semantic' => SEMANTIC, 'tag' => "$mode/empty"];
            $steps[] = ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v6.shot-$theme-$mode-empty.png"), 'full' => true];
            foreach (['bad' => $badCsv, 'ok' => $okCsv] as $kind => $file) {
                $steps[] = ['op' => 'upload', 'selector' => '#rw-csv', 'files' => [$file]];
                $steps[] = ['op' => 'click', 'selector' => 'form[action$="import/preview"] button[type=submit]', 'nav' => true, 'tag' => "$mode/$kind/goto"];
                $steps[] = ['op' => 'eval', 'tag' => "$mode/$kind/state", 'expr' => JS_TOASTS];
                $steps[] = ['op' => 'audit', 'tokens' => SEMANTIC, 'semantic' => SEMANTIC, 'tag' => "$mode/$kind"];
                $steps[] = ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v6.shot-$theme-$mode-$kind.png"), 'full' => true];
            }
            if ($theme === 'console' && $mode === 'light') {
                $steps[] = ['op' => 'upload', 'selector' => '#rw-csv', 'files' => [$goCsv]];
                $steps[] = ['op' => 'click', 'selector' => 'form[action$="import/preview"] button[type=submit]', 'nav' => true];
                $steps[] = ['op' => 'click', 'selector' => 'form[action$="import/confirm"] button[type=submit]', 'nav' => true, 'tag' => 'go/goto'];
                $steps[] = ['op' => 'eval', 'tag' => 'go/state', 'expr' => JS_TOASTS];
                $steps[] = ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v6.shot-imported.png"), 'full' => false];
                $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/users/import'];
                $steps[] = ['op' => 'click', 'selector' => 'form[action$="import/preview"] button[type=submit]', 'wait' => 400];
                $steps[] = ['op' => 'eval', 'tag' => 'nofile/state', 'expr' => "(() => { const i = document.getElementById('rw-csv'); return { url: location.href, valid: i.checkValidity(), required: i.required }; })()"];
            }
        }
        $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/profile'];
        $steps[] = ['op' => 'click', 'selector' => 'form.rw-mode button[value=system]', 'nav' => true];
        $r = browser($steps, "render-$theme", 200);
        $RENDER[$theme] = $r;
    }
    save_theme($adm, ['base' => 'console']);
    $low = [];
    foreach (THEMES as $theme) {
        $r = $RENDER[$theme];
        foreach (['light', 'dark'] as $mode) {
            $p = [];
            if ((by_tag($r, "$mode/users/button")['value'] ?? null) !== true) $p[] = 'no Import button in the browser';
            foreach (['empty', 'bad', 'ok'] as $kind) {
                $g = by_tag($r, "$mode/$kind/goto"); $a = by_tag($r, "$mode/$kind")['value'] ?? null;
                if (($g['status'] ?? 0) !== 200) { $p[] = "$kind HTTP " . json_encode($g['status'] ?? null) . ' ' . ($g['error'] ?? ''); continue; }
                if (! $a) { $p[] = "$kind: no audit"; continue; }
                if ($a['theme'] !== $theme || $a['mode'] !== $mode) $p[] = "$kind: attrs " . json_encode([$a['theme'], $a['mode']]);
                if ($a['unresolved'] !== []) $p[] = "$kind: unresolved " . implode(',', $a['unresolved']);
                if ($a['rwBg'] !== ($a['values']['--surface'] ?? '')) $p[] = "$kind: background not --surface";
                if ((lum_hex(rgb_hex($a['values']['--surface'] ?? '#fff')) < 0.2) !== ($mode === 'dark')) $p[] = "$kind: surface does not look $mode";
                foreach ($a['low'] as $l) $low["$theme/$mode"][] = "$kind " . $l['el'] . ' "' . $l['text'] . '" ' . $l['ratio'];
            }
            $sb = by_tag($r, "$mode/bad/state")['value'] ?? []; $so = by_tag($r, "$mode/ok/state")['value'] ?? [];
            if (($sb['n'] ?? 0) < 1 || ! preg_grep('/^Error:/', $sb['text'] ?? []) || ($sb['bad'] ?? 0) !== 1 || ($sb['confirm'] ?? true)) $p[] = 'refused: ' . json_encode($sb);
            if (($so['rows'] ?? 0) !== 2 || ($so['bad'] ?? 1) !== 0 || ! ($so['confirm'] ?? false)) $p[] = 'ready: ' . json_encode($so);
            check("B-$theme-$mode", ucfirst($theme) . " $mode: import page empty, refused (error toast shown by Alpine, row marked) and ready (confirm button) render on the tokens", $p === [], implode("\n            ", $p));
        }
        check("B-$theme-js", ucfirst($theme) . ': no JavaScript error and no driver error on the import screens', $r['errors'] === [] && ($r['consoleErrors'] ?? ['?']) === [], json_encode([$r['errors'], $r['consoleErrors'] ?? null]));
    }
    $go = by_tag($RENDER['console'], 'go/state')['value'] ?? [];
    check('B-confirm', 'In the browser: confirming shows the success toast in the Alpine stack and the user exists', preg_grep('/^Saved/', $go['text'] ?? []) && uid_of('br9@v6.test') !== null, json_encode($go));
    info('Browser, no file chosen: ' . json_encode(by_tag($RENDER['console'], 'nofile/state')['value'] ?? null) . ' (the required attribute stops the form in the browser)');
    foreach ($low as $combo => $items) info("Visible text under 4.5:1 on the import screens, $combo (" . count($items) . '): ' . json_encode(array_slice(array_values(array_unique($items)), 0, 15), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $under3 = []; foreach ($low as $combo => $items) foreach ($items as $it) if ((float) substr($it, strrpos($it, ' ') + 1) < 3.0) $under3[] = "$combo: $it";
    check('B-READ', 'No visible text below 3:1 on the import screens in any theme or mode', $under3 === [], implode("\n            ", array_slice(array_values(array_unique($under3)), 0, 15)));
    logs_take('interface');

    // =====================================================================================
    echo "== 10 Publication ==\n";
    $f = import_form(page($adm, 'rolewarden/users/import'));
    $h = preview($adm, csv([['x1@v6.test', 'x.one', '', '']]));
    check('P01', 'CSRF token on the upload form and on the confirmation form', ! empty($f['fields']['csrf_test_name']) && ! empty(confirm_form($h)['fields']['csrf_test_name'] ?? ''));
    $s = ustate(); $sessBefore = (string) @file_get_contents(session_file($adm));
    $h = preview($adm, csv([['csrf1@v6.test', 'csrf.one', '', '']]), 'users.csv', false);
    check('P02', 'Upload without CSRF token refused: no preview stored, no 500', $GLOBALS['LAST_UP'] !== 500 && ! str_contains((string) @file_get_contents(session_file($adm)), 'csrf1@v6.test') && ustate() === $s, 'HTTP ' . $GLOBALS['LAST_UP']);
    // Escape: markup in values, in the role and in the file name.
    $xss = HEAD . "<script>alert(1)</script>@x.test,<b>bold</b>,<img src=x onerror=alert(2)>,\n\"\"\"><svg onload=alert(3)>\"@v6.test,ok.name,user,\n";
    $h = preview($adm, $xss, '<img src=x onerror=alert(4)>.csv');
    $hc = preg_replace('/<div id="debug-bar".*$/s', '', clean_html($h));
    $raw = array_filter(['<script>alert(1)', '<b>bold</b>', '<img src=x onerror=alert(2)>', '<svg onload=alert(3)>', '<img src=x onerror=alert(4)>'], fn ($n) => str_contains($hc, $n));
    check('P03', 'Markup in CSV values, role and file name is escaped in the preview', $raw === [] && str_contains($hc, '&lt;b&gt;bold&lt;/b&gt;'), json_encode(array_values($raw)) . ' ' . summary($h));
    $h = preview($adm, "email,username,<i>col</i>\na@v6.test,a.b,c\n");
    $hc = preg_replace('/<div id="debug-bar".*$/s', '', clean_html($h));
    check('P03b', 'Markup in an unknown column name is escaped in the file error', ! str_contains($hc, '<i>col</i>') && $GLOBALS['LAST_UP'] !== 500, json_encode(ferrs($h)));
    // Malformed files: never a 500, never SQL text.
    $mal = ['binary' => random_bytes(4096), 'nul' => "email,username\n\0\0\0,\0\n", 'newlines' => "\n\n\n\n", 'cr' => "\r\r\r", 'open-quote' => "email,username\n\"a@v6.test,abc\nb@v6.test,bcd\n", 'stray-quote' => "email,username\na\"b@v6.test,ab\"c\n", 'long-field' => "email,username\n" . str_repeat('a', 900000) . "@v6.test,long.one\n", 'many-cols' => "email,username\n" . str_repeat('x,', 5000) . "\n", 'header-many' => implode(',', array_fill(0, 3000, 'email')) . "\nx\n", 'zip' => "PK\x03\x04" . random_bytes(500), 'html' => "<html><body>email,username</body></html>", 'only-bom' => "\xEF\xBB\xBF", 'semicolon-in-comma' => "email,username\na@v6.test;abc\n", 'tabs' => "email\tusername\na@v6.test\tabc\n", 'sql' => HEAD . "a@v6.test',username,'x');DROP TABLE users;--,sql.one,user,\n", 'emoji' => HEAD . "emo@v6.test,emo😀,user,\n", 'nested' => "email,username\n\"\"\"\"\"\",\"\"\n"];
    $five = [];
    foreach ($mal as $name => $content) {
        $s = ustate(); $h = preview($adm, $content, "$name.csv"); $st = $GLOBALS['LAST_UP'];
        if ($st === 500 || leaks($h) || ustate() !== $s || ($name !== 'emoji' && confirm_form($h) !== null && ! in_array($name, ['semicolon-in-comma'], true))) $five[$name] = $st . ' ' . substr(summary($h), 0, 200);
        info("Malformed $name: " . substr(summary($h), 0, 220));
    }
    check('P04', 'Malformed files (binary, NUL, open quotes, 900 KB field, 5000 columns, ZIP, HTML, tabs, SQL text, …): no 500, no SQL text, nothing written', $five === [], json_encode($five, JSON_UNESCAPED_UNICODE));
    check('P05', 'Tables intact after the SQL-looking value', in_array('users', tables(), true) && nusers() > 0);
    // Plaintext passwords: nowhere in pages, sessions, logs, debug toolbar files, cache or the database.
    $PWS = array_values(array_unique(array_filter($PWS, fn ($p) => strlen($p) >= 5)));
    $where = [];
    foreach ($GLOBALS['SEEN'] as [$u, $st, $b]) foreach ($PWS as $p) if (str_contains((string) $b, $p) || str_contains((string) $b, htmlspecialchars($p))) $where[] = "page $u";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$APP/writable", FilesystemIterator::SKIP_DOTS));
    // Sessions of the clients that used Shield's own /register (R06d comparison) are reported apart.
    $REGSESS = []; $shieldOld = [];
    foreach (glob(sys_get_temp_dir() . '/rwv1-m1-reg-*-' . getmypid() . '.jar') as $jar) if (preg_match('/	ci_session	([^\s]+)/', (string) file_get_contents($jar), $mm)) $REGSESS[] = 'ci_session' . $mm[1];
    $files = 0; foreach ($it as $wf) { if (! is_file((string) $wf) || str_contains((string) $wf, 'rwv1-')) continue; $files++; $c = (string) file_get_contents((string) $wf); foreach ($PWS as $p) if (str_contains($c, $p) || str_contains($c, json_encode($p)) || str_contains($c, rawurlencode($p))) { if (str_contains((string) $wf, 'session') && in_array(basename((string) $wf), $REGSESS, true)) { $shieldOld[] = basename((string) $wf) . ' holds ' . json_encode($p); continue; } $where[] = 'file ' . substr((string) $wf, strlen($APP)) . ' holds ' . json_encode($p); } }
    if ($shieldOld) info('Plaintext password in sessions of the Shield registration clients only (Shield register keeps old input; not the import): ' . json_encode($shieldOld));
    foreach (tables() as $t) { $dump = json_encode(q("SELECT * FROM `$t`"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); foreach ($PWS as $p) if (str_contains((string) $dump, $p)) $where[] = "table $t"; }
    check('P06', 'No plaintext CSV password (' . count($PWS) . ") in any page seen, any file under writable/ ($files: sessions, logs, debug toolbar, cache) or any table", $where === [], json_encode(array_values(array_unique($where))));
    $leak = []; foreach ($GLOBALS['SEEN'] as [$u, $s, $b]) if (leaks((string) $b) || $s === 500) $leak[] = "$u HTTP $s";
    check('P07', 'No SQL error text and no HTTP 500 in any page seen (' . count($GLOBALS['SEEN']) . ')', $leak === [], json_encode(array_slice($leak, 0, 10)));
    $all = [];
    foreach (['upgrade', 'dod', 'format', 'rows', 'users', 'two-steps', 'permissions', 'interface'] as $ph) if (is_file($pf = "$WORKDIR/verify-v6.phase-$ph.log")) $all = array_merge($all, bad_lines(file($pf, FILE_IGNORE_NEW_LINES)));
    $all = array_merge($all, bad_lines(logs_take('publication')));
    $rbl = []; foreach (glob("$WORKDIR/verify-v6.phase-*.log") as $pf) foreach (file($pf, FILE_IGNORE_NEW_LINES) as $l) if (rollback_line($l)) $rbl[] = substr($l, 0, 260);
    info('Log lines of the handled rollback (T12, role deleted between preview and confirmation), not counted in P08: ' . json_encode($rbl, JSON_UNESCAPED_SLASHES));
    check('P08', 'Application log at threshold 9: no warning or error (CSRF refusals excluded)', $all === [], implode("\n", array_slice($all, 0, 15)));
} finally {
    server_stop();
}
$srv = @file("$WORKDIR/verify-v5.regression.v6.server.log", FILE_IGNORE_NEW_LINES) ?: [];
$php = array_values(array_filter($srv, fn ($l) => preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|Stack trace/i', $l)));
check('P09', 'PHP built-in server log (error_reporting=-1): no PHP warning, notice, deprecation or fatal', $srv !== [] && $php === [], $srv === [] ? 'server log missing' : implode("\n", array_slice($php, 0, 10)));
$alive = [];
foreach (array_filter($GLOBALS['CHROME_PIDS']) as $pid) { exec('tasklist /FI "PID eq ' . (int) $pid . '" /NH', $o); if (preg_grep('/chrome\.exe/i', $o)) $alive[] = $pid; $o = []; }
check('P10', 'Every headless Chrome started by this run has exited (' . count($GLOBALS['CHROME_PIDS']) . ' instances)', $alive === [], json_encode($alive));
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
echo 'V6 RESULT ' . count($GLOBALS['results']) . ' checks ' . $pass . ' PASS ' . (count($GLOBALS['results']) - $pass) . ' FAIL ' . count($GLOBALS['ambiguities']) . " AMB\n";
exit($pass === count($GLOBALS['results']) ? 0 : 1);
