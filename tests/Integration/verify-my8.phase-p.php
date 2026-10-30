<?php
/** MY8 phase P, the panel in use (included by verify-my8.phases.php). */
declare(strict_types=1);

/* ------------------------------------------------------------------ P helpers */
function opt_value(string $html, string $select, string $labelRe): ?string
{
    foreach (dom($html)->query('//select[@name="' . $select . '"]/option') as $o) if (preg_match($labelRe, trim($o->textContent))) return $o->getAttribute('value');
    return null;
}
/** multipart POST of a file field (a browser choosing a file), cookies of $c */
function upload(Client $c, string $url, array $fields, string $field, string $path): string
{
    $ch = curl_init($url);
    $fields[$field] = new CURLFile($path, 'text/csv', basename($path));
    $c->headers = [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_TIMEOUT => 120,
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use ($c) { $p = explode(':', $h, 2); if (count($p) === 2) $c->headers[strtolower(trim($p[0]))] = trim($p[1]); return strlen($h); }]);
    $c->body = (string) curl_exec($ch);
    $c->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $c->location = $c->headers['location'] ?? '';
    $c->url = $url;
    curl_close($ch);
    return seen($c, "upload $url");
}
function entries(string $h): ?int { return preg_match('/Entries\s*([\d,.]+)/u', text_of($h), $m) ? (int) str_replace([',', '.'], '', $m[1]) : null; }
function figure(string $t, string $re): ?int { return preg_match($re, $t, $m) ? (int) $m[1] : null; }
function usernames_in(string $h, array $known): array
{
    $o = [];
    foreach (rows_text($h) as $r) foreach ($known as $k) if (preg_match('/(^|\s)' . preg_quote($k, '/') . '(\s|$)/u', $r)) { $o[] = $k; break; }
    return $o;
}
function browser(array $steps, string $tag): array
{
    $job = ['profile' => str_replace('\\', '/', sys_get_temp_dir()) . '/my8-chrome-' . $tag, 'steps' => $steps, 'settle' => 300, 'timeout' => 180000];
    $f = sys_get_temp_dir() . "/my8-job-$tag.json";
    file_put_contents($f, json_encode($job));
    $out = shell_exec('node ' . escapeshellarg(__DIR__ . '/verify-v6.browser.mjs') . ' ' . escapeshellarg($f));
    @unlink($f);
    $j = json_decode((string) $out, true) ?? ['errors' => ['no output: ' . short((string) $out, 200)]];
    if (! empty($j['errors'])) info("browser $tag errors: " . json_encode($j['errors']));
    return $j;
}
function steps_of(array $j): string { return json_encode(array_map(fn ($s) => [$s['tag'] ?? '', $s['value'] ?? ($s['status'] ?? null), $s['error'] ?? null], $j['steps'] ?? [])) . ' console=' . json_encode($j['consoleErrors'] ?? []); }
function wire_probe_route(): void
{
    // the buyer's own route and controller (api.html "On routes: the can filter", "In views and controllers: helpers"); only the application copy
    $r = APP . '/app/Config/Routes.php';
    $s = file_get_contents($r);
    if (! str_contains($s, 'my8/probe')) file_put_contents($r, $s . "\n\$routes->get('my8/probe', '\\App\\Controllers\\My8Probe::index', ['filter' => ['session', 'can:users.view']]);\n\$routes->get('my8/any', '\\App\\Controllers\\My8Probe::index', ['filter' => ['session', 'can:roles.delete,users.view']]);\n");
    file_put_contents(APP . '/app/Controllers/My8Probe.php', "<?php\nnamespace App\\Controllers;\nclass My8Probe extends BaseController\n{\n    public function index()\n    {\n        helper('rolewarden');\n        return \$this->response->setJSON(['can_users_view' => can('users.view'), 'can_roles_delete' => can('roles.delete'), 'can_any' => can_any(['roles.delete', 'users.view']), 'can_all' => can_all(['roles.delete', 'users.view']), 'permissions' => permissions()]);\n    }\n}\n");
}
function new_user(Client $own, string $un, string $em): int
{
    $f = form_with(G($own, 'rolewarden/users/create', "create $un form"), 'email');
    $v = ['username' => $un, 'email' => $em, 'password' => UPW];
    foreach ($f['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
    if ($f) submit($own, $f, $v, "create $un");
    return uid($em);
}
function rule_decides(string $t, string $ruleStart): bool
{
    // "Rules, in order: ... <rule sentence>. decides ..." : the word "decides" follows the deciding rule
    return (bool) preg_match('/' . preg_quote($ruleStart, '/') . '[^.]*\.\s*decides/i', $t);
}

/* ================================================================== P: the panel in use */
if (in_array('P', $PH, true)) {
    echo "\n== P: the panel in use (super admin and a limited user)\n";
    wire_probe_route();
    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $own = c('owner');
    check('P00', 'The super admin signs in', login2($own, OWNER, OPW), "{$own->status} {$own->location}");
    $OID = uid(OWNER);

    // ---- sign-in data over 30 days, written as Shield writes it (auth_logins), for the dashboard
    $rd = uid('reader@example.com');
    $ins = function (string $when, int $ok, string $who, ?int $u, string $ip) { q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', [$ip, 'MY8-Agent', 'email_password', $who, $u, $when, $ok]); };
    $now = time();
    foreach ([2, 2, 3, 5, 10, 12, 20, 25, 28, 35, 40, 60] as $i => $d) $ins(gmdate('Y-m-d H:i:s', $now - $d * 86400 - 3600), 1, 'reader@example.com', $rd, '10.8.0.' . ($i + 1));
    foreach ([1, 4, 4, 15, 22, 33] as $d) $ins(gmdate('Y-m-d H:i:s', $now - $d * 86400 - 7200), 0, 'nobody@my8.test', null, '10.9.0.1');
    $exp = function (int $n, int $ok): int { return (int) q1("SELECT COUNT(*) FROM auth_logins WHERE success=? AND date >= ?", [$ok, gmdate('Y-m-d 00:00:00', time() - ($n - 1) * 86400)]); };
    foreach ([7, 30] as $n) {
        $h = G($own, "rolewarden?period=$n", "dashboard $n");
        $t = text_of($h);
        $blocks = ['Active users', "Sign-ins over $n days", 'When people sign in', 'Who holds which role', 'Latest permission changes'];
        $miss = array_values(array_filter($blocks, fn ($b) => ! str_contains($t, $b)));
        $si = figure($t, "/Sign-ins, $n days\s+(\d+)/u"); $fa = figure($t, "/Failed, $n days\s+(\d+)/u");
        $au = figure($t, '/Active users\s+(\d+)/u');
        $eau = (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND active=1');
        check("P01-$n", "Dashboard $n days: 200, the five blocks, sign-ins/failed/active users equal to the queries on auth_logins and users", ok200($own) && $miss === [] && $si === $exp($n, 1) && $fa === $exp($n, 0) && $au === $eau, "status {$own->status} missing=" . json_encode($miss) . " sign-ins $si/" . $exp($n, 1) . " failed $fa/" . $exp($n, 0) . " active $au/$eau");
    }
    G($own, 'rolewarden?period=90', 'dashboard 90');
    check('P01-90', 'Dashboard 90 days: 200 without SQL or PHP errors', ok200($own), (string) $own->status);

    // ---- users: create, edit, filters, sort, search
    $U1 = new_user($own, 'my8user1', 'user1@my8.test');
    check('P02', 'Users: created from the panel (Shield user, active, identity)', $U1 > 0 && (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 1 && $own->status < 400, "{$own->status} " . short(text_of($own->body), 200));
    $fe = form_with(G($own, "rolewarden/users/$U1/edit", 'edit user form'), 'username');
    if ($fe) submit($own, $fe, ['username' => 'my8user1b'], 'edit user');
    check('P03', 'Users: edited (username saved in users)', $fe && q1('SELECT username FROM users WHERE id=?', [$U1]) === 'my8user1b', "{$own->status} " . q1('SELECT username FROM users WHERE id=?', [$U1]));
    new_user($own, 'my8user2', 'user2@my8.test');
    $U3 = new_user($own, 'my8user3', 'user3@my8.test');
    $known = array_column(q('SELECT username FROM users WHERE deleted_at IS NULL'), 'username');
    $h = G($own, 'rolewarden/users?q=my8user', 'users search');
    $found = usernames_in($h, $known); sort($found);
    check('P04', 'Users: search "my8user" lists exactly the matching users', ok200($own) && $found === ['my8user1b', 'my8user2', 'my8user3'], json_encode($found));
    $h = G($own, 'rolewarden/users?q=' . rawurlencode('my8%'), 'users search percent');
    check('P04b', 'Users: "%" is searched as text (SPEC A4): no row, no error', ok200($own) && usernames_in($h, $known) === [], json_encode(usernames_in($h, $known)));
    $hl = G($own, 'rolewarden/users', 'users list');
    $rr = opt_value($hl, 'role', '/^Reader$/');
    $h = G($own, 'rolewarden/users?role=' . $rr, 'users by role');
    $got = usernames_in($h, $known); sort($got);
    $want = array_column(q('SELECT u.username FROM users u JOIN acl_user_roles ur ON ur.user_id=u.id WHERE ur.role_id=? AND u.deleted_at IS NULL ORDER BY u.username', [$rr]), 'username');
    check('P05', 'Users: role filter (Reader) lists the users holding the role, as in acl_user_roles', ok200($own) && $got === $want && $want !== [], json_encode([$got, $want]));
    // ---- deactivate, filter by status, activate
    $fd = form_action(G($own, "rolewarden/users/$U1", 'user page'), '#/users/' . $U1 . '/deactivate$#');
    if ($fd) submit($own, $fd, [], 'deactivate');
    $h = G($own, 'rolewarden/users?status=inactive', 'users inactive');
    check('P07', 'Users: deactivated (users.active = 0) and listed by the Inactive filter only', $fd && (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 0 && usernames_in($h, $known) === ['my8user1b'], json_encode(usernames_in($h, $known)));
    $fa = form_action(G($own, "rolewarden/users/$U1", 'user page 2'), '#/users/' . $U1 . '/(activate|reactivate)$#');
    if ($fa) submit($own, $fa, [], 'activate');
    $h = G($own, 'rolewarden/users?status=active', 'users active');
    check('P08', 'Users: activated again (users.active = 1), listed by the Active filter', $fa && (int) q1('SELECT active FROM users WHERE id=?', [$U1]) === 1 && in_array('my8user1b', usernames_in($h, $known), true), (string) $own->status);
    // ---- delete
    $fx = form_action(G($own, "rolewarden/users/$U3", 'user3 page'), '#/users/' . $U3 . '/delete$#');
    if ($fx) submit($own, $fx, [], 'delete user3');
    $row = q('SELECT id, deleted_at FROM users WHERE id=?', [$U3]);
    $h = G($own, 'rolewarden/users', 'users after delete');
    info('deleted user row: ' . json_encode($row) . ', identities left: ' . q1('SELECT COUNT(*) FROM auth_identities WHERE user_id=?', [$U3]) . ', roles left: ' . q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=?', [$U3]));
    check('P09', 'Users: deleted from the panel (row gone or soft-deleted) and no longer listed', $fx && ($row === [] || $row[0]['deleted_at'] !== null) && ! in_array('my8user3', usernames_in($h, $known), true) && ok200($own), json_encode($row));

    // ---- CSV import with preview and confirm
    $csv = sys_get_temp_dir() . '/my8-import.csv';
    file_put_contents($csv, "email,username,role,password\nimp1@my8.test,my8imp1,demo-reader,\nimp2@my8.test,my8imp2,,Cedar-Window-5521\nimp3@my8.test,my8imp3,demo-author,\n");
    $fi = form_action(G($own, 'rolewarden/users/import', 'import page'), '#users/import/preview$#');
    $nb = (int) q1('SELECT COUNT(*) FROM users');
    upload($own, abs_url($own->url, $fi['action']), ['csrf_test_name' => $fi['fields']['csrf_test_name']], 'csv', $csv);
    $hp = F($own, 'import preview');
    $fcf = form_action($hp, '#users/import/confirm$#');
    check('P10', 'Import: the preview lists the 3 rows, nothing written yet, confirm offered', $fcf !== null && $nb === (int) q1('SELECT COUNT(*) FROM users') && ! uid('imp1@my8.test') && str_contains(text_of($hp), 'my8imp3'), short(text_of($hp), 400));
    if ($fcf) { submit($own, $fcf, [], 'import confirm'); F($own, 'import done'); }
    $imp = [uid('imp1@my8.test'), uid('imp2@my8.test'), uid('imp3@my8.test')];
    $roleOf = fn (int $u) => q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$u]);
    $rolesOk = $imp[0] && $roleOf($imp[0]) === 'demo-reader' && $roleOf($imp[2]) === 'demo-author';
    $logged = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='user.imported'");
    $i2 = c('imp2'); $il = login2($i2, 'imp2@my8.test', 'Cedar-Window-5521');
    check('P11', 'Import confirmed: 3 active users, roles from the file, user.imported in the log, the password of the file signs in', ! in_array(0, $imp, true) && $rolesOk && $logged === 3 && $il && (int) q1('SELECT SUM(active) FROM users WHERE id IN (?,?,?)', $imp) === 3, json_encode(['ids' => $imp, 'roles' => $rolesOk, 'logged' => $logged, 'login' => $il]));
    file_put_contents($csv, "email,username,role\nimp4@my8.test,my8imp4,\nimp1@my8.test,my8imp5,\n");
    $fi = form_action(G($own, 'rolewarden/users/import', 'import page 2'), '#users/import/preview$#');
    upload($own, abs_url($own->url, $fi['action']), ['csrf_test_name' => $fi['fields']['csrf_test_name']], 'csv', $csv);
    $hp = F($own, 'import preview 2');
    check('P12', 'Import: a file with an email already registered is refused whole (no confirm, nothing written, no SQL on screen)', form_action($hp, '#users/import/confirm$#') === null && ! uid('imp4@my8.test') && ! leaks_sql($hp), short(text_of($hp), 300));
    @unlink($csv);

    // ---- roles: create with a parent, edit, matrix cell, row, column, delete
    $fr = form_with(G($own, 'rolewarden/roles/create', 'create role form'), 'name');
    submit($own, $fr, ['slug' => 'my8-editors', 'name' => 'MY8 Editors', 'description' => 'Made by MY8', 'parent_id' => (string) role_id('demo-reader')], 'create role');
    $R1 = role_id('my8-editors');
    check('P13', 'Roles: created with a parent (acl_roles.parent_id = Reader)', $R1 > 0 && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$R1]) === role_id('demo-reader'), "{$own->status} " . short(text_of($own->body), 200));
    $fe = form_with(G($own, "rolewarden/roles/$R1/edit", 'edit role form'), 'name');
    if ($fe) submit($own, $fe, ['name' => 'MY8 Editors 2', 'description' => 'Edited by MY8', 'parent_id' => (string) role_id('demo-author')], 'edit role');
    check('P14', 'Roles: edited (name, description, parent Author)', $fe && q1('SELECT name FROM acl_roles WHERE id=?', [$R1]) === 'MY8 Editors 2' && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$R1]) === role_id('demo-author'), json_encode(q('SELECT name, description, parent_id FROM acl_roles WHERE id=?', [$R1])));
    $fe = form_with(G($own, 'rolewarden/roles/' . role_id('demo-reader') . '/edit', 'edit reader'), 'name');
    if ($fe) submit($own, $fe, ['parent_id' => (string) $R1], 'cycle');
    $after = F($own, 'after cycle');
    check('P14b', 'Roles: a cycle (Reader under MY8 Editors 2, which inherits from Reader) is refused, nothing saved, no SQL on screen', $fe && q1('SELECT parent_id FROM acl_roles WHERE id=?', [role_id('demo-reader')]) === null && ! leaks_sql($after) && $own->status < 500, json_encode(q1('SELECT parent_id FROM acl_roles WHERE id=?', [role_id('demo-reader')])));
    panelp($own, "rolewarden/roles/$R1", "rolewarden/roles/$R1/permissions", ['permission_id' => perm_id('users.view'), 'granted' => 1], 'cell grant');
    $c1 = (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$R1, perm_id('users.view')]);
    panelp($own, "rolewarden/roles/$R1", "rolewarden/roles/$R1/permissions", ['permission_id' => perm_id('users.view'), 'granted' => 0], 'cell revoke');
    $c0 = (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$R1, perm_id('users.view')]);
    check('P15', 'Matrix, single cell: granted then removed (acl_role_permissions)', $c1 === 1 && $c0 === 0, "after grant $c1, after revoke $c0 " . short($own->body, 200));
    $sel = fn (string $aria) => "(() => { const e = [...document.querySelectorAll('input[type=checkbox]')].find(x => (x.getAttribute('aria-label')||'').match($aria)); if (!e) return 'none'; e.click(); return e.getAttribute('aria-label'); })()";
    // click Save, then wait until the page has nothing left to save (button disabled again) and report the toasts
    $save = "(async () => { const b = [...document.querySelectorAll('button')].find(x => x.textContent.trim() === 'Save changes'); if (!b) return 'none'; if (b.disabled) return 'disabled'; b.click(); const t0 = Date.now(); while (Date.now() - t0 < 20000) { await new Promise(r => setTimeout(r, 200)); if (b.disabled && !document.querySelector('[aria-busy=true]')) break; } await new Promise(r => setTimeout(r, 500)); return 'clicked ' + (Date.now() - t0) + 'ms ' + [...document.querySelectorAll('.rw-toast')].map(t => t.innerText.replace(/\s+/g, ' ').trim()).join(' | '); })()";
    $j = browser([
        ['op' => 'login', 'url' => BASE . 'login', 'email' => OWNER, 'password' => OPW, 'tag' => 'login'],
        ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$R1", 'tag' => 'role'],
        ['op' => 'eval', 'expr' => $sel('/^Grant every action on users$/i'), 'tag' => 'row users', 'wait' => 300],
        ['op' => 'eval', 'expr' => $save, 'tag' => 'save row', 'wait' => 2500],
        ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$R1", 'tag' => 'role again'],
        ['op' => 'eval', 'expr' => $sel('/^Grant create on every area$/i'), 'tag' => 'column create', 'wait' => 300],
        ['op' => 'eval', 'expr' => $save, 'tag' => 'save column', 'wait' => 2500],
    ], 'matrix');
    info('matrix browser: ' . steps_of($j));
    $direct = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.slug', [$R1]), 'slug');
    $usersAll = array_column(q("SELECT slug FROM acl_permissions WHERE slug LIKE 'users.%' ORDER BY slug"), 'slug');
    $inherited = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id IN (?,?)', [role_id('demo-author'), role_id('demo-reader')]), 'slug');
    check('P16', 'Matrix, whole row "users" with the row control and Save: every users.* permission granted to the role', $usersAll !== [] && ! array_diff($usersAll, $direct, $inherited), json_encode(['direct' => $direct, 'missing' => array_values(array_diff($usersAll, $direct, $inherited))]));
    $actAll = array_column(q("SELECT slug FROM acl_permissions WHERE slug LIKE '%.create' ORDER BY slug"), 'slug');
    $saved = array_column(array_filter($j['steps'] ?? [], fn ($x) => ($x['tag'] ?? '') === 'save column'), 'value');
    check('P17', 'Matrix, whole column "create" with the column control and Save: every *.create permission granted (roles.create among them, directly)', str_starts_with((string) ($saved[0] ?? ''), 'clicked') && in_array('roles.create', $direct, true) && ! array_diff($actAll, $direct, $inherited), json_encode(['create' => $actAll, 'direct' => $direct, 'save' => $saved]));
    // the cell above was removed again: the row must have put users.view back
    $fa = form_action(G($own, "rolewarden/users/$U1", 'user page roles'), '#/users/' . $U1 . '/roles$#');
    if ($fa) submit($own, $fa, ['role_id' => (string) $R1], 'assign role');
    check('P18', 'Users: role assigned from the user page (acl_user_roles)', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$U1, $R1]) === 1, (string) $own->status);

    // ---- limited user, overrides and the precedence of the SPEC
    $lu = c('limited');
    $ll = login2($lu, 'user1@my8.test', UPW);
    G($lu, 'rolewarden/users', 'limited users'); $s1 = $lu->status;
    G($lu, 'rolewarden/roles', 'limited roles'); $s2 = $lu->status;
    G($lu, 'rolewarden', 'limited dashboard'); $dash = $lu->status . ' ' . $lu->location;
    $dt = $lu->status === 200 ? text_of($lu->body) : '';
    info('limited user dashboard: ' . short($dt, 200));
    check('P19', 'Limited user: Users (granted by the role) 200; Roles (not granted) refused; dashboard without dashboard.* shows none of the five blocks of the module', $ll && $s1 === 200 && $s2 !== 200 && $s2 < 500 && (str_starts_with($dash, '30') || ($lu->status === 200 && ! str_contains($dt, 'Active users') && ! str_contains($dt, 'Who holds which role') && ! str_contains($dt, 'Latest permission changes'))), "login=$ll users=$s1 roles=$s2 dashboard=$dash");
    panelp($own, "rolewarden/users/$U1", "rolewarden/users/$U1/permissions", ['permission_id' => perm_id('roles.view'), 'granted' => 1], 'override grant roles.view');
    G($lu, 'rolewarden/roles', 'limited roles granted'); $g = $lu->status;
    panelp($own, "rolewarden/users/$U1", "rolewarden/users/$U1/permissions", ['permission_id' => perm_id('users.view'), 'granted' => 0], 'override deny users.view');
    G($lu, 'rolewarden/users', 'limited users denied'); $d = $lu->status;
    $rows = q('SELECT p.slug, up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? ORDER BY p.slug', [$U1]);
    check('P20', 'Overrides: a grant opens Roles, a denial closes Users although the role grants it (acl_user_permissions)', $g === 200 && $d !== 200 && $d < 500 && count($rows) === 2, json_encode([$g, $d, $rows]));
    $tk = text_of(G($own, 'rolewarden/permissions/check?permission=users.view&user=' . $U1, 'check denied'));
    check('P21', 'Check a permission (user by id): users.view denied, the denial on the user decides', ok200($own) && rule_decides($tk, 'A denial on this user'), short(substr($tk, strpos($tk, 'Outcome') ?: 0), 400));
    $SA2 = new_user($own, 'my8sa2', 'sa2@my8.test');
    $fa = form_action(G($own, "rolewarden/users/$SA2", 'sa2 page'), '#/users/' . $SA2 . '/roles$#');
    if ($fa) submit($own, $fa, ['role_id' => (string) role_id('super-admin')], 'assign super-admin');
    panelp($own, "rolewarden/users/$SA2", "rolewarden/users/$SA2/permissions", ['permission_id' => perm_id('roles.view'), 'granted' => 0], 'deny sa2');
    $sa = c('sa2'); login2($sa, 'sa2@my8.test', UPW);
    G($sa, 'rolewarden/roles', 'sa2 roles'); $x1 = $sa->status;
    G($sa, 'rolewarden/users', 'sa2 users'); $x2 = $sa->status;
    $tk2 = text_of(G($own, 'rolewarden/permissions/check?permission=roles.view&user=my8sa2', 'check sa2'));
    check('P22', 'Precedence: a denial on a super admin beats the super admin role (Roles refused, Users open); Check a permission (by username) agrees', $x1 !== 200 && $x1 < 500 && $x2 === 200 && rule_decides($tk2, 'A denial on this user'), "roles=$x1 users=$x2 " . short(substr($tk2, strpos($tk2, 'Outcome') ?: 0), 300));
    $tk2b = text_of(G($own, 'rolewarden/permissions/check?permission=users.view&user=sa2@my8.test', 'check sa2 email'));
    check('P22b', 'Check a permission (by email): the super admin rule decides for users.view', rule_decides($tk2b, 'A super admin role'), short(substr($tk2b, strpos($tk2b, 'Outcome') ?: 0), 300));
    panelp($own, "rolewarden/users/$U1", "rolewarden/users/$U1/permissions", ['permission_id' => perm_id('users.view')], 'override cleared');
    G($lu, 'rolewarden/users', 'limited users cleared');
    check('P23', 'Overrides: clearing the denial gives the screen back at the next request', $lu->status === 200 && (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$U1, perm_id('users.view')]) === 0, (string) $lu->status);
    $tk3 = text_of(G($own, 'rolewarden/permissions/check?permission=users.view', 'check who'));
    check('P24', 'Check a permission without a user: who holds users.view lists user 1 through MY8 Editors 2', ok200($own) && str_contains($tk3, 'my8user1b') && str_contains($tk3, 'MY8 Editors 2'), short(substr($tk3, strpos($tk3, 'Who holds') ?: 0), 400));

    // ---- helper and can: filter on a route of the application
    $g = c('guest'); G($g, 'my8/probe', 'probe guest');
    $p1 = json_decode(G($lu, 'my8/probe', 'probe limited'), true); $ps1 = $lu->status;
    $p2 = c('reader-probe'); login2($p2, 'reader@example.com', DPW); G($p2, 'my8/probe', 'probe reader'); $ps2 = $p2->status . ' ' . $p2->location;
    check('P25', 'can: filter on an application route: guest to sign in, holder 200, non-holder turned away', in_array($g->status, [302, 303], true) && str_contains($g->location, 'login') && $ps1 === 200 && str_starts_with($ps2, '30') && ! str_contains($ps2, 'my8/probe'), "guest={$g->status} {$g->location} limited=$ps1 reader=$ps2");
    $permsU1 = $p1['permissions'] ?? [];
    check('P26', 'Helpers can/can_any/can_all/permissions() in a controller agree with the roles and overrides of the user', ($p1['can_users_view'] ?? null) === true && ($p1['can_roles_delete'] ?? null) === false && ($p1['can_any'] ?? null) === true && ($p1['can_all'] ?? null) === false && in_array('roles.view', $permsU1, true) && in_array('users.view', $permsU1, true), json_encode($p1));

    // ---- command search
    $hs = G($own, 'rolewarden/search?q=users.view', 'search perm');
    $ok1 = ok200($own) && str_contains(text_of($hs), 'users.view');
    $hs2 = G($lu, 'rolewarden/search?q=settings', 'search limited');
    check('P27', 'Command search: users.view found for the super admin; the limited user is not offered Settings', $ok1 && $lu->status === 200 && ! preg_match('/Go to Settings/i', text_of($hs2)), short(text_of($hs2), 200));

    // ---- activity with filters
    G($own, 'rolewarden/activity', 'activity');
    $n = entries(G($own, 'rolewarden/activity?action=role.created', 'activity role.created'));
    $nd = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='role.created'");
    check('P28', 'Activity: action filter (Role created) counts as acl_activity_log', ok200($own) && $n === $nd && $nd > 0, "page $n db $nd");
    $nu = entries(G($own, 'rolewarden/activity?user=my8owner&action=user.created', 'activity user filter'));
    $ndu = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='user.created' AND actor_id=?", [$OID]);
    check('P29', 'Activity: user + action filter counts as acl_activity_log', ok200($own) && $nu === $ndu && $ndu >= 3, "page $nu db $ndu");
    $day = gmdate('Y-m-d', $now - 4 * 86400 - 7200);
    $np = entries(G($own, "rolewarden/activity?action=auth.failed&from=$day&to=$day", 'activity period'));
    $ndp = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND DATE(date)=?', [$day]);
    check('P30', 'Activity: failed sign-ins on one day (period filter) counted from auth_logins', ok200($own) && $np === $ndp && $ndp >= 2, "page $np db $ndp day $day");

    // ---- settings: lock after failed sign-ins
    $fs = null; foreach (forms(G($own, 'rolewarden/settings', 'settings')) as $f) if (array_key_exists('lock_attempts', $f['types'])) $fs = $f;
    if ($fs) submit($own, $fs, ['lock_attempts' => '3', 'lock_minutes' => '15', 'sign_in_rate' => '100'], 'settings save');
    $st = q("SELECT class, `key`, value FROM settings ORDER BY id");
    info('settings rows: ' . json_encode($st));
    check('P31', 'Settings saved (Settings library table), redirect without errors', $fs && $own->status < 400 && preg_match('/lock/i', json_encode($st)), json_encode($st));
    new_user($own, 'my8lock', 'lock@my8.test');
    for ($i = 0; $i < 3; $i++) login2(c("bad$i"), 'lock@my8.test', 'Wrong-Pass-' . $i);
    $lk = c('lock-good'); $locked = ! login2($lk, 'lock@my8.test', UPW);
    $fails = (int) q1("SELECT COUNT(*) FROM auth_logins WHERE identifier='lock@my8.test' AND success=0");
    check('P32', 'Lock after 3 failed sign-ins: the right password is refused; attempts counted in auth_logins', $locked && $fails >= 3, "fails=$fails locked=" . json_encode($locked));
    $hd = text_of(G($own, 'rolewarden?period=7', 'dashboard notice'));
    check('P33', 'Dashboard: the security notice appears and names the locked email', ok200($own) && str_contains($hd, 'lock@my8.test'), short(substr($hd, strpos($hd, 'Back to the default') ?: 0, 700), 600));

    // ---- sessions and revocation
    $sA = c('u1-A'); login2($sA, 'user1@my8.test', UPW, ['User-Agent: Mozilla/5.0 (Windows NT 10.0) MY8-A Chrome/140.0']);
    $sB = c('u1-B'); login2($sB, 'user1@my8.test', UPW, ['User-Agent: Mozilla/5.0 (X11; Linux x86_64) MY8-B Firefox/140.0']);
    $before = (int) q1('SELECT COUNT(*) FROM acl_sessions WHERE user_id=?', [$U1]);
    $hs = G($sA, 'rolewarden/sessions', 'u1 sessions');
    $revs = array_values(array_filter(forms($hs), fn ($f) => (bool) preg_match('#sessions/\d+/revoke$#', $f['action'])));
    // the session of browser B: the newest one that is not "this browser"
    $sidB = (int) q1('SELECT id FROM acl_sessions WHERE user_id=? AND user_agent LIKE ? ORDER BY id DESC LIMIT 1', [$U1, '%MY8-B%']);
    $rev = null; foreach ($revs as $f) if (str_ends_with($f['action'], "sessions/$sidB/revoke")) $rev = $f;
    if ($rev) submit($sA, $rev, [], 'revoke B');
    $gone = (int) q1('SELECT COUNT(*) FROM acl_sessions WHERE id=?', [$sidB]) === 0;
    G($sB, 'rolewarden/profile', 'B after revoke'); $bst = $sB->status . ' ' . $sB->location;
    G($sA, 'rolewarden/profile', 'A after revoke');
    check('P34', 'Sessions: browser B signed out from the sessions screen (row gone in acl_sessions, B sent to sign in at its next request), A still open', $rev && $gone && $sA->status === 200 && $sB->status !== 200, "before=$before sidB=$sidB gone=" . json_encode($gone) . " B=$bst A={$sA->status}");
    $ra = form_action(G($sA, 'rolewarden/sessions', 'u1 sessions 2'), '#sessions/revoke-all$#');
    if ($ra) submit($sA, $ra, [], 'revoke all');
    $left = (int) q1('SELECT COUNT(*) FROM acl_sessions WHERE user_id=?', [$U1]);
    G($sA, 'rolewarden/profile', 'A after all'); $ast = $sA->status;
    G($lu, 'rolewarden/profile', 'limited after all');
    check('P35', 'Sessions: "sign out everywhere else" leaves only this browser (the other browser of the user is signed out)', $ra && $left === 1 && $ast === 200 && $lu->status !== 200, "left=$left A=$ast other={$lu->status}");

    // ---- appearance: save and reset
    $j = browser([
        ['op' => 'login', 'url' => BASE . 'login', 'email' => OWNER, 'password' => OPW, 'tag' => 'login'],
        ['op' => 'goto', 'url' => BASE . 'rolewarden/appearance', 'tag' => 'appearance'],
        ['op' => 'click', 'selector' => 'input[name=base][value=clarity]', 'tag' => 'clarity'],
        ['op' => 'eval', 'expr' => "(() => { const b = [...document.querySelectorAll('button')].find(x => x.textContent.trim() === 'Save theme'); if (!b) return 'none'; b.click(); return 'clicked'; })()", 'tag' => 'save', 'wait' => 3000],
    ], 'appearance');
    $tj = APP . '/writable/rolewarden/theme.json';
    $saved = is_file($tj) ? (string) file_get_contents($tj) : '';
    check('P36', 'Appearance: the theme saved from the page (writable/rolewarden/theme.json, Clarity)', str_contains($saved, 'clarity'), short($saved, 200) . ' ' . steps_of($j));
    $fr = form_action(G($own, 'rolewarden/appearance', 'appearance page'), '#appearance/reset$#');
    if ($fr) submit($own, $fr, [], 'appearance reset');
    clearstatcache();
    check('P37', 'Appearance: reset removes the saved theme', $fr && ! is_file($tj) && $own->status < 400, (string) $own->status);
    $fm = form_action(G($own, 'rolewarden/appearance', 'appearance mode'), '#appearance/mode$#');
    if ($fm) submit($own, $fm, ['mode' => 'dark'], 'mode dark');
    check('P38', 'Light/dark choice saved per user with Settings (context user:' . $OID . ')', (int) q1('SELECT COUNT(*) FROM settings WHERE context=?', ["user:$OID"]) > 0, json_encode(q('SELECT class, `key`, value, context FROM settings WHERE context=?', ["user:$OID"])));

    // ---- role deletion: soft delete
    $fr2 = form_with(G($own, 'rolewarden/roles/create', 'create role 2'), 'name');
    submit($own, $fr2, ['slug' => 'my8-temp', 'name' => 'MY8 Temp', 'description' => '', 'parent_id' => ''], 'create role 2');
    $R2 = role_id('my8-temp');
    $fx = form_action(G($own, "rolewarden/roles/$R2", 'role 2 page'), '#roles/' . $R2 . '/delete$#');
    if ($fx) submit($own, $fx, [], 'delete role 2');
    $rr = q('SELECT id, deleted_at FROM acl_roles WHERE id=?', [$R2]);
    $hl = G($own, 'rolewarden/roles', 'roles after delete');
    check('P39', 'Roles: deleted from the panel (soft-deleted, SPEC deleted_at) and no longer listed', $R2 > 0 && $fx && ($rr === [] || $rr[0]['deleted_at'] !== null) && ! str_contains(text_of($hl), 'MY8 Temp') && ok200($own), json_encode($rr));

    // ---- sorting of the users list: the only sortable column is "Last active" (users.last_active of Shield)
    $known = array_column(q('SELECT username FROM users WHERE deleted_at IS NULL'), 'username');
    $la = []; foreach (q('SELECT username, last_active FROM users WHERE deleted_at IS NULL') as $r) $la[$r['username']] = $r['last_active'];
    $asc = usernames_in(G($own, 'rolewarden/users?sort=asc', 'users asc'), $known); $okA = ok200($own);
    $desc = usernames_in(G($own, 'rolewarden/users?sort=desc', 'users desc'), $known); $okD = ok200($own);
    $seq = fn (array $l) => array_values(array_filter(array_map(fn ($u) => $la[$u] ?? null, $l), fn ($v) => $v !== null));
    $sa = $seq($asc); $sd = $seq($desc); $sa2 = $sa; sort($sa2); $sd2 = $sd; rsort($sd2);
    $a1 = $asc; $d1 = $desc; sort($a1); sort($d1);
    info('last_active order asc ' . json_encode(array_map(fn ($u) => [$u, $la[$u] ?? null], $asc)) . ' desc ' . json_encode($desc));
    check('P06', 'Users: sorting by Last active, asc and desc: same users, non-null last_active ascending / descending as in users.last_active', $okA && $okD && $a1 === $d1 && count(array_unique($sa)) >= 3 && $sa === $sa2 && $sd === $sd2, json_encode([$sa, $sd]));

    // ---- sign-in limit per address (last: it blocks this address for a minute)
    $fs = null; foreach (forms(G($own, 'rolewarden/settings', 'settings 2')) as $f) if (array_key_exists('sign_in_rate', $f['types'])) $fs = $f;
    if ($fs) submit($own, $fs, ['sign_in_rate' => '5'], 'rate 5');
    info('signInRate after saving 5 (the minimum of the form): ' . q1("SELECT value FROM settings WHERE `key`='signInRate'"));
    $codes = [];
    for ($i = 0; $i < 6; $i++) { $t = c("rate$i"); $h = G($t, 'login', 'rate form'); $f = form_with($h, 'password'); P($t, abs_url($t->url, $f['action']), array_replace($f['fields'], ['email' => "rate$i@my8.test", 'password' => 'Wrong-Pass']), "rate $i"); $codes[] = $t->status . ':' . short(text_of($t->body), 60); }
    $rg = c('rate-good'); $rok = login2($rg, 'imp2@my8.test', 'Cedar-Window-5521');
    check('P40', 'Limit per address set to 5: beyond it even the right password is refused (setting stored)', $fs && ! $rok && q1("SELECT value FROM settings WHERE `key`='signInRate'") === '5', 'codes=' . json_encode($codes) . ' good login=' . json_encode($rok) . ' ' . $rg->status);
    $fs = null; foreach (forms(G($own, 'rolewarden/settings', 'settings 3')) as $f) if (array_key_exists('sign_in_rate', $f['types'])) $fs = $f;
    if ($fs) submit($own, $fs, ['sign_in_rate' => '100', 'lock_attempts' => '5'], 'rate back');
    sleep(61); // the per-address bucket of the throttler empties within a minute
    $bad = array_values(array_filter($GLOBALS['SEEN'], fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr(text_of($s[3]))));
    check('P41', 'Phase P: no response with status 500, SQL text or PHP error (' . count($GLOBALS['SEEN']) . ' responses)', $bad === [], json_encode(array_map(fn ($s) => [$s[0], $s[1], short(text_of($s[3]), 200)], array_slice($bad, 0, 8))));
    $GLOBALS['U1'] = $U1;
    server_stop();
}
