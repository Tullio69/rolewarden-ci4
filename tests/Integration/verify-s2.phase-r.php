<?php
// Phase R (S2, second re-verification, 09e9df8): field names in the activity-log details translated. SPEC "Esiti di S2,
// decisi dall'utente": the names of the fields written by the module are translated; an unknown field keeps its name;
// a theme colour reads "Chiaro · accent". Expected names from the language files (panel.activity.fields), en and it.
// Run by verify-s2.s1.php (S1 harness). Changes made through the panel's own forms, then the log read in both languages.
$now = date('Y-m-d H:i:s');
$flatR = function (array $a, string $p = '') use (&$flatR): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flatR($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
$FEN = (require "$MOD/src/Language/en/RoleWarden.php")['panel']['activity']['fields'] ?? [];
$FIT = (require "$MOD/src/Language/it/RoleWarden.php")['panel']['activity']['fields'] ?? [];
check('R00', 'Language files: panel.activity.fields in English and Italian for the same ' . count($FEN) . ' fields', $FEN !== [] && array_keys($FEN) == array_keys($FIT), json_encode(array_keys($FEN)));
$setLangR = function (int $uid, ?string $l) use ($now) { q("DELETE FROM settings WHERE `key`='locale' AND context=?", ["user:$uid"]); if ($l) q("INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES ('RoleWarden\\\\Config\\\\RoleWarden','locale',?,'string',?,?,?)", [$l, "user:$uid", $now, $now]); };
$pr = function (Client $c, string $page, string $url, array $data, string $tag) { $t = csrf_of(G($c, $page, "token $page")); return P($c, $url, ['csrf_test_name' => $t] + $data, $tag, ['X-Requested-With: XMLHttpRequest', 'Accept: application/json']); };
$submit = function (Client $c, string $page, string $actionRe, callable $edit, string $tag): array {
    $h = G($c, $page, "R form $page");
    foreach (forms($h) as $f) if ($f['method'] === 'post' && preg_match($actionRe, $f['action'] ?: $page)) {
        $v = $edit($f['fields'], $f);
        P($c, $f['action'] ?: $page, $v, $tag);
        return [$c->status, array_keys(array_diff_assoc(array_map('strval', $v), array_map('strval', $f['fields'])))];
    }
    return [0, []];
};
q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['r-team', 'Team R', 'First', $now, $now]);
$RT = role_id('r-team');
$RU = make_owner('ruser@s2.test', OPW, 's2ruser', 'user');
sp8($APP, ['cache:clear']);
$MAXID = (int) q1('SELECT COALESCE(MAX(id),0) FROM acl_activity_log');
$setLangR($OWNER, 'it');   // the changes are made in Italian; the log is then read in both languages
serve8($APP, 'R');
$o = c8('R-owner'); login8($o, 'owner@v8b.test', OPW);
$done = [];
$done['user'] = $submit($o, "rolewarden/users/$RU/edit", '#users/' . $RU . '$#', fn ($v) => array_replace($v, ['username' => 's2ruser2']), 'R user edit');
$done['role'] = $submit($o, "rolewarden/roles/$RT/edit", '#roles/' . $RT . '$#', fn ($v) => array_replace($v, ['name' => 'Team R2', 'description' => 'Second <b>bold</b>']), 'R role edit');
$done['settings'] = $submit($o, 'rolewarden/settings', '#rolewarden/settings$#', fn ($v) => array_replace($v, ['lock_minutes' => (string) ((int) ($v['lock_minutes'] ?? 15) + 2), 'session_lifetime' => '1800', 'sign_in_rate' => '100']), 'R settings');
$done['appearance'] = $submit($o, 'rolewarden/appearance', '#rolewarden/appearance$#', function ($v, $f) {
    foreach ($f['types'] as $k => $t) if ($t === 'select' && preg_match('/radius|density/', $k)) {
        foreach (dom($f['html'])->query('//select[@name="' . $k . '"]/option') as $op) if ($op->getAttribute('value') !== (string) ($v[$k] ?? '')) { $v[$k] = $op->getAttribute('value'); break; }
    }
    info('appearance form fields: ' . json_encode($f['types']));
    foreach ($f['types'] as $k => $t) if (preg_match('/light/i', $k) && preg_match('/accent/i', $k)) { $v[$k] = '#12ab34'; break; }
    return $v;
}, 'R appearance');
$pr($o, "rolewarden/roles/$RT", "rolewarden/roles/$RT/permissions", ['permission_id' => perm_id('activity.view'), 'granted' => 1], 'R grant');
$hu = G($o, "rolewarden/users/$RU", 'R user page');
foreach (forms($hu) as $f) if (str_ends_with($f['action'], "/users/$RU/roles")) { $sel = array_keys(array_filter($f['types'], fn ($t) => $t === 'select'))[0] ?? 'role_id'; P($o, $f['action'], array_replace($f['fields'], [$sel => $RT]), 'R assign'); }
$pr($o, "rolewarden/users/$RU", "rolewarden/users/$RU/permissions", ['permission_id' => perm_id('users.delete'), 'granted' => 0], 'R override');
info('changes made: ' . json_encode($done));
$rows = q('SELECT * FROM acl_activity_log WHERE id > ? ORDER BY id', [$MAXID]);
info('activity rows written: ' . count($rows) . ' ' . json_encode(array_map(fn ($r) => array_intersect_key($r, array_flip(['type', 'action', 'event', 'details'])), $rows), JSON_UNESCAPED_UNICODE));
// one entry with an unknown field and markup, copied from a real row of the module with only the details changed
$detCol = null; foreach ($rows[0] ?? [] as $k => $v) if (is_string($v) && str_starts_with(ltrim($v), '{') && $k !== 'id') $detCol = $k;
$UNK = false;
if ($detCol) {
    $src = null; foreach ($rows as $r) { $d = json_decode((string) $r[$detCol], true); if (is_array($d) && $d) { $src = $r; break; } }
    if ($src) {
        $d = json_decode((string) $src[$detCol], true);
        $first = array_key_first($d);
        $shape = $d[$first];
        $new = ['mystery_field' => is_array($shape) ? array_combine(array_keys($shape), ['<b>old</b>', '<script>alert(73)</script>'] + array_fill(0, count($shape), 'x')) : '<script>alert(73)</script>'];
        unset($src['id']);
        $src[$detCol] = json_encode($new);
        $cols = array_keys($src);
        q('INSERT INTO acl_activity_log (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($src));
        $UNK = true;
        info('unknown-field row copied from a module row: ' . $src[$detCol]);
    }
}
server_stop();
$readLog = function (string $l) use ($setLangR, $OWNER): array {
    $setLangR($OWNER, $l === 'it' ? 'it' : null);
    serve8($GLOBALS['APP'], "R-read-$l");
    $c = c8("R-read-$l"); login8($c, 'owner@v8b.test', OPW);
    $h = G($c, 'rolewarden/activity', "R activity $l");
    server_stop();
    $out = [];
    foreach (dom($h)->query('//table//tbody/tr') as $tr) { $tds = $tr->getElementsByTagName('td'); $cells = []; foreach ($tds as $td) $cells[] = trim(preg_replace('/\s+/u', ' ', $td->textContent)); $out[] = $cells; }
    return [$h, $out];
};
[$hit, $rit] = $readLog('it');
[$hen, $ren] = $readLog('en');
$newN = count($rows) + ($UNK ? 1 : 0);
$det = fn (array $rs) => array_values(array_filter(array_map(fn ($c) => $c[5] ?? '', $rs)));
$dIt = $det($rit); $dEn = $det($ren);
info('details in Italian: ' . json_encode($dIt, JSON_UNESCAPED_UNICODE));
info('details in English: ' . json_encode($dEn, JSON_UNESCAPED_UNICODE));
$keysUsed = [];
foreach ($rows as $r) { $d = json_decode((string) ($r[$detCol] ?? ''), true); if (is_array($d)) foreach (array_keys($d) as $k) $keysUsed[$k] = true; }
$keysUsed = array_keys($keysUsed);
info('field keys in the new rows: ' . json_encode($keysUsed));
$probe = function (array $ds, array $names, array $other, string $l) use ($keysUsed): array {
    $bad = []; $all = implode(' | ', $ds);
    foreach ($keysUsed as $k) {
        $base = explode('.', $k)[0];
        if (isset($names[$k]) || isset($names[$base])) {
            $label = $names[$k] ?? $names[$base];
            if (! str_contains($all, $label)) $bad[] = "$k: label " . json_encode($label, JSON_UNESCAPED_UNICODE) . ' not shown';
            if ($label !== $k && preg_match('/(?<![\p{L}_])' . preg_quote($k, '/') . ':/u', str_replace(array_values($names), ' ', $all))) $bad[] = "$k: raw key shown";
        } else $bad[] = "$k: not in the language files";
    }
    if ($l === 'it') foreach ($keysUsed as $k) { $b = explode('.', $k)[0]; if (isset($other[$b], $names[$b]) && $other[$b] !== $names[$b] && preg_match('/(^|[\s|])' . preg_quote($other[$b], '/') . ':/u', $all)) $bad[] = "$k: English label " . $other[$b]; }
    return $bad;
};
$bIt = $probe($dIt, $FIT, $FEN, 'it'); $bEn = $probe($dEn, $FEN, $FIT, 'en');
$need = ['username', 'name', 'description', 'lock_minutes', 'session_lifetime', 'permission', 'role'];
$missing = array_values(array_diff($need, array_map(fn ($k) => explode('.', $k)[0], $keysUsed)));
check('R01-it', 'Italian log: the field names of the details written by the module (user, role, settings, appearance, permission, role assigned, override) are the Italian names', $bIt === [] && $missing === [], json_encode([$bIt, 'not written' => $missing], JSON_UNESCAPED_UNICODE));
check('R01-en', 'English log: the same entries show the English names', $bEn === [] && $missing === [], json_encode([$bEn], JSON_UNESCAPED_UNICODE));
$appKeys = array_values(array_filter($keysUsed, fn ($k) => in_array(explode('.', $k)[0], ['base', 'radius', 'density', 'light', 'dark'], true)));
info('appearance keys written: ' . json_encode($appKeys) . '; Italian: ' . json_encode(array_values(array_filter($dIt, fn ($s) => preg_match('/Raggio|Densit|Tema|Chiaro|Scuro/u', $s))), JSON_UNESCAPED_UNICODE));
$colour = array_values(array_filter($appKeys, fn ($k) => preg_match('/^(light|dark)\./', $k)));
if ($colour) check('R02', 'A theme colour reads «Chiaro · accent» (Italian) / «Light · accent» (English)', str_contains(implode(' ', $dIt), $FIT['light'] . ' · ' . explode('.', $colour[0])[1]) && str_contains(implode(' ', $dEn), $FEN['light'] . ' · ' . explode('.', $colour[0])[1]), json_encode([$colour, $dIt, $dEn], JSON_UNESCAPED_UNICODE));
else info('R02 not run: the appearance form changed no colour (' . json_encode($done['appearance']) . ')');
if ($UNK) {
    $u1 = implode(' | ', $dIt); $u2 = implode(' | ', $dEn);
    check('R03', 'An unknown field keeps its name in both languages (mystery_field)', str_contains($u1, 'mystery_field') && str_contains($u2, 'mystery_field'), json_encode([$u1, $u2]));
}
check('R04', 'Escape intact: markup in the values (role description, the unknown field) is shown as text in both languages', ! str_contains($hit . $hen, '<script>alert(73)') && ! str_contains($hit . $hen, 'Second <b>bold</b>') && str_contains(implode(' ', $dIt), 'Second <b>bold</b>'), '');
$setLangR($OWNER, null);
