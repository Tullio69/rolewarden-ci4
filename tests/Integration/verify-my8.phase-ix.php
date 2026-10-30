<?php
/** MY8 phases I (integrity), X (production) and Z (logs), included by verify-my8.phases.php. */
declare(strict_types=1);

function fks(): array
{
    return q("SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE del, r.UPDATE_RULE upd
              FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
              WHERE k.TABLE_SCHEMA = 'rolewarden_test' AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.COLUMN_NAME");
}
function orphans(): array
{
    $o = [];
    foreach (fks() as $f) {
        $n = (int) q1("SELECT COUNT(*) FROM `{$f['t']}` x LEFT JOIN `{$f['rt']}` y ON y.`{$f['rc']}` = x.`{$f['c']}` WHERE x.`{$f['c']}` IS NOT NULL AND y.`{$f['rc']}` IS NULL");
        if ($n) $o[] = "{$f['t']}.{$f['c']} -> {$f['rt']}: $n";
    }
    return $o;
}

/* ================================================================== I: integrity */
if (in_array('I', $PH, true)) {
    echo "\n== I: integrity (charset, collation, foreign keys, non-ASCII text)\n";
    $tb = q("SELECT TABLE_NAME t, ENGINE e, TABLE_COLLATION c FROM information_schema.TABLES WHERE TABLE_SCHEMA='rolewarden_test' ORDER BY TABLE_NAME");
    info('tables: ' . json_encode($tb));
    $notInno = array_values(array_filter($tb, fn ($r) => $r['e'] !== 'InnoDB'));
    check('I01', 'Every table is InnoDB', $notInno === [], json_encode($notInno));
    $notMb4 = array_values(array_filter($tb, fn ($r) => ! str_starts_with((string) $r['c'], 'utf8mb4')));
    $cols = q("SELECT TABLE_NAME t, COLUMN_NAME c, CHARACTER_SET_NAME cs, COLLATION_NAME co FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='rolewarden_test' AND CHARACTER_SET_NAME IS NOT NULL");
    $badCols = array_values(array_filter($cols, fn ($r) => $r['cs'] !== 'utf8mb4'));
    $byColl = []; foreach ($cols as $r) $byColl[$r['co']][] = $r['t'] . '.' . $r['c'];
    info('text columns by collation: ' . json_encode(array_map(fn ($l) => count($l), $byColl)) . ' (database default ' . q1("SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='rolewarden_test'") . ')');
    if (count($byColl) > 1) info('columns not in the majority collation: ' . json_encode(array_slice(array_merge(...array_values(array_filter($byColl, fn ($l) => count($l) < max(array_map('count', $byColl))))), 0, 30)));
    check('I02', 'Every table and text column is utf8mb4', $notMb4 === [] && $badCols === [], json_encode([$notMb4, array_slice($badCols, 0, 10)]));
    $f = fks();
    info('foreign keys: ' . json_encode(array_map(fn ($r) => "{$r['t']}.{$r['c']} -> {$r['rt']}.{$r['rc']} ON DELETE {$r['del']}", $f)));
    $rule = function (string $t, string $c) use ($f): ?string { foreach ($f as $r) if ($r['t'] === $t && $r['c'] === $c) return $r['del']; return null; };
    // SPEC "Modello dati" / V2 / V3: key towards Shield's users, cascading as Shield does; acl_sessions.user_id in cascade; acl_activity_log.actor_id ON DELETE SET NULL
    check('I03', 'Foreign keys as the SPEC says: acl_user_roles/acl_user_permissions/acl_sessions.user_id -> users CASCADE, acl_activity_log.actor_id SET NULL', $rule('acl_sessions', 'user_id') === 'CASCADE' && $rule('acl_activity_log', 'actor_id') === 'SET NULL' && $rule('acl_user_roles', 'user_id') === 'CASCADE' && $rule('acl_user_permissions', 'user_id') === 'CASCADE', json_encode([$rule('acl_user_roles', 'user_id'), $rule('acl_user_permissions', 'user_id'), $rule('acl_sessions', 'user_id'), $rule('acl_activity_log', 'actor_id')]));
    check('I04', 'No orphan row on any foreign key after the panel in use', orphans() === [], json_encode(orphans()));

    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $own = c('I-owner'); login2($own, OWNER, OPW);
    // a user with a role (admin), an override, a session and entries in the log as actor; then removed for good (Shield purge = DELETE)
    $UX = new_user($own, 'my8purge', 'purge@my8.test');
    $fa = form_action(G($own, "rolewarden/users/$UX", 'purge page'), '#/users/' . $UX . '/roles$#');
    if ($fa) submit($own, $fa, ['role_id' => (string) role_id('admin')], 'purge admin');
    panelp($own, "rolewarden/users/$UX", "rolewarden/users/$UX/permissions", ['permission_id' => perm_id('roles.delete'), 'granted' => 0], 'purge override');
    $px = c('purge'); login2($px, 'purge@my8.test', UPW);
    $fr = form_with(G($px, 'rolewarden/roles/create', 'purge role form'), 'name');
    if ($fr) submit($px, $fr, ['slug' => 'my8-by-purge', 'name' => 'MY8 by purge', 'description' => '', 'parent_id' => ''], 'purge creates role');
    $before = ['roles' => (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=?', [$UX]), 'over' => (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=?', [$UX]), 'sess' => (int) q1('SELECT COUNT(*) FROM acl_sessions WHERE user_id=?', [$UX]), 'log' => (int) q1('SELECT COUNT(*) FROM acl_activity_log WHERE actor_id=?', [$UX])];
    $err = '';
    try { q('DELETE FROM users WHERE id=?', [$UX]); } catch (Throwable $e) { $err = $e->getMessage(); }
    $after = ['roles' => (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=?', [$UX]), 'over' => (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=?', [$UX]), 'sess' => (int) q1('SELECT COUNT(*) FROM acl_sessions WHERE user_id=?', [$UX]), 'log_kept' => (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE actor_id IS NULL AND actor_label='my8purge'")];
    check('I05', 'User removed for good (DELETE FROM users): roles, overrides and sessions go in cascade, log entries stay with actor_id NULL', $err === '' && $before['roles'] && $before['over'] && $before['sess'] && $before['log'] && ! $after['roles'] && ! $after['over'] && ! $after['sess'] && $after['log_kept'] >= $before['log'], json_encode([$before, $after, $err]));
    G($px, 'rolewarden/profile', 'purged user next request');
    check('I05b', 'The browser of the removed user is signed out at its next request (no error)', $px->status !== 200 && $px->status < 500, (string) $px->status);
    // a role removed for good: grants and assignments in cascade, a child role keeps working
    $fr = form_with(G($own, 'rolewarden/roles/create', 'fk parent form'), 'name');
    submit($own, $fr, ['slug' => 'my8-fk-parent', 'name' => 'MY8 FK parent', 'description' => '', 'parent_id' => ''], 'fk parent');
    $RP = role_id('my8-fk-parent');
    $fr = form_with(G($own, 'rolewarden/roles/create', 'fk child form'), 'name');
    submit($own, $fr, ['slug' => 'my8-fk-child', 'name' => 'MY8 FK child', 'description' => '', 'parent_id' => (string) $RP], 'fk child');
    $RC = role_id('my8-fk-child');
    panelp($own, "rolewarden/roles/$RP", "rolewarden/roles/$RP/permissions", ['permission_id' => perm_id('activity.view'), 'granted' => 1], 'fk grant');
    $UY = new_user($own, 'my8fk', 'fk@my8.test');
    $fa = form_action(G($own, "rolewarden/users/$UY", 'fk user'), '#/users/' . $UY . '/roles$#');
    if ($fa) submit($own, $fa, ['role_id' => (string) $RP], 'fk assign');
    $b = ['grants' => (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=?', [$RP]), 'assign' => (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE role_id=?', [$RP])];
    $err = '';
    try { q('DELETE FROM acl_roles WHERE id=?', [$RP]); } catch (Throwable $e) { $err = $e->getMessage(); }
    $a = ['grants' => (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=?', [$RP]), 'assign' => (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE role_id=?', [$RP]), 'role' => (int) q1('SELECT COUNT(*) FROM acl_roles WHERE id=?', [$RP]), 'child_parent' => q1('SELECT parent_id FROM acl_roles WHERE id=?', [$RC])];
    info('role removed for good: ON DELETE of acl_roles.parent_id = ' . json_encode($rule('acl_roles', 'parent_id')) . ', of acl_role_permissions.role_id = ' . json_encode($rule('acl_role_permissions', 'role_id')) . ', of acl_user_roles.role_id = ' . json_encode($rule('acl_user_roles', 'role_id')));
    $hc = G($own, "rolewarden/roles/$RC", 'child after parent removed');
    check('I06', 'Role removed for good (DELETE FROM acl_roles): what MySQL does follows the declared rules, no orphan, the child role page still opens', ($err === '' ? (! $a['role'] && ! $a['grants'] && ! $a['assign']) : str_contains($err, 'foreign key')) && orphans() === [] && ok200($own), json_encode([$b, $a, $err, orphans()]));
    if ($err !== '') info('DELETE of a role refused by a foreign key: ' . $err);

    // ---- non-ASCII text (accents, a 4-byte emoji, CJK) in names that the panel stores
    $name = 'Ruolo àèìòù É ß 日本 🚀';
    $desc = 'Descrizione con emoji 🎉 e accenti: perché, così, naïve';
    $fr = form_with(G($own, 'rolewarden/roles/create', 'utf8 role form'), 'name');
    submit($own, $fr, ['slug' => 'my8-utf8', 'name' => $name, 'description' => $desc, 'parent_id' => ''], 'utf8 role');
    $st = $own->status; $msg = short(text_of(F($own, 'utf8 role after')), 200);
    $RU = role_id('my8-utf8');
    $dbn = $RU ? q1('SELECT name FROM acl_roles WHERE id=?', [$RU]) : null;
    $dbd = $RU ? q1('SELECT description FROM acl_roles WHERE id=?', [$RU]) : null;
    $page = $RU ? G($own, "rolewarden/roles/$RU", 'utf8 role page') : '';
    $list = G($own, 'rolewarden/roles', 'roles list utf8');
    $logl = q1("SELECT subject_label FROM acl_activity_log WHERE action='role.created' AND subject_id=?", [$RU]);
    info('role with non-ASCII name: status ' . $st . ', stored ' . json_encode([$dbn, $dbd, $logl], JSON_UNESCAPED_UNICODE) . ', page shows name ' . json_encode(str_contains(text_of($page), $name)) . ', list ' . json_encode(str_contains(text_of($list), $name)));
    check('I07', 'Role name and description with accents, CJK and a 4-byte emoji: stored byte for byte, shown on the role page and in the list, logged (no SQL error)', $dbn === $name && $dbd === $desc && str_contains(text_of($page), $name) && str_contains(text_of($page), '🎉') && str_contains(text_of($list), $name) && $logl === $name && ! leaks_sql($page), json_encode([$st, $msg, $dbn, $dbd, $logl], JSON_UNESCAPED_UNICODE));
    $hs = G($own, 'rolewarden/users?q=' . rawurlencode('🚀 àè'), 'search emoji');
    $hk = G($own, 'rolewarden/permissions/check?permission=users.view&user=' . rawurlencode('ñoño🚀'), 'check emoji');
    $ha = G($own, 'rolewarden/activity?user=' . rawurlencode('日本🚀'), 'activity emoji');
    $hq = G($own, 'rolewarden/search?q=' . rawurlencode('Ruolo àèì 🚀'), 'search command emoji');
    check('I08', 'Searches and filters with accents and emoji (users, check a permission, activity, command search): 200, no SQL error (collations)', ! array_filter([$hs, $hk, $ha, $hq], fn ($h) => leaks_sql($h)) && $own->status === 200, short(text_of($hq), 200));
    // usernames and the import: what Shield's rules do with accents and emoji (annotated, not judged)
    $res = [];
    foreach (['my8àccento' => 'acc@my8.test', 'my8🚀' => 'emoji@my8.test'] as $un => $em) {
        $f = form_with(G($own, 'rolewarden/users/create', "create $un"), 'email');
        $v = ['username' => $un, 'email' => $em, 'password' => UPW]; foreach ($f['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
        submit($own, $f, $v, "create $un"); $s = $own->status; $after = F($own, "after $un");
        $id = uid($em);
        $res[$un] = ['status' => $s, 'created' => $id > 0, 'stored' => $id ? q1('SELECT username FROM users WHERE id=?', [$id]) : null, 'sql_on_screen' => leaks_sql($after), 'message' => short(text_of(preg_replace('#<aside.*?</aside>#s', '', $after)), 160)];
    }
    info('usernames with non-ASCII characters: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
    $csv = sys_get_temp_dir() . '/my8-utf8.csv';
    file_put_contents($csv, "\xEF\xBB\xBFemail,username\nutf8a@my8.test,my8utfa\n");
    $fi = form_action(G($own, 'rolewarden/users/import', 'import utf8'), '#users/import/preview$#');
    upload($own, abs_url($own->url, $fi['action']), ['csrf_test_name' => $fi['fields']['csrf_test_name']], 'csv', $csv);
    $hp = F($own, 'import utf8 preview');
    $fcf = form_action($hp, '#users/import/confirm$#');
    if ($fcf) { submit($own, $fcf, [], 'import utf8 confirm'); F($own, 'import utf8 done'); }
    @unlink($csv);
    check('I09', 'Non-ASCII usernames: no SQL error on screen, no 500 (outcome annotated); a UTF-8 file with BOM imports', ! array_filter($res, fn ($r) => $r['sql_on_screen'] || $r['status'] >= 500) && uid('utf8a@my8.test') > 0, json_encode($res, JSON_UNESCAPED_UNICODE));
    $bad = array_values(array_filter($GLOBALS['SEEN'], fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr(text_of($s[3]))));
    check('I10', 'Phase I: no response with status 500, SQL text or PHP error', $bad === [], json_encode(array_map(fn ($s) => [$s[0], $s[1], short(text_of($s[3]), 200)], array_slice($bad, 0, 8))));
    server_stop();
}

/* ================================================================== X: production, no SQL on screen */
if (in_array('X', $PH, true)) {
    echo "\n== X: production (CI_ENVIRONMENT = production): no SQL on screen\n";
    set_env_value('CI_ENVIRONMENT', 'production');
    $GLOBALS['SEEN'] = [];
    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $own = c('X-owner');
    check('X00', 'Production: the super admin signs in', login2($own, OWNER, OPW), (string) $own->status);
    $U = (int) ($GLOBALS['U1'] ?? uid('user1@my8.test')) ?: uid(OWNER);
    $R = role_id('my8-editors') ?: role_id('admin');
    $screens = ['rolewarden', 'rolewarden?period=7', 'rolewarden?period=30', 'rolewarden?period=90', 'rolewarden/users', 'rolewarden/users?q=a&sort=asc&status=active', 'rolewarden/users/create', 'rolewarden/users/import', "rolewarden/users/$U", "rolewarden/users/$U/edit",
        'rolewarden/roles', 'rolewarden/roles/create', "rolewarden/roles/$R", "rolewarden/roles/$R/edit", 'rolewarden/permissions', 'rolewarden/permissions/check?permission=users.view&user=' . $U,
        'rolewarden/settings', 'rolewarden/activity', 'rolewarden/activity?action=auth.failed&from=' . gmdate('Y-m-d', time() - 30 * 86400) . '&to=' . gmdate('Y-m-d'), 'rolewarden/appearance', 'rolewarden/profile', 'rolewarden/sessions', 'rolewarden/search?q=role', 'demo'];
    $bad = [];
    foreach ($screens as $s) { $h = G($own, $s, "X $s"); if (! ok200($own)) $bad[] = "$s {$own->status} " . short(text_of($h), 120); }
    check('X01', 'Production: every screen (' . count($screens) . ') answers 200 without SQL or PHP errors', $bad === [], json_encode($bad));
    // odd input that reaches the queries: never a 500, never SQL on screen
    $odd = ['rolewarden/users?sort=%27;DROP', 'rolewarden/users?role=abc&status=%27x', 'rolewarden/users?q=' . rawurlencode("' OR 1=1 -- \\"), 'rolewarden/users?page=-5', 'rolewarden/users?page=99999',
        'rolewarden/activity?user=' . rawurlencode("x' OR '1"), 'rolewarden/activity?action=' . rawurlencode("'; --"), 'rolewarden?period=abc',
        'rolewarden/permissions/check?permission=' . rawurlencode("x'") . '&user=' . rawurlencode("1 OR 1"), 'rolewarden/users/99999999', 'rolewarden/roles/99999999', 'rolewarden/users/abc'];
    $res = [];
    foreach ($odd as $s) { G($own, $s, "X odd $s"); $res[] = [$s, $own->status, leaks_sql($own->body)]; }
    info('odd input: ' . json_encode($res));
    check('X02', 'Production: odd query strings and missing ids: no 500, no SQL on screen', ! array_filter($res, fn ($r) => $r[1] >= 500 || $r[2]), json_encode(array_values(array_filter($res, fn ($r) => $r[1] >= 500 || $r[2]))));
    // dates typed by hand in the activity filter (the field is <input type=date>, but the address is editable)
    $dres = [];
    foreach (['from=2026-13-45', 'to=2026-02-30', 'from=abc&to=xyz', 'from=2026-10-01&to=2026-10-31'] as $qs) { G($own, "rolewarden/activity?$qs", "X date $qs"); $dres[] = [$qs, $own->status, leaks_sql($own->body)]; }
    info('activity with dates typed by hand: ' . json_encode($dres));
    check('X02b', 'Production: Activity filter with an impossible date in the address (2026-13-45, 2026-02-30, abc): no 500, no SQL on screen', ! array_filter($dres, fn ($r) => $r[1] >= 500 || $r[2]), json_encode($dres));
    // r2 (D1 re-check on ee4f9d9): more impossible dates and valid leap days. For each: status, SQL on screen, the value
    // echoed in the filter field, the "Entries" count against the unfiltered page (filter applied or ignored) and the SQL
    // error lines the request added to writable/logs. Bounds chosen so that "applied" and "ignored" give different counts:
    // every entry of the run is from 2026-08 or later, so to=<early 2026 date> applied gives 0, ignored gives the total.
    $all0 = entries(G($own, 'rolewarden/activity', 'X d1 base'));
    $d1 = [];
    foreach (['to=2026-13-45', 'to=2026-02-30', 'to=2026-02-29', 'to=2026-04-31', 'to=0000-00-00', 'from=2026-02-29', 'from=2026-04-31', 'from=0000-00-00', 'from=2028-02-29', 'to=2028-02-29', 'to=2024-02-29'] as $qs) {
        $l0 = count(sqlerr_lines());
        $h = G($own, "rolewarden/activity?$qs", "X d1 $qs");
        [$k, $v] = explode('=', $qs, 2);
        $n = entries($h);
        $f = form_with($h, $k);
        $d1[] = ['q' => $qs, 'status' => $own->status, 'sql_on_screen' => leaks_sql($h), 'field' => $f['fields'][$k] ?? null, 'entries' => $n, 'unfiltered' => $all0, 'effect' => $n === null ? '?' : ($n === $all0 ? 'ignored/no-op' : 'applied'), 'new_sql_log_lines' => count(sqlerr_lines()) - $l0];
    }
    foreach ($d1 as $r) info('D1 ' . json_encode($r));
    $by = []; foreach ($d1 as $r) $by[$r['q']] = $r;
    $imp = ['to=2026-13-45', 'to=2026-02-30', 'to=2026-02-29', 'to=2026-04-31', 'to=0000-00-00', 'from=2026-02-29', 'from=2026-04-31', 'from=0000-00-00'];
    check('X02c', 'Production: Activity with impossible dates (2026-13-45, 2026-02-30, 2026-02-29, 2026-04-31, 0000-00-00 as from and to): 200, no SQL on screen, no SQL line in the log; to=<impossible> does not narrow the list (filter ignored)', ! array_filter($imp, fn ($q) => $by[$q]['status'] !== 200 || $by[$q]['sql_on_screen'] || $by[$q]['new_sql_log_lines'] !== 0 || (str_starts_with($q, 'to=') && $by[$q]['entries'] !== $all0)), json_encode(array_map(fn ($q) => [$q, $by[$q]['status'], $by[$q]['entries'], $by[$q]['new_sql_log_lines']], $imp)));
    check('X02d', 'Production: Activity with valid leap days: from=2028-02-29 applied (0 entries), to=2028-02-29 keeps all, to=2024-02-29 applied (0 entries); 200, no SQL in the log', $by['from=2028-02-29']['status'] === 200 && $by['from=2028-02-29']['entries'] === 0 && $by['to=2028-02-29']['entries'] === $all0 && $by['to=2024-02-29']['entries'] === 0 && $all0 > 0 && ! array_filter(['from=2028-02-29', 'to=2028-02-29', 'to=2024-02-29'], fn ($q) => $by[$q]['status'] !== 200 || $by[$q]['new_sql_log_lines'] !== 0), json_encode(array_map(fn ($q) => [$q, $by[$q]['status'], $by[$q]['entries'], $by[$q]['field']], ['from=2028-02-29', 'to=2028-02-29', 'to=2024-02-29'])) . " unfiltered $all0");
    // forms with values the database could refuse: too long, duplicate unique keys
    $long = str_repeat('é', 300);
    $fr = form_with(G($own, 'rolewarden/roles/create', 'X role form'), 'name');
    submit($own, $fr, ['slug' => 'my8-' . str_repeat('x', 300), 'name' => $long, 'description' => str_repeat('🚀', 20000), 'parent_id' => ''], 'X long role'); $r1 = [$own->status, leaks_sql(F($own, 'X long after'))];
    $fr = form_with(G($own, 'rolewarden/roles/create', 'X dup form'), 'name');
    submit($own, $fr, ['slug' => 'admin', 'name' => 'Duplicate', 'description' => '', 'parent_id' => ''], 'X dup role'); $r2 = [$own->status, leaks_sql(F($own, 'X dup after')), (int) q1("SELECT COUNT(*) FROM acl_roles WHERE slug='admin'")];
    $f = form_with(G($own, 'rolewarden/users/create', 'X dup user'), 'email');
    $v = ['username' => 'my8owner', 'email' => OWNER, 'password' => UPW]; foreach ($f['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
    submit($own, $f, $v, 'X dup user'); $r3 = [$own->status, leaks_sql(F($own, 'X dup user after'))];
    $f = form_with(G($own, 'rolewarden/users/create', 'X long user'), 'email');
    $v = ['username' => str_repeat('a', 300), 'email' => str_repeat('b', 300) . '@my8.test', 'password' => UPW]; foreach ($f['types'] ?? [] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = UPW;
    submit($own, $f, $v, 'X long user'); $r4 = [$own->status, leaks_sql(F($own, 'X long user after'))];
    info('values the database could refuse (status, SQL on screen): long role ' . json_encode($r1) . ', duplicate slug ' . json_encode($r2) . ', duplicate user ' . json_encode($r3) . ', long user ' . json_encode($r4) . ', long role stored: ' . json_encode(q1("SELECT COUNT(*) FROM acl_roles WHERE slug LIKE 'my8-xxx%'")));
    check('X03', 'Production: too long values and duplicate unique keys (role slug, user email/username) refused without 500 or SQL on screen; no second "admin"', $r1[0] < 500 && ! $r1[1] && $r2[0] < 500 && ! $r2[1] && $r2[2] === 1 && $r3[0] < 500 && ! $r3[1] && $r4[0] < 500 && ! $r4[1], json_encode([$r1, $r2, $r3, $r4]));
    // the database goes away in the middle of use
    // the database server stops (connection refused), then starts again; never on a shared server (MY8_NOSTOP, MariaDB comparison)
    if (! getenv('MY8_NOSTOP')) {
    exec('docker stop -t 5 ' . escapeshellarg(CT), $o1, $rc1);
    $d = c('X-down'); $h = G($d, 'login', 'X down login'); $s1 = $d->status;
    $h2 = G($own, 'rolewarden/users', 'X down users'); $s2 = $own->status;
    exec('docker start ' . escapeshellarg(CT), $o2, $rc2);
    for ($i = 0; $i < 60; $i++) { try { db(true); break; } catch (Throwable $e) { sleep(2); } }
    sleep(2);
    info("database stopped (rc $rc1): login $s1, users $s2; started again rc $rc2");
    check('X04', 'Production with the database server stopped: an error page (status >= 500, with a body) without SQL, driver or connection details', $rc1 === 0 && ($s1 === 200 || $s1 >= 500) && $s2 >= 500 && trim(text_of($h2)) !== '' && ! leaks_sql($h) && ! leaks_sql($h2) && ! preg_match('/3318|rwapp|127\.0\.0\.1:33|mysqli/i', $h . $h2), "login $s1 users $s2 " . short(text_of($h2), 200));
    G($own, 'rolewarden/users', 'X back');
    check('X05', 'Production: back to normal when the database answers again', ok200($own), (string) $own->status);
    }
    $bad = array_values(array_filter($GLOBALS['SEEN'], fn ($s) => leaks_sql($s[3]) || phperr(text_of($s[3]))));
    check('X06', 'Production: no response of the phase shows SQL, driver details or PHP errors (' . count($GLOBALS['SEEN']) . ' responses)', $bad === [], json_encode(array_map(fn ($s) => [$s[0], $s[1]], array_slice($bad, 0, 8))));
    server_stop();
    set_env_value('CI_ENVIRONMENT', 'development');
}

/* ================================================================== Z: the logs of the whole run */
if (in_array('Z', $PH, true)) {
    echo "\n== Z: writable/logs of the run\n";
    $all = array_merge($GLOBALS['ALOG'] ?? [], logs_of(APP));
    $sql = array_merge($GLOBALS['ASQL'] ?? [], sqlerr_lines());
    info('log lines of A/RB kept aside: ' . count($GLOBALS['ALOG'] ?? []));
    // provoked on purpose, listed apart: the wrong password of B04 (Access denied) and the stopped server of X04
    $provoked = array_values(array_filter($sql, fn ($l) => preg_match('/Unable to connect|Connection refused|gone away|Lost connection|timed out|actively refused|rifiuto persistente|Access denied for user/i', $l)));
    $real = array_values(array_diff($sql, $provoked));
    file_put_contents(__DIR__ . '/verify-my8.' . $O['label'] . '.applog.txt', redact(implode("\n", $all)) . "\n");
    $lv = []; foreach ($all as $l) if (preg_match('/^(\w+) - /', $l, $m)) $lv[$m[1]] = ($lv[$m[1]] ?? 0) + 1;
    info('log lines by level: ' . json_encode($lv) . '; provoked connection errors: ' . count($provoked));
    $other = array_values(array_filter(bad_log_lines($all), fn ($l) => ! in_array($l, $sql, true) && ! preg_match('/SMTP|fsockopen|Unable to send email|email/i', $l)));
    info('other WARNING/ERROR lines (email to the closed SMTP port excluded): ' . json_encode(array_slice(array_map(fn ($l) => short($l, 200), $other), 0, 15)));
    check('Z01', 'writable/logs: no SQL error of any kind (syntax, ONLY_FULL_GROUP_BY, collations, strict values, keys) in the whole run', $real === [], json_encode(array_slice(array_map(fn ($l) => short($l, 300), $real), 0, 10)));
}
