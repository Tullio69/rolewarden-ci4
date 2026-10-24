<?php
/**
 * U4 phase DASH (Collaudatore ad Hoc, Claude): the dashboard of 1.1 (tappa U4) in black box, over HTTP.
 * Sources of the assertions: docs/SPEC.md ("Decisioni del 2026-10-18 (v1.1)", "Dashboard, dettagli decisi il
 * 2026-10-21 (U4)", "Elementi condizionati dai permessi", precedence), docs/BRIEF-v1.1.md (U4, "Su U4", definition of
 * done), CHANGELOG (Unreleased), the design system (README: Dashboard, Charts; components/Dashboard), the language
 * files (translation only) and the HTML the server sends. Every number is recomputed here with direct queries on
 * auth_logins, users, acl_user_roles/acl_roles and acl_activity_log, on the period of the SPEC.
 * Sections: PERM (blocks per permission, redirect without any), NAV (sidebar item), NUM (figures, charts, tables for
 * 7/30/90 days), SEC (security notice), HOS (period values), LANG, CLR (colours), LOGIN (redirect after sign-in), LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
date_default_timezone_set('UTC');   // app/Config/App.php of the test application: appTimezone = 'UTC'
echo "\n== DASH: dashboard (U4)\n";
serve8($APP, 'DASH');
$S0 = count($GLOBALS['SEEN']);
const DPERMS = ['dashboard.figures', 'dashboard.signins', 'dashboard.security', 'dashboard.roles', 'dashboard.changes'];
const CHANGE_ACTIONS = ['role.permission_granted', 'role.permission_revoked', 'user.override_granted', 'user.override_denied', 'user.override_cleared', 'user.role_assigned', 'user.role_revoked'];
const DASHURL = 'rolewarden';

function norm4(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function signed_int(string $s): ?int { return preg_match('/([+\x{2212}\-]?)\s*(\d[\d,.]*)/u', $s, $m) ? (int) str_replace([',', '.'], '', $m[2]) * (in_array($m[1], ['-', "\u{2212}"], true) ? -1 : 1) : null; }
function day0(int $ts): int { return strtotime(date('Y-m-d 00:00:00', $ts)); }
/** [start, now, prevStart] of the SPEC: N calendar days, today included; the previous period the N days before. */
function period_bounds(int $n, int $now): array { $start = strtotime('-' . ($n - 1) . ' days', day0($now)); return [$start, $now, strtotime("-$n days", $start)]; }
function dt(int $ts): string { return date('Y-m-d H:i:s', $ts); }

/** The blocks of the served dashboard, found by their headings and landmarks. */
function blocks(string $h): array
{
    $xp = dom($h);
    $o = [];
    $o['figures'] = $xp->query('//section[contains(@class,"rw-dash-strip")]')->length > 0;
    $o['signins'] = $xp->query('//section[@aria-labelledby="rw-dash-signins"]')->length > 0;
    $o['heat'] = $xp->query('//section[@aria-labelledby="rw-dash-heat"]')->length > 0;
    $o['roles'] = $xp->query('//section[@aria-labelledby="rw-dash-roles"]')->length > 0;
    $o['changes'] = $xp->query('//section[@aria-labelledby="rw-dash-changes"]')->length > 0;
    $o['security'] = str_contains(text_of($h), 'Review attempts') || $xp->query('//*[contains(@class,"rw-dash-notice") or contains(@class,"rw-dash-security")]')->length > 0;
    return $o;
}
/** Everything the dashboard says, parsed from the served HTML. */
function dash(string $h): array
{
    $xp = dom($h);
    $o = ['period' => null, 'range' => null, 'figs' => [], 'days' => [], 'heat' => [], 'heatCells' => [], 'roles' => [], 'changes' => [], 'all' => null, 'notice' => null, 'svg' => null, 'legend' => []];
    foreach ($xp->query('//form[contains(@class,"rw-dash-period")]//button') as $b) if ($b->getAttribute('aria-pressed') === 'true') $o['period'] = (int) $b->getAttribute('value');
    $r = $xp->query('//*[contains(@class,"rw-dash-head")]//p[contains(@class,"rw-note")]')->item(0);
    $o['range'] = $r ? norm4($r->textContent) : null;
    foreach ($xp->query('//section[contains(@class,"rw-dash-strip")]//*[contains(@class,"rw-dash-fig")]') as $f) {
        $lab = $xp->query('.//*[contains(@class,"rw-header")]', $f)->item(0);
        $val = $xp->query('.//*[contains(@class,"rw-dash-val")]/b', $f)->item(0);
        $chip = $xp->query('.//*[contains(@class,"rw-dash-chip")]', $f)->item(0);
        $note = $xp->query('./*[contains(@class,"rw-note")]', $f)->item(0);
        $chipVisible = $chip ? norm4(preg_replace('/<span class="rw-visually-hidden">.*?<\/span>/s', '', $chip->ownerDocument->saveHTML($chip))) : null;
        $chipVisible = $chipVisible !== null ? norm4(strip_tags($chipVisible)) : null;
        $o['figs'][] = ['label' => $lab ? norm4($lab->textContent) : '', 'value' => $val ? signed_int($val->textContent) : null, 'valueClass' => $val ? $val->getAttribute('class') : '',
            'chip' => $chipVisible, 'delta' => $chipVisible !== null ? signed_int($chipVisible) : null, 'note' => $note ? norm4($note->textContent) : null];
    }
    $sec = $xp->query('//section[@aria-labelledby="rw-dash-signins"]')->item(0);
    if ($sec) {
        foreach ($xp->query('.//details//tbody/tr', $sec) as $tr) {
            $th = $xp->query('./th', $tr)->item(0); $td = $xp->query('./td', $tr);
            $o['days'][] = [norm4($th->textContent), (int) norm4($td->item(0)->textContent), (int) norm4($td->item(1)->textContent)];
        }
        $svg = $xp->query('.//*[local-name()="svg"]', $sec)->item(0);
        if ($svg) {
            $pl = $xp->query('.//*[local-name()="polyline"]', $svg)->item(0);
            $pts = [];
            if ($pl) foreach (preg_split('/\s+/', trim($pl->getAttribute('points'))) as $p) { [$x, $y] = array_map('floatval', explode(',', $p)); $pts[] = [$x, $y]; }
            $bars = [];
            foreach ($xp->query('.//*[local-name()="rect"]', $svg) as $rr) $bars[] = ['x' => (float) $rr->getAttribute('x'), 'y' => (float) $rr->getAttribute('y'), 'w' => (float) $rr->getAttribute('width'), 'h' => (float) $rr->getAttribute('height')];
            $vb = array_map('floatval', preg_split('/\s+/', trim($svg->getAttribute('viewBox'))));
            $o['svg'] = ['role' => $svg->getAttribute('role'), 'aria' => norm4($svg->getAttribute('aria-label')), 'points' => $pts, 'bars' => $bars, 'vb' => $vb, 'html' => $svg->ownerDocument->saveHTML($svg)];
        }
        foreach ($xp->query('.//*[contains(@class,"rw-legend")]/li', $sec) as $li) $o['legend'][] = norm4($li->textContent);
        $o['signinsDetails'] = $xp->query('.//details/summary', $sec)->length;
    }
    $hs = $xp->query('//section[@aria-labelledby="rw-dash-heat"]')->item(0);
    if ($hs) {
        foreach ($xp->query('.//details//tbody/tr', $hs) as $tr) {
            $row = [];
            foreach ($xp->query('./td', $tr) as $td) $row[] = (int) norm4($td->textContent);
            $o['heat'][norm4($xp->query('./th', $tr)->item(0)->textContent)] = $row;
        }
        // second re-verification on 29a5df6 (A1 decided): the heat map is an SVG; one rect per weekday and hour, each with
        // its level class and a <title> naming its count; the day is read from the title, never from the markup order
        $hsvg = $xp->query('.//*[local-name()="svg"]', $hs)->item(0);
        $o['heatSvg'] = $hsvg ? ['role' => $hsvg->getAttribute('role'), 'aria' => norm4($hsvg->getAttribute('aria-label')), 'rects' => $xp->query('.//*[local-name()="rect"]', $hsvg)->length] : null;
        $o['heatHtmlCells'] = $xp->query('.//*[contains(@class,"rw-dash-heat-row")]', $hs)->length;
        // the cells are read from the raw HTML: libxml's HTML parser drops a <title> nested in an inline svg
        if ($hsvg && preg_match('/<svg[^>]*rw-dash-heat-svg[^>]*>(.*?)<\/svg>/s', $h, $sm)) {
            preg_match_all('/<rect\b([^>]*)>\s*<title>(.*?)<\/title>\s*<\/rect>/s', $sm[1], $rm, PREG_SET_ORDER);
            foreach ($rm as $r1) {
                $tt = norm4($r1[2]);
                $lab = preg_match('/, (\S+) (\d+):00$/u', $tt, $m) ? $m[1] : '?';
                $o['heatCells'][$lab][] = [preg_match('/rw-heat-(\d)/', $r1[1], $m) ? (int) $m[1] : -1, $tt];
            }
        }
        $img = $xp->query('.//*[@role="img"]', $hs)->item(0);
        $o['heatImg'] = $img ? norm4($img->getAttribute('aria-label')) : null;
        $o['heatDetails'] = $xp->query('.//details/summary', $hs)->length;
    }
    $rs = $xp->query('//section[@aria-labelledby="rw-dash-roles"]')->item(0);
    if ($rs) {
        foreach ($xp->query('.//table//tbody/tr', $rs) as $tr) {
            $tds = $xp->query('./td', $tr);
            $o['roles'][] = [norm4($xp->query('./th', $tr)->item(0)->textContent), (int) norm4($tds->item($tds->length - 1)->textContent), norm4($tds->item(0)->textContent)];
        }
        $img = $xp->query('.//*[@role="img"]', $rs)->item(0);
        $o['rolesImg'] = $img ? norm4($img->getAttribute('aria-label')) : null;
        $o['rolesDetails'] = $xp->query('.//details/summary', $rs)->length;
        // A2 decided: the numbers in a <details>, on view only the legend of names
        $o['rolesRowsInDetails'] = $xp->query('.//details//table//tbody/tr', $rs)->length;
        $o['rolesRowsOutside'] = $xp->query('.//table//tbody/tr[not(ancestor::details)]', $rs)->length;
        $o['rolesLegend'] = [];
        foreach ($xp->query('.//*[contains(@class,"rw-legend")]/li[not(ancestor::details)]', $rs) as $li) $o['rolesLegend'][] = norm4($li->textContent);
        $n = $xp->query('.//*[contains(@class,"rw-section-title")]//*[contains(@class,"rw-note")]', $rs)->item(0);
        $o['rolesNote'] = $n ? norm4($n->textContent) : null;
    }
    $cs = $xp->query('//section[@aria-labelledby="rw-dash-changes"]')->item(0);
    if ($cs) {
        foreach ($xp->query('.//li|.//tbody/tr', $cs) as $li) $o['changes'][] = norm4($li->textContent);
        foreach ($xp->query('.//a', $cs) as $a) if (preg_match('/View all|Vedi tutt/u', $a->textContent)) $o['all'] = ['text' => norm4($a->textContent), 'href' => html_entity_decode($a->getAttribute('href'))];
        $o['changesText'] = norm4($cs->textContent);
    }
    foreach ($xp->query('//a|//button') as $a) if (preg_match('/^(Review attempts|Esamina i tentativi|Rivedi)/u', norm4($a->textContent))) {
        $box = $a->parentNode;
        while ($box && $box->nodeName !== 'section' && $box->nodeName !== 'div' && $box->nodeName !== 'aside') $box = $box->parentNode;
        $o['notice'] = ['text' => $box ? norm4($box->textContent) : '', 'href' => html_entity_decode($a->getAttribute('href')), 'html' => $box ? $box->ownerDocument->saveHTML($box) : '', 'tag' => $a->nodeName, 'class' => $a->getAttribute('class'), 'lines' => $box ? array_map(fn ($li) => norm4($li->textContent), iterator_to_array($xp->query('.//li', $box))) : []];
    }
    return $o;
}
function qs(string $href): array { parse_str((string) parse_url($href, PHP_URL_QUERY), $o); return $o; }

// ================================================================= data of the test
q('DELETE FROM auth_logins');
q('DELETE FROM acl_activity_log');
$NOW = time();
if ((int) date('H', $NOW) === 23 && (int) date('i', $NOW) >= 30) info('WARNING: run started close to midnight UTC');
$T0 = day0($NOW);
// users: created at various ages, active and disabled, some deleted (Shield's soft delete)
$mkU = function (string $name, int $ageDays, int $active = 1, bool $deleted = false, array $roles = []) use ($NOW): int {
    $c = dt($NOW - $ageDays * 86400 - 600);
    q('INSERT INTO users (username, active, created_at, updated_at, deleted_at) VALUES (?,?,?,?,?)', [$name, $active, $c, $c, $deleted ? $c : null]);
    $id = (int) db()->insert_id;
    q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,?,?,?,0,?,?)', [$id, 'email_password', "$name@u4.test", password_hash(U1PW, PASSWORD_DEFAULT), $c, $c]);
    foreach ($roles as $r) q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$id, role_id($r)]);
    return $id;
};
$RA = mkrole('u4-alpha', []); $RB = mkrole('u4-bravo', []); $RC = mkrole('u4-charlie', []); $RD = mkrole('u4-delta', []); $RE = mkrole('u4-echo', []); $RF = mkrole('u4-foxtrot', []);
$RGONE = mkrole('u4-gone', []);
// assignments of non-deleted users: alpha 8, bravo 6, charlie 5 (one disabled), delta 4, echo 3 (one user with echo and foxtrot), foxtrot 2
$plan = [['u4-alpha', 8], ['u4-bravo', 6], ['u4-charlie', 5], ['u4-delta', 4], ['u4-echo', 2], ['u4-foxtrot', 1]];
$ages = [0, 1, 3, 6, 7, 8, 12, 20, 29, 30, 31, 45, 59, 60, 61, 89, 90, 91, 100, 150, 179, 180, 181, 250];
$k = 0;
foreach ($plan as [$role, $n]) for ($i = 0; $i < $n; $i++) { $mkU("u4-$role-$i", $ages[$k % count($ages)], ($role === 'u4-charlie' && $i === 0) ? 0 : 1, false, $role === 'u4-alpha' && $i < 6 ? [$role, 'u4-gone'] : [$role]); $k++; }
$mkU('u4-two-roles', 2, 1, false, ['u4-echo', 'u4-foxtrot']);
$mkU('u4-disabled-a', 5, 0, false, ['u4-delta']);   // delta: 5 assignments with this disabled user
// deleted users: never counted, neither in the figures nor in the roles
foreach ([1, 4, 15, 40, 70] as $i => $a) $mkU("u4-deleted-$i", $a, 1, true, ['u4-foxtrot', 'u4-alpha']);
q('UPDATE acl_roles SET deleted_at=? WHERE id=?', [dt($NOW - 3600), $RGONE]);   // a deleted role with 6 assignments: never counted

// sign-ins: 200 days of history, deterministic, every row type of auth_logins; no address reaches 5 failures in a day
mt_srand(4242);
$ipDayFail = [];
$rows = [];
for ($d = 0; $d < 200; $d++) {
    $n = mt_rand(0, 9);
    for ($i = 0; $i < $n; $i++) {
        $ts = $T0 - $d * 86400 + mt_rand(0, 86399);
        if ($ts > $NOW) $ts = $NOW - mt_rand(1, 3000);
        $ok = mt_rand(0, 3) > 0 ? 1 : 0;
        $ip = '198.51.100.' . mt_rand(1, 60);
        if (! $ok) { $key = $ip . date('Ymd', $ts); if (($ipDayFail[$key] ?? 0) >= 3) $ip = '198.51.100.' . (100 + $d % 100); $ipDayFail[$ip . date('Ymd', $ts)] = ($ipDayFail[$ip . date('Ymd', $ts)] ?? 0) + 1; }
        $type = mt_rand(0, 9) === 0 ? 'magic-link' : 'email_password';
        $rows[] = [$ip, 'u4 agent', $type, 'person' . mt_rand(1, 40) . '@u4.test', $ok ? $OWNER : null, dt($ts), $ok];
    }
}
// boundary rows: first second of each period, last second before it, first second of each previous period, last second before that
foreach ([7, 30, 90] as $n) {
    [$st, , $ps] = period_bounds($n, $NOW);
    foreach ([$st, $st - 1, $ps, $ps - 1] as $j => $ts) foreach ([1, 0] as $ok) $rows[] = ['192.0.2.' . (10 + $j + $n), 'u4 edge', 'email_password', "edge$n@u4.test", null, dt($ts), $ok];
}
foreach ($rows as $r) q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', $r);
info('auth_logins rows written: ' . count($rows));

// activity log: the seven permission-change actions and other actions, at various ages; no two at the same second
$acts = array_merge(CHANGE_ACTIONS, ['user.created', 'role.updated', 'settings.updated', 'auth.logout', 'user.deleted']);
$alog = [];
for ($i = 0; $i < 160; $i++) {
    $a = $acts[$i % count($acts)];
    $age = (int) (($i * 7919 * 4253) % (120 * 86400));   // spread over 120 days
    $ts = $NOW - 60 - $age - $i;   // strictly decreasing with i only within each age; uniqueness by the -i term
    $actor = $i % 9 === 0 ? null : $OWNER;
    $alog[] = [$actor, $actor ? 'v8bowner' : null, $a, str_starts_with($a, 'role.') ? 'role' : 'user', $str = (str_starts_with($a, 'role.') ? $RA : $PLAIN), str_starts_with($a, 'role.') ? "Alpha role $i" : "Subject user $i", json_encode(['permission' => 'users.delete', 'role' => 'u4-alpha']), '127.0.0.1', dt($ts)];
}
// the ten most recent: one second apart, recent, all change actions, distinct labels
for ($i = 0; $i < 10; $i++) {
    $a = CHANGE_ACTIONS[$i % 7];
    $alog[] = [$i === 3 ? null : $ADMIN, $i === 3 ? null : 'v8badmin', $a, str_starts_with($a, 'role.') ? 'role' : 'user', str_starts_with($a, 'role.') ? $RB : $PLAIN, "Recent$i", json_encode(['permission' => 'roles.view']), '127.0.0.1', dt($NOW - 30 - $i * 7)];
}
foreach ($alog as $r) q('INSERT INTO acl_activity_log (actor_id, actor_label, action, subject_type, subject_id, subject_label, details, ip_address, created_at) VALUES (?,?,?,?,?,?,?,?,?)', $r);
sp8($APP, ['cache:clear']);

// ================================================================= expected numbers, from the tables
function expect_n(int $n, int $now): array
{
    [$st, $end, $ps] = period_bounds($n, $now);
    $S = dt($st); $E = dt($end); $P = dt($ps);
    $e = [];
    $e['active'] = (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND active=1');
    $e['total'] = (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
    $e['created'] = (int) q1('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at>=? AND created_at<=?', [$S, $E]);
    $e['ok'] = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=1 AND date>=? AND date<=?', [$S, $E]);
    $e['okPrev'] = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=1 AND date>=? AND date<?', [$P, $S]);
    $e['fail'] = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date>=? AND date<=?', [$S, $E]);
    $e['failPrev'] = (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date>=? AND date<?', [$P, $S]);
    $in = implode(',', array_fill(0, count(CHANGE_ACTIONS), '?'));
    $e['chg'] = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action IN ($in) AND created_at>=? AND created_at<=?", array_merge(CHANGE_ACTIONS, [$S, $E]));
    $e['chgPrev'] = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action IN ($in) AND created_at>=? AND created_at<?", array_merge(CHANGE_ACTIONS, [$P, $S]));
    $e['ovr'] = (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action IN ('user.override_granted','user.override_denied','user.override_cleared') AND created_at>=? AND created_at<=?", [$S, $E]);
    $e['latest'] = q("SELECT actor_label, subject_label, action, created_at FROM acl_activity_log WHERE action IN ($in) AND created_at>=? AND created_at<=? ORDER BY created_at DESC, id DESC LIMIT 5", array_merge(CHANGE_ACTIONS, [$S, $E]));
    $e['days'] = [];
    for ($d = 0; $d < $n; $d++) {
        $a = strtotime("+$d days", $st); $b = strtotime('+' . ($d + 1) . ' days', $st) - 1;
        $e['days'][] = [date('D j M', $a), (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=1 AND date>=? AND date<=? AND date<=?', [dt($a), dt($b), $E]), (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date>=? AND date<=? AND date<=?', [dt($a), dt($b), $E])];
    }
    $heat = array_fill(0, 7, array_fill(0, 24, 0));
    foreach (q('SELECT date FROM auth_logins WHERE success=1 AND date>=? AND date<=?', [$S, $E]) as $r) { $t = strtotime($r['date']); $heat[(int) date('N', $t) - 1][(int) date('G', $t)]++; }
    $e['heat'] = $heat;
    $roles = q('SELECT r.name, r.slug, COUNT(*) AS n FROM acl_user_roles ur JOIN users u ON u.id=ur.user_id AND u.deleted_at IS NULL JOIN acl_roles r ON r.id=ur.role_id AND r.deleted_at IS NULL GROUP BY r.id, r.name, r.slug ORDER BY n DESC');
    $e['roles'] = $roles;
    $e['start'] = $st; $e['prevStart'] = $ps;
    return $e;
}

// ================================================================= NUM: owner sees everything, for 7, 30 and 90 days
[$own, $ok] = u1login('d-own', 'owner@v8b.test', OPW);
check('D-login', 'The super admin signs in', $ok, "{$own->status} {$own->location}");
q('DELETE FROM auth_logins WHERE user_agent NOT IN (?, ?)', ['u4 agent', 'u4 edge']);   // the real sign-in of the test is not part of the data set
$h = G($own, DASHURL, 'dash default');
check('D-200', '/rolewarden answers 200 with the dashboard for the super admin', $own->status === 200 && blocks($h)['figures'], (string) $own->status);
$d0 = dash($h);
check('D-def30', 'Without ?period= the period is 30 days (SPEC: predefinito 30)', $d0['period'] === 30 && count($d0['days']) === 30, json_encode([$d0['period'], count($d0['days'])]));
$sideD = side($h);
check('NAV-cur', 'On /rolewarden the Dashboard item is the current one (aria-current) in the Overview group', ($sideD['items']['rolewarden']['current'] ?? '') === 'page' && ($sideD['items']['rolewarden']['group'] ?? '') === 'Overview', json_encode($sideD['items']['rolewarden'] ?? null));
check('NAV-first', 'Dashboard is the first item of the Overview group', ($sideD['groups'][0]['label'] ?? '') === 'Overview' && ($sideD['groups'][0]['items'][0]['path'] ?? '') === 'rolewarden', json_encode($sideD['groups'][0] ?? null));

foreach ([7, 30, 90] as $n) {
    $h = G($own, DASHURL . "?period=$n", "dash $n");
    $NOWQ = time();
    $d = dash($h);
    $e = expect_n($n, $NOWQ);
    $tag = "N$n";
    check("$tag-0", "$n days: 200, period $n pressed, no PHP error, no SQL text", $own->status === 200 && $d['period'] === $n && ! phperr(text_of($h)) && ! leaks_sql($h), json_encode([$own->status, $d['period']]));
    $rangeExp = date('j M', $e['start']) . ' to ' . date('j M Y', $NOWQ);
    check("$tag-range", "$n days: the header states the period from " . date('Y-m-d', $e['start']) . ' to today', $d['range'] !== null && (str_contains($d['range'], date('j M', $e['start'])) && str_contains($d['range'], date('j M', $NOWQ))), json_encode([$d['range'], $rangeExp]));
    $f = $d['figs'];
    check("$tag-F0", "$n days: four key figures", count($f) === 4, json_encode(array_column($f, 'label')));
    if (count($f) === 4) {
        check("$tag-F1", "$n days: active users = non-deleted with active=1 ({$e['active']}), note on the total of non-deleted ({$e['total']})", $f[0]['value'] === $e['active'] && preg_match('/\b' . $e['total'] . '\b/', (string) $f[0]['note']), json_encode([$f[0], $e['active'], $e['total']]));
        $pct = (int) round(100 * $e['active'] / max(1, $e['total']));
        info("$tag F1 note '{$f[0]['note']}' (share computed here: $pct%)");
        check("$tag-F1d", "$n days: the change of active users is the accounts created in the period among the non-deleted (+{$e['created']})", $f[0]['delta'] === $e['created'] && ($e['created'] === 0 || str_starts_with((string) $f[0]['chip'], '+')), json_encode([$f[0]['chip'], $e['created']]));
        check("$tag-F2", "$n days: sign-ins = auth_logins success=1 in the period ({$e['ok']})", $f[1]['value'] === $e['ok'], json_encode([$f[1], $e['ok']]));
        $dOk = $e['ok'] - $e['okPrev'];
        check("$tag-F2d", "$n days: change of sign-ins on the previous $n days, signed ($dOk)", $f[1]['delta'] === $dOk && ($dOk <= 0 || str_starts_with((string) $f[1]['chip'], '+')), json_encode([$f[1]['chip'], $e['ok'], $e['okPrev']]));
        check("$tag-F3", "$n days: failed sign-ins = success=0 in the period ({$e['fail']})", $f[2]['value'] === $e['fail'], json_encode([$f[2], $e['fail']]));
        $okDays = array_column($e['days'], 1);
        check("$tag-F2n", "$n days: the sign-ins note names the peak day count (" . max($okDays) . ')', preg_match('/\b' . max($okDays) . '\b/', (string) $f[1]['note']) === 1, (string) $f[1]['note']);
        check("$tag-F3n", "$n days: the failed note gives the previous period ({$e['failPrev']})", preg_match('/\b' . $e['failPrev'] . '\b/', (string) $f[2]['note']) === 1, (string) $f[2]['note']);
        $dF = $e['fail'] - $e['failPrev'];
        check("$tag-F3d", "$n days: change of failed sign-ins, signed ($dF)", $f[2]['delta'] === $dF && ($dF <= 0 || str_starts_with((string) $f[2]['chip'], '+')), json_encode([$f[2]['chip'], $e['fail'], $e['failPrev']]));
        check("$tag-F4", "$n days: permission changes = the seven actions in the period ({$e['chg']})", $f[3]['value'] === $e['chg'], json_encode([$f[3], $e['chg']]));
        $dC = $e['chg'] - $e['chgPrev'];
        check("$tag-F4d", "$n days: change of permission changes, signed ($dC)", $f[3]['delta'] === $dC && ($dC <= 0 || str_starts_with((string) $f[3]['chip'], '+')), json_encode([$f[3]['chip'], $e['chg'], $e['chgPrev']]));
        check("$tag-F4n", "$n days: the note says how many are overrides ({$e['ovr']})", preg_match('/\b' . $e['ovr'] . '\b/', (string) $f[3]['note']) === 1, json_encode([$f[3]['note'], $e['ovr']]));
        $neg = array_filter($f, fn ($x) => ($x['delta'] ?? 0) < 0);
        foreach ($neg as $x) check("$tag-neg-" . md5($x['label']), "$n days: a negative change carries its minus sign ({$x['label']}: {$x['chip']})", (bool) preg_match('/^[\x{2212}\-]\d/u', (string) $x['chip']), (string) $x['chip']);
        $zero = array_filter($f, fn ($x) => ($x['delta'] ?? 1) === 0);
        foreach ($zero as $x) check("$tag-zero-" . md5($x['label']), "$n days: a zero change reads 0 ({$x['label']}: {$x['chip']})", (bool) preg_match('/^0\b/u', (string) $x['chip']), (string) $x['chip']);
        check("$tag-Facc", "$n days: the first figure in accent, the others not", str_contains($f[0]['valueClass'], 'accent') && ! str_contains($f[1]['valueClass'] . $f[2]['valueClass'] . $f[3]['valueClass'], 'accent'), json_encode(array_column($f, 'valueClass')));
    }
    // sign-ins per day: table and chart
    check("$tag-S1", "$n days: the table of sign-ins per day has the $n calendar days, oldest first, with successful and failed per day = auth_logins", $d['days'] === $e['days'], json_encode(['got' => array_slice($d['days'], 0, 4), 'exp' => array_slice($e['days'], 0, 4), 'diff' => array_values(array_filter(array_map(fn ($a, $b) => $a === $b ? null : [$a, $b], $d['days'], $e['days'])))]));
    check("$tag-S2", "$n days: the legend totals are the period totals", in_array('Successful ' . number_format($e['ok']), $d['legend'], true) && in_array('Failed ' . number_format($e['fail']), $d['legend'], true), json_encode($d['legend']));
    $svg = $d['svg'];
    if ($svg) {
        $H = $svg['vb'][3] ?? 200;
        $okS = array_column($e['days'], 1); $faS = array_column($e['days'], 2);
        $kS = [];
        foreach ($svg['points'] as $i => [$x, $y]) if (($okS[$i] ?? 0) > 0) $kS[] = ($H - $y) / $okS[$i];
        $zeroOk = true;
        foreach ($svg['points'] as $i => [$x, $y]) if (($okS[$i] ?? -1) === 0 && abs($y - $H) > 0.6) $zeroOk = false;
        $spread = $kS ? max($kS) - min($kS) : 0;
        check("$tag-S3", "$n days: the line has one point per day, height proportional to the successful sign-ins of that day (zero on the baseline)", count($svg['points']) === $n && $zeroOk && $spread <= 0.02 * max($kS ?: [1]), json_encode(['points' => count($svg['points']), 'k' => [min($kS ?: [0]), max($kS ?: [0])], 'zeroOk' => $zeroOk]));
        // bars: one per day with failures, height proportional, on the same scale as the line
        $nz = array_values(array_filter(array_keys($faS), fn ($i) => $faS[$i] > 0));
        $kB = [];
        $barX = array_column($svg['bars'], 'x');
        foreach ($svg['bars'] as $j => $b) { $i = $nz[$j] ?? null; if ($i !== null) $kB[] = $b['h'] / $faS[$i]; }
        $same = $kS && $kB ? abs(array_sum($kS) / count($kS) - array_sum($kB) / count($kB)) <= 0.02 * max($kS) : true;
        check("$tag-S4", "$n days: one bar per day with failures, height proportional to the failures, on the scale of the line", count($svg['bars']) === count($nz) && ($kB === [] || max($kB) - min($kB) <= 0.02 * max($kB)) && $same, json_encode(['bars' => count($svg['bars']), 'daysWithFailures' => count($nz), 'kB' => $kB ? [min($kB), max($kB)] : [], 'kS' => $kS ? [min($kS), max($kS)] : []]));
        check("$tag-S5", "$n days: the chart is an SVG with role=img and an aria-label stating the totals", $svg['role'] === 'img' && str_contains($svg['aria'], (string) $e['ok']) && str_contains($svg['aria'], (string) $e['fail']), $svg['aria']);
    } else check("$tag-S3", "$n days: the sign-ins chart is an SVG", false, 'no svg');
    check("$tag-S6", "$n days: the numbers of the sign-ins chart sit in a <details>", ($d['signinsDetails'] ?? 0) === 1, '');
    // heat map
    $dn = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $gotHeat = [];
    foreach ($dn as $i => $w) $gotHeat[] = $d['heat'][$w] ?? null;
    check("$tag-H1", "$n days: the heat-map table = successful sign-ins of the period by weekday (Mon-Sun) and hour, summed", $gotHeat === $e['heat'], json_encode(['got' => $gotHeat[0], 'exp' => $e['heat'][0]]));
    $max = max(array_map('max', $e['heat']));
    $lvBad = [];
    foreach ($dn as $i => $w) foreach (range(0, 23) as $hr) {
        $nn = $e['heat'][$i][$hr];
        $lv = $nn === 0 ? 0 : (int) ceil(4 * $nn / $max);
        $cell = $d['heatCells'][$w][$hr] ?? [-9, ''];
        if ($cell[0] !== $lv) $lvBad[] = "$w $hr: n=$nn level {$cell[0]} expected $lv";
        if (! preg_match('/^' . $nn . '\b/', $cell[1])) $lvBad[] = "$w $hr: title '{$cell[1]}' n=$nn";
    }
    check("$tag-H2", "$n days: each cell has level ceil(4 x n / max) (0 when empty, max $max) and names its count in its title", $lvBad === [] && count($d['heatCells']) === 7, json_encode(array_slice($lvBad, 0, 6)));
    check("$tag-H3", "$n days: the heat map has role=img with an aria-label, and its numbers in a <details>", ($d['heatImg'] ?? '') !== '' && ($d['heatDetails'] ?? 0) === 1, json_encode([$d['heatImg'] ?? null, $d['heatDetails'] ?? null]));
    check("$tag-H4", "$n days: A1, the heat map is drawn as SVG (role=img, aria-label, 7 x 24 = 168 cells, no HTML cell grid)", ($d['heatSvg']['role'] ?? '') === 'img' && ($d['heatSvg']['aria'] ?? '') !== '' && ($d['heatSvg']['rects'] ?? 0) === 168 && ($d['heatHtmlCells'] ?? 1) === 0 && array_sum(array_map('count', $d['heatCells'])) === 168, json_encode([$d['heatSvg'] ?? null, $d['heatHtmlCells'] ?? null, array_map('count', $d['heatCells'])]));
    // users per role
    $er = $e['roles'];
    $top = array_slice($er, 0, 4);
    $restSum = array_sum(array_map(fn ($r) => (int) $r['n'], array_slice($er, 4)));
    $expRows = array_map(fn ($r) => [$r['name'], (int) $r['n']], $top);
    if (count($er) > 4) $expRows[] = ['Other roles', $restSum];
    $gotRows = array_map(fn ($r) => [$r[0], $r[1]], $d['roles']);
    check("$tag-R1", "$n days: users per role = direct assignments of non-deleted users to non-deleted roles, descending, the first four plus \"Other roles\" (sum of the rest, $restSum)", $gotRows === $expRows, json_encode(['got' => $gotRows, 'exp' => $expRows]));
    check("$tag-R2", "$n days: the share chart has role=img and an aria-label", ($d['rolesImg'] ?? '') !== '', (string) ($d['rolesImg'] ?? ''));
    check("$tag-R3", "$n days: A2, the numbers of users per role sit in a <details> under the bar (one row per share, none outside)", ($d['rolesDetails'] ?? 0) === 1 && ($d['rolesRowsInDetails'] ?? 0) === count($expRows) && ($d['rolesRowsOutside'] ?? 1) === 0, json_encode([$d['rolesDetails'] ?? null, $d['rolesRowsInDetails'] ?? null, $d['rolesRowsOutside'] ?? null]));
    check("$tag-R4", "$n days: A2, on view only the legend of names, in the order of the shares, without numbers", $d['rolesLegend'] === array_column($expRows, 0), json_encode(['legend' => $d['rolesLegend'], 'exp' => array_column($expRows, 0)]));
    if ($n === 30) info('roles note: ' . ($d['rolesNote'] ?? '') . ' ; percents: ' . json_encode(array_column($d['roles'], 2)) . ' ; roles details: ' . ($d['rolesDetails'] ?? 0));
    // latest changes
    $lat = $e['latest'];
    $okL = count($d['changes']) === count($lat);
    $whyL = [];
    foreach ($lat as $i => $r) {
        $t = $d['changes'][$i] ?? '';
        $actor = $r['actor_label'] ?? 'The system';
        if (! str_contains($t, (string) $r['subject_label']) || ! str_contains($t, $actor)) { $okL = false; $whyL[] = [$t, $r]; }
    }
    check("$tag-C1", "$n days: the latest five permission changes of the period, newest first (actor, subject)", $okL, json_encode(['got' => $d['changes'], 'why' => $whyL]));
    $all = $d['all'];
    $qa = $all ? qs($all['href']) : [];
    check("$tag-C2", "$n days: \"View all {$e['chg']}\" opens the activity log filtered on the period (" . date('Y-m-d', $e['start']) . ' to ' . date('Y-m-d', $NOWQ) . ')', $all !== null && preg_match('/\b' . $e['chg'] . '\b/', $all['text']) && ($qa['from'] ?? '') === date('Y-m-d', $e['start']) && ($qa['to'] ?? '') === date('Y-m-d', $NOWQ) && str_contains(u1path($all['href']), 'rolewarden/activity'), json_encode([$all, $qa]));
    if ($n === 30) info('changes block text: ' . short($d['changesText'] ?? '', 900) . ' ; view-all query: ' . json_encode($qa));
    check("$tag-noSec", "$n days: no security notice when no address reaches the threshold in a day and no email is locked", $d['notice'] === null && ! blocks($h)['security'], json_encode($d['notice']));
    if ($all) { $ha = G($own, $all['href'], "view all $n"); check("$tag-C3", "$n days: the View all link opens (200)", $own->status === 200, (string) $own->status); }
}

// ================================================================= SEC: security notice
function sec_rows(array $rows): array { $ids = []; foreach ($rows as $r) { q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', [$r[0], 'u4 sec', 'email_password', $r[1], null, $r[2], $r[3]]); $ids[] = (int) db()->insert_id; } return $ids; }
function sec_clear(): void { q("DELETE FROM auth_logins WHERE user_agent='u4 sec'"); }
function notice_for(Client $c, int $n, string $tag): ?array { $h = G($c, DASHURL . "?period=$n", $tag); return dash($h)['notice']; }
$TODAY = date('Y-m-d', $NOW);
$at = fn (int $daysAgo, string $hms) => date('Y-m-d', strtotime("-$daysAgo days", $T0)) . " $hms";
// a: 5 failures from one address on one day, 3 days ago (different emails, none locked)
sec_rows(array_map(fn ($i) => ['203.0.113.9', "victim$i@u4.test", $at(3, sprintf('10:%02d:00', $i)), 0], range(0, 4)));
$D3 = date('Y-m-d', strtotime('-3 days', $T0));
foreach ([7, 30, 90] as $n) {
    $nt = notice_for($own, $n, "sec a $n");
    $qn = $nt ? qs($nt['href']) : [];
    check("SEC-a$n", "$n days: an address with 5 failed sign-ins (lockAttempts) on one day 3 days ago gives the notice", $nt !== null, '');
    check("SEC-a$n-link", "$n days: its action opens the activity log on auth.failed for that day ($D3)", $nt && str_contains(u1path($nt['href']), 'rolewarden/activity') && ($qn['action'] ?? '') === 'auth.failed' && ($qn['from'] ?? '') === $D3 && ($qn['to'] ?? '') === $D3, json_encode([$nt['href'] ?? null, $qn]));
    if ($n === 30 && $nt) {
        info('notice: ' . $nt['text'] . ' | ' . short($nt['html'], 1200));
        check('SEC-a-txt', 'The notice names the address, the day and the threshold', str_contains($nt['text'], '203.0.113.9') && str_contains($nt['text'], '5'), $nt['text']);
        check('SEC-a-nodenied', 'The notice informs, never alarms: no denied colour or class in it', ! preg_match('/denied/i', $nt['html']), short($nt['html'], 500));
        $ha = G($own, $nt['href'], 'notice link');
        check('SEC-a-open', 'The notice action opens the activity log (200)', $own->status === 200, (string) $own->status);
        info('activity log opened by the notice: occurrences of 203.0.113.9 = ' . substr_count(text_of($ha), '203.0.113.9') . ' ; ' . short(text_of(substr($ha, (int) strpos($ha, '<main'))), 600));
    }
}
sec_clear();
// b: the same 5 failures 12 days ago: in 30 and 90, not in 7
sec_rows(array_map(fn ($i) => ['203.0.113.10', "victim$i@u4.test", $at(12, sprintf('09:%02d:00', $i)), 0], range(0, 4)));
check('SEC-b7', '7 days: 5 failures of one address 12 days ago are outside the period: no notice', notice_for($own, 7, 'sec b 7') === null, '');
check('SEC-b30', '30 days: the same failures give the notice', notice_for($own, 30, 'sec b 30') !== null, '');
sec_clear();
// c: 4 failures (threshold - 1): no notice
sec_rows(array_map(fn ($i) => ['203.0.113.11', "victim$i@u4.test", $at(2, sprintf('11:%02d:00', $i)), 0], range(0, 3)));
check('SEC-c', '4 failures of one address on a day (threshold 5): no notice', notice_for($own, 30, 'sec c') === null, '');
// d: plus one on the next calendar day (00:00:05): still no address with 5 in one day
sec_rows([['203.0.113.11', 'victim9@u4.test', $at(1, '00:00:05'), 0]]);
check('SEC-d', '4 failures on one day and 1 the next day: no notice (one calendar day counts)', notice_for($own, 30, 'sec d') === null, '');
// e: successful sign-ins of an address never count
sec_rows(array_map(fn ($i) => ['203.0.113.11', "victim$i@u4.test", $at(2, sprintf('12:%02d:00', $i)), 1], range(0, 4)));
check('SEC-e', 'Successful sign-ins of the address do not count towards the threshold', notice_for($own, 30, 'sec e') === null, '');
sec_clear();
// f: the threshold of the Settings: 3
$hs = settings_save($own, ['lock_attempts' => '3'], 'lock 3');
check('SEC-f0', 'Settings: lock threshold saved as 3', (int) (form_with(G($own, 'rolewarden/settings', 'settings after'), 'lock_attempts')['fields']['lock_attempts'] ?? 0) === 3, '');
sec_rows(array_map(fn ($i) => ['203.0.113.12', "victim$i@u4.test", $at(4, sprintf('13:%02d:00', $i)), 0], range(0, 2)));
$nt = notice_for($own, 30, 'sec f');
check('SEC-f1', 'With lockAttempts 3, 3 failures of one address in a day give the notice', $nt !== null && str_contains($nt['text'], '3'), json_encode($nt['text'] ?? null));
settings_save($own, ['lock_attempts' => '5'], 'lock 5');
check('SEC-f2', 'Back to 5, the same 3 failures give no notice', notice_for($own, 30, 'sec f2') === null, '');
sec_clear();
// g: one email locked now (5 failures since its last success, the last a minute ago, five different addresses)
sec_rows(array_map(fn ($i) => ["203.0.113." . (20 + $i), 'locked@u4.test', dt(time() - 300 + $i * 50), 0], range(0, 4)));
$lockedUi = (function () use ($own): ?bool {
    // the sign-in screen agrees: the email is refused even with any password (Shield's lock of V2)
    $c = c8('d-lockprobe');
    $h = G($c, 'login', 'lock probe form');
    $f = form_with($h, 'password');
    if (! $f) return null;
    P($c, 'login', array_replace($f['fields'], ['email' => 'locked@u4.test', 'password' => 'whatever-12345']), 'lock probe');
    $b = text_of($c->go($c->location ?: 'login'));
    q("DELETE FROM auth_logins WHERE identifier='locked@u4.test' AND user_agent<>'u4 sec'");
    return (bool) preg_match('/too many|locked|bloccat/i', $b);
})();
info('lock probe on the sign-in screen: ' . json_encode($lockedUi));
foreach ([7, 30, 90] as $n) {
    $nt = notice_for($own, $n, "sec g $n");
    $qn = $nt ? qs($nt['href']) : [];
    check("SEC-g$n", "$n days: an email locked now (5 failures from 5 addresses) gives the notice", $nt !== null, '');
    check("SEC-g$n-link", "$n days: for the locked email alone the action opens the failed sign-ins of today ($TODAY)", $nt && ($qn['action'] ?? '') === 'auth.failed' && ($qn['from'] ?? '') === $TODAY && ($qn['to'] ?? '') === $TODAY, json_encode([$nt['href'] ?? null, $qn]));
    if ($n === 30 && $nt) info('locked notice: ' . $nt['text']);
}
sec_clear();
// h: 5 failures of an email 3 hours ago (lock of 15 minutes elapsed), different addresses: no notice
sec_rows(array_map(fn ($i) => ["203.0.113." . (40 + $i), 'was-locked@u4.test', dt(time() - 3 * 3600 + $i * 60), 0], range(0, 4)));
check('SEC-h', 'An email whose lock has expired (5 failures 3 hours ago, lock 15 minutes) gives no notice', notice_for($own, 30, 'sec h') === null, '');
sec_clear();
// i: 5 failures then a success: not locked
sec_rows(array_merge(array_map(fn ($i) => ["203.0.113." . (50 + $i), 'recovered@u4.test', dt(time() - 600 + $i * 30), 0], range(0, 4)), [['203.0.113.60', 'recovered@u4.test', dt(time() - 60), 1]]));
check('SEC-i', '5 failures followed by a successful sign-in: the email is not locked, no notice', notice_for($own, 30, 'sec i') === null, '');
sec_clear();

// A4 decided (second re-verification on 29a5df6): the notice lists every reason, one line each: every address at the
// threshold with its day (the most recent first), then every locked email; the action opens auth.failed from the oldest
// day cited to the most recent, today included with a locked email; with one reason, that day.
$Dn = fn (int $k) => date('Y-m-d', strtotime("-$k days", $T0));
$ip5 = fn (string $ip, int $daysAgo, string $hh) => sec_rows(array_map(fn ($i) => [$ip, "victim$i@u4.test", $at($daysAgo, sprintf('%s:%02d:00', $hh, $i)), 0], range(0, 4)));
$lock5 = fn (string $email, int $base) => sec_rows(array_map(fn ($i) => ['198.51.100.' . ($base + $i), $email, dt(time() - 240 + $i * 30), 0], range(0, 4)));
$lineIdx = function (array $lines, string $needle): int { foreach ($lines as $k => $l) if (str_contains($l, $needle)) return $k; return -1; };
$info4 = fn (string $what, ?array $nt) => info("A4 $what: " . json_encode(['lines' => $nt['lines'] ?? null, 'query' => $nt ? qs($nt['href']) : null]));
// m: two addresses on two days (5 and 2 days ago)
$ip5('203.0.113.70', 5, '08'); $ip5('203.0.113.71', 2, '08');
$nt = notice_for($own, 30, 'sec m'); $qn = $nt ? qs($nt['href']) : []; $info4('two addresses on two days', $nt);
check('SEC-m', 'Two addresses at the threshold on two days: one notice, one action', $nt !== null && substr_count(G($own, DASHURL . '?period=30', 'sec m2'), 'Review attempts') === 1, '');
check('SEC-m-lines', 'A4: two lines, one per address, each with its day, the most recent first (203.0.113.71 then 203.0.113.70)', $nt && count($nt['lines']) === 2 && $lineIdx($nt['lines'], '203.0.113.71') === 0 && $lineIdx($nt['lines'], '203.0.113.70') === 1, json_encode($nt['lines'] ?? null));
check('SEC-m-link', 'A4: the action opens auth.failed from the oldest day cited to the most recent (' . $Dn(5) . ' to ' . $Dn(2) . ')', ($qn['action'] ?? '') === 'auth.failed' && ($qn['from'] ?? '') === $Dn(5) && ($qn['to'] ?? '') === $Dn(2), json_encode($qn));
// n: plus a locked email
$lock5('locked2@u4.test', 80);
$nt = notice_for($own, 30, 'sec n'); $qn = $nt ? qs($nt['href']) : []; $info4('two addresses and a locked email', $nt);
check('SEC-n-lines', 'A4: the two address lines first (most recent first), then the locked email on its own line', $nt && count($nt['lines']) === 3 && $lineIdx($nt['lines'], '203.0.113.71') === 0 && $lineIdx($nt['lines'], '203.0.113.70') === 1 && $lineIdx($nt['lines'], 'locked2@u4.test') === 2, json_encode($nt['lines'] ?? null));
check('SEC-n-link', 'A4: with a locked email the action runs from the oldest day cited to today (' . $Dn(5) . ' to ' . $TODAY . ')', ($qn['action'] ?? '') === 'auth.failed' && ($qn['from'] ?? '') === $Dn(5) && ($qn['to'] ?? '') === $TODAY, json_encode($qn));
check('SEC-n-one', 'A4: still one notice and one action', substr_count(G($own, DASHURL . '?period=30', 'sec n2'), 'Review attempts') === 1, '');
sec_clear();
// o: the same address at the threshold on two days (8 and 3 days ago): one line per day; in 7 days only the day inside
$ip5('203.0.113.72', 8, '07'); $ip5('203.0.113.72', 3, '07');
$d8 = date('j M', strtotime('-8 days', $T0)); $d3 = date('j M', strtotime('-3 days', $T0));
$nt = notice_for($own, 30, 'sec o30'); $qn = $nt ? qs($nt['href']) : []; $info4('one address on two days, 30', $nt);
check('SEC-o30', 'A4: one address at the threshold on two days gives two lines (one per day, the most recent first) and the action ' . $Dn(8) . ' to ' . $Dn(3), $nt && count($nt['lines']) === 2 && str_contains($nt['lines'][0], $d3) && str_contains($nt['lines'][1], $d8) && ($qn['from'] ?? '') === $Dn(8) && ($qn['to'] ?? '') === $Dn(3), json_encode([$nt['lines'] ?? null, $qn, $d3, $d8]));
$nt = notice_for($own, 7, 'sec o7'); $qn = $nt ? qs($nt['href']) : []; $info4('one address on two days, 7', $nt);
check('SEC-o7', 'A4: in 7 days only the day inside the period counts: one line, the action on that day (' . $Dn(3) . ')', $nt && count($nt['lines']) === 1 && str_contains($nt['lines'][0], $d3) && ($qn['from'] ?? '') === $Dn(3) && ($qn['to'] ?? '') === $Dn(3), json_encode([$nt['lines'] ?? null, $qn]));
sec_clear();
// p: two addresses at the threshold on the same day (4 days ago)
$ip5('203.0.113.73', 4, '06'); $ip5('203.0.113.74', 4, '09');
$nt = notice_for($own, 30, 'sec p'); $qn = $nt ? qs($nt['href']) : []; $info4('two addresses on the same day', $nt);
check('SEC-p', 'A4: two addresses on the same day give two lines, and the action is that day (' . $Dn(4) . ')', $nt && count($nt['lines']) === 2 && $lineIdx($nt['lines'], '203.0.113.73') >= 0 && $lineIdx($nt['lines'], '203.0.113.74') >= 0 && ($qn['from'] ?? '') === $Dn(4) && ($qn['to'] ?? '') === $Dn(4), json_encode([$nt['lines'] ?? null, $qn]));
sec_clear();
// q: two locked emails alone
$lock5('locked3@u4.test', 10); $lock5('locked4@u4.test', 20);
$nt = notice_for($own, 30, 'sec q'); $qn = $nt ? qs($nt['href']) : []; $info4('two locked emails', $nt);
check('SEC-q', 'A4: two locked emails alone: both cited, no address line, the action is today (' . $TODAY . ')', $nt && $lineIdx($nt['lines'], 'locked3@u4.test') >= 0 && $lineIdx($nt['lines'], 'locked4@u4.test') >= 0 && ! preg_match('/203\.0\.113|198\.51\.100/', implode(' ', $nt['lines'])) && ($qn['from'] ?? '') === $TODAY && ($qn['to'] ?? '') === $TODAY, json_encode([$nt['lines'] ?? null, $qn]));
info('A4 two locked emails: number of lines = ' . count($nt['lines'] ?? []) . ' (one per email, or one line naming both)');
// r: an address 40 days ago plus the locked emails: in 30 days only the emails (today), in 90 days from that day to today
$ip5('203.0.113.75', 40, '05');
$nt = notice_for($own, 30, 'sec r30'); $qn = $nt ? qs($nt['href']) : []; $info4('address 40 days ago and locked emails, 30', $nt);
check('SEC-r30', 'A4: an address outside the period is not cited; with only locked emails the action is today', $nt && $lineIdx($nt['lines'], '203.0.113.75') === -1 && ($qn['from'] ?? '') === $TODAY && ($qn['to'] ?? '') === $TODAY, json_encode([$nt['lines'] ?? null, $qn]));
$nt = notice_for($own, 90, 'sec r90'); $qn = $nt ? qs($nt['href']) : []; $info4('address 40 days ago and locked emails, 90', $nt);
check('SEC-r90', 'A4: in 90 days the address line comes first, the emails after, and the action runs from ' . $Dn(40) . ' to today', $nt && $lineIdx($nt['lines'], '203.0.113.75') === 0 && $lineIdx($nt['lines'], 'locked3@u4.test') > 0 && ($qn['from'] ?? '') === $Dn(40) && ($qn['to'] ?? '') === $TODAY, json_encode([$nt['lines'] ?? null, $qn]));
$okOpen = false;
if ($nt) { G($own, $nt['href'], 'notice r link'); $okOpen = $own->status === 200; }
check('SEC-r-open', 'A4: the action of the notice opens (200)', $okOpen, '');
sec_clear();
$hp = G($own, 'rolewarden/permissions', 'permissions screen');
$descOk = [];
foreach (['figures' => 'See the key figures on the dashboard', 'signins' => 'See sign-ins over time and by day and hour on the dashboard', 'security' => 'See the security notice on the dashboard', 'roles' => 'See users per role on the dashboard', 'changes' => 'See the latest permission changes on the dashboard'] as $k => $desc) $descOk[$k] = str_contains($hp, "dashboard.$k") && str_contains(text_of($hp), $desc);
check('PERMS-screen', 'The Permissions screen lists the five dashboard.* with their descriptions', ! in_array(false, $descOk, true), json_encode($descOk));

// ================================================================= PERM: each block only with its permission
sec_rows(array_map(fn ($i) => ['203.0.113.9', "victim$i@u4.test", $at(1, sprintf('10:%02d:00', $i)), 0], range(0, 4)));   // a notice exists for the whole PERM section
$PR = [];
foreach (DPERMS as $p) $PR[$p] = mkrole('u4p-' . str_replace('.', '-', $p), [$p]);
$PR['two'] = mkrole('u4p-two', ['dashboard.figures', 'dashboard.changes']);
$PR['parent'] = mkrole('u4p-parent', ['dashboard.signins']);
$PR['child'] = mkrole('u4p-child', []);
q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$PR['parent'], $PR['child']]);
foreach (['users.view', 'roles.view', 'permissions.view', 'activity.view', 'settings.view', 'appearance.update'] as $p) $PR["nd:$p"] = mkrole('u4n-' . str_replace('.', '-', $p), [$p]);
$PR['nd:settings-roles'] = mkrole('u4n-settings-roles', ['settings.view', 'roles.view']);
$ACC = [
    'owner' => [$OWNER, 'owner@v8b.test', OPW, DPERMS],
    'admin' => [$ADMIN, 'admin@v8b.test', OPW, DPERMS],
    'plain' => [$PLAIN, 'plain@v8b.test', OPW, []],
];
foreach (DPERMS as $p) { $e = 'd-' . str_replace('.', '-', $p) . '@u4.test'; $ACC["only:$p"] = [mkuser($e, 'u4p-' . str_replace('.', '-', $p)), $e, U1PW, [$p]]; }
$ACC['two'] = [mkuser('d-two@u4.test', 'u4p-two'), 'd-two@u4.test', U1PW, ['dashboard.figures', 'dashboard.changes']];
$ACC['child'] = [mkuser('d-child@u4.test', 'u4p-child'), 'd-child@u4.test', U1PW, ['dashboard.signins']];
$ACC['user+roles'] = [mkuser('d-grant@u4.test', 'user', ['dashboard.roles' => 1]), 'd-grant@u4.test', U1PW, ['dashboard.roles']];
$ACC['admin-figures'] = [mkuser('d-admin-deny@u4.test', 'admin', ['dashboard.figures' => 0, 'dashboard.security' => 0]), 'd-admin-deny@u4.test', U1PW, ['dashboard.signins', 'dashboard.roles', 'dashboard.changes']];
$ACC['super-changes'] = [mkuser('d-super-deny@u4.test', 'super-admin', ['dashboard.changes' => 0]), 'd-super-deny@u4.test', U1PW, ['dashboard.figures', 'dashboard.signins', 'dashboard.security', 'dashboard.roles']];
$ACC['child-deny'] = [mkuser('d-child-deny@u4.test', 'u4p-child', ['dashboard.signins' => 0, 'dashboard.changes' => 1]), 'd-child-deny@u4.test', U1PW, ['dashboard.changes']];
$ACC['admin-disabled'] = [mkuser('d-admin-off@u4.test', 'admin', [], 0), 'd-admin-off@u4.test', U1PW, []];
$ACC['super-disabled'] = [mkuser('d-super-off@u4.test', 'super-admin', [], 0), 'd-super-off@u4.test', U1PW, []];
$ACC['norole'] = [mkuser('d-norole@u4.test', ''), 'd-norole@u4.test', U1PW, []];
foreach (['users.view', 'roles.view', 'permissions.view', 'activity.view', 'settings.view', 'appearance.update', 'settings-roles'] as $p) { $e = 'nd-' . str_replace('.', '-', $p) . '@u4.test'; $ACC["none:$p"] = [mkuser($e, 'u4n-' . str_replace('.', '-', $p)), $e, U1PW, []]; }
sp8($APP, ['cache:clear']);
$map = ['dashboard.figures' => ['figures'], 'dashboard.signins' => ['signins', 'heat'], 'dashboard.security' => ['security'], 'dashboard.roles' => ['roles'], 'dashboard.changes' => ['changes']];
foreach ($ACC as $name => [$uid, $email, $pw, $want]) {
    [$c, $ok] = u1login('dp-' . preg_replace('/[^a-z0-9-]/i', '-', $name), $email, $pw);
    if (! $ok) { check("P-$name-0", "$name signs in", false, "{$c->status} {$c->location}"); continue; }
    $prof = G($c, 'rolewarden/profile', "perm profile $name");
    $sd = side($prof);
    $hasItem = isset($sd['items']['rolewarden']);
    check("NAV-$name", "$name: the Dashboard item is in the sidebar " . ($want ? '(holds ' . count($want) . ' dashboard.*)' : 'not at all (no dashboard.*)'), $hasItem === ($want !== []) && (! $hasItem || $sd['items']['rolewarden']['group'] === 'Overview'), json_encode([$hasItem, $sd['items']['rolewarden'] ?? null]));
    G($c, DASHURL, "perm dash $name");
    if ($want) {
        $b = blocks($c->body);
        $exp = [];
        foreach (['figures', 'signins', 'heat', 'security', 'roles', 'changes'] as $k) $exp[$k] = false;
        foreach ($want as $p) foreach ($map[$p] as $k) $exp[$k] = true;
        check("P-$name", "$name: /rolewarden renders exactly the blocks of " . implode(', ', array_map(fn ($p) => substr($p, 10), $want)), $c->status === 200 && $b == $exp, json_encode(['status' => $c->status, 'got' => $b, 'exp' => $exp]));
        if (! in_array('dashboard.figures', $want, true)) check("P-$name-leak", "$name: none of the key figures' labels reaches the page", ! str_contains($c->body, 'Active users') && ! str_contains($c->body, 'Key figures'), '');
    } else {
        // the first sidebar item the user can see, else the profile
        $first = array_key_first($sd['items']);
        $expLoc = $first ?? 'rolewarden/profile';
        $loc = u1path($c->location);
        check("P-$name", "$name (no dashboard.*): /rolewarden redirects to the first sidebar item it can see ($expLoc)", in_array($c->status, [302, 303], true) && $loc === $expLoc, json_encode([$c->status, $loc, array_keys($sd['items'])]));
        if (in_array($c->status, [302, 303], true)) { $c->go($c->location); check("P-$name-open", "$name: that screen opens (200)", $c->status === 200, (string) $c->status); }
    }
}
// a permission revoked while the session is open: the block goes at the next request
[$cr, $okr] = u1login('dp-revoke', 'd-two@u4.test', U1PW);
q('DELETE FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$PR['two'], perm_id('dashboard.changes')]);
sp8($APP, ['cache:clear']);
$b = blocks(G($cr, DASHURL, 'perm revoke'));
check('P-revoke', 'dashboard.changes revoked from the role: the next request no longer renders its block', $okr && $cr->status === 200 && ! $b['changes'] && $b['figures'], json_encode($b));
q('DELETE FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$PR['two'], perm_id('dashboard.figures')]);
sp8($APP, ['cache:clear']);
G($cr, DASHURL, 'perm revoke all');
check('P-revoke-all', 'All its dashboard.* revoked: /rolewarden now redirects away', in_array($cr->status, [302, 303], true), $cr->status . ' ' . $cr->location);
// anonymous
$an = c8('d-anon');
G($an, DASHURL, 'anon dash');
check('P-anon', 'Anonymous: /rolewarden goes to the sign-in page', in_array($an->status, [302, 303], true) && str_contains($an->location, 'login'), $an->status . ' ' . $an->location);
sec_clear();

// ================================================================= HOS: period values
foreach (['0', '1', '14', '365', '-7', 'abc', '7.5', ' 7', '07', '7abc', '1e1', "7\0", '%FF%FE'] as $v) {
    $u = DASHURL . '?period=' . ($v === '%FF%FE' ? $v : rawurlencode($v));
    $hh = G($own, $u, "hostile $v");
    $pp = dash($hh)['period'];
    check('HOS-' . bin2hex($v), "period=" . json_encode($v) . ': 200 with one of 7/30/90, no error, no SQL', $own->status === 200 && in_array($pp, [7, 30, 90], true) && ! phperr(text_of($hh)) && ! leaks_sql($hh), json_encode([$own->status, $pp]));
    if ($own->status === 200 && $pp !== 30) info("period=" . json_encode($v) . " gives $pp");
}
foreach (['period[]=7', 'period[a][b]=30', 'period=7&period=90'] as $qq) {
    $hh = G($own, DASHURL . '?' . $qq, "hostile $qq");
    $pp = dash($hh)['period'];
    check('HOS-' . md5($qq), "?$qq: 200 with one of 7/30/90, no error", $own->status === 200 && in_array($pp, [7, 30, 90], true) && ! phperr(text_of($hh)), json_encode([$own->status, $pp]));
    info("?$qq gives " . json_encode($pp));
}
foreach (['POST', 'PUT', 'DELETE'] as $m) {
    $own->req($m, DASHURL, ['period' => '7', 'csrf_test_name' => csrf_of(G($own, DASHURL, 'csrf'))]);
    check("HOS-$m", "$m /rolewarden is not answered with a 2xx", $own->status < 200 || $own->status >= 300, (string) $own->status);
}

// ================================================================= CLR: colours of the charts
$h = G($own, DASHURL . '?period=90', 'colours');
$main = substr($h, (int) strpos($h, '<main'));
$main = substr($main, 0, (int) strpos($main, '</main>'));
preg_match_all('/(?:fill|stroke|color|background(?:-color)?|stop-color)\s*[:=]\s*["\']?([^"\';>\s]+)/i', $main, $mm);
$lit = array_values(array_unique(array_filter($mm[1], fn ($v) => preg_match('/^(#|rgb|hsl|oklch|[a-z]+$)/i', $v) && ! in_array(strtolower($v), ['none', 'currentcolor', 'transparent', 'inherit'], true))));
check('CLR-1', 'No literal colour in the dashboard markup (attributes and inline styles): every colour comes from the stylesheet tokens', $lit === [], json_encode($lit));
check('CLR-2', 'No granted/denied in the charts (the markup before the changes list)', ! preg_match('/granted|denied/i', (string) preg_replace('/<section[^>]*rw-dash-changes.*$/s', '', $main)), '');
$cssUrl = preg_match('#href="([^"]*panel[^"]*\.css[^"]*)"#', $h, $m1) ? html_entity_decode($m1[1]) : '';
$css = $cssUrl !== '' ? G($own, $cssUrl, 'panel css') : '';
$GLOBALS['U4CSS'] = $css;
preg_match_all('/([^{}]*(?:rw-dash|rw-heat|rw-share|rw-legend)[^{}]*)\{([^{}]*)\}/', $css, $rr, PREG_SET_ORDER);
$badCss = [];
$used = [];
foreach ($rr as $r) {
    foreach (explode(';', $r[2]) as $decl) {
        if (! preg_match('/^\s*([a-z-]+)\s*:\s*(.+)$/i', $decl, $dm)) continue;
        if (! preg_match('/(color|fill|stroke|background|border|outline|shadow)/i', $dm[1])) continue;
        if (preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|oklch\(/i', $dm[2]) && ! preg_match('/^\s*var\(/', $dm[2])) $badCss[] = trim($r[1]) . ' { ' . trim($decl) . ' }';
        preg_match_all('/var\(--([a-z0-9-]+)/i', $dm[2], $vm);
        foreach ($vm[1] as $v) $used[$v] = 1;
        if (preg_match('/granted|denied/i', $dm[2])) $badCss[] = 'granted/denied: ' . trim($r[1]) . ' { ' . trim($decl) . ' }';
    }
}
info('dashboard CSS rules: ' . count($rr) . '; colour variables used: ' . implode(', ', array_keys($used)));
check('CLR-3', 'The served stylesheet colours every dashboard rule (rw-dash, rw-heat, rw-share, rw-legend) through variables only, never granted/denied', $rr !== [] && $badCss === [], json_encode(array_slice($badCss, 0, 8)));
$heatVars = [];
foreach (range(0, 4) as $i) $heatVars[$i] = (bool) preg_match('/\.rw-heat-' . $i . '\b[^{]*\{[^}]*var\(--(?:color-)?heat-' . $i . '\)/', $css);
check('CLR-4', 'Heat levels rw-heat-0..4 use the heat-0..heat-4 tokens', ! in_array(false, $heatVars, true), json_encode($heatVars));
foreach (['rw-dash-line' => 'accent', 'rw-dash-area' => 'accent-tint', 'rw-dash-bar' => 'ink', 'rw-dash-mid' => 'rule-strong', 'rw-dash-base' => 'ink'] as $cl => $tok) {
    $okc = (bool) preg_match('/\.' . $cl . '\b[^{]*\{[^}]*var\(--(?:color-)?' . preg_quote($tok, '/') . '\)/', $css);
    check("CLR-$cl", "Chart rule: .$cl drawn in $tok (design system Charts)", $okc, preg_match('/\.' . $cl . '\b[^{]*\{[^}]*\}/', $css, $cm) ? $cm[0] : 'no rule');
}

// ================================================================= LANG: Italian
$langBefore = q1("SELECT value FROM settings WHERE context=? AND `key` LIKE '%anguage%'", ['user:' . $OWNER]);
sec_rows(array_map(fn ($i) => ['203.0.113.9', "victim$i@u4.test", $at(1, sprintf('10:%02d:00', $i)), 0], range(0, 4)));
$hEn = G($own, DASHURL . '?period=30', 'lang en');
postform($own, 'rolewarden/profile', '#profile/language$#', ['language' => 'it'], 'lang it');
$hIt = G($own, DASHURL . '?period=30', 'lang it');
$it = text_of($hIt);
foreach (dom($hIt)->query('//@aria-label|//@title') as $a) $it .= ' ' . $a->nodeValue;
check('LANG-0', 'Italian chosen: the page is in Italian (html lang it)', html_lang($hIt) === 'it', html_lang($hIt));
$flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) { if (is_array($v)) $o += $flat($v, "$p$k."); else $o["$p$k"] = $v; } return $o; };
$en = $flat($EN['panel']['dashboard'] ?? []); $itL = $flat($IT['panel']['dashboard'] ?? []);
$ek = array_keys($en); $ik = array_keys($itL); sort($ek); sort($ik);
check('LANG-1', 'Every dashboard string exists in English and in Italian (same keys, none empty)', $en !== [] && $ek === $ik && ! in_array('', $itL, true), json_encode(['missingIt' => array_keys(array_diff_key($en, $itL)), 'extraIt' => array_keys(array_diff_key($itL, $en))]));
$same = [];
foreach ($en as $k => $v) if (($itL[$k] ?? null) === $v && ! preg_match('/^[{}#0-9,\s%a-z]*$/', $v) && ! in_array($v, ['Dashboard'], true)) $same[] = "$k=$v";
check('LANG-2', 'No dashboard string left in English in the Italian file (except names and placeholders)', $same === [], json_encode($same));
$leak = [];
foreach ($en as $k => $v) {
    $plain = trim(preg_replace('/\{[^{}]*(\{[^{}]*\}[^{}]*)*\}/', ' ', $v));
    foreach (preg_split('/\s{2,}|[:;,.]\s/', $plain) as $frag) { $frag = trim($frag, " :;,."); if (mb_strlen($frag) >= 8 && str_contains($it, $frag) && ($itL[$k] ?? '') !== $v) $leak[] = "$k: $frag"; }
}
foreach (['Mon', 'Tue', 'Sun', 'Oct'] as $w) if (preg_match('/\b' . $w . '\b/', text_of(preg_replace('/<aside.*?<\/aside>/s', '', $hIt)))) $leak[] = "short English word $w";
check('LANG-3', 'No English dashboard text in the Italian page (headings, notes, labels, tables, weekdays, months)', $leak === [], json_encode(array_slice($leak, 0, 12)));
info('IT excerpt: ' . short(text_of(substr($hIt, (int) strpos($hIt, '<main'))), 1500));
postform($own, 'rolewarden/profile', '#profile/language$#', ['language' => 'en'], 'lang back');
if ($langBefore === null) q("DELETE FROM settings WHERE context=? AND `key` LIKE '%anguage%'", ['user:' . $OWNER]);
sec_clear();

// ================================================================= LOGIN: redirect after sign-in (CHANGELOG Upgrading)
$c = c8('d-login-default');
login8($c, 'admin@v8b.test', OPW);
check('LOGIN-1', 'With the 1.0 Auth.php (login => rolewarden/users) sign-in still lands on Users', u1path($c->location) === 'rolewarden/users', $c->location);
$authCfg = "$APP/app/Config/Auth.php";
$orig = file_get_contents($authCfg);
file_put_contents($authCfg, str_replace("'login'             => 'rolewarden/users'", "'login'             => 'rolewarden'", $orig));
$changed = file_get_contents($authCfg) !== $orig;
$c = c8('d-login-dash');
login8($c, 'admin@v8b.test', OPW);
$loc = u1path($c->location);
$c->get($c->location);
check('LOGIN-2', "With login => 'rolewarden' (CHANGELOG) sign-in lands on the dashboard (200)", $changed && $loc === 'rolewarden' && $c->status === 200 && blocks($c->body)['figures'], json_encode([$changed, $loc, $c->status]));
$c = c8('d-login-nodash');
login8($c, 'nd-settings-view@u4.test', U1PW);
$c->get($c->location);
check('LOGIN-3', 'Same setting, a user without dashboard.* goes on to the first screen it can see', in_array($c->status, [302, 303], true) && u1path($c->location) === 'rolewarden/settings', json_encode([$c->status, u1path($c->location)]));
$c = c8('d-login-back');
G($c, DASHURL . '?period=7', 'anon to dash');
login8($c, 'admin@v8b.test', OPW);
info('after sign-in from /rolewarden?period=7: ' . $c->location);
file_put_contents($authCfg, $orig);

// ================================================================= TZ: calendar days in the application's time zone
$envFile = "$APP/.env";
$envOrig = file_get_contents($envFile);
file_put_contents($envFile, $envOrig . "app.appTimezone = 'Pacific/Kiritimati'\n");
server_stop();
serve8($APP, 'DASH-TZ');
[$tzc, $okz] = u1login('d-tz', 'owner@v8b.test', OPW);
$htz = G($tzc, DASHURL . '?period=7', 'tz 7');
$dz = dash($htz);
$kt = new DateTimeZone('Pacific/Kiritimati');
$nowK = new DateTime('now', $kt);
$lastLabel = $nowK->format('D j M');
info('TZ: last day of the table ' . json_encode(end($dz['days'])) . " ; today in Kiritimati: $lastLabel ; range " . $dz['range']);
check('TZ-1', 'With appTimezone Pacific/Kiritimati the last day of the period is today in that time zone', $dz['days'] !== [] && end($dz['days'])[0] === $lastLabel, json_encode([end($dz['days']), $lastLabel]));
file_put_contents($envFile, $envOrig);
server_stop();
serve8($APP, 'DASH2');

$llPre = bad_log_lines(logs_of($APP));
// ================================================================= SQL error in production: nothing on screen
if ((getenv('RW_V8B_ENV') ?: 'development') === 'production') {
    q('RENAME TABLE acl_activity_log TO acl_activity_log_u4hidden');
    $hh = G($own, DASHURL, 'sql error');
    $st = $own->status;
    q('RENAME TABLE acl_activity_log_u4hidden TO acl_activity_log');
    check('SQL-prod', 'Production, a table missing under the dashboard: no SQL text, no PHP trace on screen', ! leaks_sql($hh) && ! phperr(text_of($hh)) && ! str_contains($hh, 'acl_activity_log'), "$st " . short(text_of($hh), 300));
}

// ================================================================= LOG: no error anywhere
$bad = seen_clean($S0);
$bad = array_values(array_filter($bad, fn ($b) => ! str_starts_with($b, 'sql error')));
check('LOG-1', 'No response of this phase has status >= 500, PHP error text or SQL text', $bad === [], json_encode(array_slice($bad, 0, 10)));
$ll = $llPre;
info('log lines written by the SQL-error probe: ' . json_encode(array_slice(array_values(array_diff(bad_log_lines(logs_of($APP)), $llPre)), 0, 3)));
check('LOG-2', 'No WARNING/NOTICE/DEPRECATED/ERROR/CRITICAL line in the application log (threshold 9)', $ll === [], json_encode(array_slice($ll, 0, 8)));
$sl = '';
foreach ($GLOBALS['SERVERLOGS'] as $f) $sl .= (string) @file_get_contents($f);
preg_match_all('/^.*(PHP (Warning|Notice|Deprecated|Fatal)|Warning:|Deprecated:).*$/m', $sl, $sm);
check('LOG-3', 'No PHP warning, notice or deprecation in the server output', $sm[0] === [], json_encode(array_slice($sm[0], 0, 6)));
