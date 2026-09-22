<?php

/**
 * v1.0 black-box verification of the RoleWarden panel (design system rewrite).
 * Collaudatore ad Hoc (Claude), written against docs/SPEC.md, docs/BRIEF-MVP.md,
 * docs/design-system/ and the README wiring only. Every form field and endpoint shape
 * below was read from the HTML/JSON the panel returns over HTTP, never from its source.
 *
 * Preconditions (done by verify-v1.sh): rolewarden_test freshly installed per README,
 * the isolated app copy served on localhost:8070.
 */

declare(strict_types=1);

require __DIR__ . '/verify-v1.lib.php';
require __DIR__ . '/verify-v1.fixtures.php';

$F = make_fixtures();
$U = $F['u'];
$role = $F['role'];
$perm = $F['perm'];
$pw = $F['pw'];
$AJAX = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];

// Bulk users so the list paginates (all hold the plain "user" role, never logged in).
for ($i = 1; $i <= 30; $i++) {
    q('INSERT INTO users (username, active, created_at) VALUES (?,1,NOW())', [sprintf('bulk%02d', $i)]);
    $bid = (int) db()->insert_id;
    q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at) VALUES (?,?,?,?,0,NOW())', [$bid, 'email_password', sprintf('bulk%02d@northwind-studio.test', $i), 'x']);
    q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$bid, $role('user')]);
}

/** Every response seen, for the global SQL-leak / PHP-warning sweep. */
$SEEN = [];
function seen(Client $c, string $what): Client
{
    $GLOBALS['SEEN'][] = [$what, $c->status, $c->body];
    return $c;
}

function matrix_cfg(string $html): array
{
    if (! preg_match('/x-data="rw(?:User)?Matrix\(([^"]*)\)"/', $html, $m)) {
        return [null, []];
    }
    $cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
    $cells = [];
    foreach ($cfg['rows'] ?? [] as $r) {
        foreach ($r['cells'] as $c) {
            if ($c['permissionId'] !== null) {
                $cells[$r['area'] . '.' . $c['action']] = $c;
            }
        }
    }
    return [$cfg, $cells];
}

function user_rows(string $html): array
{
    preg_match_all('/<td class="rw-m-num">(\d+)<\/td>\s*<td class="rw-c-name"><a href="[^"]*\/users\/(\d+)">/', $html, $m, PREG_SET_ORDER);
    return array_map(fn ($x) => ['n' => (int) $x[1], 'id' => (int) $x[2]], $m);
}

function role_rows(string $html): array
{
    // One <tr> per role inside tbody.
    if (! preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $tb)) {
        return [];
    }
    preg_match_all('/<tr>(.*?)<\/tr>/s', $tb[1], $trs);
    $rows = [];
    foreach ($trs[1] as $tr) {
        if (preg_match('/<a href="[^"]*\/roles\/(\d+)">([^<]*)<\/a>/', $tr, $a)) {
            preg_match('/<td class="rw-c-parent">(.*?)<\/td>/s', $tr, $p);
            preg_match('/<td class="rw-c-perm">(.*?)<\/td>/s', $tr, $pc);
            $rows[] = [
                'id' => (int) $a[1], 'name' => $a[2],
                'branch' => substr_count($tr, 'rw-tree-branch'),
                'tree' => preg_match('/<div class="rw-tree"([^>]*)>/', $tr, $t) ? $t[1] : '',
                'parent' => trim(strip_tags($p[1] ?? '')),
                'perm' => trim(preg_replace('/\s+/', ' ', strip_tags($pc[1] ?? ''))),
                'delete' => str_contains($tr, 'rwConfirm('),
                'html' => $tr,
            ];
        }
    }
    return $rows;
}

function no_leak(string $id, Client $c, string $what): void
{
    check($id, "$what: no SQL error text on screen", ! leaks_sql($c->body), "HTTP {$c->status}, SQL/DB exception text found in body");
}

// ---------------------------------------------------------------- 1. Login
echo "\n== 1. Login (design-system view on Shield's controller)\n";
$anon = new Client('anon');
seen($anon->get('login'), 'GET login');
$lp = $anon->clean();
check('L01', 'GET /login is 200 and rendered by the RoleWarden view (rw-auth-screen)', $anon->status === 200 && str_contains($lp, 'rw-auth-screen'), "status {$anon->status}");
check('L02', 'form posts to url_to(login) with csrf_field, email, password, remember', (bool) preg_match('#<form[^>]*action="' . preg_quote(BASE, '#') . 'login"[^>]*method="post"#', $lp) && csrf_of($lp) !== '' && str_contains($lp, 'name="email"') && str_contains($lp, 'name="password"') && str_contains($lp, 'name="remember"'), 'contract fields missing');
check('L03', 'password input is type="password" in the served HTML (hidden without relying on JS)', (bool) preg_match('/<input[^>]*\stype="password"[^>]*name="password"|<input[^>]*name="password"[^>]*\stype="password"/', $lp), 'password <input> has only an Alpine-bound :type, no static type="password": before Alpine runs (or without JS) the browser renders it as a plain text field');
check('L04', 'Show/Hide button carries aria-pressed (bound) and aria-controls', (bool) preg_match('/<button[^>]*:aria-pressed="show"[^>]*aria-controls="password"/', $lp), 'missing');
seen($anon->get('login/magic-link'), 'GET login/magic-link');
$magicOk = $anon->status === 200;
check('L05', 'forgot-password link present on login (Shield magic-link recovery is enabled and served: HTTP ' . $anon->status . ')', ! $magicOk || str_contains($lp, 'login/magic-link'), 'SPEC "Login | Credenziali, recupero password" and Login README "forgot-password link": the page has no link to the recovery flow although Shield serves it at /login/magic-link');

// D5/D6 re-verification: the recovery link resolves and is a real anchor; the static type stays alongside the Alpine binding.
$recHref = preg_match('/<a[^>]*href="([^"]*login\/magic-link[^"]*)"[^>]*>(.*?)<\/a>/s', $lp, $mm) ? $mm[1] : '';
$recText = isset($mm[2]) ? trim(strip_tags($mm[2])) : '';
if ($recHref !== '') {
    seen($anon->get($recHref), 'GET recovery link');
}
check('L05b', "recovery link is an <a> that resolves (href=$recHref, text \"$recText\", HTTP {$anon->status})", $recHref !== '' && $anon->status === 200 && $recText !== '', 'no anchor or broken');
check('L05c', 'recovery link text comes from a language string, not raw key (no "RoleWarden." or "Auth." in the text)', $recText !== '' && ! preg_match('/^(RoleWarden|Auth)\./', $recText), "text '$recText'");
check('L03b', 'password input keeps the Show/Hide Alpine binding next to the static type', (bool) preg_match('/<input[^>]*:type="[^"]*"[^>]*name="password"|<input[^>]*name="password"[^>]*:type="/', $lp), 'Alpine :type binding missing');

$bad1 = new Client('bad1');
$t = $bad1->token('login');
seen($bad1->req('POST', 'login', ['csrf_test_name' => $t, 'email' => $U['subject']['email'], 'password' => 'wrong-password-1']), 'POST login wrong pw');
$loc1 = $bad1->location;
seen($bad1->get('login'), 'GET login after failure');
$e1 = $bad1->clean();
$bad2 = new Client('bad2');
$t = $bad2->token('login');
seen($bad2->req('POST', 'login', ['csrf_test_name' => $t, 'email' => 'nobody@northwind-studio.test', 'password' => 'wrong-password-1']), 'POST login unknown email');
seen($bad2->get('login'), 'GET login after failure 2');
$e2 = $bad2->clean();
$alert = fn (string $h) => preg_match('/<[^>]*role="alert"[^>]*>(.*?)<\/(div|p)>/s', $h, $m) ? trim(preg_replace('/\s+/', ' ', strip_tags($m[1]))) : '';
$a1 = $alert($e1);
$a2 = $alert($e2);
check('L06', 'failed login redirects back to login and shows a role="alert" error block', str_contains($loc1, 'login') && $a1 !== '', "location=$loc1 alert='$a1'");
check('L07', "failed login shows Shield's own message (\"$a1\")", stripos($a1, 'Unable to log you in') !== false || stripos($a1, 'credentials') !== false, "alert text '$a1'");
if ($a1 !== '' && $a1 === $a2) {
    check('L08', 'wrong password vs unknown email: identical message, no field named as wrong', true);
} else {
    amb('L08', 'failed-login message differs between a known email with a wrong password and an unknown email', "known email: '$a1' / unknown email: '$a2'. Both strings are Shield's own (Auth.invalidPassword / Auth.badAttempt), shown verbatim as the brief asks; Login README says never say which of email or password was wrong. The two requirements conflict.");
}

$rem = new Client('remember');
$before = (int) q1('SELECT COUNT(*) FROM auth_remember_tokens');
$t = $rem->token('login');
seen($rem->req('POST', 'login', ['csrf_test_name' => $t, 'email' => $U['admin2']['email'], 'password' => $pw, 'remember' => 'on']), 'POST login remember');
$okRem = in_array($rem->status, [302, 303], true) && ! str_contains($rem->location, '/login');
check('L09', 'correct credentials + remember: redirect into the panel, remember token stored by Shield', $okRem && (int) q1('SELECT COUNT(*) FROM auth_remember_tokens') === $before + 1, "status {$rem->status} loc {$rem->location}");
seen($rem->get('rolewarden/users'), 'GET users (admin2)');
check('L10', 'session from the new login view reaches a can:users.view route (200)', $rem->status === 200, "status {$rem->status}");
seen($rem->get('logout'), 'GET logout');
seen($rem->get('rolewarden/users'), 'GET users after logout');
check('L11', 'after logout the panel is closed again (redirect to login)', in_array($rem->status, [302, 303], true) && str_contains($rem->location, 'login'), "status {$rem->status} loc {$rem->location}");

$dis = new Client('disabled');
$dis->login($U['disabled']['email'], $pw);
seen($dis->get('rolewarden/users'), 'GET users (disabled)');
check('L12', 'disabled user (active=0, admin role) gets no panel access', $dis->status !== 200, "status {$dis->status}");

// ---------------------------------------------------------------- 2. Gating
echo "\n== 2. Route gating unchanged (M5)\n";
$routes = ['rolewarden/users', 'rolewarden/users/create', 'rolewarden/users/' . $U['subject']['id'], 'rolewarden/roles', 'rolewarden/roles/create', 'rolewarden/roles/' . $role('v1-child'), 'rolewarden/permissions'];
$bad = [];
foreach ($routes as $r) {
    seen($anon->get($r), "anon GET $r");
    if (! (in_array($anon->status, [302, 303], true) && str_contains($anon->location, 'login'))) {
        $bad[] = "$r => {$anon->status}";
    }
}
check('G01', 'anonymous: every panel GET redirects to login', ! $bad, implode('; ', $bad));

$lim = new Client('limited');
check('G02', 'limited user (users.view only) signs in', $lim->login($U['limited']['email'], $pw), "status {$lim->status}");
seen($lim->get('rolewarden/users'), 'limited GET users');
check('G03', 'limited: users list 200', $lim->status === 200, "status {$lim->status}");
check('G04', 'limited: "New user" button not rendered (SPEC: unusable buttons are not rendered)', ! str_contains($lim->clean(), 'users/create'), 'link to users/create rendered for a user without users.create');
$bad = [];
foreach (['rolewarden/users/create', 'rolewarden/roles', 'rolewarden/roles/create', 'rolewarden/roles/' . $role('v1-child'), 'rolewarden/permissions'] as $r) {
    seen($lim->get($r), "limited GET $r");
    if ($lim->status === 200) {
        $bad[] = "$r => 200";
    }
}
check('G05', 'limited: routes gated by other permissions are refused', ! $bad, implode('; ', $bad));
$before = q('SELECT * FROM acl_user_permissions');
$lim->post('rolewarden/users/' . $U['subject']['id'] . '/permissions', ['permission_id' => $perm('users.view'), 'granted' => 1], 'rolewarden/users', $AJAX);
seen($lim, 'limited POST users/x/permissions');
check('G06', 'limited: POST users/{id}/permissions refused (by permission, not CSRF) and nothing written', ! csrf_refused($lim) && ! str_contains($lim->body, '"ok": true') && q('SELECT * FROM acl_user_permissions') === $before, "status {$lim->status} body " . substr($lim->body, 0, 120));
$before = q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id');
$lim->post('rolewarden/roles/' . $role('v1-limited') . '/permissions', ['permission_id' => $perm('roles.view'), 'granted' => 1], 'rolewarden/users', $AJAX);
seen($lim, 'limited POST roles/x/permissions');
check('G07', 'limited: POST roles/{id}/permissions (self-escalation on own role) refused (by permission, not CSRF), nothing written', ! csrf_refused($lim) && ! str_contains($lim->body, '"ok": true') && q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id') === $before, "status {$lim->status}");
$lim->post('rolewarden/users/' . $U['subject']['id'] . '/deactivate', [], 'rolewarden/users');
seen($lim, 'limited POST deactivate');
check('G08', 'limited: POST users/{id}/deactivate refused (by permission, not CSRF), user still active', ! csrf_refused($lim) && (int) q1('SELECT active FROM users WHERE id=?', [$U['subject']['id']]) === 1, "status {$lim->status}");

// ---------------------------------------------------------------- 3. UsersList
echo "\n== 3. UsersList\n";
$own = new Client('owner');
check('U00', 'owner (only active super admin) signs in', $own->login($U['owner']['email'], $pw), "status {$own->status}");
seen($own->get('rolewarden/users'), 'owner GET users');
$ul = $own->clean();
check('U01', 'sidebar marks Users with aria-current="page"', (bool) preg_match('/<a href="[^"]*rolewarden\/users" aria-current="page"/', $ul), 'missing');
check('U02', 'Last login header has aria-sort and a sort control', (bool) preg_match('/<th[^>]*aria-sort="(ascending|descending)"[^>]*>\s*<(a|button)[^>]*>\s*Last login/s', $ul), 'missing');
$rows = user_rows($ul);
$ids = array_column($rows, 'id');
$lastOf = fn (int $id): string => (string) q1('SELECT COALESCE(MAX(date), "") FROM auth_logins WHERE user_id=? AND success=1', [$id]);
$firstIds = array_slice($ids, 0, 3);
$expect = array_map('intval', array_column(q('SELECT u.id FROM users u JOIN auth_logins l ON l.user_id=u.id AND l.success=1 GROUP BY u.id ORDER BY MAX(l.date) DESC LIMIT 3'), 'id'));
check('U03', 'default order: most recent successful login first, matching auth_logins (owner signed in last)', $firstIds === $expect && $firstIds[0] === $U['owner']['id'], 'first rows ' . json_encode($firstIds) . ' expected ' . json_encode($expect));
seen($own->get('rolewarden/users?sort=asc'), 'owner GET users asc');
$ua = $own->clean();
$asc = array_column(user_rows($ua), 'id');
check('U04', 'sort=asc: aria-sort="ascending" and never-logged-in users first, owner not on page 1 top', str_contains($ua, 'aria-sort="ascending"') && ($asc[0] ?? 0) !== $U['owner']['id'] && ! in_array($U['owner']['id'], array_slice($asc, 0, 3), true), 'asc ids ' . json_encode(array_slice($asc, 0, 5)));
check('U05', '"Never" shown for a user who never signed in', str_contains($ua, 'Never'), 'no Never cell');
check('U06', 'row-number gutter starts at 01', ($rows[0]['n'] ?? 0) === 1, 'first n ' . ($rows[0]['n'] ?? '?'));
// pagination
$pagerHtml = $ul;
preg_match_all('/href="([^"]*rolewarden\/users\?[^"]*page[^"]*)"/', $pagerHtml, $pl);
$pageLinks = array_unique(array_map(fn ($h) => html_entity_decode($h), $pl[1]));
check('U07', '36 users: list paginates, current page marked aria-current="page"', count($rows) < 36 && $pageLinks && (bool) preg_match('/class="rw-page"[^>]*aria-current="page"|aria-current="page"[^>]*class="rw-page/', $pagerHtml), 'rows on page 1: ' . count($rows) . ', page links: ' . json_encode(array_values($pageLinks)));
$prevTag = preg_match('/<(ul|nav|div)[^>]*class="rw-pager[^"]*".*?<\/\1>/s', $pagerHtml, $pg) ? $pg[0] : '';
echo "         pager markup: " . preg_replace('/\s+/', ' ', substr($prevTag, 0, 700)) . "\n";
check('U08', 'Previous is disabled (not hidden) on page 1', (bool) preg_match('/<(a|button|span)\b([^>]*)>(?:\s|<[^>]+>)*(Previous|Prev\b|&lsaquo;|‹|«)/i', $prevTag, $pv) && (bool) preg_match('/\bdisabled\b|aria-disabled="true"/', $pv[2]), 'Previous control: ' . ($pv[0] ?? 'not found'));
if ($pageLinks) {
    $p2 = null;
    foreach ($pageLinks as $h) {
        if (preg_match('/page[^=]*=2\b/', $h)) {
            $p2 = $h;
        }
    }
    if ($p2) {
        seen($own->get($p2), 'owner GET users page 2');
        $r2 = user_rows($own->clean());
        check('U09', 'page 2: row numbering continues across pages', $r2 && $r2[0]['n'] === count($rows) + 1, 'first n on page 2: ' . ($r2[0]['n'] ?? '?') . ', page size ' . count($rows));
        check('U10', 'pager count text in a role="status" region', (bool) preg_match('/role="status"[^>]*>\s*Showing/', $own->clean()), 'missing');
    } else {
        check('U09', 'page 2 link found', false, json_encode(array_values($pageLinks)));
    }
}
seen($own->get('rolewarden/users?q=mara'), 'owner GET users?q=mara');
no_leak('U11', $own, 'search q=mara');
$dbMsg = fn (string $b) => preg_match('/(Unknown column [^<\\n"]+|Cannot add or update a child row[^<\\n"]{0,160}|Duplicate entry [^<\\n"]+)/', html_entity_decode($b, ENT_QUOTES), $mm) ? $mm[1] : '';
check('U12', 'search q=mara (email/name) returns 200 with only Mara', $own->status === 200 && array_column(user_rows($own->clean()), 'id') === [$U['subject']['id']], "HTTP {$own->status}; on screen: " . $dbMsg($own->body));
seen($own->get('rolewarden/users?q=Priya'), 'owner GET users?q=Priya');
check('U13', 'search by name q=Priya returns 200 with Priya', $own->status === 200 && in_array($U['admin2']['id'], array_column(user_rows($own->clean()), 'id'), true), "HTTP {$own->status}");
// D1 re-verification: email-only match, no-match, SQL metacharacters, combination with a filter.
seen($own->get('rolewarden/users?q=' . rawurlencode('theo@northwind')), 'owner GET users?q=email-only');
check('U13b', 'search by email fragment only (q=theo@northwind) returns exactly Theo', $own->status === 200 && array_column(user_rows($own->clean()), 'id') === [$U['child']['id']], "HTTP {$own->status}; ids " . json_encode(array_column(user_rows($own->clean()), 'id')));
seen($own->get('rolewarden/users?q=zzqx-nobody'), 'owner GET users?q=nomatch');
check('U13c', 'search with no match: 200, no rows, empty-state text', $own->status === 200 && user_rows($own->clean()) === [] && str_contains($own->clean(), 'No users match'), "HTTP {$own->status}");
foreach (["o'brien", '%', '_', '\\', '" OR 1=1 -- ', "') OR ('1'='1"] as $qq) {
    seen($own->get('rolewarden/users?q=' . rawurlencode($qq)), "owner GET users?q=$qq");
    $rr = user_rows($own->clean());
    $wild = in_array($qq, ['%', '_'], true);
    check('U13d', "search q=" . json_encode($qq) . ": 200, no SQL text" . ($wild ? '' : ', no injection match-all') . ' (' . count($rr) . ' rows)', $own->status === 200 && ! leaks_sql($own->body) && ($wild || $rr === []), "HTTP {$own->status}; rows " . count($rr));
    if ($wild && $rr !== []) {
        amb('U13w', "search q=" . json_encode($qq) . ' is treated as a LIKE wildcard and matches every user (' . count($rr) . ' rows on page 1)', 'Queries stay parameterized (no SQL text, the injection strings match nothing), but %/_ typed by the user are not matched literally (also q=d_na finds Dana, q=dana%test finds her). SPEC/UsersList README only say "search"; whether wildcard characters must be literal is not specified.');
    }
}
seen($own->get('rolewarden/users?q=northwind&role=' . $role('v1-limited')), 'owner GET users?q+role');
check('U13e', 'search combined with role filter (q=northwind & role=V1 Limited) returns only Jonas', $own->status === 200 && array_column(user_rows($own->clean()), 'id') === [$U['limited']['id']], json_encode(array_column(user_rows($own->clean()), 'id')));
seen($own->get('rolewarden/users?q=PRIYA'), 'owner GET users?q=PRIYA');
check('U13f', 'search is case-insensitive on name (q=PRIYA)', $own->status === 200 && in_array($U['admin2']['id'], array_column(user_rows($own->clean()), 'id'), true), "HTTP {$own->status}");
seen($own->get('rolewarden/users?role=' . $role('v1-limited')), 'owner GET users?role=limited');
$fr = $own->clean();
check('U14', 'role filter: only holders of V1 Limited', $own->status === 200 && array_column(user_rows($fr), 'id') === [$U['limited']['id']], json_encode(array_column(user_rows($fr), 'id')));
check('U15', 'Clear filters control appears when a filter is set', (bool) preg_match('/Clear filters/i', $fr), 'no Clear filters control with role filter set (UsersList README)');
seen($own->get('rolewarden/users?status=inactive'), 'owner GET users?status=inactive');
check('U16', 'status=inactive: only the disabled user', array_column(user_rows($own->clean()), 'id') === [$U['disabled']['id']], json_encode(array_column(user_rows($own->clean()), 'id')));
seen($own->get('rolewarden/users?status=active&role=' . $role('admin')), 'owner GET users active admin');
check('U17', 'status=active + role=admin: Priya only (Lena is inactive)', array_column(user_rows($own->clean()), 'id') === [$U['admin2']['id']], json_encode(array_column(user_rows($own->clean()), 'id')));
seen($own->get('rolewarden/users?status=inactive&role=' . $role('v1-limited')), 'owner GET users no result');
check('U18', 'no results: one row "No users match these filters."', str_contains($own->clean(), 'No users match these filters'), 'empty-state text missing');
foreach (['rolewarden/users?role=abc', 'rolewarden/users?role=99999', 'rolewarden/users?status=%27%20OR%201=1--', 'rolewarden/users?sort=%27;DROP', 'rolewarden/users?page=-5', 'rolewarden/users?page=abc'] as $u) {
    seen($own->get($u), "owner GET $u");
    $st = $own->status;
    check('U19', "tampered query $u: no 500, no SQL text", $st < 500 && ! leaks_sql($own->body), "HTTP $st");
}

// ---------------------------------------------------------------- 4. RolesList
echo "\n== 4. RolesList\n";
$own->post('rolewarden/roles', ['slug' => 'v1-grandchild', 'name' => 'V1 Grandchild', 'description' => 'Third level', 'parent_id' => $role('v1-child')], 'rolewarden/roles/create');
seen($own, 'owner POST roles create grandchild');
$gc = (int) q1('SELECT id FROM acl_roles WHERE slug="v1-grandchild" AND deleted_at IS NULL');
check('R01', 'role create with parent over HTTP persists (parent = V1 Child)', $gc > 0 && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$gc]) === $role('v1-child'), "status {$own->status}");
$xss = '<script>alert("rw")</script>';
$own->post('rolewarden/roles', ['slug' => 'v1-xss', 'name' => $xss, 'description' => $xss, 'parent_id' => ''], 'rolewarden/roles/create');
seen($own, 'owner POST roles create xss');
seen($own->get('rolewarden/roles'), 'owner GET roles');
$rl = $own->clean();
check('R02', 'role name/description with markup is escaped in the list', ! str_contains($rl, $xss) && (q1('SELECT COUNT(*) FROM acl_roles WHERE slug="v1-xss"') == 1 ? str_contains($rl, '&lt;script&gt;') : true), 'raw <script> found');
check('R03', 'sidebar marks Roles with aria-current="page"', (bool) preg_match('/<a href="[^"]*rolewarden\/roles" aria-current="page"/', $rl), 'missing');
$rr = role_rows($rl);
$pos = array_flip(array_column($rr, 'id'));
$P = $role('v1-parent');
$C = $role('v1-child');
check('R04', 'depth-first: V1 Child directly under V1 Parent, V1 Grandchild directly under V1 Child', isset($pos[$P], $pos[$C], $pos[$gc]) && $pos[$C] === $pos[$P] + 1 && $pos[$gc] === $pos[$C] + 1, 'order ' . json_encode(array_column($rr, 'name')));
// Every row's "Inherits from" and indentation agree with acl_roles.parent_id
$bad = [];
$depthOf = function (int $id) {
    $d = 0;
    while ($id && ($p = q1('SELECT parent_id FROM acl_roles WHERE id=?', [$id]))) {
        $d++;
        $id = (int) $p;
    }
    return $d;
};
foreach ($rr as $r) {
    $pid = q1('SELECT parent_id FROM acl_roles WHERE id=?', [$r['id']]);
    $pname = $pid ? (string) q1('SELECT name FROM acl_roles WHERE id=?', [$pid]) : '';
    if ($pid && $r['parent'] !== $pname) {
        $bad[] = "{$r['name']}: shows parent '{$r['parent']}', DB '$pname'";
    }
    if (! $pid && $r['branch'] > 0) {
        $bad[] = "{$r['name']}: root drawn with elbow";
    }
    if ($pid && $r['branch'] === 0) {
        $bad[] = "{$r['name']}: child without elbow";
    }
    // each live role appears after its parent
    if ($pid && isset($pos[(int) $pid]) && $pos[$r['id']] < $pos[(int) $pid]) {
        $bad[] = "{$r['name']} listed before its parent";
    }
}
check('R05', '"Inherits from" and elbow for every row match acl_roles.parent_id', ! $bad, implode('; ', $bad));
$rowOf = fn ($id) => current(array_filter($rr, fn ($r) => $r['id'] === $id)) ?: ['tree' => '', 'html' => '', 'perm' => '', 'delete' => false];
$gcRow = $rowOf($gc);
$cRow = $rowOf($C);
check('R06', 'indentation grows per level (grandchild row is marked deeper than child row)', $gcRow['tree'] !== $cRow['tree'] || $gcRow['branch'] > $cRow['branch'] || preg_replace('/roles\/\d+|V1 (Grandchild|Child)|Third level|Child role for v1 tests|\d+ <span>own<\/span> \+ \d+ <span>inherited<\/span>|rwConfirm\(\'confirm-delete-\d+\'\)|<td class="rw-m-num">\d+<\/td>|<td class="rw-c-n">\d+<\/td>|<span class="rw-role rw-role--parent">[^<]*<\/span>/', '', $gcRow['html']) !== preg_replace('/roles\/\d+|V1 (Grandchild|Child)|Third level|Child role for v1 tests|\d+ <span>own<\/span> \+ \d+ <span>inherited<\/span>|rwConfirm\(\'confirm-delete-\d+\'\)|<td class="rw-m-num">\d+<\/td>|<td class="rw-c-n">\d+<\/td>|<span class="rw-role rw-role--parent">[^<]*<\/span>/', '', $cRow['html']), 'child and grandchild rows carry identical tree markup: depth 2 is drawn exactly like depth 1 (README: "indented 24px per level")');
check('R07', 'permission counts: child "1 own + 2 inherited", grandchild "0 own + 3 inherited"', $cRow['perm'] === '1 own + 2 inherited' && $rowOf($gc)['perm'] === '0 own + 3 inherited', "child '{$cRow['perm']}', grandchild '{$rowOf($gc)['perm']}'");
$sys = array_filter($rr, fn ($r) => (int) q1('SELECT is_system FROM acl_roles WHERE id=?', [$r['id']]) === 1);
check('R08', 'system roles: no Delete button, note explains why', $sys && ! array_filter($sys, fn ($r) => $r['delete'] || ! str_contains($r['html'], 'system role')), 'a system role shows Delete or lacks the note');
check('R09', 'delete dialog of V1 Limited names the user who loses it', (bool) preg_match('/<dialog id="confirm-delete-' . $role('v1-limited') . '".*?' . preg_quote($U['limited']['username'], '/') . '.*?<\/dialog>/s', $rl), 'user not named');
$assign = (int) q1('SELECT COUNT(*) FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE r.deleted_at IS NULL');
$pdef = (int) q1('SELECT COUNT(*) FROM acl_permissions');
check('R10', "footer totals match DB ($assign assignments, $pdef permissions)", (bool) preg_match('/<tfoot>.*?<td class="rw-c-n">' . $assign . '<\/td>.*?' . $pdef . ' <span>defined/s', $rl), 'footer mismatch');
// cycle refused
$own->post('rolewarden/roles/' . $P, ['name' => 'V1 Parent', 'description' => 'Parent role for v1 tests', 'parent_id' => $gc], 'rolewarden/roles/' . $P . '/edit');
seen($own, 'owner POST cycle');
check('R11', 'cycle (V1 Parent -> V1 Grandchild) refused at save, DB unchanged', q1('SELECT parent_id FROM acl_roles WHERE id=?', [$P]) === null && ! leaks_sql($own->body), "status {$own->status}");
// system role delete refused server side
$own->post('rolewarden/roles/' . $role('admin') . '/delete', [], 'rolewarden/roles');
seen($own, 'owner POST delete system role');
check('R12', 'system role delete refused server side', q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$role('admin')]) === null, 'admin role soft-deleted');

// ---------------------------------------------------------------- 5. Role PermissionMatrix
echo "\n== 5. Role permission matrix\n";
$child = new Client('child');
check('M00', 'Theo (V1 Child holder) signs in', $child->login($U['child']['email'], $pw));
seen($child->get('rolewarden/roles'), 'child GET roles');
check('M01', 'Theo reaches roles list via inherited roles.view (200)', $child->status === 200, "status {$child->status}");
seen($child->get('rolewarden/users/create'), 'child GET users/create');
check('M02', 'Theo has no users.create yet', $child->status !== 200, "status {$child->status}");

seen($own->get('rolewarden/roles/' . $C), 'owner GET role child');
$rp = $own->clean();
[$cfg, $cells] = matrix_cfg($rp);
check('M03', 'matrix config: inherited cells locked with origin (roles.view, permissions.view "from V1 Parent")', ($cells['roles.view']['state'] ?? '') === 'inherit' && ($cells['roles.view']['editable'] ?? true) === false && str_contains($cells['roles.view']['tooltip'] ?? '', 'from V1 Parent') && ($cells['permissions.view']['state'] ?? '') === 'inherit', json_encode($cells['roles.view'] ?? null));
check('M04', 'own grant users.view: state role, editable', ($cells['users.view']['state'] ?? '') === 'role' && ($cells['users.view']['editable'] ?? false) === true, json_encode($cells['users.view'] ?? null));
check('M05', 'cell buttons bind aria-pressed and aria-disabled; legend shows the 5 marks with the README SVG shapes', str_contains($rp, ':aria-pressed=') && str_contains($rp, ':aria-disabled=') && substr_count($rp, 'rw-mark rw-mark--') >= 4 && str_contains($rp, 'M4.5 10.5l3.5 3.5 7.5-8') && str_contains($rp, 'M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6') && str_contains($rp, 'cx="10" cy="10" r="8"') && str_contains($rp, 'M1.5 10.5h17') && str_contains($rp, 'rw-mark-blank'), 'legend/ARIA incomplete');
check('M06', 'row and column select-all checkboxes present, unsaved bar with Discard / Save changes and "All changes saved."', str_contains($rp, 'toggleRow(') && str_contains($rp, 'toggleColumn(') && str_contains($rp, 'indeterminate') && str_contains($rp, 'Discard') && str_contains($rp, 'Save changes') && str_contains($rp, 'All changes saved.'), 'missing');
check('M07', 'footer "Effective" line: view column counts 3 (1 own + 2 inherited)', (bool) preg_match('/Effective.*?(<td class="rw-m-act">\d+.*?){3}<td class="rw-m-act">3<span/s', $rp) || (bool) preg_match('/Effective/', $rp) && substr_count(preg_replace('/.*<tfoot>/s', '', $rp), '>3<span') >= 1, 'no Effective 3');

$own->post('rolewarden/roles/' . $C . '/permissions', ['permission_id' => $perm('users.create'), 'granted' => 1], 'rolewarden/roles/' . $C, $AJAX);
seen($own, 'owner POST role cell grant');
$j = json_decode($own->body, true);
check('M08', 'single-cell save (granted=1) answers ok + fresh csrfHash and persists in acl_role_permissions', ($j['ok'] ?? false) === true && ! empty($j['csrfHash']) && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$C, $perm('users.create')]) === 1, "status {$own->status} body " . substr($own->body, 0, 100));
seen($child->get('rolewarden/users/create'), 'child GET users/create after grant');
check('M09', 'grant effective on Theo\'s next request, no logout (users/create 200)', $child->status === 200, "status {$child->status}");
// the returned csrfHash is usable for the next POST
$own->req('POST', 'rolewarden/roles/' . $C . '/permissions', ['permission_id' => $perm('users.create'), 'granted' => 0, 'csrf_test_name' => $j['csrfHash'] ?? ''], $AJAX);
seen($own, 'owner POST role cell revoke with returned hash');
check('M10', 'revoke (granted=0) with the csrfHash returned by the previous save succeeds and deletes the row', str_contains($own->body, '"ok": true') && (int) q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$C, $perm('users.create')]) === 0, "status {$own->status}");
seen($child->get('rolewarden/users/create'), 'child GET users/create after revoke');
check('M11', 'revoke effective on Theo\'s next request, no logout (users/create refused)', $child->status !== 200, "status {$child->status}");

// child cannot revoke what the parent grants: direct request on the locked cell
$parentRows = q('SELECT * FROM acl_role_permissions WHERE role_id=? ORDER BY permission_id', [$P]);
$own->post('rolewarden/roles/' . $C . '/permissions', ['permission_id' => $perm('roles.view'), 'granted' => 0], 'rolewarden/roles/' . $C, $AJAX);
seen($own, 'owner POST revoke inherited');
$resp = "HTTP {$own->status} " . preg_replace('/\s+/', ' ', substr(trim($own->body), 0, 80));
seen($child->get('rolewarden/roles'), 'child GET roles after inherited revoke attempt');
check('M12', "direct POST revoking inherited roles.view on the child is refused or ignored ($resp): parent grants intact, Theo still in (200)", q('SELECT * FROM acl_role_permissions WHERE role_id=? ORDER BY permission_id', [$P]) === $parentRows && $child->status === 200, "Theo status {$child->status}");
seen($own->get('rolewarden/roles/' . $C), 'owner GET role child again');
[, $cells2] = matrix_cfg($own->clean());
check('M13', 'after that request roles.view is still shown inherited+locked on the child', ($cells2['roles.view']['state'] ?? '') === 'inherit' && ($cells2['roles.view']['editable'] ?? true) === false, json_encode($cells2['roles.view'] ?? null));
// parent revoke propagates down the chain on next request
$own->post('rolewarden/roles/' . $P . '/permissions', ['permission_id' => $perm('roles.view'), 'granted' => 0], 'rolewarden/roles/' . $P, $AJAX);
seen($own, 'owner POST parent revoke');
seen($child->get('rolewarden/roles'), 'child GET roles after parent revoke');
check('M14', 'revoking roles.view on the PARENT removes Theo\'s access on his next request (cache invalidated down the tree)', $child->status !== 200, "status {$child->status}");
$own->post('rolewarden/roles/' . $P . '/permissions', ['permission_id' => $perm('roles.view'), 'granted' => 1], 'rolewarden/roles/' . $P, $AJAX);
seen($child->get('rolewarden/roles'), 'child GET roles after parent re-grant');
check('M15', 're-granting on the parent restores it on the next request', $child->status === 200, "status {$child->status}");

// CSRF and malformed input on the role endpoint
$snap = q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id');
$own->req('POST', 'rolewarden/roles/' . $C . '/permissions', ['permission_id' => $perm('users.delete'), 'granted' => 1], $AJAX);
seen($own, 'role POST no csrf');
check('M16', 'role endpoint without CSRF token: refused, nothing written', ! str_contains($own->body, '"ok": true') && q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id') === $snap, "HTTP {$own->status}");
no_leak('M16b', $own, 'role endpoint without CSRF');
$own->req('POST', 'rolewarden/roles/' . $C . '/permissions', ['permission_id' => $perm('users.delete'), 'granted' => 1, 'csrf_test_name' => str_repeat('0', 32)], $AJAX);
seen($own, 'role POST wrong csrf');
check('M17', 'role endpoint with a wrong CSRF token: refused, nothing written', ! str_contains($own->body, '"ok": true') && q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id') === $snap, "HTTP {$own->status}");
no_leak('M17b', $own, 'role endpoint wrong CSRF');
foreach (['abc', '99999', '1 OR 1=1', '', '-1', '1.5'] as $v) {
    $own->post('rolewarden/roles/' . $C . '/permissions', ['permission_id' => $v, 'granted' => 1], 'rolewarden/roles/' . $C, $AJAX);
    seen($own, "role POST permission_id=$v");
    $wrote = q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id') !== $snap;
    check('M18', "role endpoint permission_id='$v': no 500, no SQL text", $own->status < 500 && ! leaks_sql($own->body), "HTTP {$own->status} " . substr($own->body, 0, 100));
    if ($wrote) {
        echo "         OBS: role permission_id='$v' was accepted and written (lenient integer cast)
";
        $own->post('rolewarden/roles/' . $C . '/permissions', ['permission_id' => (int) $v, 'granted' => 0], 'rolewarden/roles/' . $C, $AJAX);
    }
}
$own->post('rolewarden/roles/99999/permissions', ['permission_id' => $perm('users.view'), 'granted' => 1], 'rolewarden/roles/' . $C, $AJAX);
seen($own, 'role POST nonexistent role');
check('M19', 'role endpoint on nonexistent role 99999: no 500, no SQL text, nothing written', $own->status < 500 && ! leaks_sql($own->body) && q('SELECT * FROM acl_role_permissions ORDER BY role_id, permission_id') === $snap, "HTTP {$own->status}");

// ---------------------------------------------------------------- 6. UserDetail and overrides
echo "\n== 6. UserDetail and per-user overrides\n";
$mara = new Client('mara');
check('D00', 'Mara (role user, no permissions) signs in', $mara->login($U['subject']['email'], $pw));
seen($mara->get('rolewarden/users'), 'mara GET users');
check('D01', 'Mara has no users.view yet', $mara->status !== 200, "status {$mara->status}");
seen($own->get('rolewarden/users/' . $U['subject']['id']), 'owner GET user mara');
$up = $own->clean();
check('D02', 'sections A Roles, B Effective permissions, C Account lettered in the gutter', (bool) preg_match('/rw-section-n">A<\/span><h2>Roles.*rw-section-n">B<\/span><h2>Effective permissions.*rw-section-n">C<\/span><h2>Account/s', $up), 'section structure differs');
check('D03', 'user matrix + five-mark legend + unsaved bar present', str_contains($up, 'rwUserMatrix(') && str_contains($up, 'M1.5 10.5h17') && str_contains($up, 'rw-mark-blank') && (bool) preg_match('/Save overrides|Save changes/', $up), 'missing');
check('D04', 'deferred features not offered: no Send reset link / Sign out everywhere / Export CSV / Invite / Settings controls', ! preg_match('/Send reset link|Sign out everywhere|Export CSV|Invite user|rolewarden\/settings/i', $up . $ul . $rl), 'deferred control rendered');

$sid = $U['subject']['id'];
$own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view'), 'granted' => 1], "rolewarden/users/$sid", $AJAX);
seen($own, 'owner POST grant override');
check('D05', 'override grant (granted=1) persists as granted=1', (int) q1('SELECT granted FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$sid, $perm('users.view')]) === 1 && str_contains($own->body, '"ok": true'), "HTTP {$own->status} " . substr($own->body, 0, 80));
seen($mara->get('rolewarden/users'), 'mara GET users after grant');
check('D06', 'grant override effective on Mara\'s next request, no logout (200)', $mara->status === 200, "status {$mara->status}");
seen($own->get("rolewarden/users/$sid"), 'owner GET mara after grant');
[, $uc] = matrix_cfg($own->clean());
check('D07', 'user matrix shows users.view as state grant, header fact Overrides = 1', ($uc['users.view']['state'] ?? '') === 'grant' && (bool) preg_match('/<dt>Overrides<\/dt><dd[^>]*>1</', $own->clean()), json_encode($uc['users.view'] ?? null));
$own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view')], "rolewarden/users/$sid", $AJAX);
seen($own, 'owner POST clear override');
check('D08', 'request without granted removes the override row', (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=?', [$sid]) === 0, 'row still present');
seen($mara->get('rolewarden/users'), 'mara GET users after clear');
check('D09', 'removal effective on Mara\'s next request (refused again)', $mara->status !== 200, "status {$mara->status}");

// deny on a role grant
$pid2 = $U['admin2']['id'];
$pri = new Client('priya');
check('D10', 'Priya (admin) signs in', $pri->login($U['admin2']['email'], $pw));
seen($pri->get('rolewarden/users'), 'priya GET users');
$priBefore = $pri->status;
$own->post("rolewarden/users/$pid2/permissions", ['permission_id' => $perm('users.view'), 'granted' => 0], "rolewarden/users/$pid2", $AJAX);
seen($own, 'owner POST deny priya');
seen($pri->get('rolewarden/users'), 'priya GET users after deny');
check('D11', 'deny override (granted=0) on a role grant: stored as 0 and Priya refused on next request', $priBefore === 200 && (int) q1('SELECT granted FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$pid2, $perm('users.view')]) === 0 && $pri->status !== 200, "before $priBefore after {$pri->status}");
seen($own->get("rolewarden/users/$pid2"), 'owner GET priya');
[, $pc] = matrix_cfg($own->clean());
check('D12', 'Priya\'s matrix: users.view deny, users.create still role', ($pc['users.view']['state'] ?? '') === 'deny' && ($pc['users.create']['state'] ?? '') === 'role', json_encode([$pc['users.view'] ?? null, $pc['users.create']['state'] ?? null]));
// role matrix summarises holder overrides
seen($own->get('rolewarden/roles/' . $role('admin')), 'owner GET admin role');
[, $ac] = matrix_cfg($own->clean());
check('D13', 'admin role matrix summarises the denial and names Priya ("denied for 1 user")', ($ac['users.view']['state'] ?? '') === 'deny' && str_contains($ac['users.view']['tooltip'] ?? '', $U['admin2']['username']) && stripos($ac['users.view']['tooltip'] ?? '', 'denied for 1 user') !== false, json_encode($ac['users.view'] ?? null));
check('D14', 'override cells are read-only on the role matrix (editable=false)', ($ac['users.view']['editable'] ?? true) === false, json_encode($ac['users.view'] ?? null));
// deny beats super admin
$own->post("rolewarden/users/$pid2/roles", ['role_id' => $role('super-admin')], "rolewarden/users/$pid2");
seen($own, 'owner POST add super-admin to priya');
seen($pri->get('rolewarden/users'), 'priya GET users as super admin with deny');
seen($pri->get('rolewarden/roles'), 'priya GET roles as super admin');
check('D15', 'Priya now super admin: denied users.view still refused, other routes open (deny beats super admin)', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$pid2, $role('super-admin')]) === 1 && $pri->status === 200 && ($pri->get('rolewarden/users')->status !== 200), 'priya users status ' . $pri->status);
$own->post("rolewarden/users/$pid2/permissions", ['permission_id' => $perm('users.view')], "rolewarden/users/$pid2", $AJAX);
seen($pri->get('rolewarden/users'), 'priya GET users after deny cleared');
check('D16', 'clearing the denial reopens users.view on the next request', $pri->status === 200, "status {$pri->status}");
$own->post("rolewarden/users/$pid2/roles/" . $role('super-admin') . '/revoke', [], "rolewarden/users/$pid2");
seen($own, 'owner POST revoke priya super-admin');
check('D17', 'second super admin removed again (owner is the only active super admin)', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$pid2, $role('super-admin')]) === 0, 'still assigned');

// Removing a role that feeds an override keeps the override
$own->post("rolewarden/users/$sid/roles", ['role_id' => $role('v1-limited')], "rolewarden/users/$sid");
$own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view'), 'granted' => 0], "rolewarden/users/$sid", $AJAX);
$own->post("rolewarden/users/$sid/roles/" . $role('v1-limited') . '/revoke', [], "rolewarden/users/$sid");
seen($own, 'owner POST revoke feeding role');
check('D18', 'removing the role that fed a denial keeps the override row', (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=? AND permission_id=? AND granted=0', [$sid, $perm('users.view')]) === 1 && (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$sid, $role('v1-limited')]) === 0, 'override lost or role still there');
$own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view')], "rolewarden/users/$sid", $AJAX);

// Override endpoint: CSRF, malformed input, nonexistent user
$snapU = q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id');
$own->req('POST', "rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view'), 'granted' => 1], $AJAX);
seen($own, 'user POST no csrf');
check('D19', 'override endpoint without CSRF: refused, nothing written', ! str_contains($own->body, '"ok": true') && q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id') === $snapU, "HTTP {$own->status}");
no_leak('D19b', $own, 'override endpoint without CSRF');
$own->req('POST', "rolewarden/users/$sid/permissions", ['permission_id' => $perm('users.view'), 'granted' => 1, 'csrf_test_name' => 'deadbeef'], $AJAX);
seen($own, 'user POST wrong csrf');
check('D20', 'override endpoint with wrong CSRF: refused, nothing written', ! str_contains($own->body, '"ok": true') && q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id') === $snapU, "HTTP {$own->status}");
foreach (['abc', '99999', '1 OR 1=1', '', '-1'] as $v) {
    $own->post("rolewarden/users/$sid/permissions", ['permission_id' => $v, 'granted' => 1], "rolewarden/users/$sid", $AJAX);
    seen($own, "user POST permission_id=$v");
    $wrote = q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id') !== $snapU;
    check('D21', "override endpoint permission_id='$v': no 500, no SQL text", $own->status < 500 && ! leaks_sql($own->body), "HTTP {$own->status} " . substr($own->body, 0, 100));
    if ($wrote) {
        echo "         OBS: permission_id='$v' was accepted and written as " . json_encode(array_values(array_filter(q('SELECT * FROM acl_user_permissions WHERE user_id=?', [$sid])))) . " (lenient integer cast)
";
        $own->post("rolewarden/users/$sid/permissions", ['permission_id' => (int) $v], "rolewarden/users/$sid", $AJAX);
    }
}
foreach (['2', 'yes', '-1'] as $g) {
    $own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('roles.view'), 'granted' => $g], "rolewarden/users/$sid", $AJAX);
    seen($own, "user POST granted=$g");
    $row = q('SELECT granted FROM acl_user_permissions WHERE user_id=? AND permission_id=?', [$sid, $perm('roles.view')]);
    check('D22', "override endpoint granted='$g': no 500/SQL text, stored value (if any) is 0/1", $own->status < 500 && ! leaks_sql($own->body) && (! $row || in_array((int) $row[0]['granted'], [0, 1], true)), "HTTP {$own->status} row " . json_encode($row));
    $own->post("rolewarden/users/$sid/permissions", ['permission_id' => $perm('roles.view')], "rolewarden/users/$sid", $AJAX);
}
$own->post('rolewarden/users/99999/permissions', ['permission_id' => $perm('users.view'), 'granted' => 1], "rolewarden/users/$sid", $AJAX);
seen($own, 'user POST nonexistent user');
check('D23', 'override endpoint on nonexistent user 99999: no 500, no SQL text, nothing written', $own->status < 500 && ! leaks_sql($own->body) && (int) q1('SELECT COUNT(*) FROM acl_user_permissions WHERE user_id=99999') === 0, "HTTP {$own->status} " . substr(clean_html($own->body), 0, 150));

// M4 protections through the v1 panel
echo "\n== 7. M4 protections through the rewritten panel\n";
$oid = $U['owner']['id'];
$own->post("rolewarden/users/$oid/roles/" . $role('super-admin') . '/revoke', [], "rolewarden/users/$oid");
seen($own, 'owner POST revoke own last super admin');
check('P01', 'last active super admin cannot lose the super admin role (self)', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$oid, $role('super-admin')]) === 1 && ! leaks_sql($own->body), "HTTP {$own->status}");
$pri->post("rolewarden/users/$oid/roles/" . $role('super-admin') . '/revoke', [], 'rolewarden/users/' . $oid);
seen($pri, 'priya POST revoke owner super admin');
check('P02', 'last active super admin cannot lose it through another admin (roles.assign)', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$oid, $role('super-admin')]) === 1, "HTTP {$pri->status}");
$pri->post("rolewarden/users/$oid/deactivate", [], 'rolewarden/users/' . $oid);
seen($pri, 'priya POST deactivate owner');
check('P03', 'last active super admin cannot be deactivated by another admin', (int) q1('SELECT active FROM users WHERE id=?', [$oid]) === 1, 'owner deactivated');
$pri->post("rolewarden/users/$oid/delete", [], 'rolewarden/users/' . $oid);
seen($pri, 'priya POST delete owner');
check('P04', 'last active super admin cannot be deleted by another admin', (int) q1('SELECT COUNT(*) FROM users WHERE id=? AND deleted_at IS NULL', [$oid]) === 1, 'owner deleted');
$selfDanger = '/<button class="rw-btn rw-btn--danger rw-btn--sm" type="(submit|button)"( onclick="[^"]*")?>(Deactivate|Delete)/';
seen($own->get("rolewarden/users/$oid"), 'owner GET own page');
check('P07', 'own page (owner): Deactivate/Delete controls disabled or absent', ! preg_match($selfDanger, $own->clean()), 'enabled self-destructive control rendered');
seen($pri->get("rolewarden/users/$pid2"), 'priya GET own page');
check('P07b', 'own page (Priya, admin): Deactivate/Delete controls disabled or absent', $pri->status === 200 && ! preg_match($selfDanger, $pri->clean()), "HTTP {$pri->status}");
/** Self-protection attempt; if the server lets it through, record it and repair the fixture by SQL. */
$selfTry = function (string $id, Client $c, int $uid, string $action, string $label) {
    $c->post("rolewarden/users/$uid/$action", [], "rolewarden/users/$uid");
    seen($c, "$label POST $action self");
    $resp = "HTTP {$c->status} -> " . ($c->location ?: substr(trim(strip_tags(clean_html($c->body))), 0, 60));
    if ($action === 'deactivate') {
        $ok = (int) q1('SELECT active FROM users WHERE id=?', [$uid]) === 1;
        $ok || q('UPDATE users SET active=1 WHERE id=?', [$uid]);
    } else {
        $ok = (int) q1('SELECT COUNT(*) FROM users WHERE id=? AND deleted_at IS NULL', [$uid]) === 1;
        $ok || q('UPDATE users SET deleted_at=NULL WHERE id=?', [$uid]);
    }
    // The SQL repair bypasses the resolver's cache invalidation: drop the app copy's file cache.
    $ok || array_map('unlink', array_filter(glob(getenv('RW_V1_APP') . '/writable/cache/*') ?: [], fn ($f) => is_file($f) && basename($f) !== 'index.html'));
    check($id, "$label cannot $action themselves (server side) [$resp]", $ok, "users.id=$uid was {$action}d by their own request; fixture repaired by SQL afterwards");
};
// Priya is admin, not super admin: the last-super-admin rule cannot be what protects her.
$selfTry('P05', $pri, $pid2, 'delete', 'Priya (admin)');
$selfTry('P06', $pri, $pid2, 'deactivate', 'Priya (admin)');
// Owner with a second active super admin present.
$own->post("rolewarden/users/$pid2/roles", ['role_id' => $role('super-admin')], "rolewarden/users/$pid2");
check('P08', 'fixture: Priya made second super admin', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$pid2, $role('super-admin')]) === 1, 'not assigned');
$selfTry('P05b', $own, $oid, 'delete', 'Owner (super admin, another one present)');
$selfTry('P06b', $own, $oid, 'deactivate', 'Owner (super admin, another one present)');
$own->post("rolewarden/users/$pid2/roles/" . $role('super-admin') . '/revoke', [], "rolewarden/users/$pid2");
check('P08b', 'fixture reset through the panel: Priya no longer super admin', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$pid2, $role('super-admin')]) === 0, 'still assigned');
// system permission / 404s
foreach (['rolewarden/users/99999', 'rolewarden/roles/99999', 'rolewarden/users/abc', 'rolewarden/roles/99999/edit', 'rolewarden/users/99999/edit'] as $u) {
    seen($own->get($u), "owner GET $u");
    check('P09', "$u: 404 (no 500, no SQL text)", $own->status === 404 && ! leaks_sql($own->body), "HTTP {$own->status}");
}
foreach (["rolewarden/users/99999/deactivate", "rolewarden/users/99999/delete", "rolewarden/roles/99999/delete", "rolewarden/users/$sid/roles/99999/revoke"] as $u) {
    $own->post($u, [], 'rolewarden/users');
    seen($own, "owner POST $u");
    check('P10', "POST $u: reaches the controller (not a CSRF refusal), no 500, no SQL text", ! csrf_refused($own) && $own->status < 500 && ! leaks_sql($own->body), "HTTP {$own->status}");
}
$own->post("rolewarden/users/$sid/roles", ['role_id' => 'abc'], "rolewarden/users/$sid");
seen($own, 'owner POST add role abc');
check('P11', 'add role with role_id=abc: no 500, no SQL text', $own->status < 500 && ! leaks_sql($own->body), "HTTP {$own->status}");
$own->post("rolewarden/users/$sid/roles", ['role_id' => '99999'], "rolewarden/users/$sid");
seen($own, 'owner POST add role 99999');
check('P12', 'add role with role_id=99999: no 500, no SQL text, nothing written', $own->status < 500 && ! leaks_sql($own->body) && (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE role_id=99999') === 0, "HTTP {$own->status}; on screen: " . $dbMsg($own->body));
// Design: a user may not remove their own last administrator role (UserDetail README).
$pri->post("rolewarden/users/$pid2/roles/" . $role('admin') . '/revoke', [], "rolewarden/users/$pid2");
seen($pri, 'priya POST revoke own admin role');
$selfRevoke = (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$pid2, $role('admin')]) === 1;
if (! $selfRevoke) {
    q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$pid2, $role('admin')]);
    array_map('unlink', array_filter(glob(getenv('RW_V1_APP') . '/writable/cache/*') ?: [], fn ($f) => is_file($f) && basename($f) !== 'index.html'));
    amb('P15', 'Priya (admin) removed her own admin role through the panel (server accepted it; restored by SQL)', 'UserDetail README: "Do not let a user remove their own last Administrator role". The spec never says which RoleWarden role "Administrator" is (system role admin? any role granting users/roles management? super-admin only, already covered by the last-super-admin rule), so this is reported, not decided.');
} else {
    check('P15', 'Priya cannot remove her own admin role', true);
}
// ---- Re-verification of the D2-D4 fixes: edges around the new server-side checks.
$okRole = $role('user');
$own->post('rolewarden/users/99999/roles', ['role_id' => $okRole], 'rolewarden/users');
seen($own, 'owner POST add role to nonexistent user');
check('RV01', "D2 edge: add an existing role to nonexistent user 99999: not a CSRF refusal, no 500, no SQL text, nothing written (HTTP {$own->status})", ! csrf_refused($own) && $own->status < 500 && ! leaks_sql($own->body) && (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=99999') === 0, "HTTP {$own->status}; on screen: " . $dbMsg($own->body));
$v1c = $role('v1-child');
$own->post("rolewarden/users/$sid/roles", ['role_id' => $v1c], "rolewarden/users/$sid");
seen($own, 'owner POST add valid role');
check('RV02', 'D2 no regression: adding an existing role to an existing user still works', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$sid, $v1c]) === 1, "HTTP {$own->status}");
$own->post("rolewarden/users/$sid/roles/$v1c/revoke", [], "rolewarden/users/$sid");
check('RV03', 'fixture reset through the panel: role removed again', (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$sid, $v1c]) === 0, 'still assigned');
// After a refused self-action the actor keeps a working session (no silent logout / lockout).
$own->get('rolewarden/users');
check('RV04', 'owner still reaches the panel after the refused self-actions (session intact)', $own->status === 200, "HTTP {$own->status}");
// Deactivating / deleting ANOTHER user must still work (the self check must not block everyone).
$bulkA = (int) q1("SELECT user_id FROM auth_identities WHERE secret='bulk29@northwind-studio.test'");
$bulkB = (int) q1("SELECT user_id FROM auth_identities WHERE secret='bulk30@northwind-studio.test'");
$own->post("rolewarden/users/$bulkA/deactivate", [], "rolewarden/users/$bulkA");
seen($own, 'owner POST deactivate other');
check('RV05', 'D3 no regression: an admin can still deactivate another user', (int) q1('SELECT active FROM users WHERE id=?', [$bulkA]) === 0, "HTTP {$own->status}");
$own->post("rolewarden/users/$bulkB/delete", [], "rolewarden/users/$bulkB");
seen($own, 'owner POST delete other');
check('RV06', 'D4 no regression: an admin can still delete another user', (int) q1('SELECT COUNT(*) FROM users WHERE id=? AND deleted_at IS NULL', [$bulkB]) === 0, "HTTP {$own->status}");
// The refusal of a self-action is explained to the user (flash message on the redirect target).
$own->post("rolewarden/users/$oid/deactivate", [], "rolewarden/users/$oid");
$to = $own->location;
$flash = $to !== '' ? trim(strip_tags(implode(' ', (function ($h) { preg_match_all('/<[^>]*role="(?:alert|status)"[^>]*>(.*?)<\/[a-z]+>/s', $h, $m); return $m[1]; })(clean_html($own->get($to)->body))))) : '';
check('RV07', "D3: refused self-deactivation is explained after the redirect (\"" . substr(preg_replace('/\s+/', ' ', $flash), 0, 90) . "\")", (int) q1('SELECT active FROM users WHERE id=?', [$oid]) === 1 && $flash !== '', "HTTP redirect to '$to', no alert/status text; active=" . q1('SELECT active FROM users WHERE id=?', [$oid]));
q('UPDATE users SET active=1 WHERE id=?', [$oid]);

// CSRF on classic forms
$own->req('POST', "rolewarden/users/$sid/deactivate", []);
seen($own, 'owner POST deactivate no csrf');
check('P13', 'form POST (deactivate) without CSRF refused, user still active, no SQL text', (int) q1('SELECT active FROM users WHERE id=?', [$sid]) === 1 && ! leaks_sql($own->body), "HTTP {$own->status}");
$own->req('POST', 'rolewarden/roles', ['slug' => 'v1-nocsrf', 'name' => 'No csrf', 'csrf_test_name' => 'bad']);
seen($own, 'owner POST role create bad csrf');
check('P14', 'form POST (role create) with wrong CSRF refused, nothing written', (int) q1('SELECT COUNT(*) FROM acl_roles WHERE slug="v1-nocsrf"') === 0 && ! leaks_sql($own->body), "HTTP {$own->status}");

// ---------------------------------------------------------------- 8. Links, assets, overrides
echo "\n== 8. Links, assets, view override\n";
$pages = ['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions', "rolewarden/users/$sid", "rolewarden/users/$oid", 'rolewarden/roles/' . $C, 'rolewarden/users/create', 'rolewarden/roles/create'];
$links = [];
foreach ($pages as $p) {
    $own->get($p);
    preg_match_all('/(?:href|src)="(' . preg_quote('http://localhost:8070/', '/') . '[^"#]*)"/', clean_html($own->body), $m);
    foreach ($m[1] as $l) {
        if (! str_contains($l, 'logout') && ! str_contains($l, '?debugbar')) {
            $links[html_entity_decode($l)] = $p;
        }
    }
}
$broken = [];
foreach ($links as $l => $from) {
    $own->get($l);
    if ($own->status >= 400) {
        $broken[] = "$l ({$own->status}, from $from)";
    }
}
check('X01', 'every link/asset on the panel pages resolves (' . count($links) . ' checked, no 4xx/5xx)', ! $broken, implode('; ', $broken));
$own->get('rolewarden/assets/css/panel.css');
$css = $own->body;
$ctype = $own->headers['content-type'] ?? '';
preg_match_all('/(--[a-z0-9-]+)\s*:/', (string) file_get_contents(__DIR__ . '/../../docs/design-system/tokens.css'), $tk);
$missing = array_values(array_filter(array_unique($tk[1]), fn ($t) => ! str_contains($css, $t . ':')));
check('X02', 'panel.css served as text/css and defines every token of docs/design-system/tokens.css', str_contains($ctype, 'text/css') && ! $missing, "content-type '$ctype', missing: " . implode(', ', array_slice($missing, 0, 20)));
check('X03', 'panel.css has a [data-theme="dark"] token set and :focus-visible outlines on var(--focus)', str_contains($css, 'data-theme="dark"') && str_contains($css, ':focus-visible') && str_contains($css, 'var(--focus)'), 'missing');
$trav = [];
foreach (['rolewarden/assets/..%2f..%2f..%2f.env', 'rolewarden/assets/css/..%2f..%2fConfig%2fRoleWarden.php', 'rolewarden/assets/..%2F..%2FConfig%2FRouteRegistrar.php', 'rolewarden/assets/css/%2e%2e/%2e%2e/Config/RoleWarden.php', 'rolewarden/assets/js/../../Controllers/UsersController.php', 'rolewarden/assets/css/panel.php'] as $u) {
    $anon->get($u);
    if ($anon->status === 200 && (str_contains($anon->body, '<?php') || str_contains($anon->body, 'database.'))) {
        $trav[] = "$u => 200 with source";
    }
}
check('X04', 'asset route does not serve files outside the asset folder (path traversal)', ! $trav, implode('; ', $trav));
// Which module view renders the role page: read from CI4's own DEBUG-VIEW comments (development output).
$own->get('rolewarden/roles/' . $C);
preg_match_all('#DEBUG-VIEW START \d+ (\S*?[\\\\/]src[\\\\/]Views[\\\\/](\S+?\.php))#', $own->body, $vm);
$viewRel = '';
foreach ($vm[2] as $v) {
    $v = str_replace('\\', '/', $v);
    if (str_starts_with($v, 'roles/')) {
        $viewRel = $v;
        break;
    }
}
$ovFile = getenv('RW_V1_APP') . '/app/Views/overrides/RoleWarden/Views/' . $viewRel;
$ovOk = false;
if ($viewRel !== '') {
    @mkdir(dirname($ovFile), 0777, true);
    file_put_contents($ovFile, '<p>V1-HOST-OVERRIDE-MARKER</p>');
    $own->get('rolewarden/roles/' . $C);
    $ovOk = str_contains($own->body, 'V1-HOST-OVERRIDE-MARKER');
    unlink($ovFile);
}
$own->get('rolewarden/roles/' . $C);
check('X05', "host override app/Views/overrides/RoleWarden/Views/$viewRel wins, removing it restores the module view", $viewRel !== '' && $ovOk && str_contains($own->body, 'rwMatrix('), 'override not applied or not reverted; views seen: ' . json_encode($vm[2]));

// ---------------------------------------------------------------- 9. Global sweep
echo "\n== 9. Global sweep over every response\n";
$leaks = [];
$warn = [];
foreach ($SEEN as [$what, $st, $body]) {
    if (leaks_sql($body)) {
        $leaks[] = "$what (HTTP $st)";
    }
    $b = preg_replace('/<div id="debug-bar".*$/s', '', clean_html($body));
    if (preg_match('/(<b>)?(Warning|Notice|Deprecated)(<\/b>)?:\s|ErrorException/', $b)) {
        $warn[] = "$what (HTTP $st)";
    }
}
check('Z01', 'no SQL/DB error text in any of ' . count($SEEN) . ' responses', ! $leaks, implode('; ', $leaks));
check('Z02', 'no PHP warning/notice/deprecation in any response', ! $warn, implode('; ', $warn));

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "\nTOTAL: $pass PASS, $fail FAIL\n";
exit($fail ? 1 : 0);
