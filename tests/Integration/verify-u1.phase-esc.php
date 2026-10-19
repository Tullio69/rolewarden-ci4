<?php
// U1 phase ESC (point 8, the security rules on what U1 adds): escape of the new output (a sidebar label changed by the
// application's language file, documented in views.html), wrong input on the new fields never echoed, CSRF on the new
// form, no SQL or PHP error anywhere in the run, application log clean.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== ESC: escape, CSRF, errors, logs\n";
serve8($APP, 'ESC');
$S0 = count($GLOBALS['SEEN']);
$own = c8('e-owner'); login8($own, 'owner@v8b.test', OPW);
$XSS = 'People <img src=x onerror=alert(1)> & "q"';
@mkdir("$APP/app/Language/en", 0777, true);
$langFile = "$APP/app/Language/en/RoleWarden.php";
$had = is_file($langFile);
if ($had) exit("ABORT: the test application already has app/Language/en/RoleWarden.php\n");
file_put_contents($langFile, "<?php\nreturn ['panel' => ['nav' => ['users' => " . var_export($XSS, true) . "]]];\n");
$h = G($own, 'rolewarden/roles', 'esc lang override');
$s = side($h);
$raw = substr($h, (int) strpos($h, '<aside'), (int) strpos($h, '</aside>') - (int) strpos($h, '<aside'));
check('E-1', 'A sidebar label from the application language file (views.html "Changing a few lines") is printed escaped, in the text and in the tooltip', ($s['items']['rolewarden/users']['text'] ?? '') === $XSS && ! str_contains($raw, '<img src=x') && ! str_contains($raw, '"q""'), json_encode([$s['items']['rolewarden/users']['text'] ?? null, $s['items']['rolewarden/users']['title'] ?? null]));
// on the rail the label goes into title="": still escaped
$t = $s['toggle']; P($own, $t['action'], $t['fields'], 'esc collapse');
$h = G($own, 'rolewarden/roles', 'esc rail');
$raw = substr($h, (int) strpos($h, '<aside'), (int) strpos($h, '</aside>') - (int) strpos($h, '<aside'));
$s = side($h);
check('E-2', 'On the rail the same label in the title attribute is escaped too', ($s['items']['rolewarden/users']['title'] ?? '') === $XSS && ! str_contains($raw, '<img src=x') && ! preg_match('/title="People <|title="[^"]*"q""/', $raw), json_encode($s['items']['rolewarden/users']['title'] ?? null));
P($own, $s['toggle']['action'], $s['toggle']['fields'], 'esc expand');
unlink($langFile);
// wrong input on the new fields: never echoed raw, no error page
$adm = c8('e-admin'); login8($adm, 'admin@v8b.test', OPW);
$evil = '"><svg onload=alert(2)>';
$b1 = settings_save($adm, ['sidebar_today' => $evil], 'esc settings evil');
$st1 = $adm->status; $f1 = in_array($st1, [302, 303], true) ? G($adm, $adm->location, 'esc settings follow') : $b1;
$b2 = theme_save($adm, ['base' => 'console', 'sidebar' => $evil], 'esc appearance evil');
$st2 = $adm->status; $f2 = in_array($st2, [302, 303], true) ? G($adm, $adm->location, 'esc appearance follow') : $b2;
$b3 = P($adm, 'rolewarden/sidebar', ['csrf_test_name' => csrf_of(G($adm, 'rolewarden/profile', 'esc token')), 'collapsed' => $evil], 'esc sidebar evil');
$st3 = $adm->status; $f3 = in_array($st3, [302, 303], true) ? G($adm, $adm->location, 'esc sidebar follow') : $b3;
check('E-3', 'A crafted value on the switch, the style and the collapse field is never printed raw and gives no error page', $st1 < 500 && $st2 < 500 && $st3 < 500 && ! str_contains($f1 . $f2 . $f3, '<svg onload=alert(2)>'), json_encode([$st1, $st2, $st3]));
check('E-4', 'The sidebar form (the one form U1 adds) carries the CSRF token on every page seen in this phase', ! array_filter(array_slice($GLOBALS['SEEN'], $S0), fn ($v) => $v[1] === 200 && str_contains($v[3], 'rolewarden/sidebar"') && ! preg_match('#action="[^"]*rolewarden/sidebar">\s*<input type="hidden" name="csrf_test_name" value="[0-9a-f]{20,}"#', $v[3])), '');
// the whole run: every body seen, the application log
$bad = seen_clean(0);
check('E-5', 'Every response of this U1 run (' . count($GLOBALS['SEEN']) . ' bodies): no 5xx, no SQL text, no PHP error', $bad === [], json_encode(array_slice($bad, 0, 20)));
$ll = bad_log_lines(logs_of($APP));
info('application log lines WARNING or above: ' . count($ll) . ' ' . short(implode(' | ', array_slice($ll, 0, 8)), 1500));
// the run sends three POSTs without the CSRF token on purpose (R-2, T-9, S-csrf): CodeIgniter logs each refusal as CRITICAL SecurityException
$csrf = array_filter($ll, fn ($l) => str_contains($l, 'SecurityException: The action you requested is not allowed.'));
$other = array_values(array_diff($ll, $csrf));
check('E-6', 'Application log: no WARNING, ERROR or CRITICAL line apart from one CSRF refusal per deliberate POST without the token (' . count($csrf) . ')', $other === [] && count($csrf) <= 3, short(implode(' | ', array_slice($other, 0, 8)), 1500));
