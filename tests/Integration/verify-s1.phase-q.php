<?php
// Phase Q (S1): saved values when the application adds or removes app/Config/RoleWarden.php, the way
// documentation/configuration.html shows for any option. First round: per-user choices on 30de388 and on the baseline
// b1cca14 (D1, already present there). Re-check of 79a0cda (author's statement: saved rows follow the class
// config('RoleWarden') answers to, in both directions, the current class wins when a key exists under both, and this
// covers every row of the class, Settings screen values included): inverse case, both classes, a global Settings value
// (lockAttempts) applied at sign-in, the admin's value winning over a default the application class overrides.
// Needs phase N's helpers.
if (! function_exists('set_languages')) exit("phase Q needs phase N first\n");
$ENVQ = getenv('RW_V8B_ENV') ?: 'development';
$CLASSQ = "<?php\n\nnamespace Config;\n\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public array \$extraThemes = ['ocean' => 'Ocean'];\n}\n";
$modeOf = fn (int $uid) => array_map(fn ($r) => $r['class'] . '.' . $r['key'] . '=' . $r['value'], q("SELECT class, `key`, value FROM settings WHERE context LIKE ? AND `key` NOT LIKE '%ocale%'", ['%:' . $uid]));
$allSet = fn () => array_map(fn ($r) => $r['class'] . '.' . $r['key'] . '=' . $r['value'] . ($r['context'] ? ' @' . $r['context'] : ''), q('SELECT class, `key`, value, context FROM settings ORDER BY id'));
$pick = function (string $tag, string $email, ?string $lang, ?string $mode): array {
    $c = c8($tag); login8($c, $email, OPW);
    if ($lang !== null && ($f = lselectN(G($c, 'rolewarden/profile', "$tag profile")))) P($c, $f['action'], array_replace($f['fields'], ['language' => $lang]), "$tag picks $lang");
    if ($mode !== null) { foreach (forms(G($c, 'rolewarden/profile')) as $ff) if (str_ends_with($ff['action'], 'appearance/mode')) { P($c, $ff['action'], $ff['fields'] + ['mode' => $mode], "$tag mode $mode"); break; } }
    $h = G($c, 'rolewarden/profile', "$tag profile after");
    return [html_lang($h), (bool) preg_match('/data-rw-mode="dark"/', $h)];
};
$look = function (string $tag, string $email): array { $c = c8($tag); login8($c, $email, OPW); $h = G($c, 'rolewarden/profile', "$tag look"); return [html_lang($h), (bool) preg_match('/data-rw-mode="dark"/', $h)]; };
$revs = getenv('RW_S1_QBASE') ? ['new' => $MODFULL, 'b1cca14' => "$WORK/basefull"] : ['new' => $MODFULL];
foreach ($revs as $rev => $mod) {
    $AQ = app_copy("appQ$rev", $mod, true, $ENVQ);
    $u = make_owner("q$rev@s1.test", OPW, "s1q$rev", 'user');
    @unlink("$AQ/app/Config/RoleWarden.php");
    $cQ = serve8($AQ, "Q$rev");
    $before = $pick("Q$rev", "q$rev@s1.test", 'it', 'dark');
    server_stop();
    file_put_contents("$AQ/app/Config/RoleWarden.php", $CLASSQ);
    $cQ = serve8($AQ, "Q$rev-b");
    $after = $look("Q$rev-b", "q$rev@s1.test");
    info("Q $rev: before the class " . json_encode($before) . '; after adding app/Config/RoleWarden.php (extraThemes only) ' . json_encode($after) . ' rows ' . json_encode(array_merge(lang_settingN($u), $modeOf($u))));
    $GLOBALS['Q'][$rev] = [$before, $after];
    server_stop();
}
[$b30, $a30] = $GLOBALS['Q']['new'];
check('Q01', 'A user who chose Italian keeps Italian after the application adds app/Config/RoleWarden.php for another option (extraThemes), as configuration.html shows', $b30[0] === 'it' && $a30[0] === 'it', json_encode([$b30, $a30]));
check('Q02', 'The light/dark choice survives the same change', $b30[1] === true && $a30[1] === true, json_encode([$b30[1], $a30[1]]));
if (isset($GLOBALS['Q']['b1cca14'])) { [$bb, $ab] = $GLOBALS['Q']['b1cca14']; info('Q baseline b1cca14: language ' . $bb[0] . ' -> ' . $ab[0] . ', dark ' . json_encode($bb[1]) . ' -> ' . json_encode($ab[1])); }

// ---- inverse: choices made while the class exists survive its removal (same copy, class present now)
$AQ = "$WORK/appQnew";
$u2 = make_owner('qinv@s1.test', OPW, 's1qinv', 'user');
$cQ = serve8($AQ, 'Qinv');
$b = $pick('Qinv', 'qinv@s1.test', 'it', 'dark');
$rowsWith = array_merge(lang_settingN($u2), $modeOf($u2));
server_stop();
unlink("$AQ/app/Config/RoleWarden.php");
$cQ = serve8($AQ, 'Qinv-b');
$a = $look('Qinv-b', 'qinv@s1.test');
$a1 = $look('Qinv-c', 'q' . 'new@s1.test');
info('Q inverse: chosen with the class ' . json_encode($b) . ' rows ' . json_encode($rowsWith) . '; after removing the class ' . json_encode($a) . ' rows ' . json_encode(array_merge(lang_settingN($u2), $modeOf($u2))));
check('Q04', 'Inverse: language and light/dark chosen while app/Config/RoleWarden.php exists survive its removal', $b === ['it', true] && $a === ['it', true], json_encode([$b, $a]));
check('Q04b', 'The user of Q01 (chosen before the class, kept with it) still has Italian and dark after the class is removed again', $a1 === ['it', true], json_encode($a1));
server_stop();

// ---- the same key saved under both classes: the current class wins
$u3 = make_owner('qboth@s1.test', OPW, 's1qboth', 'user');
$now = date('Y-m-d H:i:s');
foreach ([['RoleWarden\\Config\\RoleWarden', 'en', 'light'], ['Config\\RoleWarden', 'it', 'dark']] as [$cls, $l, $m]) {
    q('INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES (?,?,?,?,?,?,?)', [$cls, 'locale', $l, 'string', "user:$u3", $now, $now]);
    q('INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES (?,?,?,?,?,?,?)', [$cls, 'colorScheme', $m, 'string', "user:$u3", $now, $now]);
}
info('Q both classes, rows written: ' . json_encode(array_merge(lang_settingN($u3), $modeOf($u3))));
file_put_contents("$AQ/app/Config/RoleWarden.php", $CLASSQ);
$cQ = serve8($AQ, 'Qboth');
$w = $look('Qboth', 'qboth@s1.test');
$rowsB = array_merge(lang_settingN($u3), $modeOf($u3));
info('Q both classes, class present: seen ' . json_encode($w) . ', rows now ' . json_encode($rowsB));
check('Q05', 'Same key under both classes, application class present: the value of the current class (Config\\RoleWarden: it, dark) is the one used, without errors', $w === ['it', true], json_encode([$w, $rowsB]));
server_stop();
unlink("$AQ/app/Config/RoleWarden.php");
$cQ = serve8($AQ, 'Qboth2');
$w2 = $look('Qboth2', 'qboth@s1.test');
info('Q both classes, class removed afterwards: seen ' . json_encode($w2) . ', rows ' . json_encode(array_merge(lang_settingN($u3), $modeOf($u3))));
check('Q05b', 'Then the class is removed: the user keeps the value that was current (it, dark), the stale one of the module class does not come back', $w2 === ['it', true], json_encode($w2));
server_stop();

// ---- a global Settings value (lockAttempts) saved from the screen, then the class added (with a default of its own)
$lockedAfter = function (string $tag, string $email, string $pw, int $fails): bool {
    for ($i = 0; $i < $fails; $i++) { $x = c8("$tag-f$i"); login8($x, $email, 'Wrong-Pass-' . $i . 'q'); }
    $y = c8("$tag-ok"); return ! login8($y, $email, $pw);
};
$u4 = make_owner('qlock1@s1.test', OPW, 's1qlock1', 'user');
$u5 = make_owner('qlock2@s1.test', OPW, 's1qlock2', 'user');
$u6 = make_owner('qlock3@s1.test', OPW, 's1qlock3', 'user');
$cQ = serve8($AQ, 'Qlock');
$own = c8('Q-own'); login8($own, 'owner@v8b.test', OPW);
check('Q06a', 'Sensitivity, default lock (5 failures): 2 failures do not lock', ! $lockedAfter('Qs', 'qlock1@s1.test', OPW, 2));
$h = G($own, 'rolewarden/settings', 'Q settings');
$sf = null; $lk = null;
foreach (forms($h) as $f) foreach ($f['types'] as $k => $t) if (preg_match('/lock/i', $k) && ! preg_match('/minute|duration/i', $k)) { $sf = $f; $lk = $k; }
preg_match_all('/<option value="(\d+)"/', (string) (preg_match('/<select[^>]*name="' . preg_quote((string) $lk, '/') . '"[^>]*>(.*?)<\/select>/s', $h, $mm) ? $mm[1] : ''), $opts);
info('Settings lock field: ' . json_encode([$lk, $sf['fields'][$lk] ?? null, $opts[1] ?? [], $sf['types'][$lk] ?? null]));
$val = ($opts[1] ?? []) ? (string) min(array_map('intval', $opts[1])) : '2';
if (preg_match('/<input[^>]*name="' . preg_quote((string) $lk, '/') . '"[^>]*>/', $h, $im)) { info('lock input: ' . $im[0]); if (preg_match('/min="(\d+)"/', $im[0], $mn)) $val = $mn[1]; }
$maxIdQ = (int) q1('SELECT COALESCE(MAX(id),0) FROM settings');
// the copy's .env sets rolewarden.signInRate = 1000 for the suites, above what the screen accepts: send the screen's maximum
$extraQ = []; if (preg_match('/<input[^>]*name="sign_in_rate"[^>]*>/', $h, $ir) && preg_match('/max="(\d+)"/', $ir[0], $mx) && (int) ($sf['fields']['sign_in_rate'] ?? 0) > (int) $mx[1]) $extraQ['sign_in_rate'] = $mx[1];
info('sign_in_rate sent: ' . json_encode($extraQ));
if ($sf) { P($own, $sf['action'], array_replace($sf['fields'], [$lk => $val], $extraQ), "Q save lock $val"); info("save: status {$own->status} location {$own->location} fields " . json_encode(array_keys($sf['fields'])) . ' fields sent ' . json_encode(array_replace($sf['fields'], [$lk => $val], $extraQ)) . ' page after ' . short(text_of($own->go(abs_url($own->url, $own->location ?: $own->url))), 900)); preg_match_all('/&quot;message&quot;&#x3A;&quot;(.*?)&quot;/', $own->body, $tm); info('messages: ' . json_encode($tm[1])); }
$saved = q("SELECT class, value FROM settings WHERE `key`='lockAttempts' AND context IS NULL");
info("lockAttempts saved from the screen: $val, rows " . json_encode($saved));
info('all settings rows after the save: ' . json_encode($allSet()));
check('Q06b', "lockAttempts set to $val from the Settings screen is applied: $val failures lock the email", $sf !== null && (int) $val < 5 && $lockedAfter('Qb', 'qlock2@s1.test', OPW, (int) $val), json_encode($saved));
server_stop();
file_put_contents("$AQ/app/Config/RoleWarden.php", str_replace("public array \$extraThemes = ['ocean' => 'Ocean'];", "public array \$extraThemes = ['ocean' => 'Ocean'];\n    public int \$lockAttempts = 9;", $CLASSQ));
info('class written: ' . short(file_get_contents("$AQ/app/Config/RoleWarden.php"), 300));
$cQ = serve8($AQ, 'Qlock2');
$own = c8('Q-own2'); login8($own, 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden/settings', 'Q settings after class');
$shown = null; foreach (forms($h) as $f) if (isset($f['fields'][$lk])) $shown = $f['fields'][$lk];
$saved2 = q("SELECT class, value FROM settings WHERE `key`='lockAttempts' AND context IS NULL");
info('after adding the class with lockAttempts = 9: Settings shows ' . json_encode($shown) . ', rows ' . json_encode($saved2));
check('Q07', "After the class is added (overriding the default with 9), the Settings screen still shows the admin's $val", (string) $shown === $val, json_encode([$shown, $saved2]));
check('Q07b', "and the admin's $val is still applied at sign-in ($val failures lock), not the class default 9", $lockedAfter('Qc', 'qlock3@s1.test', OPW, (int) $val), json_encode($saved2));
server_stop();
unlink("$AQ/app/Config/RoleWarden.php");
q("DELETE FROM settings WHERE `key`='lockAttempts'");
q('DELETE FROM settings WHERE id > ? AND context IS NULL', [$maxIdQ]); // every global value the Settings save wrote (rate, durations, role, retention, Shield session config)
q("DELETE FROM auth_logins WHERE identifier LIKE 'qlock%'");
info('settings rows at the end of Q: ' . count($allSet()));
mailpit('DELETE', 'messages');
