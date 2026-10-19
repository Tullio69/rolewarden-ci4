<?php
// U1 phase CNT (point 2): counters of users, roles, permissions against direct queries (deleted users and roles left
// out); the activity log counter (entries of the module log written by others and failed sign-ins after the user's
// last opening of the log; absent before the first opening; reset by opening; one per user).
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== CNT: counters\n";
serve8($APP, 'CNT');
$S0 = count($GLOBALS['SEEN']);
function dbcounts(): array
{
    return ['users' => (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'), 'roles' => (int) q1('SELECT COUNT(*) FROM acl_roles WHERE deleted_at IS NULL'),
        'permissions' => (int) q1('SELECT COUNT(*) FROM acl_permissions'), 'active' => (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND active=1'),
        'inactive' => (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND active=0')];
}
function shown(Client $c, string $tag): array
{
    $h = G($c, 'rolewarden/users', $tag);
    $s = side($h);
    $xp = dom($h); $facts = [];
    foreach ($xp->query('//dl[contains(@class,"rw-facts")]/div') as $d) $facts[trim($xp->query('.//dt', $d)->item(0)->textContent)] = trim($xp->query('.//dd', $d)->item(0)->textContent);
    return ['users' => $s['items']['rolewarden/users']['count'] ?? null, 'roles' => $s['items']['rolewarden/roles']['count'] ?? null,
        'permissions' => $s['items']['rolewarden/permissions']['count'] ?? null, 'activity' => $s['items']['rolewarden/activity']['count'] ?? null, 'facts' => $facts];
}
function other_opt(Client $c, string $name): string { $xp = dom(G($c, 'rolewarden/settings', 'options')); foreach ($xp->query("//select[@name='$name']/option") as $o) if (! $o->hasAttribute('selected')) return $o->getAttribute('value'); return ''; }
function cmp(array $sh, array $db, bool $roles = true): bool
{
    return $sh['users'] === (string) $db['users'] && (! $roles || $sh['roles'] === (string) $db['roles']) && $sh['permissions'] === (string) $db['permissions']
        && ($sh['facts']['Users'] ?? null) === (string) $db['users'] && ($sh['facts']['Active'] ?? null) === (string) $db['active'] && ($sh['facts']['Inactive'] ?? null) === (string) $db['inactive'];
}
$own = c8('cnt-owner'); login8($own, 'owner@v8b.test', OPW);
$sh = shown($own, 'cnt 1'); $db = dbcounts();
check('C-1', 'Users and Permissions counters and the Users screen header equal direct queries (start; Roles apart in C-2r)', cmp($sh, $db, false), json_encode([$sh, $db]));
// a deactivated user still counts; a deleted one (panel Delete) does not; a deleted role does not; a custom permission counts
$v1 = mkuser('cnt-victim@example.com', 'user');
$v2 = mkuser('cnt-off@example.com', 'user', [], 0);
$rv = mkrole('u1r-cnt-victim', []);
postform($own, "rolewarden/users/$v1", "#rolewarden/users/$v1/delete$#", [], 'cnt delete user');
$del = q1('SELECT deleted_at FROM users WHERE id=?', [$v1]);
info("user deleted from the panel: {$own->status}, deleted_at=" . json_encode($del) . ', row still there=' . json_encode(q1('SELECT COUNT(*) FROM users WHERE id=?', [$v1])));
if ($del === null && (int) q1('SELECT COUNT(*) FROM users WHERE id=?', [$v1]) === 1) { q('UPDATE users SET deleted_at=? WHERE id=?', [date('Y-m-d H:i:s'), $v1]); info('panel delete did not soft-delete: deleted_at set in the table'); }
postform($own, "rolewarden/roles/$rv", "#rolewarden/roles/$rv/delete$#", [], 'cnt delete role');
info("role deleted from the panel: {$own->status}, deleted_at=" . json_encode(q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$rv])));
if (q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$rv]) === null && (int) q1('SELECT COUNT(*) FROM acl_roles WHERE id=?', [$rv]) === 1) { q('UPDATE acl_roles SET deleted_at=? WHERE id=?', [date('Y-m-d H:i:s'), $rv]); info('role soft-deleted in the table'); }
$extra = q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL');
q('INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES (?,?,?,0,?,?)', ['reports.export', 'reports', 'U1 custom permission', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
$sh = shown($own, 'cnt 2'); $db = dbcounts();
check('C-2', 'After a deactivation, a deleted user and a new permission: Users and Permissions counters and the Users header still equal the queries (deleted users left out, deactivated counted)', cmp($sh, $db, false) && (int) $extra >= 1, json_encode([$sh, $db, 'deleted users' => $extra]));
$rl = G($own, 'rolewarden/roles', 'cnt roles list');
$rows = dom($rl)->query('//main//table/tbody/tr')->length;
info("Roles screen rows: $rows; roles not deleted: {$db['roles']}; with the deleted ones: " . q1('SELECT COUNT(*) FROM acl_roles') . '; Roles counter: ' . $sh['roles']);
check('C-2r', 'After a role deleted from the panel the Roles counter equals the roles not deleted (and the rows of the Roles screen)', $sh['roles'] === (string) $db['roles'], json_encode(['counter' => $sh['roles'], 'not deleted' => $db['roles'], 'all rows' => q1('SELECT COUNT(*) FROM acl_roles'), 'roles screen rows' => $rows]));
$adm = c8('cnt-admin'); login8($adm, 'admin@v8b.test', OPW);
$sa = shown($adm, 'cnt admin');
check('C-3', 'Another account (admin) sees the same figures', $sa['users'] === $sh['users'] && $sa['roles'] === $sh['roles'] && $sa['permissions'] === $sh['permissions'] && $sa['facts'] === $sh['facts'], json_encode($sa));

// ---- activity log counter: holder H (admin role, can act and read the log), other O (owner)
$H = mkuser('cnt-holder@example.com', 'admin');
[$h1, $ok] = u1login('cnt-holder', 'cnt-holder@example.com');
$logRows = (int) q1('SELECT COUNT(*) FROM acl_activity_log');
$s = side(G($h1, 'rolewarden/profile', 'cnt holder first'));
check('C-4', 'Before the first opening of the log there is no Activity counter, whatever the log holds (' . $logRows . ' entries, ' . q1('SELECT COUNT(*) FROM auth_logins WHERE success=0') . ' failed sign-ins)', isset($s['items']['rolewarden/activity']) && $s['items']['rolewarden/activity']['count'] === null && $logRows > 0, json_encode($s['items']['rolewarden/activity'] ?? null));
// make sure there is at least one failed sign-in and one entry by another before the first opening
login8(c8('cnt-fail0'), 'ghost@example.com', 'wrong-Password-1');
sleep(1);
G($h1, 'rolewarden/activity', 'cnt holder opens the log');
$seen = setting_row('activitySeenAt', "user:$H");
info('after the first opening: ' . json_encode($seen));
$s = side(G($h1, 'rolewarden/profile', 'cnt holder after opening'));
check('C-5', 'Right after opening the log the counter is absent (nothing new)', actcount($s) === null, json_encode($s['items']['rolewarden/activity'] ?? null));
sleep(2);
$t0 = q1('SELECT NOW()');
// by others: two settings changes by O; by the holder himself: one; failed sign-ins: 3 unknown email + 1 on the holder's email; one successful sign-in
settings_save($own, ['activity_retention' => '90'], 'cnt O change 1');
settings_save($own, ['activity_retention' => '365'], 'cnt O change 2');
settings_save($h1, ['lock_minutes' => '30'], 'cnt H own change');
for ($i = 0; $i < 3; $i++) login8(c8("cnt-fail$i"), 'ghost@example.com', 'wrong-Password-1');
login8(c8('cnt-fail-h'), 'cnt-holder@example.com', 'wrong-Password-1');
login8(c8('cnt-okin'), 'plain@v8b.test', OPW);
$seenAt = (string) (setting_row('activitySeenAt', "user:$H")['value'] ?? '');
$others = (int) q1('SELECT COUNT(*) FROM acl_activity_log WHERE created_at > ? AND (actor_id IS NULL OR actor_id <> ?)', [$seenAt, $H]);
$mine = (int) q1('SELECT COUNT(*) FROM acl_activity_log WHERE created_at > ? AND actor_id = ?', [$seenAt, $H]);
$fails = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date > ?', [$seenAt]);
$okins = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=1 AND date > ?', [$seenAt]);
info("since $seenAt: log entries by others $others (actions " . json_encode(array_column(q('SELECT action FROM acl_activity_log WHERE created_at > ? AND (actor_id IS NULL OR actor_id <> ?)', [$seenAt, $H]), 'action')) . "), by the holder $mine, failed sign-ins $fails, successful $okins");
$s = side(G($h1, 'rolewarden/profile', 'cnt holder count'));
$cnt = $s['items']['rolewarden/activity']['count'] ?? null;
info('activity counter shown: ' . json_encode($s['items']['rolewarden/activity'] ?? null));
check('C-6', "Activity counter = log entries by others + failed sign-ins since the last opening ($others + $fails); the holder's own entries ($mine) and successful sign-ins ($okins) not counted", $cnt !== null && (int) preg_replace('/\D/', '', $cnt) === $others + $fails && $mine >= 1 && $fails >= 4 && $others >= 2, json_encode([$cnt, $others, $fails, $mine]));
check('C-6b', 'The counter is a figure the holder can act on (digits, "new" chip marked for attention)', $cnt !== null && preg_match('/\d/', $cnt) === 1, json_encode($s['items']['rolewarden/activity'] ?? null));
mkuser('cnt-other@example.com', 'admin');
[$own2, $ok] = u1login('cnt-other', 'cnt-other@example.com');
$so = side(G($own2, 'rolewarden/profile', 'cnt other never opened'));
check('C-7', 'Each user has his own: another admin, who never opened the log, has no counter while the holder has one', actcount($so) === null && $cnt !== null, json_encode([$so['items']['rolewarden/activity'] ?? null, $cnt]));
G($own2, 'rolewarden/activity', 'cnt other opens');
$s = side(G($h1, 'rolewarden/profile', 'cnt holder after owner opened'));
check('C-8', "Another user's opening does not touch the holder's counter", ($s['items']['rolewarden/activity']['count'] ?? null) === $cnt, json_encode([$cnt, $s['items']['rolewarden/activity'] ?? null]));
G($h1, 'rolewarden/activity?user=' . $OWNER, 'cnt holder opens filtered');
$s = side(G($h1, 'rolewarden/profile', 'cnt holder after reopening'));
check('C-9', 'Opening the log (here with a filter) resets the counter', actcount($s) === null, json_encode($s['items']['rolewarden/activity'] ?? null));
// only failed sign-ins after the reset
sleep(2);
login8(c8('cnt-fail9'), 'ghost@example.com', 'wrong-Password-1');
$s = side(G($h1, 'rolewarden/users', 'cnt holder one failure'));
$seenAt = (string) (setting_row('activitySeenAt', "user:$H")['value'] ?? '');
$exp = (int) q1('SELECT COUNT(*) FROM acl_activity_log WHERE created_at > ? AND (actor_id IS NULL OR actor_id <> ?)', [$seenAt, $H]) + (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date > ?', [$seenAt]);
check('C-10', "After the reset a single failed sign-in shows as $exp", (int) preg_replace('/\D/', '', (string) ($s['items']['rolewarden/activity']['count'] ?? '')) === $exp && $exp === 1, json_encode([$s['items']['rolewarden/activity'] ?? null, $exp]));
// a user without activity.view never gets the item nor a counter
[$u, $ok] = u1login('cnt-uv', 'nav-users-view@example.com');
check('C-11', 'Without activity.view: no Activity item, so no counter', $ok && ! isset(side(G($u, 'rolewarden/users', 'cnt users.view'))['items']['rolewarden/activity']), '');
check('C-clean', 'CNT pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
