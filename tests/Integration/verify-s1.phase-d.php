<?php
// Phase D (S1, point 4): demo:uninstall after real use. SPEC "Installazione e rimozione" and the decision of 2026-10-16
// (A1 of V8): "Le voci con un account della demo come autore o come soggetto si tolgono; quelle scritte da un utente vero
// restano, anche se riguardano ruoli o permessi della demo". The full V8 install/uninstall lifecycle runs again in the
// regression (suite V8, phase A); here the log rows are the subject.
$DEMOMAILS = ['reader@example.com', 'author@example.com', 'approver@example.com'];
$demoIds = fn () => array_map('intval', array_column(q("SELECT user_id FROM auth_identities WHERE type='email_password' AND secret IN ('reader@example.com','author@example.com','approver@example.com')"), 'user_id'));
$o = sp8($APP, ['demo:uninstall']);
check('D00', 'demo:uninstall of the demo installed at the start (after the other phases) succeeds', rc($o) === 0 && ! phperr($o), short($o));
$realIds = array_map('intval', array_column(q('SELECT id FROM users'), 'id'));
$S0 = fullstate();
$o = sp8($APP, ['demo:install'], ['demo.password' => DPW]);
check('D01', 'demo:install again (exit 0)', rc($o) === 0 && ! phperr($o), short($o));
$DIDS = $demoIds();
$demoRoleIds = array_map('intval', array_column(q("SELECT id FROM acl_roles WHERE slug LIKE 'demo-%'"), 'id'));
$demoPermIds = array_map('intval', array_column(q("SELECT id FROM acl_permissions WHERE area='documents'"), 'id'));
check('D01b', 'The reinstalled demo stores its English seed text (Reader, Author, Approver)', array_column(q("SELECT name FROM acl_roles WHERE slug LIKE 'demo-%' ORDER BY id"), 'name') === ['Reader', 'Author', 'Approver']);
$ENVD = getenv('RW_V8B_ENV') ?: 'development';
write_env($APP, $ENVD);
$cD = serve8($APP, 'D');
$own = c8('D-own'); login8($own, 'owner@v8b.test', OPW);
// the real owner works on demo objects
$rr = role_id('demo-reader');
$f = form_with(G($own, "rolewarden/roles/$rr/edit"), 'name');
P($own, $f['action'], array_replace($f['fields'], ['description' => 'Edited by the real owner (S1)']), 'D owner edits Reader');
$apId = (int) q1("SELECT user_id FROM auth_identities WHERE secret='approver@example.com'");
foreach (forms(G($own, "rolewarden/users/$apId")) as $ff) if (str_ends_with($ff['action'], '/deactivate')) { P($own, $ff['action'], $ff['fields'], 'D owner deactivates approver'); break; }
foreach (forms(G($own, "rolewarden/users/$apId")) as $ff) if (str_ends_with($ff['action'], '/activate')) { P($own, $ff['action'], $ff['fields'], 'D owner activates approver'); break; }
foreach (forms(G($own, "rolewarden/users/$PLAIN")) as $ff) if (str_ends_with($ff['action'], "/users/$PLAIN/roles") && array_key_exists('role_id', $ff['types'])) { P($own, $ff['action'], array_replace($ff['fields'], ['role_id' => role_id('demo-author')]), 'D owner gives demo-author to plain'); break; }
// a demo account acts: sign-in, a document, its own password
$au = c8('D-au'); login8($au, 'author@example.com', DPW);
$cf = form_with(G($au, 'demo/create'), 'title');
if ($cf) P($au, $cf['action'] ?: 'demo', array_replace($cf['fields'], ['title' => 'S1 document', 'body' => 'x']), 'D author creates');
foreach (forms(G($au, 'rolewarden/profile')) as $ff) if (array_key_exists('password', $ff['types']) || array_key_exists('new_password', $ff['types'])) {
    $v = $ff['fields']; foreach ($ff['types'] as $k => $t) if ($t === 'password') $v[$k] = str_contains($k, 'current') ? DPW : DPW . 'x';
    P($au, $ff['action'], $v, 'D author changes own password'); break;
}
server_stop();
$log = q('SELECT * FROM acl_activity_log ORDER BY id');
$isDemoAcc = fn ($r) => in_array((int) $r['actor_id'], $DIDS, true) || ($r['subject_type'] === 'user' && in_array((int) $r['subject_id'], $DIDS, true));
$realAboutDemo = array_values(array_filter($log, fn ($r) => ! $isDemoAcc($r) && ! in_array($r, $S0['acl_activity_log']['rows'], false) && ((in_array($r['subject_type'], ['role'], true) && in_array((int) $r['subject_id'], $demoRoleIds, true)) || str_contains((string) $r['details'] . $r['subject_label'], 'demo-') || str_contains((string) $r['details'], 'Author'))));
$demoRows = array_values(array_filter($log, $isDemoAcc));
info('activity rows written by real users about demo objects: ' . json_encode(array_map(fn ($r) => [$r['actor_label'], $r['action'], $r['subject_type'], $r['subject_label']], $realAboutDemo), JSON_UNESCAPED_UNICODE));
info('activity rows with a demo account as actor or subject: ' . json_encode(array_map(fn ($r) => [$r['actor_label'], $r['action'], $r['subject_type'], $r['subject_label']], $demoRows), JSON_UNESCAPED_UNICODE));
check('D02', 'Before the removal there are rows of both kinds: real owner about demo roles/accounts, and demo accounts as actor or subject', count($realAboutDemo) >= 1 && count($demoRows) >= 1, json_encode([count($realAboutDemo), count($demoRows)]));
$o = sp8($APP, ['demo:uninstall']);
check('D03', 'demo:uninstall after the real use (exit 0, no PHP error)', rc($o) === 0 && ! phperr($o), short($o));
$S2 = fullstate();
$after = $S2['acl_activity_log']['rows'] ?? [];
$keptReal = array_filter($realAboutDemo, fn ($r) => in_array($r, $after, false));
check('D04', 'Rows written by the real owner about demo roles and the demo role assignment stay after the removal, unchanged (decision of 2026-10-16)', count($keptReal) === count($realAboutDemo), json_encode(array_map(fn ($r) => [$r['action'], $r['subject_label']], array_filter($realAboutDemo, fn ($r) => ! in_array($r, $after, false)))));
$leftDemo = array_filter($after, fn ($r) => in_array((int) $r['actor_id'], $DIDS, true) || ($r['subject_type'] === 'user' && in_array((int) $r['subject_id'], $DIDS, true)));
check('D05', 'Rows with a demo account as actor or as subject are gone (deactivation and activation of the approver, the author\'s own actions)', $leftDemo === [], json_encode(array_values($leftDemo)));
// everything else as before the installation, apart from what real accounts did (sign-in, sessions, their log rows)
$d = statediff($S0, $S2);
$allowed = array_values(array_filter($d, fn ($l) => preg_match('/^(acl_activity_log|acl_sessions|auth_logins|auth_remember_tokens) row extra|^users row (missing|extra)/', $l)));
$allowed = array_merge($allowed, array_values(array_filter($d, fn ($l) => preg_match('/^auth_identities row (missing|extra): \{"id":\d+,"user_id":(\d+),/', $l, $m) && in_array((int) $m[2], $realIds, true))));
$allowed = array_merge($allowed, array_values(array_filter($d, fn ($l) => str_starts_with($l, 'settings row extra: ') && str_contains($l, '"key":"activityPrunedOn"'))));
info('Differences left by real accounts (allowed): ' . count($allowed));
$rest = array_values(array_diff($d, $allowed));
// users rows may differ only in last_active of real accounts
check('D06', 'Apart from what real accounts did (their log rows, sign-ins and password rehash, sessions, last activity, the daily pruning date), the database is as before the installation: no demo table, role, permission, account, assignment, setting or migration left', $rest === [], json_encode(array_slice($rest, 0, 10)));
$pr = fn ($st) => array_values(array_filter($st['acl_user_roles']['rows'], fn ($r) => (int) $r['user_id'] === $PLAIN));
check('D06b', 'The plain user lost only the demo role given to it, its own roles are as before', $pr($S2) == $pr($S0), json_encode([$pr($S0), $pr($S2)]));
$ur = fn ($st) => array_map(function ($r) { unset($r['last_active'], $r['updated_at']); return $r; }, $st['users']['rows']);
check('D06c', 'Real accounts: every users row as before the installation, apart from last activity', $ur($S2) == $ur($S0), json_encode(array_slice(statediff(['users' => ['create' => '', 'ai' => null, 'rows' => $ur($S0)]], ['users' => ['create' => '', 'ai' => null, 'rows' => $ur($S2)]]), 0, 4)));
$o = sp8($APP, ['demo:install'], ['demo.password' => DPW]);
check('D07', 'The demo installs again after the removal (exit 0)', rc($o) === 0, short($o));
