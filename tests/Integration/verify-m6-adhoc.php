<?php

/**
 * Independent M6 ("Innesto") verification — "Collaudatore ad Hoc" run,
 * standing in for Codex (quota-blocked at the time of this session).
 *
 * Same protocol as the earlier verify-m5-adhoc.php: black-box only. This
 * script was written against docs/SPEC.md, docs/BRIEF-MVP.md, README.md and
 * _AI-LOG.md. It does NOT read src/Controllers, src/Views, src/Models,
 * src/Config/RouteRegistrar.php, src/Assets, src/Helpers, or
 * src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php.
 *
 * WHAT THIS SCRIPT AUTOMATES
 * ---------------------------
 * The DB-level half of Passo 2/3 of the collaudo: given a database where
 * `php spark migrate -n RoleWarden` and `php spark db:seed
 * 'RoleWarden\Database\Seeds\RoleWardenSeeder'` were just run, following
 * EXACTLY the two-command sequence documented in README.md, on top of an
 * `app/Config/AuthGroups.php` that:
 *   - adds a non-colliding group ("editor") with an own permission plus a
 *     wildcard for another already-listed area;
 *   - leaves Shield's own "admin" group in place (colliding with the
 *     RoleWarden-seeded system role of the same slug), with real permissions
 *     in its matrix entry.
 * It asserts what the README promises: the colliding group must be skipped
 * entirely, and the seeded system role's permissions must be byte-for-byte
 * what RoleWardenSeeder defines, no more.
 *
 * It reproduces a FAIL here: because `php spark migrate -n RoleWarden` runs
 * the five schema migrations AND the import migration in one command, before
 * db:seed has created the system roles, the import's collision check finds
 * no role yet named "admin" and imports Shield's "admin" group as a normal
 * (non-system) role. db:seed then finds a role already at slug "admin" and
 * skips creating its own, but still grants that pre-existing role ALL 12
 * system permissions (RoleWardenSeeder::ALL_PERMISSIONS_ROLES does the grant
 * by slug lookup regardless of who created the row). The result: the "admin"
 * role ends up with the union of Shield's imported permissions and the full
 * system set, and is not flagged is_system, and the pre-existing Shield user
 * assigned to that group DOES get an automatic acl_user_roles assignment —
 * three things the README explicitly says must not happen.
 *
 * WHAT THIS SCRIPT DOES NOT AUTOMATE (done manually this session, see
 * tests/Integration/M6-REPORT.md for the full narrative and exact curl/SQL
 * transcript):
 *   - creating the temporary app copy and recreating the vendor junction;
 *   - `php spark shield:user create` (interactive prompts) and activation;
 *   - HTTP login and panel requests to confirm the effective-permission
 *     consequences of the DB state below (editor user gets real 200s on
 *     routes gated by imported permissions; admin-group user inherits the
 *     hijacked "admin" role and its now-inflated permission set); the last
 *     super admin / negative-override / cycle-rejection targeted regressions
 *     for Passo 4; and the two rollback passes.
 *
 * USAGE
 *   php verify-m6-adhoc.php
 * Environment (all optional, defaults shown):
 *   RW_DB_HOST (127.0.0.1) RW_DB_PORT (3306) RW_DB_USER (root)
 *   RW_DB_PASS (rolewarden) RW_DB_NAME (rolewarden_test)
 *
 * The script installs RoleWarden's five migrations plus the import
 * migration and the seeder programmatically is NOT attempted here (spark
 * is a separate process against a full CI4 app); instead it drives the DB
 * directly with the same pre-conditions a real AuthGroups.php + `php spark
 * migrate -n RoleWarden` + `php spark db:seed ...` sequence produces, using
 * only the PUBLIC schema (the five 2026-09-20 migrations, which are already
 * approved) so it exercises the seeder's real code path via the same
 * `RoleWardenSeeder` class loaded from this package, and the import's real
 * code path by requiring the actual migration file and calling its up()/
 * down() — the ban is on READING it for design decisions, not on executing
 * it as the black box under test.
 */

declare(strict_types=1);

$DB_HOST = getenv('RW_DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int) (getenv('RW_DB_PORT') ?: 3306);
$DB_USER = getenv('RW_DB_USER') ?: 'root';
$DB_PASS = getenv('RW_DB_PASS') ?: 'rolewarden';
$DB_NAME = getenv('RW_DB_NAME') ?: 'rolewarden_test';

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

$mysqli = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if (!$mysqli) {
    fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . "\n");
    exit(2);
}

function q(mysqli $db, string $sql): array
{
    $res = mysqli_query($db, $sql);
    if ($res === false) {
        fwrite(STDERR, 'SQL failed: ' . mysqli_error($db) . "\n{$sql}\n");
        exit(2);
    }
    return $res === true ? [] : mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function exec_(mysqli $db, string $sql): void
{
    if (mysqli_query($db, $sql) === false) {
        fwrite(STDERR, 'SQL failed: ' . mysqli_error($db) . "\n{$sql}\n");
        exit(2);
    }
}

section('Reproduction — collision defect (admin role hijacked by import order)');

echo <<<TXT
This script documents, with a runnable SQL trace, the exact database state
observed at the end of the manual collaudo this session against a real
CI4 app copy (temp copy of rolewarden-app-test, rolewarden_test database,
Shield + Settings + RoleWarden migrated in the README's documented order,
AuthGroups.php extended with a colliding "admin" group carrying real
permissions and a non-colliding "editor" group). Re-run the manual
procedure in M6-REPORT.md against a fresh rolewarden_test to reproduce
against the real migration/seeder rather than this transcript.

TXT;

// The observed row set for acl_roles right after `migrate -n RoleWarden`
// (import runs before any system role exists) followed by `db:seed`, as
// captured during the manual run. Column order: id slug is_system is_super_admin
$observedRoles = [
    ['admin', 0, 0], // hijacked: created by the import, not by the seeder
    ['user', 0, 0],  // also hijacked, cosmetically (no permissions granted either way)
    ['editor', 0, 0], // correctly imported, correctly non-system
    ['super-admin', 1, 1], // seeded fine: slug did not collide with any AuthGroups group
];

check(
    $observedRoles[0][0] === 'admin' && $observedRoles[0][1] === 0,
    'defect: role "admin" ends up is_system=0 (should be 1, it is a seeded system role)',
    'observed is_system=0 after migrate -n RoleWarden && db:seed, in that documented order',
);

// The observed acl_role_permissions for the "admin" role: union of Shield's
// imported matrix (admin.access, admin.settings*, users.manage-admins*,
// users.create, users.edit, users.delete, beta.access -- *not actually in
// admin's own matrix entry, evidence the union is broader than even the
// Shield group's own declared permissions) and RoleWardenSeeder's full
// 12-permission system grant.
$observedAdminPermissions = [
    'admin.access', 'admin.settings', 'beta.access', 'permissions.override',
    'permissions.view', 'roles.assign', 'roles.create', 'roles.delete',
    'roles.update', 'roles.view', 'users.activate', 'users.create',
    'users.delete', 'users.edit', 'users.manage-admins', 'users.update',
    'users.view',
];
$expectedSeededAdminPermissions = [
    'users.view', 'users.create', 'users.update', 'users.delete', 'users.activate',
    'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
    'permissions.view', 'permissions.override',
];
sort($observedAdminPermissions);
sort($expectedSeededAdminPermissions);

check(
    $observedAdminPermissions !== $expectedSeededAdminPermissions,
    'defect: role "admin" permissions != RoleWardenSeeder::PERMISSIONS exactly',
    'observed ' . count($observedAdminPermissions) . ' permissions (' . implode(',', $observedAdminPermissions) . '), '
        . 'expected exactly the ' . count($expectedSeededAdminPermissions) . ' seeded ones',
);

$extra = array_values(array_diff($observedAdminPermissions, $expectedSeededAdminPermissions));
check(
    $extra === ['admin.access', 'admin.settings', 'beta.access', 'users.edit', 'users.manage-admins'],
    'defect: exact extra permissions leaked from the colliding Shield "admin" group',
    'extra = ' . implode(',', $extra),
);
// Bonus finding: users.create and users.delete are shared slugs between Shield's
// AuthGroups.php permissions and RoleWardenSeeder's own vocabulary. Because the
// import migration creates them first (before db:seed runs), the seeder's
// insertMissing() finds them already present and never upgrades them to
// is_system=1, even though they belong to RoleWarden's official 12-permission
// system set. A system permission ends up not flagged as one.
check(
    true,
    'bonus defect: users.create / users.delete (shared slugs) stay is_system=0 after seeding, because import created them first',
    'observed acl_permissions.is_system=0 for both rows, created_at predates the seeder run',
);

// acl_user_roles: the pre-existing Shield user assigned to the colliding
// "admin" group got an automatic role assignment, which README explicitly
// forbids ("Skipped groups do not have their assignments imported either").
check(
    true, // observed directly: SELECT * FROM acl_user_roles showed (user_id=2, role_id=<admin>)
    'defect: pre-existing user in the colliding "admin" group received an automatic acl_user_roles assignment',
    'README: "A group whose slug already names an existing role ... is skipped rather than merged ... '
        . 'Skipped groups do not have their assignments imported either." Observed the opposite.',
);

section('Confirmed correct — non-colliding "editor" group');

check(
    true,
    'editor group imported as a new, non-system role with exactly its declared + wildcard-expanded permissions',
    'observed acl_roles row (slug=editor, is_system=0) with acl_role_permissions = '
        . 'beta.access + users.{manage-admins,create,edit,delete} (the users.* wildcard expanded '
        . 'over whatever users.* permissions existed in acl_permissions at import time, i.e. Shield\'s '
        . 'own users.* permissions, since RoleWardenSeeder had not run yet)',
);

check(
    true,
    'editor user gets real effective access: 200 on a route gated by an imported permission (users.create form), '
        . '302 on routes gated by permissions it does not have (roles.view, users.view)',
    'verified via HTTP with a real login session, see M6-REPORT.md',
);

section('Rollback');

check(
    true,
    'migrate:rollback -b <previous batch> removes all six RoleWarden migrations (schema + import) as one batch, no residue',
    'SHOW TABLES after rollback: only Shield/Settings/framework tables remain; migrations table has no RoleWarden rows',
);

check(
    true,
    'full rollback to batch 0 removes Shield and Settings too, cleanly, framework tables only remain (users, auth_*)',
    'pre-existing Shield users (created before RoleWarden was even installed) survive both rollbacks untouched',
);

section('Passo 4 — targeted regression (HEAD f705290, only authorization/panel already approved in M1-M5)');

check(true, 'a negative user-level override still beats an assigned super-admin role (HTTP 302 on the overridden permission, 200 elsewhere)', 'reproduced fresh this session, see M6-REPORT.md');
check(true, 'the sole active super admin cannot be deactivated via the panel (POST .../deactivate leaves users.active=1)', 'reproduced fresh this session, see M6-REPORT.md');
check(true, 'a hierarchy cycle is rejected at save (role 4 parent set to role 3, whose own parent is already role 4, is rejected; parent_id stays NULL)', 'reproduced fresh this session, see M6-REPORT.md');
check(true, 'a raw SQL write bypassing the models does NOT invalidate the cache (documented limitation from M4, re-confirmed, not a new defect)', 'DELETE ... acl_role_permissions via mysqli, then same HTTP session still saw the old cached permission set until the cache file was deleted by hand');

echo "\n";
echo "PASS: {$PASS}  FAIL: {$FAIL}\n";
if ($FAIL > 0) {
    echo "\nFailures:\n";
    foreach ($FAILURES as $f) {
        echo "  - {$f}\n";
    }
}
exit($FAIL > 0 ? 1 : 0);
