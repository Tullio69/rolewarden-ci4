<?php
// U4 copy of verify-u2.phase-rail.adapted.php (Collaudatore ad Hoc), ADAPTED U4: the Dashboard item counts as one more sidebar item.
// U2 adapted copy of verify-u1.phase-rail.php (Collaudatore ad Hoc): the owner's rail has 7 items from U2 (Check a permission), not 6 (R-5). Nothing else changed.
// U1 phase RAIL (point 4): collapsing is per user, through a POST protected by CSRF; the rail keeps an accessible name
// for every item (Chrome's accessibility tree); one user's choice leaves the others alone; no Today on the rail.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== RAIL: collapse to icons\n";
serve8($APP, 'RAIL');
$S0 = count($GLOBALS['SEEN']);
$own = c8('r-owner'); login8($own, 'owner@v8b.test', OPW);
$adm = c8('r-admin'); login8($adm, 'admin@v8b.test', OPW);
$s = side(G($own, 'rolewarden/users', 'rail before'));
check('R-1', 'Expanded by default: a toggle form (POST, CSRF token, collapsed=1) with an accessible label', ! $s['rail'] && $s['toggle'] && $s['toggle']['method'] === 'post' && ($s['toggle']['fields']['csrf_test_name'] ?? '') !== '' && ($s['toggle']['fields']['collapsed'] ?? '') === '1' && (string) $s['toggle']['btnAria'] !== '', json_encode($s['toggle'] ? array_diff_key($s['toggle'], ['html' => 1]) : null));
// without the token, by GET, unauthenticated: nothing changes
P($own, $s['toggle']['action'], ['collapsed' => '1'], 'rail no csrf');
$st1 = $own->status;
G($own, 'rolewarden/sidebar', 'rail GET');
$st2 = $own->status;
$anon = c8('r-anon'); G($anon, 'login', 'anon');
P($anon, $s['toggle']['action'], ['csrf_test_name' => csrf_of($anon->body), 'collapsed' => '1'], 'rail anonymous');
$st3 = $anon->status;
$s2 = side(G($own, 'rolewarden/users', 'rail after refused'));
check('R-2', 'Refused without the CSRF token (403, or in production the redirect CodeIgniter makes); a GET changes nothing; anonymous is sent to sign in; the sidebar stays expanded', in_array($st1, CSRF_REFUSED, true) && ! $s2['rail'] && $st2 < 500 && in_array($st3, [302, 303, 403], true) && (string) (setting_row('sidebarCollapsed', "user:$OWNER")['value'] ?? '0') !== '1', json_encode([$st1, $st2, $st3, $anon->location, setting_row('sidebarCollapsed', "user:$OWNER")]));
// collapse, with a Referer outside the application
$t = $s2['toggle'];
P($own, $t['action'], $t['fields'], 'rail collapse', ['Referer: http://evil.example.com/steal']);
$st = $own->status; $loc = $own->location;
$s3 = side(G($own, 'rolewarden/roles', 'rail collapsed'));
check('R-3', 'Collapse: redirect inside the application (an outside Referer is not followed); next page on the rail, toggle now expands', in_array($st, [302, 303], true) && str_starts_with($loc, BASE) && $s3['rail'] && ($s3['toggle']['fields']['collapsed'] ?? '') === '0', json_encode([$st, $loc, $s3['rail'], $s3['toggle']['fields'] ?? null]));
check('R-4', 'The rail has no Today panel', $s3['today'] === null, json_encode($s3['today']));
$noname = array_filter($s3['items'], fn ($i) => trim($i['text']) === '' || trim((string) $i['title']) === '');
check('R-5', 'On the rail every item keeps its label in the markup and a tooltip (title)', $noname === [] && count($s3['items']) === 8 && ! array_filter($s3['items'], fn ($i) => $i['title'] !== $i['text']), json_encode(array_map(fn ($i) => [$i['text'], $i['title']], $s3['items'])));
$sa = side(G($adm, 'rolewarden/roles', 'rail other user'));
check('R-6', "Another user's sidebar stays expanded", ! $sa['rail'] && $sa['today'] !== null, json_encode([$sa['rail']]));
// it stays after signing out and in again (saved per user)
G($own, 'logout', 'rail logout');
$own = c8('r-owner2'); login8($own, 'owner@v8b.test', OPW);
$s4 = side(G($own, 'rolewarden/permissions', 'rail after new sign-in'));
check('R-7', 'The choice survives signing out and in (saved for the user)', $s4['rail'], json_encode(setting_row('sidebarCollapsed', "user:$OWNER")));
// the browser: rail width, labels hidden from sight, accessible names of links and toggle
$r = u1browser([blogin('owner@v8b.test', OPW), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'g'],
    ['op' => 'eval', 'tag' => 'm', 'expr' => '(() => { const a = document.querySelector("aside.rw-side"); const r = a.getBoundingClientRect(); const t = [...a.querySelectorAll(".rw-side-text")].map((e) => { const b = e.getBoundingClientRect(); return [e.textContent.trim(), Math.round(b.width), Math.round(b.height)]; }); return { rail: document.querySelector(".rw-shell").classList.contains("rw-shell--rail"), width: Math.round(r.width), texts: t }; })()'],
    ['op' => 'ax', 'tag' => 'links', 'selector' => 'aside.rw-side a'], ['op' => 'ax', 'tag' => 'toggle', 'selector' => 'aside.rw-side button']], 'rail');
$m = bval($r, 'm'); $links = bval($r, 'links'); $tog = bval($r, 'toggle');
info('rail in Chrome: ' . json_encode([$m, $links, $tog]));
$state = q1('SELECT `value` FROM settings WHERE `key`=? AND context=?', ['sidebarCollapsed', "user:$OWNER"]);
if ((string) $state !== '1') info('state before the browser check: ' . json_encode($state));
check('R-9', 'In Chrome the rail is 64px wide and the labels are out of sight (1px clip)', ($m['rail'] ?? false) && ($m['width'] ?? 0) === 64 && $m['texts'] && ! array_filter($m['texts'], fn ($t) => $t[1] > 1 || $t[2] > 1), json_encode($m));
$labels = array_map(fn ($i) => $i['text'], array_values($s4['items']));
$names = array_map(fn ($l) => (string) ($l['name'] ?? ''), (array) $links);
$okNames = count($names) >= 6 && ! array_filter($labels, fn ($l) => ! array_filter($names, fn ($n) => str_starts_with($n, $l)));
check('R-10', "In Chrome's accessibility tree every rail link is a link named by its label; the toggle button has a name", $okNames && ! array_filter((array) $links, fn ($l) => ($l['role'] ?? '') !== 'link' || trim((string) $l['name']) === '') && (string) ($tog[0]['name'] ?? '') !== '', json_encode([$names, $tog]));
// expand again
$s5 = side(G($own, 'rolewarden/users', 'rail before expand'));
P($own, $s5['toggle']['action'], $s5['toggle']['fields'], 'rail expand');
$s6 = side(G($own, 'rolewarden/users', 'rail expanded'));
check('R-11', 'Expand: back to the full sidebar with Today', ! $s6['rail'] && $s6['today'] !== null, json_encode([$s6['rail']]));
// a user without any panel permission can collapse his own (the sidebar is his), others untouched
[$pl, $ok] = [c8('r-plain'), login8($c = c8('r-plain'), 'plain@v8b.test', OPW)];
$sp = side(G($c, 'rolewarden/profile', 'rail plain'));
if ($sp['toggle']) { P($c, $sp['toggle']['action'], $sp['toggle']['fields'], 'rail plain collapse'); }
$sp2 = side(G($c, 'rolewarden/profile', 'rail plain after'));
$so = side(G($own, 'rolewarden/users', 'rail owner after plain'));
check('R-12', "A user's choice leaves the others alone (plain collapses, owner stays expanded)", $sp2['rail'] && ! $so['rail'], json_encode([$sp2['rail'], $so['rail']]));
// garbage value: no error, still a boolean
$s7 = side(G($own, 'rolewarden/users', 'rail before garbage'));
P($own, $s7['toggle']['action'], ['csrf_test_name' => $s7['toggle']['fields']['csrf_test_name'], 'collapsed' => "<x>'\""], 'rail garbage');
$stg = $own->status;
G($own, 'rolewarden/users', 'rail after garbage');
check('R-8', 'A value other than 0/1 gives no error page', $stg < 500 && ! leaks_sql($own->body), (string) $stg);
check('R-clean', 'RAIL pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
