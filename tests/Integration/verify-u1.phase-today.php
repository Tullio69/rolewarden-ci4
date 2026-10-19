<?php
// U1 phase TODAY (point 3): the Today panel only with activity.view, switch on and the sidebar expanded; its figures
// (successful and failed sign-ins of today, by hour) against auth_logins; the Settings switch needs settings.update,
// is saved, logged in the activity log and applies to everyone.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== TODAY: panel, figures, switch\n";
serve8($APP, 'TODAY');
$S0 = count($GLOBALS['SEEN']);
// sign-ins of today at earlier hours (and of yesterday, which must not count), written as Shield writes them
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));   // the application's timezone (app/Config/App.php: UTC)
$today = $now->format('Y-m-d');
$curH = (int) $now->format('G');
$ins = function (string $date, int $ok, string $who = 'ghost@example.com') { q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', ['192.0.2.7', 'U1 test', 'email_password', $who, null, $date, $ok]); };
$plan = [0 => [2, 1], (int) floor($curH / 3) => [3, 0], (int) floor(2 * $curH / 3) => [1, 2]];
foreach ($plan as $hh => [$okN, $failN]) {
    for ($i = 0; $i < $okN; $i++) $ins(sprintf('%s %02d:%02d:10', $today, $hh, 5 + $i), 1, 'owner@v8b.test');
    for ($i = 0; $i < $failN; $i++) $ins(sprintf('%s %02d:%02d:20', $today, $hh, 30 + $i), 0);
}
$y = $now->modify('-1 day')->format('Y-m-d');
for ($i = 0; $i < 5; $i++) $ins("$y 23:4$i:00", 1, 'owner@v8b.test');
$ins("$y 12:00:00", 0);
info('auth_logins planted for today ' . json_encode($plan) . ', current UTC hour ' . $curH);

function expected_today(string $today): array
{
    $ok = (int) q1("SELECT COUNT(*) FROM auth_logins WHERE success=1 AND date >= ? AND date < ? + INTERVAL 1 DAY", ["$today 00:00:00", "$today 00:00:00"]);
    $fail = (int) q1("SELECT COUNT(*) FROM auth_logins WHERE success=0 AND date >= ? AND date < ? + INTERVAL 1 DAY", ["$today 00:00:00", "$today 00:00:00"]);
    $hOk = array_fill(0, 24, 0); $hAll = array_fill(0, 24, 0);
    foreach (q("SELECT HOUR(date) h, success s, COUNT(*) n FROM auth_logins WHERE date >= ? AND date < ? + INTERVAL 1 DAY GROUP BY HOUR(date), success", ["$today 00:00:00", "$today 00:00:00"]) as $r) {
        $hAll[(int) $r['h']] += (int) $r['n'];
        if ((int) $r['s'] === 1) $hOk[(int) $r['h']] += (int) $r['n'];
    }
    return ['ok' => $ok, 'fail' => $fail, 'hOk' => $hOk, 'hAll' => $hAll];
}
/** Does the polyline (24 points, y from the bottom to the top of the view box) draw these hourly counts? */
function draws(array $pts, array $counts): bool
{
    if (count($pts) !== 24) return false;
    $ys = array_column($pts, 1);
    $max = max($counts);
    $base = max($ys); $top = min($ys);
    if ($max === 0) return count(array_unique($ys)) === 1;
    if ($base === $top) return false;
    foreach ($counts as $i => $c) if (abs(($base - $ys[$i]) / ($base - $top) * $max - $c) > 0.25) return false;
    $xs = array_column($pts, 0);
    for ($i = 1; $i < 24; $i++) if ($xs[$i] <= $xs[$i - 1]) return false;
    return true;
}
function today_of(Client $c, string $tag): ?array { return side(G($c, 'rolewarden/profile', $tag))['today']; }
function nums(?array $t): array { preg_match_all('/\d+/', (string) ($t['text'] ?? ''), $m); return array_map('intval', $m[0]); }

$own = c8('t-owner'); login8($own, 'owner@v8b.test', OPW);
$t = today_of($own, 'today owner');
$e = expected_today($today);
info('Today shown: ' . json_encode($t) . ' expected ' . json_encode(['ok' => $e['ok'], 'fail' => $e['fail']]));
$n = nums($t);
check('T-1', "Today: sign-ins of today ({$e['ok']}) and failed ones ({$e['fail']}) as in auth_logins; yesterday's left out", $t !== null && (int) ($t['b'] ?? -1) === $e['ok'] && ($n[0] ?? -1) === $e['ok'] && ($n[1] ?? -1) === $e['fail'], json_encode([$t['text'] ?? null, $e['ok'], $e['fail']]));
$okShape = draws($t['points'] ?? [], $e['hOk']);
$allShape = draws($t['points'] ?? [], $e['hAll']);
info('hourly successful ' . json_encode($e['hOk']) . ' all attempts ' . json_encode($e['hAll']) . ' y=' . json_encode(array_column($t['points'] ?? [], 1)) . ' matches successful=' . json_encode($okShape) . ' all=' . json_encode($allShape));
if ($okShape) check('T-2', 'Hourly line: 24 points, one per hour, heights proportional to the successful sign-ins of each hour', true);
elseif ($allShape) amb('T-2', 'Hourly line counts every attempt, failed included', 'The line matches successful+failed attempts per hour, not the successful sign-ins only; SPEC says "andamento orario" and the Sidebar README "hourly counts" next to "sign-ins today": which series is meant is not stated.');
else check('T-2', 'Hourly line: 24 points, one per hour, heights proportional to the sign-ins of each hour', false, json_encode([$t['points'] ?? null, $e['hOk'], $e['hAll']]));
$mx = max($e['hOk']); $mh = array_search($mx, $e['hOk'], true);
check('T-3', "Line with role=img and a label naming its sense (busiest hour " . sprintf('%02d:00', $mh) . " with $mx)", ($t['svgRole'] ?? '') === 'img' && str_contains((string) $t['svgAria'], sprintf('%02d:00', $mh)) && preg_match('/\b' . $mx . '\b/', (string) $t['svgAria']) === 1, json_encode([$t['svgRole'] ?? null, $t['svgAria'] ?? null]));
// who sees it: activity.view (any way it is held), nobody else
$who = ['owner' => ['owner@v8b.test', OPW, true], 'admin' => ['admin@v8b.test', OPW, true], 'plain' => ['plain@v8b.test', OPW, false],
    'activity.view role' => ['nav-activity-view@example.com', U1PW, true], 'inherited activity.view' => ['nav-child@example.com', U1PW, true],
    'users.view only' => ['nav-users-view@example.com', U1PW, false], 'settings.view only' => ['nav-settings-view@example.com', U1PW, false],
    'admin, activity.view denied' => ['nav-admin-deny@example.com', U1PW, false], 'user + granted roles/settings' => ['nav-user-grant@example.com', U1PW, false],
    'super admin with denials' => ['nav-super-deny@example.com', U1PW, true], 'inherited but denied' => ['nav-child-deny@example.com', U1PW, false]];
$bad = []; $CL = [];
foreach ($who as $k => [$em, $pw, $exp]) {
    $c = c8('t-' . substr(md5($k), 0, 6)); if (! login8($c, $em, $pw)) { $bad[] = "$k no login"; continue; }
    $CL[$k] = $c;
    $has = today_of($c, "today $k") !== null;
    if ($has !== $exp) $bad[] = "$k: " . ($has ? 'shown' : 'absent');
}
check('T-4', 'Today only for those holding activity.view (role, inheritance, super admin), never without it or with it denied', $bad === [], json_encode($bad));
// the switch
$t0 = (string) q1('SELECT NOW()');
sleep(1);
$adm = $CL['admin'];
$logBefore = (int) q1('SELECT MAX(id) FROM acl_activity_log');
$h = settings_save($adm, ['sidebar_today' => '0'], 'today switch off');
$st = $adm->status;
$row = q("SELECT `class`, `value`, `type`, context FROM settings WHERE `key` LIKE '%oday%'");
info("switch off: HTTP $st -> {$adm->location}; settings rows " . json_encode($row));
$logs = q('SELECT action, details FROM acl_activity_log WHERE id > ?', [$logBefore]);
info('log entries after the switch: ' . json_encode($logs));
check('T-5', 'Settings: the switch is saved (Hidden) by a holder of settings.update and the page shows it', in_array($st, [302, 303], true) && preg_match('/<option value="0" selected/', G($adm, 'rolewarden/settings', 'settings after off')) === 1, "HTTP $st");
check('T-6', 'The change is in the activity log (one entry naming the setting)', count(array_filter($logs, fn ($l) => preg_match('/today/i', (string) $l['details']))) >= 1, json_encode($logs));
$bad = [];
foreach (['owner', 'admin', 'activity.view role', 'super admin with denials'] as $k) if (today_of($CL[$k], "today off $k") !== null) $bad[] = $k;
check('T-7', 'Switched off: Today disappears for everyone who could see it', $bad === [], json_encode($bad));
// settings.view only: the screen opens, the switch cannot be saved
$sv = $CL['settings.view only'];
$hs = G($sv, 'rolewarden/settings', 'settings as settings.view');
$fs = null; foreach (forms($hs) as $f) if ($f['method'] === 'post' && array_key_exists('sidebar_today', $f['types'])) $fs = $f;
info('settings.view only: settings page ' . $sv->status . ', editable form with the switch: ' . json_encode($fs !== null));
$tok = csrf_of($hs);
P($sv, 'rolewarden/settings', ['csrf_test_name' => $tok, 'sidebar_today' => '1'] + ($fs['fields'] ?? []), 'settings.view posts the switch');
$v = q1("SELECT `value` FROM settings WHERE `key` LIKE '%oday%' AND context IS NULL");
check('T-8', 'Without settings.update the switch is refused (stays Hidden)', in_array((string) $v, ['0', ''], true) && today_of($CL['owner'], 'today after refused') === null, json_encode([$sv->status, $v]));
// wrong value, missing CSRF
settings_save($adm, ['sidebar_today' => '7'], 'today wrong value');
$st1 = $adm->status;
settings_save($adm, ['sidebar_today' => '1'], 'today no csrf', false);
$st2 = $adm->status;
$v = q1("SELECT `value` FROM settings WHERE `key` LIKE '%oday%' AND context IS NULL");
check('T-9', 'A value other than Shown/Hidden is refused and a POST without the CSRF token is refused (403, or the production redirect): the switch stays Hidden', in_array($st2, CSRF_REFUSED, true) && in_array((string) $v, ['0', ''], true) && today_of($CL['owner'], 'today after wrong') === null, json_encode([$st1, $st2, $v]));
settings_save($adm, ['sidebar_today' => '1'], 'today switch on');
$bad = [];
foreach (['owner', 'admin', 'activity.view role'] as $k) if (today_of($CL[$k], "today on $k") === null) $bad[] = $k;
foreach (['plain', 'users.view only'] as $k) if (today_of($CL[$k], "today on $k") !== null) $bad[] = "$k shown";
check('T-10', 'Switched on again: back for everyone with activity.view, still not for the others', $bad === [], json_encode($bad));
check('T-clean', 'TODAY pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
