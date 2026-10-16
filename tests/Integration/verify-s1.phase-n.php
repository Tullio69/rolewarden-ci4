<?php
// Phase N (S1, point 1): one language only. documentation/configuration.html ("languages: Languages the panel offers,
// for example ['en']; empty offers every language it has a file for") and views.html#languages ("List the languages
// to offer in app/Config/RoleWarden.php. With a single one the language switch disappears from the top bar and from
// the profile, and every page and email is in that language, whatever the application's locale"); SPEC, decisions of
// 2026-10-16. The class is written exactly as the documentation shows it, in the application copy only.
function set_languages(string $app, ?array $langs): void
{
    $f = "$app/app/Config/RoleWarden.php";
    if ($langs === null) { @unlink($f); return; }
    file_put_contents($f, "<?php\n\nnamespace Config;\n\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public array \$languages = " . var_export($langs, true) . ";\n}\n");
    $f2 = file_get_contents($f);
    $f2 = preg_replace("/array \(\s*\)/", '[]', $f2);
    $f2 = preg_replace("/array \(\s*0 => '(\w+)',\s*\)/", "['$1']", $f2);
    file_put_contents($f, $f2);
}
function set_app_locale(string $app, string $loc): void
{
    $f = "$app/app/Config/App.php";
    $s = file_get_contents($f);
    $s = preg_replace("/public string \\\$defaultLocale = '[^']*';/", "public string \$defaultLocale = '$loc';", $s, 1, $n1);
    $s = preg_replace("/public array \\\$supportedLocales = \[[^\]]*\];/", "public array \$supportedLocales = ['en', 'it'];", $s, 1, $n2);
    if ($n1 !== 1 || $n2 !== 1) exit("ABORT: App.php locale lines not found\n");
    file_put_contents($f, $s);
}
function lswitchN(string $h): ?array
{
    foreach (forms($h) as $f) if (preg_match('#/rolewarden/profile/language$#', $f['action']) && str_contains($f['html'], 'aria-pressed')) return $f;
    return null;
}
function lselectN(string $h): ?array
{
    foreach (forms($h) as $f) if (($f['types']['language'] ?? '') === 'select') return $f;
    return null;
}
/** Every language form or language button on the page (top bar or profile), whatever its shape. */
function lang_controls(string $h): array
{
    $o = [];
    foreach (forms($h) as $f) if (str_contains($f['action'], 'profile/language') || array_key_exists('language', $f['types'])) $o[] = $f['action'] . ' ' . json_encode($f['types']);
    return $o;
}
function lang_settingN(int $uid): array
{
    return array_map(fn ($r) => $r['class'] . '.' . $r['key'] . '=' . $r['value'], q("SELECT class, `key`, value FROM settings WHERE context LIKE ? AND (`key` LIKE '%ocale%' OR `key` LIKE '%anguage%')", ['%:' . $uid]));
}
function set_pw(Client $by, int $target, string $pw): void
{
    $h = G($by, "rolewarden/users/$target/edit", "edit user $target");
    foreach (forms($h) as $f) if (array_key_exists('password', $f['types'])) {
        $v = array_replace($f['fields'], ['password' => $pw]);
        foreach ($f['types'] as $k => $t) if (str_contains($k, 'confirm')) $v[$k] = $pw;
        P($by, $f['action'] ?: "rolewarden/users/$target", $v, "set password $target");
        return;
    }
    info("set_pw: no password form for $target");
}
function wait_mailN(int $n, int $tries = 40): array
{
    for ($i = 0; $i < $tries; $i++) { $m = mailpit('GET', 'messages'); if (($m['messages_count'] ?? 0) >= $n) break; usleep(250000); }
    usleep(500000);
    return mailpit('GET', 'messages')['messages'] ?? [];
}
/** Language of a security email from its subject and body, against the module's language files. */
function mail_lang(array $m): string
{
    global $EN, $IT;
    $full = mailpit('GET', 'message/' . $m['ID']);
    $body = (string) ($full['Text'] ?? '') . ' ' . strip_tags((string) ($full['HTML'] ?? ''));
    $hit = ['en' => 0, 'it' => 0];
    foreach (['en' => $EN, 'it' => $IT] as $l => $lang) {
        foreach (($lang['mail'] ?? []) as $k => $part) {
            if (! is_array($part) || ! isset($part['subject'])) continue;
            $head = trim(explode('{', $part['subject'])[0]);
            if ($head !== '' && str_starts_with($m['Subject'], $head)) $hit[$l] += 10;
            if (isset($part['title']) && str_contains($body, $part['title'])) $hit[$l] += 1;
        }
        if (str_contains($body, (string) ($lang['mail']['footer'] ?? "\0"))) $hit[$l] += 1;
    }
    return $hit['en'] > $hit['it'] ? 'en' : ($hit['it'] > $hit['en'] ? 'it' : '?');
}
/** Password change by the owner, a sign-in, then lockAttempts failures: every email captured, with its language. */
function mail_round(Client $own, int $uid, string $email, string $pw, string $tag): array
{
    mailpit('DELETE', 'messages');
    set_pw($own, $uid, $pw);
    $x = c8("$tag-in"); login8($x, $email, $pw);
    for ($i = 0; $i < 6; $i++) { $y = c8("$tag-f$i"); login8($y, $email, 'Wrong-Pass-' . $i . 'xyz'); }
    $ms = wait_mailN(3);
    return array_map(fn ($m) => [$m['To'][0]['Address'] ?? '', $m['Subject'], mail_lang($m)], $ms);
}

$ENVN = getenv('RW_V8B_ENV') ?: 'development';
$AN = app_copy('appN', $MODFULL, true, $ENVN);
// the demo answers only once installed: carry over what demo:install left in writable/ of the main copy
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$APP/writable", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $it) {
    $rel = substr(str_replace('\\', '/', $it->getPathname()), strlen("$APP/writable") + 1);
    if (preg_match('#^(logs|session|debugbar|cache)(/|$)#', $rel)) continue;
    $dst = "$AN/writable/$rel";
    if ($it->isDir()) { @mkdir($dst, 0777, true); continue; }
    if (! is_file($dst)) { @mkdir(dirname($dst), 0777, true); copy($it->getPathname(), $dst); info("writable carried to appN: $rel"); }
}
$langstate = fn (string $stage) => info("language settings at $stage: owner " . json_encode(lang_settingN($GLOBALS['OWNER'])) . ', itu ' . json_encode(lang_settingN($GLOBALS['ITU'])) . ', enu ' . json_encode(lang_settingN($GLOBALS['ENU'])) . ', nou ' . json_encode(lang_settingN($GLOBALS['NOU'])));
$ITU = make_owner('itu@s1.test', OPW, 's1itu', 'user');
$ENU = make_owner('enu@s1.test', OPW, 's1enu', 'user');
$NOU = make_owner('nou@s1.test', OPW, 's1nou', 'user');
$ENM = make_owner('enm@s1.test', OPW, 's1enm', 'user'); // mail round of step 3 only, so that enu is never locked
$PANELN = ['rolewarden/users', "rolewarden/users/$PLAIN", "rolewarden/users/$PLAIN/edit", 'rolewarden/users/create', 'rolewarden/users/import', 'rolewarden/roles', 'rolewarden/roles/' . role_id('admin'), 'rolewarden/roles/' . role_id('admin') . '/edit', 'rolewarden/roles/create', 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/sessions', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/profile', 'demo', 'demo/create'];
$ENH1 = ['rolewarden/users' => L($EN, 'panel.users.indexTitle'), 'rolewarden/permissions' => L($EN, 'panel.permissions.indexTitle')];
$ITH1 = ['rolewarden/users' => L($IT, 'panel.users.indexTitle'), 'rolewarden/permissions' => L($IT, 'panel.permissions.indexTitle')];
info('h1 reference: ' . json_encode([$ENH1, $ITH1], JSON_UNESCAPED_UNICODE));

/** Survey of the panel for one client: [lang attributes, pages with a language control, h1 of users/permissions]. */
$survey = function (Client $c) use ($PANELN): array {
    $langs = []; $ctl = []; $h1 = []; $bad = [];
    foreach ($PANELN as $u) {
        $h = G($c, $u, "N survey {$c->name} $u");
        if ($c->status !== 200) { $bad[] = "$u {$c->status}"; continue; }
        $langs[html_lang($h)][] = $u;
        if (lang_controls($h)) $ctl[] = $u;
        if (isset($GLOBALS['ENH1'][$u])) $h1[$u] = h1($h);
    }
    return ['lang' => array_map('count', $langs), 'controls' => $ctl, 'h1' => $h1, 'bad' => $bad, 'langPages' => $langs];
};

// ---- 1. default (no class in the application), app locale it: the switch and Italian for a user who has not chosen
set_app_locale($AN, 'it');
set_languages($AN, null);
$cN = serve8($AN, 'N1');
$own = c8('N-own'); check('N00', 'Owner signs in (application copy with Config\App::$defaultLocale = it)', login8($own, 'owner@v8b.test', OPW));
foreach ([[$ITU, 'itu@s1.test', 'it'], [$ENU, 'enu@s1.test', 'en'], [$ENM, 'enm@s1.test', 'en']] as [$id, $em, $l]) {
    $c = c8("N-pick-$l"); login8($c, $em, OPW);
    $f = lselectN(G($c, 'rolewarden/profile'));
    if ($f) P($c, $f['action'], array_replace($f['fields'], ['language' => $l]), "N $em picks $l");
}
$own0 = $survey($own);
$nou = c8('N-nou'); login8($nou, 'nou@s1.test', OPW);
$hn = G($nou, 'rolewarden/profile');
check('N01', 'Sensitivity, no $languages class: owner (no choice) follows the app locale it on every page; switch on every page; the two users chose it and en', $own0['lang'] === ['it' => count($PANELN)] && count($own0['controls']) === count($PANELN) && html_lang($hn) === 'it' && lang_settingN($ITU) !== [] && lang_settingN($ENU) !== [], json_encode([$own0, html_lang($hn), lang_settingN($ITU), lang_settingN($ENU)], JSON_UNESCAPED_UNICODE));
// owner picks Italian explicitly (a saved choice that ['en'] must override)
$f = lselectN(G($own, 'rolewarden/profile'));
P($own, $f['action'], array_replace($f['fields'], ['language' => 'it']), 'N owner picks it');
$OWNSET = lang_settingN($OWNER);
check('N01b', 'Owner\'s choice "it" is saved in the user settings', (bool) preg_grep('/=it$/', $OWNSET), json_encode($OWNSET));
server_stop();

$langstate('// ---- 2.');
// ---- 2. $languages = ['en'], app locale it
set_languages($AN, ['en']);
info('app/Config/RoleWarden.php written: ' . short(file_get_contents("$AN/app/Config/RoleWarden.php"), 300));
$cN = serve8($AN, 'N2');
$own = c8('N-own2'); login8($own, 'owner@v8b.test', OPW);
$s = $survey($own);
check('N02', "['en'], app locale it, owner who chose Italian: every page (" . count($PANELN) . ', panel and demo) in English, html lang="en"', $s['bad'] === [] && $s['lang'] === ['en' => count($PANELN)] && $s['h1'] === $ENH1, json_encode($s, JSON_UNESCAPED_UNICODE));
check('N03', "['en']: no language switch in the top bar and no language list in the profile, on any page", $s['controls'] === [], json_encode($s['controls']));
$hp = G($own, 'rolewarden/profile', 'N2 profile');
check('N03b', "['en']: the profile still opens (200) and keeps its other forms (password, mode)", $own->status === 200 && count(forms($hp)) >= 2 && lselectN($hp) === null, count(forms($hp)) . ' forms');
$nou = c8('N-nou2'); login8($nou, 'nou@s1.test', OPW);
$sn = $survey($nou);
info('nou survey (no permission: 302 on panel pages expected): ' . json_encode(['lang' => $sn['lang'], 'bad' => count($sn['bad'])]));
$hn = G($nou, 'rolewarden/profile');
check('N04', "['en']: a user who never chose a language sees the profile in English with the app locale it, no language control", html_lang($hn) === 'en' && lang_controls($hn) === [], html_lang($hn) . ' ' . json_encode(lang_controls($hn)));
$itc = c8('N-itu2'); login8($itc, 'itu@s1.test', OPW);
$hi = G($itc, 'rolewarden/profile');
check('N04b', "['en']: the user who chose it sees their profile and sessions in English", html_lang($hi) === 'en' && html_lang(G($itc, 'rolewarden/sessions')) === 'en', html_lang($hi));
// POST language=it, built by hand (no form on the page any more)
$beforeN = lang_settingN($NOU); $beforeO = lang_settingN($OWNER);
$tok = csrf_of(G($nou, 'rolewarden/profile'));
$nou->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it'], ['Referer: ' . BASE . 'index.php/rolewarden/profile']);
seen($nou, 'N05 POST it');
$st = $nou->status; $b = $nou->body; $loc = $nou->location;
$after = G($nou, 'rolewarden/profile');
check('N05', "['en']: POST language=it is ignored without errors (no 4xx/5xx other than a redirect, no PHP or SQL text), the page stays English", $st < 400 && ! phperr($b) && ! leaks_sql($b) && html_lang($after) === 'en', "status $st location $loc lang " . html_lang($after));
check('N05b', "['en']: the ignored POST stores nothing for the user", lang_settingN($NOU) === $beforeN, json_encode([$beforeN, lang_settingN($NOU)]));
$tok = csrf_of(G($own, 'rolewarden/profile'));
$own->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it', 'back' => BASE . 'index.php/rolewarden/users'], ['Referer: ' . BASE . 'index.php/rolewarden/users']);
seen($own, 'N05c POST it owner');
check('N05c', "['en']: the same POST from the owner (with back field) gives no error and the panel stays English", $own->status < 400 && html_lang(G($own, 'rolewarden/users')) === 'en', "status {$own->status}");
// emails
$mr = mail_round($own, $ITU, 'itu@s1.test', OPW . 'n2', 'N2m');
info('N2 emails: ' . json_encode($mr, JSON_UNESCAPED_UNICODE));
$toItu = array_values(array_filter($mr, fn ($m) => $m[0] === 'itu@s1.test'));
check('N06', "['en'], app locale it: every security email to the user who chose Italian is in English (password changed, sign-in, too many failures: " . count($toItu) . ')', count($toItu) >= 2 && ! array_filter($toItu, fn ($m) => $m[2] !== 'en'), json_encode($mr, JSON_UNESCAPED_UNICODE));
check('N06b', "['en']: every other email of the round (alert holders) is in English too", ! array_filter($mr, fn ($m) => $m[2] !== 'en'), json_encode($mr, JSON_UNESCAPED_UNICODE));
mailpit('DELETE', 'messages');
server_stop();

$langstate('// ---- 3.');
// ---- 3. $languages = ['it'], app locale en
set_app_locale($AN, 'en');
set_languages($AN, ['it']);
q('DELETE FROM auth_logins WHERE identifier=?', ['itu@s1.test']);
$cN = serve8($AN, 'N3');
$own = c8('N-own3'); login8($own, 'owner@v8b.test', OPW);
// owner switches to en from the profile first? no form: build it by hand later; owner keeps "it"
$enc = c8('N-enu3'); login8($enc, 'enu@s1.test', OPW);
$he = G($enc, 'rolewarden/profile');
check('N07', "['it'], app locale en: the user who chose English sees profile and sessions in Italian, no language control", html_lang($he) === 'it' && lang_controls($he) === [] && html_lang(G($enc, 'rolewarden/sessions')) === 'it', html_lang($he) . json_encode(lang_controls($he)));
$tok = csrf_of($he);
$enc->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'en'], ['Referer: ' . BASE . 'index.php/rolewarden/profile']);
seen($enc, 'N07b POST en');
check('N07b', "['it']: POST language=en ignored without errors, page stays Italian", $enc->status < 400 && ! phperr($enc->body) && html_lang(G($enc, 'rolewarden/profile')) === 'it', "status {$enc->status}");
// owner: set "en" in the database as if chosen earlier, then survey
$s = $survey($own);
check('N08', "['it']: every page (" . count($PANELN) . ', panel and demo) in Italian for the owner, no language control anywhere', $s['bad'] === [] && $s['lang'] === ['it' => count($PANELN)] && $s['controls'] === [] && $s['h1'] === $ITH1, json_encode($s, JSON_UNESCAPED_UNICODE));
$nou = c8('N-nou3'); login8($nou, 'nou@s1.test', OPW);
check('N08b', "['it']: a user who never chose sees Italian with the app locale en", html_lang(G($nou, 'rolewarden/profile')) === 'it');
$mr = mail_round($own, $ENM, 'enm@s1.test', OPW . 'n3', 'N3m');
info('N3 emails: ' . json_encode($mr, JSON_UNESCAPED_UNICODE));
$toEnu = array_values(array_filter($mr, fn ($m) => $m[0] === 'enm@s1.test'));
check('N09', "['it'], app locale en: every security email to the user who chose English is in Italian (" . count($toEnu) . ')', count($toEnu) >= 2 && ! array_filter($toEnu, fn ($m) => $m[2] !== 'it'), json_encode($mr, JSON_UNESCAPED_UNICODE));
check('N09b', "['it']: every other email of the round is in Italian too", ! array_filter($mr, fn ($m) => $m[2] !== 'it'), json_encode($mr, JSON_UNESCAPED_UNICODE));
mailpit('DELETE', 'messages');
server_stop();

$langstate('// ---- 4.');
// ---- 4. $languages = [] (explicit), app locale en: behaviour as before
set_languages($AN, []);
q('DELETE FROM auth_logins WHERE identifier IN (?,?)', ['itu@s1.test', 'enu@s1.test']);
$cN = serve8($AN, 'N4');
$own = c8('N-own4'); login8($own, 'owner@v8b.test', OPW);
$s = $survey($own);
$sw = lswitchN(G($own, 'rolewarden/permissions'));
$sel = lselectN(G($own, 'rolewarden/profile'));
preg_match_all('/<option[^>]*value="([^"]*)"/', $sel['html'] ?? '', $opts);
check('N10', '[]: owner (chose it) sees every page in Italian, the EN/IT switch on every page, the profile list offers same-as-app, en, it', $s['lang'] === ['it' => count($PANELN)] && count($s['controls']) === count($PANELN) && $sw && str_contains($sw['html'], 'value="en"') && str_contains($sw['html'], 'value="it"') && $opts[1] === ['', 'en', 'it'], json_encode([$s['lang'], count($s['controls']), $opts[1] ?? null]));
$enc = c8('N-enu4'); login8($enc, 'enu@s1.test', OPW);
$nou = c8('N-nou4'); login8($nou, 'nou@s1.test', OPW);
$l1 = html_lang(G($enc, 'rolewarden/profile')); $l2 = html_lang(G($nou, 'rolewarden/profile'));
check('N10b', '[]: the user who chose en sees English, the user who never chose follows the app locale (en)', $l1 === 'en' && $l2 === 'en', json_encode([$l1, $l2, $enc->status, $nou->status]));
// switch works again
$tok = csrf_of(G($nou, 'rolewarden/profile'));
$nou->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it'], ['Referer: ' . BASE . 'index.php/rolewarden/profile']);
seen($nou, 'N11 POST it');
check('N11', '[]: POST language=it from the user who never chose is saved and the profile turns Italian', (bool) preg_grep('/=it$/', lang_settingN($NOU)) && html_lang(G($nou, 'rolewarden/profile')) === 'it', json_encode(lang_settingN($NOU)));
$mr = mail_round($own, $ITU, 'itu@s1.test', OPW . 'n4', 'N4m');
info('N4 emails: ' . json_encode($mr, JSON_UNESCAPED_UNICODE));
$toItu = array_values(array_filter($mr, fn ($m) => $m[0] === 'itu@s1.test'));
check('N12', '[]: security emails in the recipient\'s language again (the user who chose it gets Italian)', count($toItu) >= 2 && ! array_filter($toItu, fn ($m) => $m[2] !== 'it'), json_encode($mr, JSON_UNESCAPED_UNICODE));
mailpit('DELETE', 'messages');
server_stop();
$langstate('set_language');
set_languages($AN, null);
q('DELETE FROM auth_logins WHERE identifier IN (?,?)', ['itu@s1.test', 'enu@s1.test']);
// back to the owner's English for the following phases
q("DELETE FROM settings WHERE context LIKE ? AND (`key` LIKE '%ocale%' OR `key` LIKE '%anguage%')", ['%:' . $OWNER]);
