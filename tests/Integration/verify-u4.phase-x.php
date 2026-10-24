<?php
/**
 * U4 probe X (Collaudatore ad Hoc, Claude): exploration only, no checks. Dumps the HTML a browser receives for the
 * dashboard, the activity log, the settings and a user page, plus the rows of the tables the dashboard reads, so that
 * the checks of phase DASH can be written against the served markup. Output folder: RW_U4_OUT.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$OUT = getenv('RW_U4_OUT');
echo "\n== X: probe (U4)\n";
serve8($APP, 'X');
$now = time();
for ($i = 0; $i < 40; $i++) {
    $t = date('Y-m-d H:i:s', $now - $i * 3 * 3600);
    q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', ['10.0.0.' . ($i % 4), 'probe', 'email_password', 'owner@v8b.test', $OWNER, $t, $i % 3 ? 1 : 0]);
}
[$own, $ok] = u1login('x-own', 'owner@v8b.test', OPW);
info('login owner ok=' . json_encode($ok) . ' location=' . $own->location);
foreach (['rolewarden' => 'dash', 'rolewarden?period=7' => 'dash7', 'rolewarden/activity' => 'activity', 'rolewarden/settings' => 'settings', "rolewarden/users/$PLAIN" => 'user', 'rolewarden/roles' => 'roles'] as $u => $n) {
    $h = G($own, $u, "x $u");
    info("$u -> {$own->status} " . $own->location);
    file_put_contents("$OUT/verify-u4.x.$n.html", $h);
    foreach (forms($h) as $f) info("form on $u: " . json_encode(['action' => $f['action'], 'method' => $f['method'], 'fields' => $f['fields'], 'types' => $f['types']]));
}
info('activity_log rows: ' . json_encode(q('SELECT * FROM acl_activity_log ORDER BY id LIMIT 30')));
info('roles: ' . json_encode(q('SELECT id, slug, name, parent_id, is_super_admin, deleted_at FROM acl_roles')));
info('dashboard perms: ' . json_encode(q("SELECT p.slug, r.slug AS role FROM acl_permissions p LEFT JOIN acl_role_permissions rp ON rp.permission_id=p.id LEFT JOIN acl_roles r ON r.id=rp.role_id WHERE p.slug LIKE 'dashboard.%'")));
info('settings: ' . json_encode(q('SELECT `class`, `key`, `value`, `type`, context FROM settings')));
info('users: ' . json_encode(q('SELECT id, username, active, created_at, deleted_at FROM users')));
info('auth_logins columns: ' . json_encode(q('SHOW COLUMNS FROM auth_logins')));
info('migrations: ' . json_encode(q("SELECT version, class, `group`, namespace, batch FROM migrations WHERE namespace LIKE 'RoleWarden%'")));
foreach (['plain@v8b.test'] as $e) {
    [$c, $ok] = u1login('x-plain', $e, OPW);
    info("login $e ok=" . json_encode($ok) . ' location=' . $c->location);
    G($c, 'rolewarden', 'x plain dash');
    info('plain /rolewarden -> ' . $c->status . ' ' . $c->location);
}
