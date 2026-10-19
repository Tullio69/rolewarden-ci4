<?php
/**
 * U1 phases for verify-u1.s3.php (point 6, the update from 1.0.0), run inside a u1-web container (Docker, PHP 8.3,
 * Apache) on the complete application of build/rolewarden-app-1.0.0.zip:
 *   UPG  1.0.0 installed by its installer and in use (users, a theme file of 1.0 without a sidebar style); the module
 *        folder replaced by `git archive 6c636d4` and `php spark migrate -n RoleWarden` (CHANGELOG "Upgrading"):
 *        Paper sidebar, Today off, nothing else changed in the database
 *   RB   rollback of the new migration: the database back to its 1.0.0 content; migrating again turns Today off again
 *   OV   the 1.0 layouts/panel.php copied to app/Views/overrides/RoleWarden/Views/layouts/: every screen answers, the
 *        phases U..L of S3 then run with that copy in place (no error in the logs, L01-L04)
 *   NEW  a new installation from 6c636d4 (module folder replaced before the installer): Today on
 * Assertions from docs/SPEC.md (v1.1, U1 details), docs/BRIEF-v1.1.md, CHANGELOG [Unreleased], documentation/.
 */
declare(strict_types=1);
require_once __DIR__ . '/verify-u1.lib.php';

function u1st(): array
{
    // every table, every row; volatile tables and columns of a signed-in request left out (sessions, sign-ins, last_active)
    $s = fullstate();
    foreach (['acl_sessions', 'auth_logins', 'auth_remember_tokens', 'auth_token_logins', 'ci_sessions'] as $t) unset($s[$t]);
    // Shield rehashes a password at sign-in (cost 10 of the accounts written here -> its own 12): secret2 left out too
    foreach ($s as $t => &$v) { foreach ($v['rows'] as &$r) foreach (array_keys($r) as $k) if (preg_match('/^(last_active|last_used_at|updated_at|last_seen_at|secret2)$/', $k)) unset($r[$k]); unset($r); }
    unset($v);
    // what using the 1.1 panel writes for a user (log opened, rail chosen) is usage, not the migration's doing
    if (isset($s['settings'])) $s['settings']['rows'] = array_values(array_filter($s['settings']['rows'], fn ($r) => ! (str_starts_with((string) $r['context'], 'user:') && in_array($r['key'], ['activitySeenAt', 'sidebarCollapsed'], true))));
    return $s;
}
function today_shown(Client $c, string $tag): ?bool { $s = side(G($c, 'rolewarden/users', $tag)); return $s['found'] ? $s['today'] !== null : null; }
function mig_rw(): array { return array_column(q("SELECT class FROM migrations WHERE namespace='RoleWarden' ORDER BY id"), 'class'); }

if (in_array('UPG', $PHASES, true)) {
    echo "\n== UPG: update of a 1.0.0 installation to 6c636d4\n";
    $v = json_decode(dcat(APPD . '/modules/rolewarden/composer.json'), true);
    info('module folder before: version ' . ($v['version'] ?? '?') . ', CHANGELOG head: ' . short(implode(' | ', array_slice(preg_grep('/^## /', explode("\n", dcat(APPD . '/modules/rolewarden/CHANGELOG.md'))), 0, 2)), 200));
    $UA = make_owner('upadmin@u1.test', OPW, 'u1upadmin', 'admin');
    $UP = make_owner('upplain@u1.test', OPW, 'u1upplain', 'user');
    $own = c('upg-owner'); login2($own, OWNER, OPW);
    $h = G($own, 'rolewarden/users', 'upg 1.0 users');
    check('UP01', '1.0.0 installed and in use: the 1.0 sidebar (no data-sidebar, no groups), three users', $own->status === 200 && ! str_contains($h, 'data-sidebar') && str_contains($h, 'rw-nav') && (int) q1('SELECT COUNT(*) FROM users') === 3, json_encode([$own->status, q1('SELECT COUNT(*) FROM users')]));
    // a theme of 1.0, saved by its customizer: Clarity, no sidebar style in 1.0
    $ha = G($own, 'rolewarden/appearance', 'upg 1.0 appearance');
    $fa = null; foreach (forms($ha) as $f) if ($f['method'] === 'post' && array_key_exists('base', $f['types'])) $fa = $f;
    $va = $fa['fields'] ?? []; foreach (array_keys($va) as $k) if (str_starts_with($k, 'colors[')) $va[$k] = '';
    P($own, resolve($own->url, $fa['action'] ?? 'rolewarden/appearance'), array_replace($va, ['base' => 'clarity', 'radius' => '', 'density' => '']), 'upg 1.0 save theme');
    $tj = dcat(APPD . '/writable/rolewarden/theme.json');
    info('1.0 theme file: ' . short($tj, 300));
    check('UP02', 'The 1.0 customizer saved a theme file (Clarity) with no sidebar style in it', str_contains($tj, 'clarity') && stripos($tj, 'sidebar') === false, short($tj, 200));
    dx('cp ' . APPD . '/modules/rolewarden/src/Views/layouts/panel.php /tmp/panel-1.0.php', null, 'root');
    $GLOBALS['PRE'] = u1st();
    $migBefore = mig_rw();
    [$rc, $o] = dx('rm -rf ' . APPD . '/modules/rolewarden && mkdir ' . APPD . '/modules/rolewarden && tar -xf /tmp/mod-u1.tar -C ' . APPD . '/modules/rolewarden && chown -R www-data:www-data ' . APPD . '/modules/rolewarden && apache2ctl -k graceful', null, 'root');
    check('UP03', 'Module folder replaced by the 6c636d4 archive (a folder swap, nothing else touched)', $rc === 0 && dexists(APPD . '/modules/rolewarden/src/Views/partials/sidebar.php'), short($o, 300));
    sleep(2);
    [$rc, $o] = spark2(['migrate', '-n', 'RoleWarden']);
    info('migrate -n RoleWarden: rc ' . $rc . ' ' . short($o, 400));
    $new = array_values(array_diff(mig_rw(), $migBefore));
    $GLOBALS['NEWMIG'] = $new;
    check('UP04', 'php spark migrate -n RoleWarden runs (exit 0) and adds the migration of 1.1', $rc === 0 && $new !== [], json_encode($new));
    $post = u1st();
    $d = statediff($GLOBALS['PRE'], $post);
    info('database changes made by the migration: ' . json_encode($d));
    $unexpected = array_values(array_filter($d, fn ($l) => ! preg_match('/^(migrations|settings) row extra/', $l)));
    check('UP05', 'The migration only writes its own migrations row and settings: no table, no role, no permission, no user changed', $unexpected === [], json_encode($unexpected));
    $row = q("SELECT `class`, `key`, `value`, `type`, context FROM settings WHERE `key` LIKE '%oday%'");
    info('Today setting after the migration: ' . json_encode($row));
    $o2 = c('upg-owner2'); login2($o2, OWNER, OPW);
    $h = G($o2, 'rolewarden/users', 'upg 1.1 users');
    $s = side($h);
    check('UP06', 'After the update the sidebar is the new one in Paper, with every item for the super admin', $s['found'] && $s['style'] === 'paper' && count($s['items']) === 6, json_encode([$s['style'], paths($s)]));
    check('UP07', 'Today is off on the updated installation (it already had users)', $s['today'] === null && $row !== [] && in_array((string) $row[0]['value'], ['0', '', 'false'], true), json_encode($row));
    $hs = G($o2, 'rolewarden/settings', 'upg settings');
    check('UP08', 'The Settings screen shows "Today in the sidebar" as Hidden', preg_match('/name="sidebar_today"[^>]*>\s*<option value="1"\s*>[^<]*<\/option>\s*<option value="0" selected/', $hs) === 1, short(substr($hs, (int) strpos($hs, 'sidebar_today'), 300), 300));
    check('UP09', 'The 1.0 theme file is kept: theme Clarity, sidebar Paper', str_contains($h, 'data-rw-theme="clarity"') && $s['style'] === 'paper', '');
    $a2 = c('upg-admin'); login2($a2, 'upadmin@u1.test', OPW);
    $sa = side(G($a2, 'rolewarden/users', 'upg admin'));
    $p2 = c('upg-plain'); login2($p2, 'upplain@u1.test', OPW);
    $sp = side(G($p2, 'rolewarden/profile', 'upg plain'));
    check('UP10', 'Updated installation: admin gets the six items and no Today, the plain user no item', count($sa['items']) === 6 && $sa['today'] === null && $sp['found'] && $sp['items'] === [], json_encode([paths($sa), paths($sp)]));
    $bad = [];
    foreach (screens($UID, $RID) as $sc) { G($o2, $sc, "upg screen $sc"); if ($o2->status !== 200 || ! no500($o2)) $bad[] = "$sc {$o2->status}"; }
    check('UP11', 'Every screen of the updated installation answers 200 without error text', $bad === [], json_encode($bad));
}

if (in_array('RB', $PHASES, true)) {
    echo "\n== RB: rollback of the new migration\n";
    $mx = (int) q1('SELECT MAX(batch) FROM migrations');
    $inBatch = array_column(q('SELECT class FROM migrations WHERE batch=?', [$mx]), 'class');
    info("last batch $mx: " . json_encode($inBatch));
    [$rc, $o] = spark2(['migrate:rollback', '-b', (string) ($mx - 1), '-f']);
    info('rollback: rc ' . $rc . ' ' . short($o, 400));
    $d = statediff($GLOBALS['PRE'], u1st());
    check('RB01', 'migrate:rollback of that batch (README, rollback per batch) exits 0 and leaves the database as the 1.0.0 installation had it', $rc === 0 && $inBatch === ($GLOBALS['NEWMIG'] ?? ['?']) && $d === [], json_encode([$rc, $inBatch, $d]));
    $o3 = c('rb-owner'); login2($o3, OWNER, OPW);
    G($o3, 'rolewarden/users', 'rb panel after rollback');
    info('panel with the 1.1 folder and the migration rolled back: HTTP ' . $o3->status . ', Today shown=' . json_encode(side($o3->body)['today'] !== null));
    check('RB02', 'With the migration rolled back the panel still answers (200, no error text)', $o3->status === 200 && no500($o3), (string) $o3->status);
    [$rc, $o] = spark2(['migrate', '-n', 'RoleWarden']);
    $row = q("SELECT `value` FROM settings WHERE `key` LIKE '%oday%'");
    check('RB03', 'Migrating again works and turns Today off again (users exist)', $rc === 0 && $row !== [] && in_array((string) $row[0]['value'], ['0', '', 'false'], true) && today_shown($o3, 'rb today again') === false, json_encode([$rc, $row]));
}

if (in_array('OV', $PHASES, true)) {
    echo "\n== OV: a 1.0 layouts/panel.php in app/Views/overrides\n";
    [$rc, $o] = dx('mkdir -p ' . APPD . '/app/Views/overrides/RoleWarden/Views/layouts && cp /tmp/panel-1.0.php ' . APPD . '/app/Views/overrides/RoleWarden/Views/layouts/panel.php && chown -R www-data:www-data ' . APPD . '/app/Views/overrides', null, 'root');
    check('OV01', 'The 1.0 layout copied as the application override', $rc === 0 && dexists(APPD . '/app/Views/overrides/RoleWarden/Views/layouts/panel.php'), $o);
    $o4 = c('ov-owner'); login2($o4, OWNER, OPW);
    $bad = []; $oldBar = 0;
    foreach (screens($UID, $RID) as $sc) {
        $h = G($o4, $sc, "ov screen $sc");
        if ($o4->status !== 200 || ! no500($o4)) $bad[] = "$sc {$o4->status} " . short(text_of($h), 120);
        if (str_contains($h, 'rw-nav') && ! str_contains($h, 'data-sidebar')) $oldBar++;
    }
    check('OV02', 'With the 1.0 layout override every screen answers 200, no error text, and shows the 1.0 sidebar (' . $oldBar . ' of ' . count(screens($UID, $RID)) . ')', $bad === [] && $oldBar === count(screens($UID, $RID)), json_encode($bad));
    $ll = bad_lines(uptime_logs(), ['/SecurityException|The action you requested is not allowed/']);
    check('OV03', 'No warning or error in the application log after those pages', $ll === [], json_encode(array_slice($ll, 0, 6)));
    info('phases U..L of S3 follow with the 1.0 layout override in place');
}

if (in_array('NEW', $PHASES, true)) {
    echo "\n== NEW: new installation from 6c636d4\n";
    $o5 = c('new-owner'); login2($o5, OWNER, OPW);
    $s = side(G($o5, 'rolewarden/users', 'new users'));
    $row = q("SELECT `value` FROM settings WHERE `key` LIKE '%oday%'");
    info('migrations of RoleWarden: ' . json_encode(mig_rw()) . '; Today setting rows: ' . json_encode($row));
    check('NW01', 'New installation through the installer: Paper sidebar, Today shown to the first super admin', $s['found'] && $s['style'] === 'paper' && $s['today'] !== null, json_encode([$s['style'], $s['today']]));
    $hs = G($o5, 'rolewarden/settings', 'new settings');
    check('NW02', 'The Settings screen shows "Today in the sidebar" as Shown', preg_match('/name="sidebar_today"[^>]*>\s*<option value="1" selected/', $hs) === 1, short(substr($hs, (int) strpos($hs, 'sidebar_today'), 300), 300));
    check('NW03', 'The new installation has the 1.1 migration and no setting forcing Today off', in_array('RoleWarden\Database\Migrations\SidebarTodayOnUpdate', mig_rw(), true) && ! array_filter($row, fn ($r) => in_array((string) $r['value'], ['0', 'false'], true)), json_encode($row));
}
