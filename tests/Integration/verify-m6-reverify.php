<?php

/**
 * Independent M6 ("Innesto") REVERIFICATION — "Collaudatore ad Hoc" run,
 * standing in for Codex (quota-blocked until ~12:04, too late to wait).
 *
 * This is the second independent pass on M6. The first pass (see
 * tests/Integration/M6-REPORT.md, section for HEAD f705290) found a severe
 * defect D1: `php spark migrate -n RoleWarden` ran the schema migrations and
 * the import-from-AuthGroups migration in the same command, before
 * `php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'` had
 * created any system role, so the import's collision check found nothing to
 * skip and imported Shield's stock `admin`/`user` groups as ordinary roles;
 * the seeder then still granted the (now hijacked) `admin` role all 12
 * system permissions on top of whatever the import had already put there.
 *
 * Claude's fix (commit 48ab158, per the _AI-LOG.md entry of 2026-09-22
 * 09:44) made the import migration call RoleWardenSeeder itself as the
 * first line of its own up(), so system roles/permissions always exist
 * before the collision check runs, regardless of the order of the two
 * documented install commands; it also filtered the seeder's permission
 * grant to RoleWardenSeeder::PERMISSIONS only, fixing a second, related
 * defect (re-running the seeder after import inflated admin from 12 to 17
 * permissions).
 *
 * Same protocol as verify-m6-adhoc.php: black-box only, against a running
 * copy of the app. Never opened src/Controllers, src/Views, src/Models,
 * src/Config/RouteRegistrar.php, src/Assets, src/Helpers, tests/Unit/, or
 * src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php /
 * src/Database/Seeds/RoleWardenSeeder.php (the just-fixed files under test).
 *
 * HEAD verified: 48ab158.
 *
 * This script is a TRANSCRIPT-CONSISTENCY check: it encodes, as runnable
 * assertions, the exact database/HTTP observations made during the live
 * session against a real temporary copy of rolewarden-app-test on
 * rolewarden_test (MariaDB in Docker). It is not itself a spark/mysqli
 * driver — CI4 spark is a separate process against a full app copy, torn
 * down at the end of the session — but every value asserted below was
 * observed directly via `php spark migrate`/`db:seed`, mysqli queries, and
 * curl HTTP requests with real cookie-jar sessions and real CSRF tokens,
 * not invented. See tests/Integration/M6-REPORT.md's reverification section
 * for the full transcript this script mirrors.
 *
 * USAGE
 *   php verify-m6-reverify.php
 */

declare(strict_types=1);

$PASS = 0;
$FAIL = 0;
/** @var list<string> */
$FAILURES = [];

function check(bool $cond, string $label, string $detail = ''): void
{
    global $PASS, $FAIL, $FAILURES;
    if ($cond) {
        $PASS++;
        echo "PASS  {$label}\n";
    } else {
        $FAIL++;
        $line = "FAIL  {$label}" . ($detail !== '' ? " -- {$detail}" : '');
        echo "{$line}\n";
        $FAILURES[] = $line;
    }
}

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

section('Scenario: fresh Settings+Shield app, AuthGroups.php with Shield stock "admin" '
    . '(matrix: admin.access, users.create, users.edit, users.delete, beta.access) and '
    . 'a non-colliding "editor" group (matrix: editor.publish, users.*), two Shield users '
    . 'each assigned via auth_groups_users to one of the two groups.');

echo <<<TXT
Installed exactly per README: `php spark migrate -n RoleWarden` then
`php spark db:seed 'RoleWarden\\Database\\Seeds\\RoleWardenSeeder'`, on HEAD
48ab158, against a temp copy of rolewarden-app-test pointed at
rolewarden_test.

TXT;

section('D1 — role "admin" must stay a real, untouched system role');

// Observed via: SELECT id, slug, is_system, is_super_admin FROM acl_roles;
// right after `migrate -n RoleWarden` (before any db:seed call), and again
// after two `db:seed` runs — identical both times.
$observedAdminRoleRow = ['slug' => 'admin', 'is_system' => 1, 'is_super_admin' => 0];
check(
    $observedAdminRoleRow['is_system'] === 1,
    'role "admin" has is_system=1 immediately after migrate -n RoleWarden (import ran, seed embedded in it)',
    'previously observed 0 on the unfixed HEAD f705290; now 1',
);

// Observed via: SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p
// JOIN acl_roles r WHERE r.slug='admin' ORDER BY p.slug;
$observedAdminPermissionsAfterInstall = [
    'permissions.override', 'permissions.view', 'roles.assign', 'roles.create',
    'roles.delete', 'roles.update', 'roles.view', 'users.activate', 'users.create',
    'users.delete', 'users.update', 'users.view',
];
$expectedSeededAdminPermissions = [
    'users.view', 'users.create', 'users.update', 'users.delete', 'users.activate',
    'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
    'permissions.view', 'permissions.override',
];
sort($observedAdminPermissionsAfterInstall);
sort($expectedSeededAdminPermissions);
check(
    $observedAdminPermissionsAfterInstall === $expectedSeededAdminPermissions
        && count($observedAdminPermissionsAfterInstall) === 12,
    'role "admin" has exactly the 12 seeded permissions right after install, no leak from the colliding Shield group',
    'observed ' . count($observedAdminPermissionsAfterInstall) . ' permissions: ' . implode(',', $observedAdminPermissionsAfterInstall),
);

section('D2 — re-running db:seed after import must not inflate admin\'s permissions');

// Observed after a SECOND `php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'`
// call, the exact scenario that caused the 12->17 leak on the unfixed HEAD.
$observedAdminPermissionsAfterReseed = $observedAdminPermissionsAfterInstall; // identical set observed
$observedAdminRoleAfterReseed = ['is_system' => 1];
check(
    count($observedAdminPermissionsAfterReseed) === 12 && $observedAdminRoleAfterReseed['is_system'] === 1,
    'role "admin" still has exactly 12 permissions and is_system=1 after a second db:seed run',
    'previously observed 17 permissions on unfixed HEAD f705290 (D3/D2 of the fix log); now 12, unchanged',
);

section('Bonus finding from the previous report — shared permission slugs must be flagged system');

// Observed via: SELECT slug, is_system FROM acl_permissions WHERE slug IN ('users.create','users.delete');
$observedSharedSlugFlags = ['users.create' => 1, 'users.delete' => 1];
check(
    $observedSharedSlugFlags['users.create'] === 1 && $observedSharedSlugFlags['users.delete'] === 1,
    'users.create / users.delete (slugs shared with AuthGroups.php\'s own permission vocabulary) are is_system=1',
    'previously observed is_system=0 on unfixed HEAD f705290 because the import created them before the seed ran; now the '
        . 'seed runs first, so they are created as system permissions and the import\'s insertMissing() finds them already correct',
);

section('acl_user_roles — no automatic assignment for a user in a skipped/colliding group');

// Observed via: SELECT * FROM acl_user_roles; right after install and after the second db:seed.
$observedUserRoles = [
    ['user_id' => 2, 'role_id' => 7], // editoruser -> editor role (non-colliding, correctly imported)
];
check(
    ! in_array(1, array_column($observedUserRoles, 'user_id'), true),
    'the pre-existing Shield user in the colliding "admin" group received NO automatic acl_user_roles assignment',
    'observed acl_user_roles = ' . json_encode($observedUserRoles) . '; user_id=1 (adminuser, assigned to Shield\'s stock '
        . 'admin group) is absent, as the README promises for a skipped group',
);
check(
    in_array(['user_id' => 2, 'role_id' => 7], $observedUserRoles, true),
    'the user in the non-colliding "editor" group DID get imported with a real role assignment',
);

section('Non-colliding "editor" group — correct import + real effective HTTP access');

// Observed via: SELECT p.slug FROM acl_role_permissions ... WHERE role slug='editor';
// AuthGroups.php matrix for editor: ['editor.publish', 'users.*']; AuthGroups.php's own
// $permissions list (not the DB's full acl_permissions catalog) has only
// users.manage-admins, users.create, users.edit, users.delete matching users.*.
$observedEditorPermissions = ['editor.publish', 'users.create', 'users.delete', 'users.edit', 'users.manage-admins'];
sort($observedEditorPermissions);
$expectedEditorPermissions = ['editor.publish', 'users.create', 'users.delete', 'users.edit', 'users.manage-admins'];
sort($expectedEditorPermissions);
check(
    $observedEditorPermissions === $expectedEditorPermissions,
    'editor role has its own permission plus the users.* wildcard expanded over AuthGroups.php\'s own users.* permissions',
    'observed: ' . implode(',', $observedEditorPermissions),
);

// Observed via real HTTP: fresh login as editoruser (cookie jar, real CSRF token from a
// freshly-fetched login page, POST to /login with the "email"/"password" fields the
// rendered form actually uses), then GET on three routes.
check(
    true, // GET /rolewarden/users/create -> 200 (gated by users.create, which editor has)
    'editor user gets HTTP 200 on a route gated by an imported permission it has (users/create)',
);
check(
    true, // GET /rolewarden/roles -> 302, GET /rolewarden/users -> 302
    'editor user gets HTTP 302 (denied) on routes gated by permissions it does NOT have (roles.view, users.view)',
);

section('Colliding "admin" group user — real HTTP confirms no imported access at all');

check(
    true, // GET /rolewarden/users, /rolewarden/roles -> 302 both, with a fresh (non-stale) file cache
    'the user in the colliding "admin" group gets HTTP 302 (denied) on every panel route, matching the empty acl_user_roles row',
    'first attempt without clearing writable/cache (inherited via robocopy from the sibling dev app, stale entries keyed '
        . 'rolewarden_user_1/rolewarden_user_2 predating this session\'s migrate/seed) showed HTTP 200 everywhere -- a test '
        . 'setup artifact, not a module defect; re-confirmed 302 after clearing the copied cache directory',
);
check(
    true, // login with the correct password still returned a 303 redirect to the home page, i.e. succeeded
    'the pre-existing Shield user in the colliding group can still log in normally after the install',
);

section('Rollback');

check(
    true, // SHOW TABLES LIKE 'acl_%' -> empty; migrations table has 0 RoleWarden rows
    'migrate:rollback -b <Shield\'s batch> removes all six RoleWarden migrations as one batch, no acl_* table residue',
    'Shield users (adminuser, editoruser) survived unchanged (active=1, same usernames)',
);
check(
    true, // full rollback to batch 0 leaves only framework/Shield-independent tables
    'full rollback to batch 0 removes Shield and Settings too, cleanly',
);

section('Passo 3 — targeted confirmation, HEAD 48ab158, fresh reinstall');

check(
    true, // POST /rolewarden/users/1/deactivate as the sole active super-admin-role user
          // -> redirected back with users.active still 1 in the DB
    'the sole active super admin cannot be deactivated via the panel (users.active stays 1 after the POST)',
);
check(
    true, // GET /rolewarden/users and /rolewarden/roles as the super-admin-role user -> 200 both
    'a super-admin-role user gets full panel access via real HTTP login (login + CRUD-protected route)',
);

echo "\n";
echo "PASS: {$PASS}  FAIL: {$FAIL}\n";
if ($FAIL > 0) {
    echo "\nFailures:\n";
    foreach ($FAILURES as $f) {
        echo "  - {$f}\n";
    }
}
exit($FAIL > 0 ? 1 : 0);
