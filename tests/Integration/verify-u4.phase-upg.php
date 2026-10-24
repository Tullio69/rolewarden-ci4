<?php
/**
 * U4 phase UPG (Collaudatore ad Hoc, Claude): the dashboard.* permissions on a fresh install and on an update from
 * 1.0.0. Sources: SPEC "Permessi sulle installazioni esistenti" and U4 "Permessi"; BRIEF-v1.1 definition of done
 * ("Da una 1.0.0 installata ... il rollback della migrazione toglie solo quelli"); CHANGELOG Unreleased, Upgrading.
 * Module trees from git archive: v1.0.0, 8dc8a49 (1.1 up to U3, the commit before U4) and the commit under test.
 * The database is compared as a whole (verify-v8.lib.php fullstate/statediff: every table, every row).
 */
require_once __DIR__ . '/verify-u1.lib.php';
echo "\n== UPG: dashboard permissions on install and update (U4)\n";
$ROOT = getenv('RW_U4_ROOT');
$NEWC = getenv('RW_V8_NEW') ?: 'cc26ffb';
const DP = ['dashboard.changes', 'dashboard.figures', 'dashboard.roles', 'dashboard.security', 'dashboard.signins'];

function grants_of(array $slugs): array
{
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $r = q("SELECT r.slug AS role, p.slug AS perm FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug IN ($in) ORDER BY r.slug, p.slug", $slugs);
    return array_map(fn ($x) => $x['role'] . ':' . $x['perm'], $r);
}
function direct_activity_roles(bool $withDeleted = false): array
{
    return array_column(q("SELECT DISTINCT r.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug='activity.view'" . ($withDeleted ? '' : ' AND r.deleted_at IS NULL') . ' ORDER BY r.slug'), 'slug');
}
function all_grants(): array { return array_map(fn ($x) => $x['role'] . ':' . $x['perm'], q('SELECT r.slug AS role, p.slug AS perm FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id ORDER BY r.slug, p.slug')); }
function expected_grants(array $roles): array { $o = []; foreach (array_unique($roles) as $r) foreach (DP as $p) $o[] = "$r:$p"; sort($o); return $o; }
function tree(string $commit, string $dir): string
{
    global $ROOT, $WORK;
    $tar = "$WORK/$dir.tar";
    $p = proc_open(['git', '-C', $ROOT, 'archive', '--format=tar', '-o', $tar, $commit], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp);
    stream_get_contents($pp[1]); $e = stream_get_contents($pp[2]);
    if (proc_close($p) !== 0) throw new RuntimeException("git archive $commit: $e");
    @mkdir("$WORK/$dir", 0777, true);
    $p = proc_open(['tar', '-xf', "$dir.tar", '-C', $dir], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, $WORK);
    stream_get_contents($pp[1]); $e = stream_get_contents($pp[2]);
    if (proc_close($p) !== 0) throw new RuntimeException("tar $commit: $e");
    return "$WORK/$dir";
}
function use_module(string $app, string $mod): void
{
    $j = "$app/vendor/rolewarden/codeigniter4-rolewarden";
    if (is_dir($j)) rmdir($j);
    junction($j, $mod);
    sp8($app, ['cache:clear']);
}
function spark_ok(string $app, array $a, string $tag): string { $o = sp8($app, $a); if (rc($o) !== 0) info("$tag: " . short($o, 800)); return $o; }

// ---------------------------------------------------------------- fresh install (setup S01 of the harness, with the demo installed after it)
$fresh = grants_of(DP);
$expFresh = expected_grants(array_merge(['admin'], direct_activity_roles()));
check('UPG-fresh-1', 'Fresh install: the five dashboard.* exist', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'dashboard.%'") === 5, json_encode(array_column(q("SELECT slug FROM acl_permissions WHERE slug LIKE 'dashboard.%'"), 'slug')));
check('UPG-fresh-2', 'Fresh install: the seeder grants them to admin (and to no role without activity.view)', in_array('admin:dashboard.figures', $fresh, true) && array_diff($fresh, $expFresh) === [], json_encode(['got' => $fresh, 'roles with activity.view' => direct_activity_roles()]));

$M100 = tree('v1.0.0', 'm100');
$MU3 = tree('8dc8a49', 'mu3');
$MNEW = tree($NEWC, 'mnew');
$APPU = app_copy('appu', $M100, false);

// ---------------------------------------------------------------- re-verification on c998ac8: fresh installs without the demo, admin gets every stock permission
function admin_missing(): array { return array_column(q("SELECT p.slug FROM acl_permissions p WHERE p.id NOT IN (SELECT rp.permission_id FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id WHERE r.slug='admin') ORDER BY p.slug"), 'slug'); }
use_module($APPU, $M100);
base_install($APPU);
$miss100 = admin_missing(); $perms100 = array_column(q('SELECT slug FROM acl_permissions ORDER BY slug'), 'slug');
use_module($APPU, $MNEW);
check('UPG-fresh-3', 'Fresh install of the commit under test (no demo) succeeds', base_install($APPU), '');
$missNew = admin_missing(); $permsNew = array_column(q('SELECT slug FROM acl_permissions ORDER BY slug'), 'slug');
info('fresh 1.0.0: ' . count($perms100) . ' permissions, admin lacks ' . json_encode($miss100) . '; fresh new: ' . count($permsNew) . ' permissions, admin lacks ' . json_encode($missNew) . '; new since 1.0.0: ' . json_encode(array_values(array_diff($permsNew, $perms100))));
check('UPG-fresh-4', 'Fresh install: admin is granted every stock permission it had on a fresh 1.0.0 plus the five dashboard.* (it lacks nothing more than in 1.0.0)', $missNew === $miss100 && array_intersect(DP, $missNew) === [], json_encode(['new lacks' => $missNew, '1.0.0 lacks' => $miss100]));

/** 1.0.0 installed and used: custom roles, a buyer's own permission, overrides, admin customised. */
function install_100(string $app, bool $adminWithoutActivity): array
{
    global $M100;
    use_module($app, $M100);
    check('UPG-100-' . ($adminWithoutActivity ? 'b' : 'a'), '1.0.0 installs (migrations and seed) on rolewarden_test', base_install($app));
    $ids = [];
    $now = date('Y-m-d H:i:s');
    $cols = array_column(q('SHOW COLUMNS FROM acl_permissions'), 'Field');
    $v = array_intersect_key(['slug' => 'reports.export', 'name' => 'Export reports', 'area' => 'reports', 'description' => 'buyer permission', 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now], array_flip($cols));
    q('INSERT INTO acl_permissions (`' . implode('`,`', array_keys($v)) . '`) VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')', array_values($v));
    $ids['auditor'] = mkrole('u4-auditor', ['activity.view', 'reports.export']);
    $ids['kid'] = mkrole('u4-auditor-kid', ['users.view']);
    q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$ids['auditor'], $ids['kid']]);
    $ids['plain'] = mkrole('u4-plainrole', ['users.view']);
    $ids['gone'] = mkrole('u4-gone-auditor', ['activity.view']);
    q('UPDATE acl_roles SET deleted_at=? WHERE id=?', [$now, $ids['gone']]);
    q('DELETE rp FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE r.slug=? AND p.slug=?', ['admin', 'users.delete']);
    if ($adminWithoutActivity) q('DELETE rp FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE r.slug=? AND p.slug=?', ['admin', 'activity.view']);
    q('UPDATE acl_roles SET name=? WHERE slug=?', ['Administrators (renamed by the buyer)', 'admin']);
    $ids['uOwner'] = make_owner('upg-owner@u4.test', U1PW, 'upgowner', 'super-admin');
    $ids['uAud'] = mkuser('upg-aud@u4.test', 'u4-auditor', ['users.view' => 0]);
    $ids['uKid'] = mkuser('upg-kid@u4.test', 'u4-auditor-kid');
    $ids['uPlain'] = mkuser('upg-plain@u4.test', 'u4-plainrole', ['roles.view' => 1]);
    $ids['uAdm'] = mkuser('upg-adm@u4.test', 'admin');
    return $ids;
}
$NOISE = fn (array $d) => array_values(array_filter($d, fn ($l) => ! preg_match('/^(migrations|settings) row (extra|missing)/', $l)));

// ================================================================= A: 1.0.0 -> commit under test, one migrate
$ids = install_100($APPU, false);
$S0 = fullstate();
$G0 = all_grants();
$rolesAv = direct_activity_roles();
use_module($APPU, $MNEW);
$o = spark_ok($APPU, ['migrate', '-n', 'RoleWarden'], 'migrate A');
check('UPG-A0', '1.0.0 -> 1.1: migrate -n RoleWarden succeeds', rc($o) === 0, short($o));
$S1 = fullstate();
$expA = expected_grants(array_merge(['admin'], $rolesAv));
$gotA = grants_of(DP);
check('UPG-A1', 'The migration creates the five dashboard.* permissions, slugs ' . implode(', ', DP), array_column(q("SELECT slug FROM acl_permissions WHERE slug LIKE 'dashboard.%' ORDER BY slug"), 'slug') === DP, '');
check('UPG-A2', 'It grants them to admin and to every role with activity.view directly (' . implode(', ', $rolesAv) . '), not to a child that inherits it nor to other roles', array_values(array_diff($gotA, array_merge($expA, expected_grants(['u4-gone-auditor'])))) === [] && array_values(array_diff($expA, $gotA)) === [], json_encode(['got' => $gotA, 'exp' => $expA]));
info('deleted role with activity.view (u4-gone-auditor) received: ' . json_encode(array_values(array_filter($gotA, fn ($g) => str_starts_with($g, 'u4-gone-auditor:')))));
$dA = statediff($S0, $S1);
$expRows = 5 + count(array_unique(array_merge(['admin'], $rolesAv))) * 5;
$other = array_values(array_filter($NOISE($dA), fn ($l) => ! preg_match('/^acl_permissions row extra: .*"slug":"dashboard\./', $l) && ! preg_match('/^acl_role_permissions(:| row extra)/', $l)));
$addedA = array_values(array_diff(all_grants(), $G0)); $goneA = array_values(array_diff($G0, all_grants()));
$nonDashA = array_values(array_filter($addedA, fn ($g) => ! str_contains($g, ':dashboard.')));
check('UPG-A3', 'Nothing else changes: no other grant added or removed, role names, buyer permission, overrides and users untouched', $other === [] && $nonDashA === [] && $goneA === [], json_encode(['grants added besides dashboard.*' => $nonDashA, 'grants removed' => $goneA, 'other rows' => array_slice($other, 0, 8)]));
info('A diff (migrations and settings rows included): ' . json_encode(array_slice($dA, 0, 30)));
check('UPG-A4', "admin still lacks users.delete and keeps its buyer name", ! in_array('admin:users.delete', grants_of(['users.delete']), true) && q1('SELECT name FROM acl_roles WHERE slug=?', ['admin']) === 'Administrators (renamed by the buyer)', '');
// re-verification on c998ac8: path A rollback (the whole 1.1 batch of the single migrate) gives back the 1.0.0 database exactly
$o = spark_ok($APPU, ['migrate:rollback', '-f'], 'rollback A');
$dRA = statediff($S0, fullstate());
check('UPG-A6', 'Path A: migrate:rollback brings the database back exactly to the customised 1.0.0 state (every table, every row)', rc($o) === 0 && $dRA === [], json_encode(array_slice($dRA, 0, 10)));
$o = spark_ok($APPU, ['migrate', '-n', 'RoleWarden'], 'migrate A again');
check('UPG-A7', 'Path A: migrating again gives the same dashboard.* grants and still no other grant added', rc($o) === 0 && grants_of(DP) === $gotA && array_values(array_filter(array_diff(all_grants(), $G0), fn ($g) => ! str_contains($g, ':dashboard.'))) === [], json_encode(grants_of(DP)));
// the upgraded panel: the auditor sees the dashboard, its child inherits it, the plain role does not
server_stop();
serve8($APPU, 'UPG-A');
foreach ([['upg-aud@u4.test', true], ['upg-kid@u4.test', true], ['upg-plain@u4.test', false], ['upg-adm@u4.test', true], ['upg-owner@u4.test', true]] as [$e, $sees]) {
    [$c, $ok] = u1login('upg-' . substr(md5($e), 0, 6), $e);
    G($c, 'rolewarden', "upg dash $e");
    check('UPG-A5-' . strtok($e, '@'), "$e after the update: " . ($sees ? 'sees the dashboard (200)' : 'is sent on (no dashboard.*)'), $ok && ($sees ? $c->status === 200 : in_array($c->status, [302, 303], true)), $c->status . ' ' . $c->location);
}
server_stop();

// ================================================================= B: 1.0.0 -> 8dc8a49 (U3) -> commit under test, then rollback of the U4 batch
$ids = install_100($APPU, true);
use_module($APPU, $MU3);
$o = spark_ok($APPU, ['migrate', '-n', 'RoleWarden'], 'migrate B1');
check('UPG-B0', '1.0.0 -> 8dc8a49 (1.1 up to U3): migrate succeeds, no dashboard.* yet', rc($o) === 0 && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'dashboard.%'") === 0, short($o));
$SB1 = fullstate();
$GB1 = all_grants();
$rolesAvB = direct_activity_roles();
use_module($APPU, $MNEW);
$o = spark_ok($APPU, ['migrate', '-n', 'RoleWarden'], 'migrate B2');
$SB2 = fullstate();
$batchRows = q("SELECT class, batch FROM migrations WHERE batch=(SELECT MAX(batch) FROM migrations)");
check('UPG-B1', 'The U4 step is one migration in its own batch (AddDashboardPermissions)', rc($o) === 0 && count($batchRows) === 1 && str_contains($batchRows[0]['class'], 'AddDashboardPermissions'), json_encode($batchRows));
$gotB = grants_of(DP);
$expB = expected_grants(array_merge(['admin'], $rolesAvB));
check('UPG-B2', 'admin without activity.view still receives dashboard.* (CHANGELOG: granted to admin and to every role with activity.view); u4-auditor too', array_values(array_diff($expB, $gotB)) === [] && array_values(array_diff($gotB, array_merge($expB, expected_grants(['u4-gone-auditor'])))) === [], json_encode(['got' => $gotB, 'exp' => $expB]));
$dB = statediff($SB1, $SB2);
$otherB = array_values(array_filter($dB, fn ($l) => ! preg_match('/^acl_permissions row extra: .*"slug":"dashboard\./', $l) && ! preg_match('/^acl_role_permissions(:| row extra)/', $l) && ! preg_match('/^migrations row extra: .*AddDashboardPermissions/', $l)));
$addedB = array_values(array_diff(all_grants(), $GB1)); $goneB = array_values(array_diff($GB1, all_grants()));
$nonDashB = array_values(array_filter($addedB, fn ($g) => ! str_contains($g, ':dashboard.')));
check('UPG-B3', 'The U4 migration alone adds the five permissions, their grants and its migrations row, nothing else (no other grant added: admin keeps users.delete and activity.view revoked)', $otherB === [] && $nonDashB === [] && $goneB === [], json_encode(['grants added besides dashboard.*' => $nonDashB, 'grants removed' => $goneB, 'other rows' => array_slice($otherB, 0, 8)]));
// use of the new permissions before the rollback: overrides both ways, a grant to another role; and one unrelated change
$now = date('Y-m-d H:i:s');
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,1,?,?)', [$ids['uPlain'], perm_id('dashboard.figures'), $now, $now]);
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,0,?,?)', [$ids['uAud'], perm_id('dashboard.changes'), $now, $now]);
q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$ids['plain'], perm_id('dashboard.roles')]);
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,1,?,?)', [$ids['uKid'], perm_id('users.create'), $now, $now]);
$keep = q('SELECT * FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$ids['uKid'], perm_id('users.create')]);
$o = spark_ok($APPU, ['migrate:rollback', '-f'], 'rollback B');
$SB3 = fullstate();
check('UPG-B4', 'migrate:rollback of the U4 batch succeeds and leaves no dashboard.* permission, grant or override', rc($o) === 0 && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'dashboard.%'") === 0 && grants_of(DP) === [], short($o));
$dR = statediff($SB1, $SB3);
$expKeep = array_map(fn ($r) => 'acl_user_permissions row extra: ' . substr(json_encode($r), 0, 300), $keep);
info('grants after the rollback that were not there before the U4 migration: ' . json_encode(array_values(array_diff(all_grants(), $GB1))) . ' ; missing: ' . json_encode(array_values(array_diff($GB1, all_grants()))));
check('UPG-B5', 'After the rollback the database is the one before the U4 migration plus the unrelated override written in between: nothing else removed or changed', $dR === $expKeep, json_encode(['diff' => array_slice($dR, 0, 10), 'expected' => $expKeep]));
$o = spark_ok($APPU, ['migrate', '-n', 'RoleWarden'], 'migrate B3');
check('UPG-B6', 'Migrating again after the rollback gives back the five permissions with the same grants', rc($o) === 0 && grants_of(DP) === $gotB, json_encode(grants_of(DP)));
