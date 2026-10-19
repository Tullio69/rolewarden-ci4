<?php
// U1 phase LANG (point 7): every new string of U1 exists in English and Italian: the Italian panel shows no new English
// text, apart from the style names Paper, Ink, Charcoal. Compared position by position on the served HTML.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== LANG: English and Italian\n";
serve8($APP, 'LANG');
$S0 = count($GLOBALS['SEEN']);
const KEEP = ['Paper', 'Ink', 'Charcoal', 'RoleWarden'];
function norm(string $s): string { return trim(preg_replace(['/\d+(:\d+)?/', '/\s+/u'], ['#', ' '], $s)); }
function row_strings(string $h, string $id, string $p): array
{
    $xp = dom($h); $o = [];
    $sel = $xp->query("//*[@id='$id']")->item(0);
    if (! $sel) return [];
    $tr = $xp->query('ancestor::tr[1]', $sel)->item(0);
    if ($tr) { foreach ($xp->query('.//label', $tr) as $i => $n) $o["$p-label$i"] = trim($n->textContent); foreach ($xp->query('.//small', $tr) as $i => $n) $o["$p-help$i"] = trim($n->textContent); }
    foreach ($xp->query('.//option', $sel) as $i => $n) $o["$p-opt$i"] = trim($n->textContent);
    $sec = $xp->query('preceding::*[contains(@class,"rw-section-title")][1]//h2', $sel)->item(0);
    if ($sec) $o["$p-section"] = trim($sec->textContent);
    return $o;
}
function ui_strings(Client $c): array
{
    $o = [];
    $s = side(G($c, 'rolewarden/users', 'lang users'));
    $o['aside'] = (string) $s['aria'];
    foreach ($s['groups'] as $i => $g) $o["group$i"] = $g['label'];
    foreach ($s['items'] as $p => $it) { $o["item:$p"] = $it['text']; if ($it['title'] !== '') $o["title:$p"] = $it['title']; if ($it['count'] !== null && ! ctype_digit($it['count'])) $o["count:$p"] = norm($it['count']); }
    $o['tog-aria'] = (string) $s['toggle']['btnAria']; $o['tog-title'] = (string) $s['toggle']['btnTitle'];
    if ($s['today']) {
        $xp = dom($c->body);
        $lab = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," rw-today ")]//*[contains(@class,"rw-side-label")]')->item(0);
        $o['today-label'] = $lab ? trim($lab->textContent) : '';
        $sm = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," rw-today ")]//small')->item(0);
        $o['today-note'] = norm($sm ? $sm->textContent : '');
        $o['today-svg'] = norm((string) $s['today']['svgAria']);
    }
    // collapsed: the toggle's other label
    P($c, $s['toggle']['action'], $s['toggle']['fields'], 'lang collapse');
    $r = side(G($c, 'rolewarden/users', 'lang rail'));
    $o['rail-tog-aria'] = (string) $r['toggle']['btnAria']; $o['rail-tog-title'] = (string) $r['toggle']['btnTitle'];
    P($c, $r['toggle']['action'], $r['toggle']['fields'], 'lang expand');
    $o += row_strings(G($c, 'rolewarden/settings', 'lang settings'), 'rw-sidebar_today', 'settings');
    $o += row_strings(G($c, 'rolewarden/appearance', 'lang appearance'), 'rw-sidebar', 'appearance');
    return $o;
}
function set_lang(Client $c, string $l): void { postform($c, 'rolewarden/profile', '#profile/language$#', ['language' => $l], "lang $l"); }
$own = c8('l-owner'); login8($own, 'owner@v8b.test', OPW);
// a counter that needs attention, so its words are compared too
G($own, 'rolewarden/activity', 'lang owner opens log'); sleep(1);
login8(c8('l-fail'), 'ghost@example.com', 'wrong-Password-1');
set_lang($own, 'en');
$en = ui_strings($own);
set_lang($own, 'it');
$it = ui_strings($own);
$hIt = '';
// the Activity screen is left out of L-2: its Subject column prints the label stored with each entry when it was written
// (an entry written by an English-speaking admin keeps "Settings"), which is 1.0 data, not a string of U1; see L-4
foreach (['rolewarden/users', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/profile'] as $u) { $hh = G($own, $u, "lang it $u"); $hIt .= ' ' . text_of($hh); foreach (dom($hh)->query('//@aria-label|//@title') as $a) $hIt .= ' ' . $a->nodeValue; }
// fresh entries for L-4: the admin switches Today off and on and sets the style Ink then Paper, right now
$ladm = c8('l-admin'); login8($ladm, 'admin@v8b.test', OPW);
settings_save($ladm, ['sidebar_today' => '0'], 'lang L-4 off'); settings_save($ladm, ['sidebar_today' => '1'], 'lang L-4 on');
theme_save($ladm, ['base' => 'console', 'sidebar' => 'ink'], 'lang L-4 ink'); theme_save($ladm, ['base' => 'console', 'sidebar' => ''], 'lang L-4 paper');
@unlink(theme_file_u1());
$actIt = text_of(G($own, 'rolewarden/activity', 'lang it activity'));
set_lang($own, 'en');
$actEn = text_of(G($own, 'rolewarden/activity', 'lang en activity'));
info('EN ' . json_encode($en, JSON_UNESCAPED_UNICODE));
info('IT ' . json_encode($it, JSON_UNESCAPED_UNICODE));
$missing = array_diff_key($en, $it);
$same = []; $leak = [];
foreach ($en as $k => $v) {
    if (! isset($it[$k]) || $v === '' || in_array($v, KEEP, true) || preg_match('/^[#\W]*$/u', $v)) continue;
    if ($it[$k] === $v) $same[] = "$k=$v";
    if (strlen($v) > 3 && preg_match('/(?<![\pL\pN])' . preg_quote($v, '/') . '(?![\pL\pN])/u', $hIt) && $it[$k] !== $v) $leak[] = $v;
}
check('L-1', 'Every new string of the sidebar, the Today panel, the toggle (both states), the Settings switch and the Appearance style row has its Italian text', $missing === [] && $same === [] && count($en) >= 25, json_encode(['missing' => array_keys($missing), 'same' => $same, 'count' => count($en)], JSON_UNESCAPED_UNICODE));
check('L-2', 'None of those English strings appears as a word in the text, labels or tooltips of the Italian pages (Users, Settings, Appearance, Activity, Profile; Paper, Ink, Charcoal excepted)', $leak === [], json_encode($leak, JSON_UNESCAPED_UNICODE));
check('L-3', 'The style names stay Paper, Ink and Charcoal in Italian', ($it['appearance-opt0'] ?? '') === 'Paper' && ($it['appearance-opt1'] ?? '') === 'Ink' && ($it['appearance-opt2'] ?? '') === 'Charcoal', json_encode([$it['appearance-opt0'] ?? null, $it['appearance-opt1'] ?? null, $it['appearance-opt2'] ?? null]));
$ex = function (string $t, string $re) { return preg_match_all('/.{0,40}' . $re . '.{0,30}/u', $t, $m) ? array_slice($m[0], 0, 3) : []; };
info('L-4 excerpts: ' . json_encode(['en today' => $ex($actEn, 'Today in the sidebar'), 'it today' => $ex($actIt, 'Oggi nella barra laterale'), 'en sidebar' => $ex($actEn, 'Sidebar'), 'it sidebar' => $ex($actIt, 'Barra laterale'), 'en head' => mb_substr($actEn, (int) mb_strpos($actEn, '01 '), 400)], JSON_UNESCAPED_UNICODE));
check('L-4', 'The activity log names the two new fields in each language (Today in the sidebar / Oggi nella barra laterale, Sidebar / Barra laterale)', $ex($actEn, 'Today in the sidebar:') !== [] && $ex($actIt, 'Oggi nella barra laterale:') !== [] && $ex($actEn, 'Sidebar:') !== [] && $ex($actIt, 'Barra laterale:') !== [], '');
check('L-clean', 'LANG pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
