<?php
// Phase O (S1, second re-check, f40170e / a396354): the sign-out link in the top bar ("Sign out" / "Esci", to Shield's
// logout). On every panel page for a signed-in user, translated, visible focus, the click closes the session, no
// horizontal scroll at 1280 px with the link present (Y03 measures every screen as well).
$OUT_EN = L($EN, 'panel.signOut'); $OUT_IT = L($IT, 'panel.signOut');
info("sign-out text from the language files: en \"$OUT_EN\", it \"$OUT_IT\"");
/** The sign-out links of the top bar: [text, href]. */
function signout_links(string $h, string $text): array
{
    $xp = dom($h); $o = [];
    foreach ($xp->query('//header//a | //*[contains(@class,"rw-topbar")]//a') as $a) if (trim(preg_replace('/\s+/u', ' ', $a->textContent)) === $text) $o[] = [$text, $a->getAttribute('href')];
    return array_values(array_unique($o, SORT_REGULAR));
}
$R9 = role_id('demo-approver');
$PANELO = ['rolewarden/users', "rolewarden/users/$PLAIN", "rolewarden/users/$PLAIN/edit", 'rolewarden/users/create', 'rolewarden/users/import', 'rolewarden/roles', "rolewarden/roles/$R9", "rolewarden/roles/$R9/edit", 'rolewarden/roles/create', 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/sessions', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/profile'];
$cO = serve8($APP, 'O');
$own = c8('O-own'); login8($own, 'owner@v8b.test', OPW);
$miss = []; $hrefs = [];
foreach ($PANELO as $u) { $h = G($own, $u, "O $u"); $l = signout_links($h, $OUT_EN); if ($own->status !== 200 || count($l) !== 1) $miss[] = "$u ({$own->status}) " . json_encode($l); else $hrefs[$l[0][1]] = true; }
info('sign-out hrefs: ' . json_encode(array_keys($hrefs)));
check('O01', 'Every panel page (' . count($PANELO) . ') carries one top-bar link "' . $OUT_EN . '" for the signed-in super admin', $miss === [], implode(' | ', $miss));
$href = array_key_first($hrefs) ?? '';
check('O02', 'The link points at Shield\'s logout of this application (same href everywhere)', count($hrefs) === 1 && preg_match('#^(' . preg_quote(rtrim(BASE, '/'), '#') . '(/index\.php)?)?/logout$#', $href) === 1, $href);
$pl = c8('O-plain'); login8($pl, 'plain@v8b.test', OPW);
$miss = []; foreach (['rolewarden/profile', 'rolewarden/sessions'] as $u) { $h = G($pl, $u, "O plain $u"); if ($pl->status !== 200 || count(signout_links($h, $OUT_EN)) !== 1) $miss[] = "$u {$pl->status}"; }
check('O03', 'A user with no permission has the link on the pages open to them (profile, own sessions)', $miss === [], implode(' | ', $miss));
// Italian
$f = null; foreach (forms(G($own, 'rolewarden/profile')) as $ff) if (($ff['types']['language'] ?? '') === 'select') $f = $ff;
P($own, $f['action'], array_replace($f['fields'], ['language' => 'it']), 'O owner it');
$miss = []; foreach ($PANELO as $u) { $h = G($own, $u, "O it $u"); if (count(signout_links($h, $OUT_IT)) !== 1 || signout_links($h, $OUT_EN) !== []) $miss[] = $u; }
check('O04', "Italian: the link reads \"$OUT_IT\" on every panel page, never \"$OUT_EN\"", $miss === [] && $OUT_IT !== '' && $OUT_IT !== $OUT_EN, implode(' | ', $miss));
q("DELETE FROM settings WHERE `key`='locale' AND context=?", ['user:' . $OWNER]);
// logout over HTTP: the session is closed
$x = c8('O-http'); login8($x, 'owner@v8b.test', OPW);
G($x, 'rolewarden/users', 'O before logout');
$st0 = $x->status;
$x->go($href); seen($x, 'O logout');
$x->req('GET', 'rolewarden/users'); seen($x, 'O panel after logout');
check('O05', 'Following the link closes the session: a panel page then redirects to the sign-in page', $st0 === 200 && in_array($x->status, [302, 303], true) && str_contains($x->location, '/login'), "before $st0, after {$x->status} {$x->location}");
$x2 = c8('O-http2'); login8($x2, 'owner@v8b.test', OPW); $x2->go($href); seen($x2, 'O logout 2');
$x2->req('GET', 'demo'); seen($x2, 'O demo after logout');
check('O05b', 'After the sign-out the demo page is closed as well (redirect to sign-in or not served)', $x2->status !== 200 || str_contains($x2->body, 'name="password"'), "status {$x2->status} {$x2->location}");
server_stop();
// browser: visible focus, the click, 1280 px
$FOCUS = <<<'JS'
(() => { const txt = %s; const a = [...document.querySelectorAll('a')].find((e) => e.textContent.trim() === txt); if (!a) return null;
  const st = (e) => { const c = getComputedStyle(e); return [c.outlineStyle, c.outlineWidth, c.outlineColor, c.boxShadow, c.textDecorationLine, c.backgroundColor, c.color].join('|'); };
  document.activeElement && document.activeElement.blur(); const before = st(a); a.focus(); const after = st(a);
  const r = a.getBoundingClientRect(); return { before, after, focused: document.activeElement === a, focusVisible: a.matches(':focus-visible'), visible: r.width > 0 && r.height > 0 && r.right <= innerWidth, overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth }; })()
JS;
$cO = serve8($APP, 'O2');
$steps = [blogin('owner@v8b.test', OPW), ['op' => 'viewport', 'width' => 1280, 'height' => 900]];
foreach (['rolewarden/users', 'rolewarden/roles/' . $R9, 'rolewarden/permissions', 'rolewarden/settings'] as $u) { $steps[] = ['op' => 'goto', 'url' => BASE . $u, 'tag' => "g $u"]; $steps[] = ['op' => 'eval', 'expr' => sprintf($FOCUS, json_encode($OUT_EN)), 'tag' => "f $u"]; }
$steps[] = ['op' => 'screenshot', 'path' => "$WORK/verify-s1.signout-focus-1280.png", 'full' => false, 'tag' => 'shot'];
$steps[] = ['op' => 'click', 'selector' => 'header a[href$="/logout"], .rw-topbar a[href$="/logout"]', 'nav' => true, 'tag' => 'click'];
$steps[] = ['op' => 'goto', 'url' => BASE . 'rolewarden/users', 'tag' => 'after'];
$r = browser($steps, 'sign-out');
@copy("$WORK/verify-s1.signout-focus-1280.png", __DIR__ . '/verify-s1.signout-focus-1280.png');
$bad = []; $ov = [];
foreach (['rolewarden/users', 'rolewarden/roles/' . $R9, 'rolewarden/permissions', 'rolewarden/settings'] as $u) {
    $m = bval($r, "f $u");
    if (! $m || ! $m['focused'] || ! $m['focusVisible'] || $m['before'] === $m['after'] || ! $m['visible']) $bad[] = "$u " . json_encode($m);
    if (($m['overflowX'] ?? 1) > 0) $ov[] = "$u +{$m['overflowX']}px";
}
info('focus styles: ' . json_encode(bval($r, 'f rolewarden/users')));
check('O06', 'Chrome, 1280 px: the link takes the keyboard focus, matches :focus-visible and its look changes (outline, shadow, underline or colours)', $r['errors'] === [] && $bad === [], implode(' | ', $bad) . json_encode($r['errors']));
check('O07', 'Chrome, 1280 px: no horizontal scroll with the link in the top bar (users, role, Permissions, Settings)', $ov === [], implode(' | ', $ov));
$after = bstep($r, 'after'); $click = bstep($r, 'click');
check('O08', 'Chrome: clicking the link signs out; the panel then opens the sign-in page', ($click['value'] ?? false) === true && str_contains((string) ($after['url'] ?? ''), '/login'), json_encode([$click, $after]));
server_stop();
