<?php

/**
 * Version matrix PHP 8.5 (Collaudatore ad Hoc B): live re-run of the M6 "existing Shield app"
 * scenario that verify-m6-reverify.php only transcribes. Driven by verify-matrix-php85.sh,
 * which prepares the state for each stage; this script only observes (mysqli + HTTP).
 *
 * Scenario (as in M6-REPORT.md): Settings + Shield migrated first, AuthGroups.php keeps Shield's
 * stock `admin` group (colliding with the system role `admin`) and adds a non-colliding
 * `editor` group (matrix editor.publish, users.*); adminuser@m6.test is in `admin`,
 * editoruser@m6.test in `editor`; then the README install of RoleWarden.
 *
 * Usage: php verify-matrix-php85-m6.php installed|reseeded|rolledback|rolledback0
 * Needs RW_DB_* (and RW_M6_PW for the HTTP part of `reseeded`).
 */

declare(strict_types=1);

require __DIR__ . '/verify-v1.lib.php';

$stage = $argv[1] ?? '';
$seeded = ['permissions.override', 'permissions.view', 'roles.assign', 'roles.create', 'roles.delete', 'roles.update',
    'roles.view', 'users.activate', 'users.create', 'users.delete', 'users.update', 'users.view'];
$editorExpected = ['editor.publish', 'users.create', 'users.delete', 'users.edit', 'users.manage-admins'];

function role_perms(string $slug): array
{
    $rows = q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id = rp.permission_id
               JOIN acl_roles r ON r.id = rp.role_id WHERE r.slug = ? ORDER BY p.slug', [$slug]);
    return array_column($rows, 'slug');
}

function uid(string $email): int
{
    return (int) q1("SELECT user_id FROM auth_identities WHERE type = 'email_password' AND secret = ?", [$email]);
}

function acl_state(): array
{
    return [
        'admin' => q("SELECT is_system, is_super_admin FROM acl_roles WHERE slug = 'admin'")[0] ?? null,
        'editor' => q("SELECT is_system, parent_id FROM acl_roles WHERE slug = 'editor'")[0] ?? null,
        'user_roles' => q('SELECT ur.user_id, r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id = ur.role_id ORDER BY ur.user_id, r.slug'),
    ];
}

function agu(): string
{
    $rows = q('SELECT user_id, `group` FROM auth_groups_users ORDER BY user_id, `group`');
    return implode('', array_map(fn ($r) => $r['user_id'] . ':' . $r['group'] . ',', $rows));
}

$aguBefore = (string) getenv('RW_M6_AGU');
if ($stage !== 'rolledback0') {
    $adminId = uid('adminuser@m6.test');
    $editorId = uid('editoruser@m6.test');
}

if ($stage === 'installed' || $stage === 'reseeded') {
    $p = $stage === 'installed' ? 'I' : 'R';
    $s = acl_state();
    check("M6$p.01", "[$stage] role admin is a system role (is_system=1, not super admin)",
        $s['admin'] !== null && (int) $s['admin']['is_system'] === 1 && (int) $s['admin']['is_super_admin'] === 0, json_encode($s['admin']));
    $ap = role_perms('admin');
    check("M6$p.02", "[$stage] role admin has exactly the 12 seeded permissions, nothing from the colliding Shield group",
        $ap === $seeded, 'observed ' . count($ap) . ': ' . implode(',', $ap));
    $flags = array_column(q("SELECT slug, is_system FROM acl_permissions WHERE slug IN ('users.create','users.delete')"), 'is_system', 'slug');
    check("M6$p.03", "[$stage] users.create / users.delete (shared with AuthGroups.php) are system permissions",
        (int) ($flags['users.create'] ?? 0) === 1 && (int) ($flags['users.delete'] ?? 0) === 1, json_encode($flags));
    check("M6$p.04", "[$stage] non-colliding group editor imported as a non-system root role",
        $s['editor'] !== null && (int) $s['editor']['is_system'] === 0 && $s['editor']['parent_id'] === null, json_encode($s['editor']));
    $ep = role_perms('editor');
    check("M6$p.05", "[$stage] editor role = editor.publish + users.* expanded over AuthGroups.php's own users.* permissions",
        $ep === $editorExpected, 'observed: ' . implode(',', $ep));
    $ur = array_map(fn ($r) => $r['user_id'] . ':' . $r['slug'], $s['user_roles']);
    check("M6$p.06", "[$stage] acl_user_roles: editoruser -> editor imported, adminuser (skipped colliding group) has no role",
        $ur === ["$editorId:editor"], 'observed: ' . json_encode($ur));
    $users = q("SELECT u.username, u.active FROM users u WHERE u.id IN (?, ?) AND u.deleted_at IS NULL ORDER BY u.username", [$adminId, $editorId]);
    check("M6$p.07", "[$stage] the two pre-existing Shield users are untouched (usernames, active=1)",
        $users === [['username' => 'adminuser', 'active' => 1], ['username' => 'editoruser', 'active' => 1]], json_encode($users));
    check("M6$p.08", "[$stage] Shield's own auth_groups_users rows are left as they were",
        $aguBefore !== '' && agu() === $aguBefore, 'before ' . $aguBefore . ' now ' . agu());

    if ($stage === 'reseeded') {
        $pw = (string) getenv('RW_M6_PW');
        $diag = '/(<b>)?(Deprecated|Warning|Notice|Fatal error)(<\/b>)?: .{0,300} on line/i';
        $bodies = [];
        $e = new Client('m6-editor');
        check('M6H.01', 'editoruser (imported role) signs in', $e->login('editoruser@m6.test', $pw), "HTTP {$e->status} -> {$e->location}");
        $e->get('rolewarden/users/create');
        $bodies[] = ['editor users/create', $e->status, $e->body];
        check('M6H.02', 'editoruser gets 200 on a route gated by an imported permission it holds (users/create)', $e->status === 200, "HTTP {$e->status}");
        foreach (['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions'] as $u) {
            $e->get($u);
            $bodies[] = ["editor $u", $e->status, $e->body];
            check('M6H.03', "editoruser is denied $u (permission not held)", in_array($e->status, [302, 303], true), "HTTP {$e->status}");
        }
        $a = new Client('m6-admin');
        check('M6H.04', 'adminuser (skipped colliding group) still signs in after the install', $a->login('adminuser@m6.test', $pw), "HTTP {$a->status} -> {$a->location}");
        foreach (['rolewarden/users', 'rolewarden/users/create', 'rolewarden/roles', 'rolewarden/permissions'] as $u) {
            $a->get($u);
            $bodies[] = ["admin $u", $a->status, $a->body];
            check('M6H.05', "adminuser has no imported access: $u denied", in_array($a->status, [302, 303], true), "HTTP {$a->status}");
        }
        $bad = [];
        foreach ($bodies as [$n, $st, $b]) {
            if (leaks_sql($b) || preg_match($diag, clean_html($b))) {
                $bad[] = "$n HTTP $st";
            }
        }
        check('M6H.06', 'no SQL text and no PHP Deprecated/Warning/Notice in any M6 response', ! $bad, implode('; ', $bad));
    }
} elseif ($stage === 'rolledback') {
    $acl = array_merge(...array_map('array_values', q("SHOW TABLES LIKE 'acl\\_%'")) ?: [[]]);
    check('M6B.01', 'rollback to the batch before RoleWarden leaves no acl_* table', $acl === [], implode(',', $acl));
    check('M6B.02', 'no RoleWarden row left in migrations', (int) q1("SELECT COUNT(*) FROM migrations WHERE namespace = 'RoleWarden'") === 0, 'rows left');
    $users = q("SELECT username, active FROM users WHERE id IN (?, ?) AND deleted_at IS NULL ORDER BY username", [$adminId, $editorId]);
    check('M6B.03', 'Shield users and their groups survive the module rollback',
        $users === [['username' => 'adminuser', 'active' => 1], ['username' => 'editoruser', 'active' => 1]]
            && $aguBefore !== '' && agu() === $aguBefore, json_encode($users) . ' groups before ' . $aguBefore . ' now ' . agu());
} elseif ($stage === 'rolledback0') {
    $left = array_values(array_filter(array_merge(...array_map('array_values', q('SHOW TABLES')) ?: [[]]), fn ($t) => $t !== 'migrations'));
    check('M6Z.01', 'rollback -b 0 removes Settings, Shield and RoleWarden cleanly (only the migrations table left)', $left === [], implode(',', $left));
} else {
    fwrite(STDERR, "unknown stage\n");
    exit(2);
}

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "TOTAL M6 [$stage]: $pass PASS, $fail FAIL\n";
exit($fail ? 1 : 0);
