<?php

/**
 * Graft scenario, live (version matrix PHP 8.3 + CI4 4.5, Collaudatore ad Hoc A).
 * Same scenario as verify-m6-reverify.php, but every value is read now from rolewarden_test
 * and from HTTP instead of being transcribed: an existing Shield app (Shield's own wiring,
 * stock AuthGroups.php plus a non-colliding "editor" group, two users created with
 * `shield:user create/activate/addgroup`), then the README wiring and install, then rollback.
 * Written against README, SPEC and BRIEF only. Driven step by step by verify-matrix-ci45.sh:
 *   php verify-matrix-ci45-graft.php pre|migrated|seeded|http <env>|rolledback|rolledback0
 * Passwords come from RW_PWA / RW_PWE (random per run, never on disk); state between steps in RW_STATE.
 */

declare(strict_types=1);

require __DIR__ . '/verify-v1.lib.php';

$step = $argv[1] ?? '';
$env = $argv[2] ?? '';
$stateFile = getenv('RW_STATE');
$state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
echo "\n=== graft step: $step $env\n";

function tables(string $like): array
{
    return array_column(q('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ? ORDER BY table_name', [$like]), 't');
}

/** Shield-owned state that the module must never change. Login bookkeeping (last_active) excluded. */
function shield_state(): array
{
    return [
        'users' => q('SELECT id, username, active, status, deleted_at FROM users ORDER BY id'),
        'identities' => q("SELECT user_id, type, secret, secret2 FROM auth_identities WHERE type = 'email_password' ORDER BY user_id"),
        'groups' => q('SELECT user_id, `group` FROM auth_groups_users ORDER BY user_id, `group`'),
        'perms' => q('SELECT user_id, permission FROM auth_permissions_users ORDER BY user_id, permission'),
    ];
}

function acl_digest(): string
{
    $s = [];
    foreach (['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles'] as $t) {
        $s[$t] = q("SELECT * FROM $t");
        foreach ($s[$t] as &$r) {
            unset($r['created_at'], $r['updated_at']);
        }
        sort($s[$t]);
    }
    return md5(json_encode($s));
}

function role_perms(string $role): array
{
    $p = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id = rp.role_id JOIN acl_permissions p ON p.id = rp.permission_id WHERE r.slug = ? ORDER BY p.slug', [$role]), 'slug');
    sort($p);
    return $p;
}

/** The probe route prints json_encode(inGroup()); development appends the debug toolbar, so only the leading token counts. */
function probe(Client $c, string $g): string
{
    $c->get('rw-probe/ingroup/' . $g);
    return preg_match('/^\s*(true|false|null)/', $c->body, $m) ? $m[1] : "HTTP {$c->status}";
}

function uid(string $username): int
{
    return (int) q1('SELECT id FROM users WHERE username = ?', [$username]);
}

$SEEDED = ['permissions.override', 'permissions.view', 'roles.assign', 'roles.create', 'roles.delete', 'roles.update', 'roles.view', 'users.activate', 'users.create', 'users.delete', 'users.update', 'users.view'];
// Stock Shield AuthGroups.php (v1.4.1 as published by shield:setup) plus the "editor" group; wildcards
// expand over AuthGroups' own $permissions (README: "reads ... its $groups, $permissions and $matrix").
$EXPECT_IMPORTED = [
    'superadmin' => ['admin.access', 'admin.settings', 'beta.access', 'users.create', 'users.delete', 'users.edit', 'users.manage-admins'],
    'developer' => ['admin.access', 'admin.settings', 'beta.access', 'users.create', 'users.edit'],
    'beta' => ['beta.access'],
    'editor' => ['editor.publish', 'users.create', 'users.delete', 'users.edit', 'users.manage-admins'],
];

function import_checks(string $tag): void
{
    global $SEEDED, $EXPECT_IMPORTED, $state;
    $roles = [];
    foreach (q('SELECT slug, is_system, is_super_admin FROM acl_roles') as $r) {
        $roles[$r['slug']] = [(int) $r['is_system'], (int) $r['is_super_admin']];
    }
    check("$tag.1", 'system roles present: super-admin (system, super), admin and user (system)', ($roles['super-admin'] ?? null) === [1, 1] && ($roles['admin'] ?? null) === [1, 0] && ($roles['user'] ?? null) === [1, 0], json_encode($roles));
    foreach ($EXPECT_IMPORTED as $slug => $perms) {
        check("$tag.2-$slug", "non-colliding group \"$slug\" imported as an ordinary role with its matrix expanded", ($roles[$slug] ?? null) === [0, 0] && role_perms($slug) === $perms, json_encode([$roles[$slug] ?? null, role_perms($slug)]));
    }
    $extra = array_diff(array_keys($roles), ['super-admin', 'admin', 'user'], array_keys($EXPECT_IMPORTED));
    check("$tag.3", 'no other role created', $extra === [], implode(',', $extra));
    check("$tag.4", 'colliding group "admin" skipped: role admin keeps exactly the 12 seeded permissions (no admin.access / beta.access / users.edit leak)', role_perms('admin') === $SEEDED, implode(',', role_perms('admin')));
    check("$tag.5", 'colliding group "user" skipped: role user gets nothing from AuthGroups', array_diff(role_perms('user'), $SEEDED) === [], implode(',', role_perms('user')));
    $ur = q('SELECT u.username, r.slug FROM acl_user_roles ur JOIN users u ON u.id = ur.user_id JOIN acl_roles r ON r.id = ur.role_id ORDER BY u.username, r.slug');
    check("$tag.6", 'assignments: only editoruser -> editor imported; admin and user (colliding, skipped) assignments not imported', $ur === [['username' => 'editoruser', 'slug' => 'editor']], json_encode($ur));
    $shared = q("SELECT slug, is_system FROM acl_permissions WHERE slug IN ('users.create','users.delete') ORDER BY slug");
    check("$tag.7", 'slugs shared with AuthGroups (users.create, users.delete) remain system permissions', array_column($shared, 'is_system') === ['1', '1'] || array_column($shared, 'is_system') === [1, 1], json_encode($shared));
    check("$tag.8", 'Shield users, identities, groups and user permissions unchanged by the install', shield_state() == $state['shield'], 'differs from the pre-install state');
}

switch ($step) {
    case 'pre':
        check('PRE.1', 'existing app: Shield users adminuser and editoruser exist and are active', q1("SELECT COUNT(*) FROM users WHERE username IN ('adminuser','editoruser') AND active = 1") == 2);
        $g = q('SELECT u.username, g.`group` FROM auth_groups_users g JOIN users u ON u.id = g.user_id ORDER BY u.username, g.`group`');
        check('PRE.2', 'Shield groups assigned (default "user" plus admin / editor)', $g === [['username' => 'adminuser', 'group' => 'admin'], ['username' => 'adminuser', 'group' => 'user'], ['username' => 'editoruser', 'group' => 'editor'], ['username' => 'editoruser', 'group' => 'user']], json_encode($g));
        check('PRE.3', 'no RoleWarden table before the module install', tables('acl\_%') === []);
        $state['shield'] = shield_state();
        $state['tables'] = tables('%');
        break;

    case 'migrated':
        $rwBatch = q1("SELECT MAX(batch) FROM migrations WHERE namespace = 'RoleWarden'");
        $prev = q1("SELECT MAX(batch) FROM migrations WHERE namespace <> 'RoleWarden'");
        check('MIG.0', 'migrate -n RoleWarden creates a batch of its own, after Shield\'s', $rwBatch !== null && (int) $rwBatch === (int) $prev + 1 && q1("SELECT COUNT(DISTINCT batch) FROM migrations WHERE namespace = 'RoleWarden'") == 1, "RoleWarden batch=$rwBatch previous=$prev");
        import_checks('MIG');
        $state['acl'] = acl_digest();
        break;

    case 'seeded':
        import_checks('SEED');
        check('SEED.9', 'db:seed after the import is a no-op (acl_* content identical)', acl_digest() === $state['acl']);
        break;

    case 'http':
        $pwA = getenv('RW_PWA');
        $pwE = getenv('RW_PWE');
        $denied = fn (Client $c) => (in_array($c->status, [302, 303], true) && ! str_contains($c->location, '/rolewarden/')) || $c->status === 403;
        $e = new Client("graft-editor-$env");
        check("H$env.1", 'pre-existing user editoruser still logs in with the password set before the install', $e->login('editoruser@example.test', $pwE), "status {$e->status} -> {$e->location}");
        $e->get('rolewarden/users/create');
        check("H$env.2", 'editoruser: route gated by an imported permission (users.create) -> 200', $e->status === 200 && ! leaks_sql($e->body), "status {$e->status}");
        foreach (['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions'] as $i => $p) {
            $e->get($p);
            check("H$env.3" . chr(97 + $i), "editoruser: $p (permission not held) is denied", $denied($e), "status {$e->status} -> {$e->location}");
        }
        $inEditor = probe($e, 'editor');
        $inAdmin = probe($e, 'admin');
        check("H$env.4", 'inGroup() on the graft: editoruser inGroup("editor") true (imported role), inGroup("admin") false', $inEditor === 'true' && $inAdmin === 'false', "editor=$inEditor admin=$inAdmin");

        $a = new Client("graft-admin-$env");
        check("H$env.5", 'pre-existing user adminuser (colliding "admin" group) still logs in', $a->login('adminuser@example.test', $pwA), "status {$a->status} -> {$a->location}");
        if ($env === 'development') {
            $a->get('rolewarden/users');
            check("H$env.6", 'adminuser without imported roles: rolewarden/users denied', $denied($a), "status {$a->status}");
            // README "Creating the first super admin", SQL verbatim with the email replaced
            q("INSERT INTO acl_user_roles (user_id, role_id)
SELECT i.user_id, r.id
FROM auth_identities i
JOIN acl_roles r ON r.slug = 'super-admin'
WHERE i.type = 'email_password' AND i.secret = ?", ['adminuser@example.test']);
            array_map('unlink', array_filter(glob(getenv('RW_V1_APP') . '/writable/cache/*') ?: [], fn ($f) => is_file($f) && basename($f) !== 'index.html'));
            $a = new Client("graft-admin2-$env");
            $a->login('adminuser@example.test', $pwA);
        }
        foreach (['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions'] as $i => $p) {
            $a->get($p);
            check("H$env.7" . chr(97 + $i), "adminuser made super admin with the README SQL: $p -> 200", $a->status === 200 && ! leaks_sql($a->body), "status {$a->status}");
        }
        $p = probe($a, 'super-admin');
        check("H$env.8", 'inGroup("super-admin") true for the user holding that role', $p === 'true', $p);
        $a->get('rolewarden/roles');
        check("H$env.9", 'roles list shows the imported roles', stripos($a->body, '>Editor<') !== false && stripos($a->body, '>Developer<') !== false && stripos($a->body, '>Beta User<') !== false, "status {$a->status}");
        break;

    case 'rolledback':
        check('RB.1', 'rollback to the batch before RoleWarden\'s: no acl_* table left', tables('acl\_%') === [], implode(',', tables('acl\_%')));
        check('RB.2', 'no RoleWarden row left in migrations', q1("SELECT COUNT(*) FROM migrations WHERE namespace = 'RoleWarden'") == 0);
        check('RB.3', 'Shield users, identities, groups and permissions identical to before the install', shield_state() == $state['shield']);
        check('RB.4', 'table set identical to the pre-install one', tables('%') === $state['tables'], json_encode(array_values(array_diff(tables('%'), $state['tables']))));
        check('RB.5', 'Settings and Shield migrations still recorded', q1("SELECT COUNT(*) FROM migrations WHERE namespace IN ('CodeIgniter\\\\Settings','CodeIgniter\\\\Shield')") > 0);
        break;

    case 'rolledback0':
        $left = tables('%');
        check('RB0.1', 'rollback -b 0: only the (empty) migrations table is left', $left === ['migrations'] && q1('SELECT COUNT(*) FROM migrations') == 0, json_encode($left));
        break;

    default:
        exit("unknown step\n");
}

file_put_contents($stateFile, json_encode($state));
$f = count(array_filter($GLOBALS['results'], fn ($r) => ! $r[2]));
printf("TOTAL step %s %s: %d PASS, %d FAIL\n", $step, $env, count($GLOBALS['results']) - $f, $f);
exit($f > 0 ? 1 : 0);
