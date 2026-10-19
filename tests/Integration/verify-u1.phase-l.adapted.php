<?php
// U1 adapted copy of verify-v8b.phase-l.php: only L17 changes. Since v1.1 (SPEC 2026-10-18, "La barra si comprime a una
// colonna di icone, scelta che resta per utente") every panel page carries the sidebar collapse form (POST rolewarden/sidebar),
// a submit button that is not a Settings action: it joins the language and light/dark switches in the allowed list.
// Phase L: language switch EN/IT in the top bar of every panel and demo page (CHANGELOG 1.0.0, README "Languages",
// documentation/views.html#languages, SPEC V7 "Scelta della lingua").
function lswitch(string $h): ?array
{
    foreach (forms($h) as $f) {
        if (! preg_match('#/rolewarden/profile/language$#', $f['action'])) continue;
        if (! str_contains($f['html'], 'aria-pressed')) continue; // the profile's own form has a select, not buttons
        preg_match_all('/<button[^>]*name="language"[^>]*>/', $f['html'], $m);
        $btn = [];
        foreach ($m[0] as $b) {
            preg_match('/value="([^"]*)"/', $b, $v); preg_match('/aria-pressed="([^"]*)"/', $b, $p);
            $btn[$v[1] ?? ''] = $p[1] ?? '';
        }
        $hidden = array_filter($f['fields'], fn ($v, $k) => ($f['types'][$k] ?? '') === 'hidden' && $k !== 'csrf_test_name', ARRAY_FILTER_USE_BOTH);
        return ['action' => $f['action'], 'csrf' => $f['fields']['csrf_test_name'] ?? '', 'buttons' => $btn, 'html' => $f['html'], 'hidden' => $hidden];
    }
    return null;
}
function lang_setting(int $uid): array
{
    return array_map(fn ($r) => $r['class'] . '.' . $r['key'] . '=' . $r['value'], q("SELECT class, `key`, value FROM settings WHERE context LIKE ? AND (`key` LIKE '%ocale%' OR `key` LIKE '%anguage%')", ['%:' . $uid]));
}
function lpost(Client $c, string $value, ?string $referer, string $tag, bool $csrf = true, ?string $tok = null, ?array $extra = null): Client
{
    // as a browser: the form is read from the page the switch is used on (token and every hidden field it carries)
    if ($tok === null) {
        $page = ($referer !== null && str_starts_with($referer, BASE)) ? $referer : 'rolewarden/profile';
        $ph = G($c, $page, "page for $tag");
        $tok = csrf_of($ph);
        $extra ??= lswitch($ph)['hidden'] ?? [];
    }
    $h = $referer === null ? [] : ['Referer: ' . $referer];
    $c->req('POST', 'rolewarden/profile/language', ($csrf ? ['csrf_test_name' => $tok] : []) + ($extra ?? []) + ['language' => $value], $h);
    seen($c, $tag);
    return $c;
}
$loc = fn (Client $c) => abs_url($c->url, $c->location);

$cL = serve8($APP, 'lang');
$own = c8('owner');
check('L00', 'Owner signs in', login8($own, 'owner@v8b.test', OPW));
$R9 = role_id('demo-approver');
$PANEL = ['rolewarden/users', "rolewarden/users/$PLAIN", "rolewarden/users/$PLAIN/edit", 'rolewarden/users/create', 'rolewarden/users/import', 'rolewarden/roles', "rolewarden/roles/$R9", "rolewarden/roles/$R9/edit", 'rolewarden/roles/create', 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/sessions', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/profile'];
$miss = [];
foreach ($PANEL as $u) {
    $h = G($own, $u);
    $s = lswitch($h);
    if ($own->status !== 200 || ! $s || array_keys($s['buttons']) !== ['en', 'it'] || $s['buttons'] !== ['en' => 'true', 'it' => 'false'] || $s['csrf'] === '' || html_lang($h) !== 'en') $miss[] = "$u ({$own->status}) " . json_encode($s['buttons'] ?? null) . ' lang=' . html_lang($h);
}
check('L01', 'Every panel page (' . count($PANEL) . ') carries the EN/IT switch, a POST form to rolewarden/profile/language with CSRF; EN pressed on an English page', $miss === [], implode(' | ', $miss));
$h = G($own, 'rolewarden/permissions');
$s = lswitch($h);
check('L01b', 'Switch buttons named in their own language (English, Italiano), lang attribute on each', $s && str_contains($s['html'], 'lang="en"') && str_contains($s['html'], 'lang="it"') && str_contains($s['html'], 'English') && str_contains($s['html'], 'Italiano'), $s['html'] ?? '');

$pl = c8('plain');
check('L02a', 'Plain user (role user, no permission) signs in', login8($pl, 'plain@v8b.test', OPW));
$miss = [];
foreach (['rolewarden/profile', 'rolewarden/sessions'] as $u) { $h = G($pl, $u); if ($pl->status !== 200 || ! lswitch($h)) $miss[] = "$u {$pl->status}"; }
check('L02', 'A user with no permission sees the switch on the pages open to them (profile, own sessions)', $miss === [], implode(' | ', $miss));

$au = c8('author');
check('L03a', 'Demo author signs in', login8($au, 'author@example.com', DPW));
$miss = [];
foreach (['demo', 'demo/create'] as $u) { $h = G($au, $u); if ($au->status !== 200 || ! lswitch($h)) $miss[] = "$u {$au->status} " . json_encode(lswitch($h)['buttons'] ?? null); }
check('L03', 'Demo pages (list, new document) carry the same switch for a demo user', $miss === [], implode(' | ', $miss));
$h = G($own, 'demo');
check('L03b', 'Demo list carries the switch for the super admin too', $own->status === 200 && lswitch($h) !== null);

// switch to Italian from the Permissions page, as a browser posts it (Referer = the page)
$from = BASE . 'index.php/rolewarden/permissions';
$before = lang_setting($OWNER);
lpost($own, 'it', $from, 'L04 it');
check('L04', 'POST it from the Permissions page redirects back to that page', in_array($own->status, [302, 303], true) && rtrim($loc($own), '/') === $from, "status {$own->status} location {$own->location}");
$h = $own->go($loc($own));
$s = lswitch($h);
check('L04b', 'Back on the page: h1 translated (Permessi), lang="it", IT pressed and EN not', h1($h) === L($IT, 'panel.permissions.indexTitle') && html_lang($h) === 'it' && ($s['buttons'] ?? []) === ['en' => 'false', 'it' => 'true'], h1($h) . ' ' . html_lang($h) . ' ' . json_encode($s['buttons'] ?? null));
check('L04c', 'Italian page: intro and "Granted by" column in Italian (Concesso da)', str_contains($h, 'Concesso da') && str_contains(text_of($h), 'I ruoli super admin li hanno tutti'), short(text_of($h), 300));
check('L04d', 'Italian page: the switch form itself is labelled in Italian (aria-label not "Language")', $s && ! str_contains($s['html'], 'aria-label="Language"'), $s['html'] ?? '');
$after = lang_setting($OWNER);
check('L05', 'The choice is saved in the profile (Settings, user context): it', $after !== $before && (bool) preg_grep('/=it$/', $after), json_encode([$before, $after]));
$h = G($own, 'rolewarden/profile');
$f = null; foreach (forms($h) as $ff) if (str_contains($ff['action'], 'profile/language') && array_key_exists('language', $ff['types']) && $ff['types']['language'] === 'select') $f = $ff;
check('L06', 'The profile\'s language list shows the same choice (Italiano selected)', ($f['fields']['language'] ?? null) === 'it', json_encode($f['fields'] ?? null));
$h = G($own, "rolewarden/roles/$R9");
check('L06b', 'Other pages follow: the role page in Italian with the Italian matrix instruction', html_lang($h) === 'it' && str_contains($h, L($IT, 'panel.roles.matrixHelp')), html_lang($h));
// persistence after a new sign-in
$own2 = c8('owner2');
login8($own2, 'owner@v8b.test', OPW);
$h = G($own2, 'rolewarden/users');
check('L07', 'New sign-in (fresh browser): panel in Italian, IT pressed', html_lang($h) === 'it' && (lswitch($h)['buttons'] ?? []) === ['en' => 'false', 'it' => 'true'] && h1($h) === L($IT, 'panel.users.indexTitle'), html_lang($h) . ' ' . h1($h));
// back to English from the users page
$from2 = BASE . 'index.php/rolewarden/users?q=v8b&page=1';
lpost($own2, 'en', $from2, 'L08 en');
check('L08', 'POST en from the users list (with a query string) returns to exactly that address', in_array($own2->status, [302, 303], true) && $loc($own2) === $from2, $own2->location);
$h = $own2->go($loc($own2));
check('L08b', 'Back in English: h1 Users, lang="en", EN pressed', h1($h) === 'Users' && html_lang($h) === 'en' && (lswitch($h)['buttons'] ?? []) === ['en' => 'true', 'it' => 'false'], h1($h) . ' ' . html_lang($h));
check('L08c', 'Profile list follows (English selected)', (function () use ($own2) { foreach (forms(G($own2, 'rolewarden/profile')) as $ff) if (($ff['types']['language'] ?? '') === 'select') return $ff['fields']['language'] === 'en'; return false; })());
$good = lang_setting($OWNER);
// invalid values
$bad = [];
foreach (['xx', 'EN', 'it;', '../en', 'it_IT', str_repeat('i', 300), '<script>'] as $v) {
    lpost($own2, $v, $from, "L09 $v");
    $st = $own2->status; $b = $own2->body; $after = lang_setting($OWNER);
    $h = G($own2, 'rolewarden/permissions');
    if ($st >= 500 || leaks_sql($b) || phperr($b) || $after !== $good || html_lang($h) !== 'en') $bad[] = "$v: status $st setting " . json_encode($after) . ' lang ' . html_lang($h);
}
check('L09', 'Invalid values (xx, EN, it;, ../en, it_IT, 300 chars, markup) are ignored: no 500, no SQL, choice unchanged, page still English', $bad === [], implode(' | ', $bad));
$own2->req('POST', 'rolewarden/profile/language', 'csrf_test_name=' . urlencode(csrf_of(G($own2, 'rolewarden/profile'))) . '&language[]=it', ['Referer: ' . $from]);
check('L09b', 'An array as value is ignored without error', $own2->status < 500 && lang_setting($OWNER) === $good && ! phperr($own2->body), "status {$own2->status}");
// without CSRF
lpost($own2, 'it', $from, 'L10 no csrf', false);
check('L10', 'POST without CSRF token refused: choice unchanged', lang_setting($OWNER) === $good && html_lang(G($own2, 'rolewarden/permissions')) === 'en', "status {$own2->status} " . json_encode(lang_setting($OWNER)));
lpost($own2, 'it', $from, 'L10b bad csrf', true, str_repeat('0', 32));
check('L10b', 'POST with a wrong CSRF token refused: choice unchanged', lang_setting($OWNER) === $good, "status {$own2->status}");
$own2->req('GET', 'rolewarden/profile/language?language=it');
check('L10c', 'GET with ?language=it changes nothing', lang_setting($OWNER) === $good, "status {$own2->status}");
// open redirect
$bad = [];
foreach (['http://evil.example.com/steal', 'https://evil.example.com/', '//evil.example.com/x', 'http://127.0.0.1:8071.evil.example.com/', 'http://127.0.0.1@evil.example.com/', 'javascript:alert(1)', 'http://127.0.0.1:9999/other-app'] as $ref) {
    lpost($own2, 'en', $ref, 'L11 ' . $ref);
    $l = $own2->location;
    $abs = $l === '' ? '' : abs_url($own2->url, $l);
    $host = parse_url($abs, PHP_URL_HOST) . ':' . parse_url($abs, PHP_URL_PORT);
    if ($own2->status >= 500 || ($l !== '' && ($host !== '127.0.0.1:8071' || preg_match('#^(//|javascript:)#i', $l) || str_contains($l, 'evil')))) $bad[] = "$ref -> {$own2->status} $l";
}
check('L11', 'No open redirect: a Referer on another host, port, scheme-relative or javascript: never becomes the redirect target', $bad === [], implode(' | ', $bad));
// Host header and X-Forwarded-Host on the GET before the switch: the redirect target stays on the application
$tok = csrf_of(G($own2, 'rolewarden/profile'));
$own2->req('GET', 'rolewarden/users', null, ['Host: evil.example.com']);
info('GET with Host: evil.example.com answered ' . $own2->status);
lpost($own2, 'en', null, 'L11b host', true, $tok);
check('L11b', 'A page fetched with a forged Host header just before the switch does not make the redirect leave the application', $own2->status < 500 && ! str_contains($own2->location, 'evil'), "status {$own2->status} {$own2->location}");
$own2->req('GET', 'rolewarden/users', null, ['X-Forwarded-Host: evil.example.com']);
lpost($own2, 'en', null, 'L11c xfh', true, $tok);
check('L11c', 'Same with X-Forwarded-Host', $own2->status < 500 && ! str_contains($own2->location, 'evil'), "status {$own2->status} {$own2->location}");
// two tabs: the switch used on the page in tab A while tab B loaded another page later
$ta = BASE . 'index.php/rolewarden/permissions';
$sA = lswitch(G($own2, $ta, 'tab A'));
info('hidden fields of the switch form on Permissions: ' . json_encode($sA['hidden'] ?? null));
G($own2, 'rolewarden/activity', 'tab B');
lpost($own2, 'en', $ta, 'L19 two tabs', true, $sA['csrf'] ?? '', $sA['hidden'] ?? []);
$l19 = $loc($own2);
info('two tabs: switch used on Permissions (tab A, Referer sent) after Activity loaded in tab B -> ' . $l19);
check('L19', 'Two tabs: the switch used on the page of tab A returns to that page (the browser sends it as Referer), not to the page last opened in tab B', rtrim($l19, '/') === $ta, $l19);
// D1 recheck: two tabs where tab B is another switch-carrying page, and the reverse order
$sB = lswitch(G($own2, 'rolewarden/users', 'tab B2 first'));
$sA2 = lswitch(G($own2, 'rolewarden/settings', 'tab A2 later'));
lpost($own2, 'en', BASE . 'index.php/rolewarden/users', 'L19b', true, $sB['csrf'] ?? '', $sB['hidden'] ?? []);
check('L19b', 'Two tabs, reverse order: the switch on the users list (loaded first) returns to the users list although Settings was loaded later', rtrim($loc($own2), '/') === BASE . 'index.php/rolewarden/users', $loc($own2));
// the form's own fields tampered by hand: never an external target
$hid = array_keys($sA['hidden'] ?? []);
$bad = []; $tried = 0;
foreach ($hid as $field) {
    foreach (['http://evil.example.com/steal', 'https://evil.example.com/', '//evil.example.com/x', '///evil.example.com/x', '/\\evil.example.com/x', '\\\\evil.example.com\\x', 'http://127.0.0.1:8071.evil.example.com/', 'http://127.0.0.1:8071@evil.example.com/', 'http://127.0.0.1:80719/', 'https://127.0.0.1:8071/', 'http:evil.example.com', ' http://evil.example.com/', "\thttp://evil.example.com/", 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,x', '/%2F%2Fevil.example.com', 'http://evil.example.com%2F@127.0.0.1:8071/', BASE . 'index.php/rolewarden/../../../evil'] as $v) {
        $tried++;
        $tok = csrf_of(G($own2, 'rolewarden/permissions'));
        $own2->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, $field => $v, 'language' => 'en']);
        seen($own2, "L20 $field=$v");
        $l = $own2->location;
        $abs = $l === '' ? '' : abs_url($own2->url, $l);
        $hp = strtolower((string) parse_url($abs, PHP_URL_HOST)) . ':' . parse_url($abs, PHP_URL_PORT);
        $safe = (str_starts_with($l, BASE) || (str_starts_with($l, '/') && ! str_starts_with($l, '//'))) && ! str_contains($l, chr(92)) && ! preg_match('/[\x00-\x20]/', $l);
        if ($own2->status >= 500 || ! in_array($own2->status, [302, 303], true) || ! $safe || $hp !== '127.0.0.1:8071') $bad[] = json_encode($v) . " -> {$own2->status} " . json_encode($l);
    }
}
check('L20', 'Hand-made values in the switch form\'s own field(s) ' . json_encode($hid) . " ($tried tries: other host, //host, same prefix with another host or port, other scheme, javascript:, encoded tricks): the redirect never leaves the application, no 500", $hid !== [] && $bad === [], $hid === [] ? 'no hidden field in the switch form' : implode(' | ', array_slice($bad, 0, 6)));
$okf = $hid ? $hid[0] : '';
if ($okf !== '') {
    $tok = csrf_of(G($own2, 'rolewarden/permissions'));
    $own2->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, $okf => BASE . 'index.php/rolewarden/roles', 'language' => 'en']);
    info("L20b local target in the field -> {$own2->status} {$own2->location}");
    check('L20b', 'A local address of the application in the same field is followed (the field is used, not ignored)', rtrim($loc($own2), '/') === BASE . 'index.php/rolewarden/roles', $own2->location);
    $tok = csrf_of(G($own2, 'rolewarden/permissions'));
    $own2->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, $okf => 'http://evil.example.com/', 'language' => 'it'], ['Referer: http://evil.example.com/']);
    check('L20c', 'External value in the field and external Referer together: redirect inside the application, the language is still saved', str_starts_with($loc($own2), BASE) && (bool) preg_grep('/=it$/', lang_setting($OWNER)), "{$own2->status} {$own2->location}");
    // D3 recheck: hand-made query strings inside an internal back address
    $U = BASE . 'index.php/rolewarden/users';
    $qbad = []; $qinfo = [];
    foreach (['?x=//evil.example.com', '?next=http://evil.example.com/&q=a', '?x=%0d%0aX-Injected:%201', "?x=\r\nX-Injected: 1", '?x=%0d%0aLocation:%20http://evil.example.com/', '#//evil.example.com', '?q=%22%3E%3Cscript%3Ealert(1)%3C/script%3E', '?q=' . str_repeat('a', 3000)] as $suffix) {
        $tok = csrf_of(G($own2, 'rolewarden/permissions'));
        $own2->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, $okf => $U . $suffix, 'language' => 'en']);
        seen($own2, 'L20d ' . substr($suffix, 0, 40));
        $l = $own2->location; $abs = $l === '' ? '' : abs_url($own2->url, $l);
        $hp = strtolower((string) parse_url($abs, PHP_URL_HOST)) . ':' . parse_url($abs, PHP_URL_PORT);
        $inj = isset($own2->headers['x-injected']);
        $qinfo[] = json_encode($suffix) . ' -> ' . $own2->status . ' ' . json_encode(substr($l, 0, 160));
        if ($own2->status >= 500 || ! in_array($own2->status, [302, 303], true) || $hp !== '127.0.0.1:8071' || $inj || preg_match('/[\x00-\x1f]/', $l) || ! str_starts_with($l, BASE)) $qbad[] = end($qinfo);
    }
    info('L20d targets: ' . implode(' | ', $qinfo));
    check('L20d', 'Internal back address with hand-made query strings (?x=//evil, ?next=http://evil, CR/LF raw and %0d%0a, #//evil, markup, 3000 chars): redirect stays on the application, no injected header, no 500', $qbad === [], implode(' | ', $qbad));
    // D4 recheck: raw control characters (decoded value) in the query and in the path of an internal back address
    $cbad = []; $cprev = []; $cinfo = [];
    $ctl = ['CR' => "\r", 'LF' => "\n", 'CRLF' => "\r\n", 'NUL' => "\0", 'TAB' => "\t", 'DEL' => "\x7f"];
    foreach ($ctl as $cn => $c) {
        foreach (['query' => $U . '?x=a' . $c . 'X-Injected: 1', 'path' => BASE . 'index.php/rolewarden/us' . $c . 'ers', 'end' => $U . $c] as $where => $val) {
            $tok = csrf_of(G($own2, 'rolewarden/permissions'));
            $own2->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, $okf => $val, 'language' => 'it']);
            seen($own2, "L20e $cn $where");
            $l = $own2->location; $saved = (bool) preg_grep('/=it$/', lang_setting($OWNER));
            $cinfo[] = "$cn/$where -> {$own2->status} " . json_encode($l);
            if ($own2->status >= 500 || ! in_array($own2->status, [302, 303], true) || ! str_starts_with($l, BASE) || preg_match('/[\x00-\x1f\x7f]/', $l) || isset($own2->headers['x-injected']) || ! $saved) $cbad[] = end($cinfo) . ($saved ? '' : ' (language not saved)');
            if (rtrim($l, '/') !== BASE . 'index.php/rolewarden/permissions') $cprev[] = end($cinfo);
            lpost($own2, 'en', BASE . 'index.php/rolewarden/permissions', 'L20e reset');
        }
    }
    info('L20e targets: ' . implode(' | ', $cinfo));
    check('L20e', 'back with raw CR, LF, CRLF, NUL, TAB or DEL in query, path or at the end (18 cases): no 500, redirect inside the application with no control character, no injected header, language saved', $cbad === [], implode(' | ', $cbad));
    check('L20f', 'In those 18 cases the field is ignored and the switch returns to the page it was used on (Permissions, the last page loaded)', $cprev === [], implode(' | ', $cprev));
    lpost($own2, 'en', BASE . 'index.php/rolewarden/permissions', 'L20 back');
}
lpost($own2, 'en', null, 'L12 no referer');
check('L12', 'Without Referer the switch still answers with a redirect inside the application', in_array($own2->status, [302, 303], true) && str_starts_with(abs_url($own2->url, $own2->location), BASE), "status {$own2->status} {$own2->location}");
// guest
$g = c8('guest');
$tok = csrf_of(G($g, 'login'));
$g->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it'], ['Referer: ' . $from]);
check('L13', 'Guest POST: sent to sign in, nothing saved for anybody', in_array($g->status, [302, 303], true) && str_contains($g->location, 'login') && (int) q1("SELECT COUNT(*) FROM settings WHERE value='it'") === 0, "status {$g->status} {$g->location}");
// "Same as the application" from the profile
lpost($own2, 'it', $from, 'L14 it again');
lpost($own2, '', BASE . 'index.php/rolewarden/profile', 'L14 reset');
$h = G($own2, 'rolewarden/permissions');
check('L14', 'Empty value (the profile\'s "Same as the application") goes back to the application language (English): page English, EN pressed', html_lang($h) === 'en' && (lswitch($h)['buttons'] ?? []) === ['en' => 'true', 'it' => 'false'], html_lang($h) . ' ' . json_encode(lang_setting($OWNER)));
// demo: switch from a demo page returns to it
lpost($au, 'it', BASE . 'index.php/demo', 'L15 demo it');
$l = $loc($au);
$h = $au->go($l);
check('L15', 'Demo: switching to it from the demo list returns to the demo list, now Italian (lang="it", h1 changed, IT pressed)', rtrim($l, '/') === BASE . 'index.php/demo' && html_lang($h) === 'it' && h1($h) !== 'Documents' && (lswitch($h)['buttons'] ?? []) === ['en' => 'false', 'it' => 'true'], "$l " . html_lang($h) . ' ' . h1($h));
info('demo h1 in Italian: ' . h1($h));
lpost($au, 'en', BASE . 'index.php/demo', 'L15b demo en');
$h = $au->go($loc($au));
check('L15b', 'Demo: back to en, list in English', html_lang($h) === 'en' && h1($h) === 'Documents', h1($h));
// plain user switches; his choice does not touch the owner's
lpost($pl, 'it', BASE . 'index.php/rolewarden/profile', 'L16 plain it');
$h = $pl->go($loc($pl));
check('L16', 'Plain user: switch to it from the profile, back on the profile in Italian; the owner stays English', html_lang($h) === 'it' && html_lang(G($own2, 'rolewarden/users')) === 'en', html_lang($h));
// G07 of V2/V5 now fails: view-only settings shows submit buttons. Are they only the top-bar switches?
q("INSERT INTO acl_roles (slug, name, description, is_system, is_super_admin, created_at, updated_at) VALUES ('l-setview','Settings viewer','',0,0,NOW(),NOW())");
q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [role_id('l-setview'), perm_id('settings.view')]);
$sv = make_owner('setview@v8b.test', OPW, 'v8bsetview', 'l-setview');
sp8($APP, ['cache:clear']);
$cs = c8('setview');
login8($cs, 'setview@v8b.test', OPW);
$h = G($cs, 'rolewarden/settings');
$other = [];
foreach (forms($h) as $f) if (preg_match('/type="submit"|<button(?![^>]*type="button")/', $f['html']) && ! preg_match('#/(profile/language|appearance/mode|logout|rolewarden/sidebar)$#', $f['action'])) $other[] = $f['action'];
check('L17', 'View-only Settings (settings.view only): the only submit buttons are the top-bar language and light/dark switches and, from v1.1, the sidebar collapse toggle (explains G07/G07v5 of the regression)', $cs->status === 200 && $other === [] && lswitch($h) !== null, "status {$cs->status} " . json_encode($other));

// another language added as documented: app/Language/fr/RoleWarden.php
@mkdir("$APP/app/Language/fr", 0777, true);
$fr = $EN; $fr['panel']['permissions']['indexTitle'] = 'Autorisations';
file_put_contents("$APP/app/Language/fr/RoleWarden.php", "<?php\nreturn " . var_export($fr, true) . ";\n");
$h = G($own2, 'rolewarden/permissions');
$s = lswitch($h);
check('L18', 'A third language added as documented (app/Language/fr/RoleWarden.php) appears in the top-bar switch', $s && isset($s['buttons']['fr']), json_encode($s['buttons'] ?? null));
lpost($own2, 'fr', BASE . 'index.php/rolewarden/permissions', 'L18 fr');
$h = $own2->go($loc($own2));
check('L18b', 'Switching to fr shows the French line and marks FR pressed', h1($h) === 'Autorisations' && (lswitch($h)['buttons']['fr'] ?? '') === 'true', h1($h) . ' ' . json_encode(lswitch($h)['buttons'] ?? null));
lpost($own2, 'en', BASE . 'index.php/rolewarden/permissions', 'L18 back');
@unlink("$APP/app/Language/fr/RoleWarden.php"); @rmdir("$APP/app/Language/fr");

// single language: the documentation does not say how to make only one language available
$appcfg = (string) file_get_contents("$APP/app/Config/App.php");
info('Config\\App of the test application: supportedLocales = ' . (preg_match('/supportedLocales\s*=\s*([^;]+);/', $appcfg, $m) ? $m[1] : '?') . ', negotiateLocale = ' . (preg_match('/negotiateLocale\s*=\s*([^;]+);/', $appcfg, $m2) ? $m2[1] : '?'));
file_put_contents("$APP/app/Config/App.php", preg_replace('/(public\s+array\s+\$supportedLocales\s*=\s*)\[[^\]]*\]/', "\$1['en']", $appcfg));
$h = G($own2, 'rolewarden/permissions');
$one = lswitch($h);
amb('LA1', 'How a single available language is configured', 'README and documentation/views.html say languages are English and Italian, a third is added with a file in app/Language; nothing says how to have only one, so "with one language the switch does not appear" cannot be set up as documented. Tried Config\\App::$supportedLocales = [\'en\'] in the host application: switch ' . ($one ? 'still shown with ' . json_encode(array_keys($one['buttons'])) : 'hidden') . '. Removing src/Language/it from the module would mean editing the module (not allowed to the buyer).');
file_put_contents("$APP/app/Config/App.php", $appcfg);
server_stop();
