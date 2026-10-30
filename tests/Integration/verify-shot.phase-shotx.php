<?php
/**
 * SHOT phase SHOTX (Collaudatore ad Hoc, Claude): exploration only, no checks. Saves the served HTML of the pages the
 * SHOT commit touched and the permission catalogue, so the checks of phase SHOT can be written from what a browser receives.
 * Output folder: RW_SHOT_OUT (session scratchpad), never the repository.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== SHOTX: exploration\n";
$OUT = getenv('RW_SHOT_OUT') ?: exit("RW_SHOT_OUT missing\n");
@mkdir($OUT, 0777, true);
serve8($APP, 'SHOTX');
$perms = array_column(q('SELECT slug FROM acl_permissions ORDER BY slug'), 'slug');
file_put_contents("$OUT/perms.txt", implode("\n", $perms));
file_put_contents("$OUT/roles.json", json_encode(q('SELECT id, slug, name, parent_id, is_super_admin, deleted_at FROM acl_roles'), JSON_PRETTY_PRINT));
file_put_contents("$OUT/users.json", json_encode(q('SELECT u.id, u.username, u.active, i.secret FROM users u JOIN auth_identities i ON i.user_id=u.id AND i.type="email_password"'), JSON_PRETTY_PRINT));
[$c, $ok] = u1login('x-owner', 'owner@v8b.test', OPW);
info('owner login ' . ($ok ? 'ok' : 'FAILED'));
$editor = role_id('editor') ?: (int) q1('SELECT id FROM acl_roles WHERE is_super_admin=0 ORDER BY id DESC LIMIT 1');
$pages = [
    'dashboard' => 'rolewarden',
    'users' => 'rolewarden/users',
    'roles' => 'rolewarden/roles',
    'role-admin' => 'rolewarden/roles/' . role_id('admin'),
    'role-other' => "rolewarden/roles/$editor",
    'user-owner' => 'rolewarden/users/' . $OWNER,
    'user-admin' => 'rolewarden/users/' . $ADMIN,
    'user-plain' => 'rolewarden/users/' . $PLAIN,
    'permissions' => 'rolewarden/permissions',
    'check-granted' => 'rolewarden/permissions/check?permission=users.delete&user=' . $ADMIN,
    'activity' => 'rolewarden/activity',
    'appearance' => 'rolewarden/appearance',
    'settings' => 'rolewarden/settings',
];
foreach ($pages as $k => $u) {
    $h = G($c, $u, "x $k");
    file_put_contents("$OUT/page-$k.html", $h);
    info("$k: $u -> {$c->status} " . strlen($h) . ' bytes');
}
