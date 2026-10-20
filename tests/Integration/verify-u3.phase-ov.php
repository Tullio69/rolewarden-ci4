<?php
/**
 * U3 phase OV (Collaudatore ad Hoc, Claude): the Ctrl K overlay in Chrome (headless, CDP real key presses), against
 * docs/design-system/components/CommandSearch/README.md (and preview.html), components/Sidebar/README.md, the design
 * system README (tokens, focus, depth) and the SPEC v1.1 "Ricerca comandi". Measured on the served page:
 * opening (Ctrl K, Cmd K on macOS, the sidebar button, also on the rail), first focus, Up/Down/Enter/Esc, focus given
 * back, ARIA roles (dialog, combobox, listbox, option, aria-activedescendant), no database write, and in 3 themes x
 * light/dark x 3 sidebar styles: geometry, surface, shadow, scrim, highlighted row, Enter hint, focus ring, every
 * colour a token value, text contrast.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== OV: the overlay in Chrome (U3)\n";
serve8($APP, 'OV');
$S0 = count($GLOBALS['SEEN']);
function u3browser(array $steps, string $tag, int $timeout = 300000): array
{
    global $WORK;
    static $n = 0; $n++;
    $job = ['profile' => "$WORK/chrome-u3-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 400];
    $jf = "$WORK/u3job-$n.json";
    file_put_contents($jf, json_encode($job));
    $p = proc_open(['node', __DIR__ . '/verify-u3.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'profileRemoved' => false, 'consoleErrors' => []]; }
    if ($r['errors']) info("browser $tag errors: " . short(implode(' | ', $r['errors']), 800));
    if (! empty($r['consoleErrors'])) info("browser $tag console errors: " . short(implode(' | ', $r['consoleErrors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    return $r;
}
$K = fn (string $key, string $code, int $kc, int $mod = 0, string $tag = '', bool $nav = false) => ['op' => 'key', 'key' => $key, 'code' => $code, 'keyCode' => $kc, 'modifiers' => $mod, 'tag' => $tag ?: "key $key", 'nav' => $nav, 'wait' => 500];
$CTRLK = fn (string $tag = 'ctrlk') => $K('k', 'KeyK', 75, 2, $tag);
$ESC = fn (string $tag = 'esc') => $K('Escape', 'Escape', 27, 0, $tag);
$E = fn (string $tag, string $expr, int $wait = 0) => ['op' => 'eval', 'tag' => $tag, 'expr' => $expr, 'wait' => $wait];
$STATE = <<<'JS'
(() => {
  const d = document.querySelector('dialog.rw-cmd'), q = document.getElementById('rw-cmd-q'), lb = document.getElementById(q ? q.getAttribute('aria-controls') : '') ;
  const a = document.activeElement;
  const opts = lb ? [...lb.querySelectorAll('[role=option]')] : [];
  return { url: location.href, open: !!(d && d.open), modal: !!(d && d.matches(':modal')), activeIsField: a === q, active: a ? (a.id || a.className || a.tagName) : null, activeMark: a && a.dataset ? a.dataset.u3 || null : null,
    activeSide: !!(a && a.classList && a.classList.contains('rw-side-search')),
    ad: q ? q.getAttribute('aria-activedescendant') : null, expanded: q ? q.getAttribute('aria-expanded') : null, controls: q ? q.getAttribute('aria-controls') : null, lbRole: lb ? lb.getAttribute('role') : null, qRole: q ? q.getAttribute('role') : null,
    dRole: d ? (d.getAttribute('role') || 'dialog') : null, dName: d ? (d.getAttribute('aria-label') || d.getAttribute('aria-labelledby')) : null,
    options: opts.map((o) => ({ id: o.id, sel: o.getAttribute('aria-selected'), href: o.getAttribute('href'), text: o.textContent.trim().replace(/\s+/g, ' ').slice(0, 80) })),
    kbd: (document.querySelector('.rw-side-search kbd') || {}).textContent || null };
})()
JS;
$own = c8('ov-owner'); login8($own, 'owner@v8b.test', OPW);
$adm = c8('ov-admin'); login8($adm, 'admin@v8b.test', OPW);

// ================================================================ KEY: keyboard and focus (Console, light, Paper)
echo "\n-- KEY\n";
$strip = function (array $st): array {
    foreach (['acl_sessions', 'auth_logins', 'auth_remember_tokens', 'auth_token_logins'] as $t) unset($st[$t]);
    if (isset($st['auth_identities'])) foreach ($st['auth_identities']['rows'] as &$r) unset($r['last_used_at'], $r['updated_at']);
    unset($r);
    if (isset($st['users'])) foreach ($st['users']['rows'] as &$r) unset($r['last_active']);
    return $st;
};
$before = $strip(fullstate());
$steps = [blogin('owner@v8b.test', OPW), ['op' => 'emulate', 'scheme' => 'light', 'motion' => 'no-preference'], ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'g1'], ['op' => 'requests', 'tag' => 'req0'],
    $E('s0', $STATE),
    $E('mark', '(() => { const a = document.querySelector("main a[href]"); a.dataset.u3 = "1"; a.focus(); return document.activeElement === a; })()'),
    $CTRLK('ck1'), $E('s1', $STATE), $ESC('esc1'), $E('s2', $STATE),
    // the sidebar button, from the keyboard (focus then Enter) and with a click
    $E('fside', '(() => { const b = document.querySelector(".rw-side-search"); b.focus(); return document.activeElement === b; })()'),
    $K('Enter', 'Enter', 13, 0, 'enter-side'), $E('s3', $STATE), $ESC('esc2'), $E('s4', $STATE),
    ['op' => 'click', 'selector' => '.rw-side-search', 'tag' => 'click-side', 'wait' => 600], $E('s5', $STATE), $ESC('esc3'),
    // Up, Down, Enter
    $CTRLK('ck2'), ['op' => 'type', 'text' => 'role', 'wait' => 900], $E('l0', $STATE),
    $K('ArrowDown', 'ArrowDown', 40, 0, 'd1'), $E('l1', $STATE), $K('ArrowDown', 'ArrowDown', 40, 0, 'd2'), $E('l2', $STATE), $K('ArrowUp', 'ArrowUp', 38, 0, 'u1'), $E('l3', $STATE),
    $K('Enter', 'Enter', 13, 0, 'enter-go', true), $E('l4', $STATE),
    // an action: opens its screen
    $CTRLK('ck3'), ['op' => 'type', 'text' => 'Add a role', 'wait' => 900], $E('a0', $STATE), $K('Enter', 'Enter', 13, 0, 'enter-act', true), $E('a1', '(() => ({ url: location.href, h1: (document.querySelector("main h1") || {}).textContent }))()'),
    // an identifier: top result opens Check a permission
    $CTRLK('ck4'), ['op' => 'type', 'text' => 'users.del', 'wait' => 900], $E('i0', $STATE), $K('Enter', 'Enter', 13, 0, 'enter-id', true), $E('i1', '(() => ({ url: location.href, sel: (document.querySelector("select[name=permission] option:checked") || {}).value }))()'),
    // Ctrl K from other screens, Esc closes there too
    ['op' => 'goto', 'url' => U1BASE . 'rolewarden/permissions/check?permission=users.view', 'tag' => 'g2'], $CTRLK('ck5'), $E('o1', $STATE), $ESC('esc5'), $E('o2', $STATE),
    ['op' => 'goto', 'url' => U1BASE . 'rolewarden/search?q=users', 'tag' => 'g3'], $CTRLK('ck6'), $E('o3', $STATE), $ESC('esc6'),
    ['op' => 'requests', 'tag' => 'req1'],
    ['op' => 'ax', 'selector' => '.rw-side-search', 'tag' => 'ax-side'],
    $CTRLK('ck7'), ['op' => 'type', 'text' => 'users', 'wait' => 900],
    ['op' => 'ax', 'selector' => 'dialog.rw-cmd', 'tag' => 'ax-dialog'], ['op' => 'ax', 'selector' => '#rw-cmd-q', 'tag' => 'ax-field'], ['op' => 'ax', 'selector' => '#rw-cmd-results', 'tag' => 'ax-list'], ['op' => 'ax', 'selector' => '#rw-cmd-results [role=option]', 'tag' => 'ax-opts'],
    // a click on the scrim closes it
    $E('scrim', '(() => { const d = document.querySelector("dialog.rw-cmd"); const r = d.getBoundingClientRect(); const ev = new MouseEvent("click", { bubbles: true, clientX: 5, clientY: innerHeight - 5 }); d.dispatchEvent(ev); return d.open; })()', 300),
];
$r = u3browser($steps, 'ov keys');
$after = $strip(fullstate());
$v = fn ($t) => bval($r, $t);
$s0 = $v('s0'); $s1 = $v('s1'); $s2 = $v('s2'); $s3 = $v('s3'); $s4 = $v('s4'); $s5 = $v('s5');
info('states: ' . json_encode(['s0' => $s0, 's1' => $s1, 's2' => $s2, 's3' => $s3, 's4' => $s4, 's5' => $s5, 'mark' => $v('mark'), 'fside' => $v('fside')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
check('K-1', 'Ctrl K opens the search as a modal dialog with the field focused (first focus)', ($s1['open'] ?? false) && ($s1['modal'] ?? false) && ($s1['activeIsField'] ?? false), json_encode($s1));
check('K-2', 'Esc closes it and gives focus back to where it was (a link of the page)', ($v('mark') === true) && ! ($s2['open'] ?? true) && ($s2['activeMark'] ?? null) === '1', json_encode($s2));
check('K-3', 'The sidebar button opens it from the keyboard (Enter) without leaving the page, field focused; Esc gives focus back to the button', ($s3['open'] ?? false) && ($s3['activeIsField'] ?? false) && ($s3['url'] ?? '') === ($s0['url'] ?? 'x') && ! ($s4['open'] ?? true) && ($s4['activeSide'] ?? false), json_encode([$s3, $s4]));
check('K-4', 'A click on the sidebar button opens it too, field focused', ($s5['open'] ?? false) && ($s5['activeIsField'] ?? false) && ($s5['url'] ?? '') === ($s0['url'] ?? 'x'), json_encode($s5));
$l = [$v('l0'), $v('l1'), $v('l2'), $v('l3')];
$selIdx = fn ($st) => is_array($st) ? array_search('true', array_column($st['options'], 'sel'), true) : null;
$idx = array_map($selIdx, $l);
$adOk = ! array_filter($l, fn ($st) => ! is_array($st) || ($st['ad'] ?? null) !== ($st['options'][$selIdx($st)]['id'] ?? 'x') || count(array_filter($st['options'], fn ($o) => $o['sel'] === 'true')) !== 1);
info('Up/Down: selected index ' . json_encode($idx) . ', options ' . json_encode(array_column($l[0]['options'] ?? [], 'text')));
check('K-5', 'Typing shows results; the first is highlighted; Down, Down, Up move the highlight 0, 1, 2, 1; exactly one option aria-selected and aria-activedescendant on the field names it; the field keeps focus', $idx === [0, 1, 2, 1] && $adOk && ($l[3]['activeIsField'] ?? false), json_encode($l));
$want = $l[3]['options'][1]['href'] ?? null; $l4 = $v('l4');
check('K-6', 'Enter opens the highlighted result (its address, 200)', $want !== null && ($l4['url'] ?? '') === $want && (bstep($r, 'enter-go')['status'] ?? 0) === 200, json_encode([$want, $l4['url'] ?? null, bstep($r, 'enter-go')]));
$a0 = $v('a0'); $a1 = $v('a1');
check('K-7', 'An action chosen with Enter opens its screen (Add a role: the create form), it does not act', ($a0['options'][0]['text'] ?? '') !== '' && str_contains((string) ($a1['url'] ?? ''), 'rolewarden/roles/create') && (bstep($r, 'enter-act')['status'] ?? 0) === 200, json_encode([$a0['options'] ?? null, $a1, bstep($r, 'enter-act')]));
$i0 = $v('i0'); $i1 = $v('i1');
check('K-8', 'A typed identifier (users.del): Enter on the top result opens Check a permission on users.delete', str_contains((string) ($i0['options'][0]['href'] ?? ''), 'permission=users.delete') && ($i1['sel'] ?? null) === 'users.delete', json_encode([$i0['options'][0] ?? null, $i1]));
$o1 = $v('o1'); $o2 = $v('o2'); $o3 = $v('o3');
check('K-9', 'Ctrl K works on other screens (Check a permission, the search page) and Esc closes', ($o1['open'] ?? false) && ($o1['activeIsField'] ?? false) && ! ($o2['open'] ?? true) && ($o3['open'] ?? false), json_encode([$o1, $o2, $o3]));
$st = $s1 ?: [];
$ax = ['side' => $v('ax-side'), 'dialog' => $v('ax-dialog'), 'field' => $v('ax-field'), 'list' => $v('ax-list'), 'opts' => $v('ax-opts')];
info('accessibility tree: ' . json_encode($ax, JSON_UNESCAPED_UNICODE));
check('K-aria', 'ARIA as Chrome computes it: dialog with a name, combobox with a name and aria-controls on the listbox (named), aria-expanded, options with role option; the sidebar button has a name',
    ($ax['dialog'][0]['role'] ?? '') === 'dialog' && (string) ($ax['dialog'][0]['name'] ?? '') !== '' && ($ax['field'][0]['role'] ?? '') === 'combobox' && (string) ($ax['field'][0]['name'] ?? '') !== ''
    && ($ax['list'][0]['role'] ?? '') === 'listbox' && (string) ($ax['list'][0]['name'] ?? '') !== '' && ($st['controls'] ?? '') === 'rw-cmd-results' && in_array($st['expanded'] ?? null, ['true', 'false'], true)
    && count($ax['opts'] ?? []) > 0 && ! array_filter($ax['opts'], fn ($o) => $o['role'] !== 'option') && (string) ($ax['side'][0]['name'] ?? '') !== '', json_encode($ax));
check('K-scrim', 'A click on the scrim (outside the panel) closes the search', $v('scrim') === false, json_encode($v('scrim')));
$reqs = array_values(array_filter(array_merge($v('req0') ?? [], $v('req1') ?? []), fn ($x) => str_contains($x['url'], '/rolewarden/search')));
$nonGet = array_filter($reqs, fn ($x) => $x['method'] !== 'GET');
info('search requests made by the page: ' . json_encode(array_map(fn ($x) => $x['method'] . ' ' . $x['url'] . ' ' . ($x['headers']['X-Requested-With'] ?? ''), $reqs)));
check('K-req', 'The overlay asks the server (GET /rolewarden/search, results alone) and never with another method', $reqs !== [] && $nonGet === [], json_encode($nonGet));
$diff = statediff($before, $after);
check('K-db', 'Opening, typing, moving and choosing (screens, an action, an identifier) wrote nothing to the database (sign-in bookkeeping aside)', $diff === [], json_encode(array_slice($diff, 0, 8)));
check('K-js', 'No JavaScript error on the pages', ($r['consoleErrors'] ?? []) === [] && $r['errors'] === [], json_encode([$r['consoleErrors'] ?? null, $r['errors']]));

// ================================================================ MAC: Cmd K on macOS
$mac = ['op' => 'ua', 'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'platform' => 'MacIntel',
    'metadata' => ['platform' => 'macOS', 'platformVersion' => '14.0.0', 'architecture' => 'x86', 'model' => '', 'mobile' => false, 'brands' => [['brand' => 'Chromium', 'version' => '130']]]];
$r = u3browser([$mac, blogin('owner@v8b.test', OPW), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'g'], $E('m0', $STATE), $K('k', 'KeyK', 75, 4, 'cmdk'), $E('m1', $STATE), $ESC(), $E('m2', $STATE)], 'ov mac');
$m0 = bval($r, 'm0'); $m1 = bval($r, 'm1'); $m2 = bval($r, 'm2');
check('K-mac', 'On macOS the button shows Cmd K and Cmd K opens the search (field focused), Esc closes', str_contains((string) ($m0['kbd'] ?? ''), '⌘') && ($m1['open'] ?? false) && ($m1['activeIsField'] ?? false) && ! ($m2['open'] ?? true), json_encode([$m0['kbd'] ?? null, $m1, $m2], JSON_UNESCAPED_UNICODE));
info('kbd on Windows: ' . json_encode($s0['kbd'] ?? null, JSON_UNESCAPED_UNICODE));

// ================================================================ RAIL: the collapsed sidebar
$r = u3browser([blogin('owner@v8b.test', OPW), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'g'],
    ['op' => 'click', 'selector' => '.rw-side-toggle', 'nav' => true, 'tag' => 'collapse'],
    $E('r0', '(() => { const b = document.querySelector(".rw-side-search"); if (!b) return null; const r = b.getBoundingClientRect(); const t = b.querySelector(".rw-side-text"); const sh = document.querySelector(".rw-shell"); return { rail: sh.classList.contains("rw-shell--rail"), w: r.width, h: r.height, visible: r.width > 0 && r.height > 0, textShown: !!(t && t.getClientRects().length && getComputedStyle(t).visibility !== "hidden" && t.getBoundingClientRect().width > 1), title: b.getAttribute("title"), aria: b.getAttribute("aria-label") }; })()'),
    ['op' => 'ax', 'selector' => '.rw-side-search', 'tag' => 'ax'],
    $E('rf', '(() => { const b = document.querySelector(".rw-side-search"); b.focus(); return document.activeElement === b; })()'),
    $K('Enter', 'Enter', 13, 0, 'enter'), $E('r1', $STATE), $ESC(), $E('r2', $STATE),
    $CTRLK(), $E('r3', $STATE), $ESC(),
    ['op' => 'click', 'selector' => '.rw-side-toggle', 'nav' => true, 'tag' => 'expand'], $E('r4', '(() => document.querySelector(".rw-shell").classList.contains("rw-shell--rail"))()'),
], 'ov rail');
$r0 = bval($r, 'r0'); $rax = bval($r, 'ax'); $r1 = bval($r, 'r1'); $r2 = bval($r, 'r2'); $r3 = bval($r, 'r3');
info('rail: ' . json_encode([$r0, $rax], JSON_UNESCAPED_UNICODE));
check('K-rail', 'Collapsed rail: the search is an icon button (label hidden) with an accessible name; Enter on it and Ctrl K open the search, Esc gives focus back',
    ($r0['rail'] ?? false) && ($r0['visible'] ?? false) && ! ($r0['textShown'] ?? true) && (string) ($rax[0]['name'] ?? '') !== '' && ($r1['open'] ?? false) && ($r1['activeIsField'] ?? false) && ($r2['activeSide'] ?? false) && ($r3['open'] ?? false) && bval($r, 'r4') === false,
    json_encode([$r0, $rax, $r1, $r2, $r3, bval($r, 'r4')]));

// ================================================================ LOOK: 3 themes x light/dark x 3 sidebar styles
echo "\n-- LOOK\n";
$css = '';
foreach (dom(G($own, 'rolewarden/users', 'ov css'))->query('//link[@rel="stylesheet"]') as $lk) { $u = html_entity_decode($lk->getAttribute('href')); $cc = c8('ov-css'); $cc->get($u); if (str_contains($u, 'panel.css')) $css = $cc->body; }
preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $mm);
$TOK = array_values(array_unique($mm[1]));
info('custom properties declared in the served CSS: ' . count($TOK));
file_put_contents(__DIR__ . '/verify-u3.ov.' . ($GLOBALS['LABEL'] ?? 'run') . '.served-panel.css', $css);
$MEAS = <<<'JS'
(names) => {
  const wrap = document.createElement('div'); wrap.style.color = 'rgb(1, 2, 3)'; const probe = document.createElement('span'); wrap.appendChild(probe); document.body.appendChild(wrap);
  const col = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const shd = (t) => { probe.style.boxShadow = ''; probe.style.boxShadow = 'var(' + t + ')'; return getComputedStyle(probe).boxShadow; };
  const T = {}; for (const n of names) { const c = col(n); if (c !== 'rgb(1, 2, 3)') T[n] = c; }
  const SH = shd('--shadow-float'); probe.style.borderRadius = 'var(--radius-md)'; const RAD = getComputedStyle(probe).borderTopLeftRadius; wrap.remove();
  const allowed = new Set(Object.values(T));
  const parse = (c) => { const m = String(c).match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lin = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const ratio = (x, y) => { const [p, q] = [lum(x), lum(y)].sort((u, v) => v - u); return Math.round(((p + 0.05) / (q + 0.05)) * 100) / 100; };
  const over = (t, u) => ({ r: t.r * t.a + u.r * (1 - t.a), g: t.g * t.a + u.g * (1 - t.a), b: t.b * t.a + u.b * (1 - t.a), a: 1 });
  const bgOf = (el) => { const L = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c.a > 0) { L.push(c); if (c.a >= 1) break; } } let b = parse(getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 }; for (let i = L.length - 1; i >= 0; i--) b = over(L[i], b); return b; };
  const okColor = (c) => { const p = parse(c); return !p || p.a === 0 || allowed.has(c) || allowed.has('rgb(' + p.r + ', ' + p.g + ', ' + p.b + ')'); };
  const audit = (root) => { const bad = [], low = []; let min = 99;
    for (const el of [root, ...root.querySelectorAll('*')]) {
      if (!el.getClientRects().length) continue;
      const s = getComputedStyle(el); if (s.visibility === 'hidden' || Number(s.opacity) === 0) continue;
      const nm = el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).join('.') : '');
      const props = { color: s.color, background: s.backgroundColor };
      for (const side of ['Top', 'Right', 'Bottom', 'Left']) if (parseFloat(s['border' + side + 'Width']) > 0 && s['border' + side + 'Style'] !== 'none') props['border' + side] = s['border' + side + 'Color'];
      if (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) props.outline = s.outlineColor;
      if (el.tagName.toLowerCase() === 'svg' || el.closest('svg')) { props.stroke = s.stroke; delete props.background; }
      for (const [k, c] of Object.entries(props)) if (c && c !== 'none' && !okColor(c)) bad.push(nm + ' ' + k + ' ' + c);
      const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
      if (own) { const fg = parse(s.color); const bg = bgOf(el); const r = ratio(over(fg, bg), bg); min = Math.min(min, r); if (r < 4.5) low.push(nm + ' "' + el.textContent.trim().slice(0, 30) + '" ' + r); }
    }
    return { bad: bad.slice(0, 20), badCount: bad.length, low, min }; };
  const d = document.querySelector('dialog.rw-cmd'), q = document.getElementById('rw-cmd-q');
  const ds = d ? getComputedStyle(d) : null, rect = d ? d.getBoundingClientRect() : null, bd = d ? getComputedStyle(d, '::backdrop').backgroundColor : null;
  const opts = d ? [...d.querySelectorAll('[role=option]')] : [];
  const sel = opts.find((o) => o.getAttribute('aria-selected') === 'true'), other = opts.find((o) => o.getAttribute('aria-selected') !== 'true');
  const hint = (o) => { if (!o) return null; const k = o.querySelector('kbd'); if (!k) return false; const s = getComputedStyle(k); return !!k.getClientRects().length && s.visibility !== 'hidden' && Number(s.opacity) > 0; };
  const qs = q ? getComputedStyle(q) : null;
  const lab = d ? d.querySelector('.rw-cmd-label') : null, foot = d ? d.querySelector('.rw-cmd-foot') : null;
  const side = document.querySelector('.rw-side-search');
  return { theme: document.documentElement.getAttribute('data-rw-theme'), dark: matchMedia('(prefers-color-scheme: dark)').matches, style: (document.querySelector('aside.rw-side') || {}).dataset ? document.querySelector('aside.rw-side').dataset.sidebar : null,
    radiusMd: RAD, T: { surfaceRaised: T['--surface-raised'], surface: T['--surface'], ink: T['--ink'], inkMuted: T['--ink-muted'], inkFaint: T['--ink-faint'], accentTint: T['--accent-tint'], focus: T['--focus'], rule: T['--rule'], ruleStrong: T['--rule-strong'] }, shadowFloat: SH,
    open: !!(d && d.open), w: rect ? rect.width : null, top: rect ? rect.top : null, centered: rect ? Math.abs((innerWidth - rect.width) / 2 - rect.left) < 2 : null,
    bg: ds ? ds.backgroundColor : null, shadow: ds ? ds.boxShadow : null, radius: ds ? ds.borderTopLeftRadius : null, backdrop: bd,
    field: qs ? { focused: document.activeElement === q, outlineStyle: qs.outlineStyle, outlineWidth: qs.outlineWidth, outlineColor: qs.outlineColor, outlineOffset: qs.outlineOffset, font: qs.fontFamily } : null,
    sel: sel ? { bg: getComputedStyle(sel).backgroundColor, hint: hint(sel) } : null, other: other ? { bg: getComputedStyle(other).backgroundColor, hint: hint(other) } : null, nOpts: opts.length,
    label: lab ? { color: getComputedStyle(lab).color, text: lab.textContent.trim() } : null, foot: foot ? { bg: getComputedStyle(foot).backgroundColor, color: getComputedStyle(foot).color } : null,
    dlg: d ? audit(d) : null, side: side ? audit(side) : null, sideColor: side ? getComputedStyle(side).color : null };
}
JS;
$FOCUS = <<<'JS'
(names) => {
  const wrap = document.createElement('div'); const probe = document.createElement('span'); wrap.appendChild(probe); document.body.appendChild(wrap);
  probe.style.color = 'var(--focus)'; const focus = getComputedStyle(probe).color; wrap.remove();
  const ring = (el) => { if (!el) return null; el.focus(); const s = getComputedStyle(el); return { active: document.activeElement === el, fv: el.matches(':focus-visible'), style: s.outlineStyle, width: s.outlineWidth, color: s.outlineColor, offset: s.outlineOffset }; };
  const nav = ring(document.querySelector('aside .rw-side-nav a')), main = ring(document.querySelector('main .rw-btn, main a.rw-btn, main button'));
  const b = ring(document.querySelector('.rw-side-search'));
  return Object.assign({}, b, { nav, main, focus });
}
JS;
$LOOK = [];
foreach (['console', 'clarity', 'contrast'] as $theme) foreach (['paper', 'ink', 'charcoal'] as $style) {
    theme_save($adm, ['base' => $theme, 'sidebar' => $style], "ov theme $theme $style");
    $steps = [blogin('owner@v8b.test', OPW)];
    foreach (['light', 'dark'] as $mode) {
        $steps[] = ['op' => 'emulate', 'scheme' => $mode, 'motion' => 'no-preference'];
        $steps[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => "g-$mode"];
        $steps[] = $K('Tab', 'Tab', 9, 0, "tab-$mode");
        if ($style !== 'paper' && $mode === 'light') $steps[] = ['op' => 'screenshot', 'path' => __DIR__ . "/verify-u3.ov-side-$theme-$style-$mode." . ($GLOBALS['LABEL'] ?? 'run') . '.png', 'tag' => "side-$mode"];
        $steps[] = $E("focus-$mode", '(' . $FOCUS . ')(' . json_encode($TOK) . ')');
        $steps[] = $CTRLK("ck-$mode");
        $steps[] = ['op' => 'type', 'text' => 'role', 'wait' => 900];
        $steps[] = $E("m-$mode", '(' . $MEAS . ')(' . json_encode($TOK) . ')');
        if ($style === 'paper' || $theme === 'console') $steps[] = ['op' => 'screenshot', 'path' => __DIR__ . "/verify-u3.ov-$theme-$style-$mode." . ($GLOBALS['LABEL'] ?? 'run') . '.png', 'tag' => "shot-$mode"];
        $steps[] = $ESC("esc-$mode");
    }
    $r = u3browser($steps, "ov look $theme $style");
    foreach (['light', 'dark'] as $mode) {
        $m = bval($r, "m-$mode"); $f = bval($r, "focus-$mode");
        $id = "LK-$theme-$style-$mode";
        if (! is_array($m) || ! ($m['open'] ?? false)) { check("$id-0", "$theme/$style/$mode measured", false, json_encode([$r['errors'], $m])); continue; }
        $LOOK[$id] = ['dlgMin' => $m['dlg']['min'] ?? null, 'sideMin' => $m['side']['min'] ?? null, 'w' => $m['w'], 'top' => $m['top'], 'backdrop' => $m['backdrop']];
        $T = $m['T'];
        $ink = parse_rgba3($T['ink']); $bd = parse_rgba3((string) $m['backdrop']);
        $geo = abs($m['w'] - 600) < 1 && abs($m['top'] - 72) < 1 && $m['centered'] && $m['bg'] === $T['surfaceRaised'] && $m['shadow'] === $m['shadowFloat'] && $m['shadow'] !== 'none' && $m['radius'] === $m['radiusMd'] && $bd && abs($bd[3] - 0.45) < 0.011;
        $SCRIM["$theme/$mode"] = [$m['backdrop'], $T['ink']];
        check("$id-1", "$theme/$style/$mode: a 600px `surface-raised` panel, centred, 72px from the top, `shadow-float`, `radius-md` ({$m['radiusMd']}), over a 45% scrim", $geo, json_encode(['w' => $m['w'], 'top' => $m['top'], 'centered' => $m['centered'], 'bg' => $m['bg'], 'T' => $T['surfaceRaised'], 'shadow' => $m['shadow'], 'float' => $m['shadowFloat'], 'radius' => $m['radius'], 'backdrop' => $m['backdrop'], 'ink' => $T['ink']]));
        check("$id-2", "$theme/$style/$mode: the highlighted row takes `accent-tint` and shows the Enter hint, the others do not", ($m['sel']['bg'] ?? null) === $T['accentTint'] && ($m['sel']['hint'] ?? false) === true && ($m['other'] === null || (($m['other']['bg'] ?? '') !== $T['accentTint'] && ($m['other']['hint'] ?? true) === false)), json_encode([$m['sel'], $m['other'], $T['accentTint']]));
        $mr = $f['main'] ?? []; $nr = $f['nav'] ?? [];
        check("$id-3", "$theme/$style/$mode: the field has focus with the panel's ring (`focus` colour, the width and offset of a content button's ring); the sidebar button reached from the keyboard has the same ring as the sidebar items",
            ($m['field']['focused'] ?? false) && ($m['field']['outlineStyle'] ?? 'none') !== 'none' && ($m['field']['outlineColor'] ?? '') === $T['focus'] && ($m['field']['outlineWidth'] ?? '') === ($mr['width'] ?? 'x') && ($m['field']['outlineOffset'] ?? '') === ($mr['offset'] ?? 'x')
            && ($f['active'] ?? false) && ($f['style'] ?? 'none') !== 'none' && ($nr['style'] ?? 'none') !== 'none' && [$f['style'], $f['width'], $f['color'], $f['offset']] === [$nr['style'] ?? null, $nr['width'] ?? null, $nr['color'] ?? null, $nr['offset'] ?? null], json_encode(['field' => $m['field'], 'button' => $f, 'focus' => $T['focus']]));
        check("$id-4", "$theme/$style/$mode: every colour in the dialog and on the sidebar button is a token value", ($m['dlg']['badCount'] ?? 1) === 0 && ($m['side']['badCount'] ?? 1) === 0, json_encode(['dlg' => $m['dlg']['bad'] ?? null, 'side' => $m['side']['bad'] ?? null]));
        check("$id-5", "$theme/$style/$mode: text contrast at least 4.5:1 in the dialog (min {$m['dlg']['min']}) and on the sidebar button (min {$m['side']['min']})", ($m['dlg']['low'] ?? [1]) === [] && ($m['side']['low'] ?? [1]) === [], json_encode(['dlg' => $m['dlg']['low'] ?? null, 'side' => $m['side']['low'] ?? null]));
        check("$id-6", "$theme/$style/$mode: group labels `ink-muted`, footer on `surface`", ($m['label']['color'] ?? null) === $T['inkMuted'] && ($m['foot']['bg'] ?? null) === $T['surface'], json_encode([$m['label'], $m['foot'], $T['inkMuted'], $T['surface']]));
    }
    if (! empty($r['consoleErrors'])) check("LK-$theme-$style-js", "$theme/$style: no JavaScript error", false, json_encode($r['consoleErrors']));
}
function parse_rgba3(string $c): ?array { return preg_match('/rgba?\(([^)]+)\)/', $c, $m) ? array_map('floatval', preg_split('/[ ,\/]+/', trim($m[1]))) + [3 => 1.0] : null; }
info('look summary: ' . json_encode($LOOK));
info('scrim vs ink token: ' . json_encode($SCRIM ?? []));
$fixed = count(array_unique(array_column($SCRIM ?? [], 0))) === 1;
$offInk = array_filter($SCRIM ?? [], fn ($x) => ($p = parse_rgba3($x[0])) && ($i = parse_rgba3($x[1])) && (abs($p[0] - $i[0]) > 1 || abs($p[1] - $i[1]) > 1 || abs($p[2] - $i[2]) > 1));
// U3 A1 decided (6d65c48, CommandSearch README): a fixed dark scrim, rgba(23, 25, 28, 0.45), in light and dark mode alike
$badScrim = array_filter($SCRIM ?? [], fn ($x) => ! (($p = parse_rgba3($x[0])) && abs($p[0] - 23) < 1 && abs($p[1] - 25) < 1 && abs($p[2] - 28) < 1 && abs($p[3] - 0.45) < 0.011));
check('LK-scrim', 'The scrim is the fixed dark rgba(23, 25, 28, 0.45) in every theme, in light and dark mode (decision A1; ' . count($SCRIM ?? []) . ' theme/mode pairs)', ($SCRIM ?? []) !== [] && $badScrim === [], json_encode($badScrim ?: ($SCRIM ?? [])));
theme_save($adm, ['base' => 'console', 'sidebar' => ''], 'ov theme back');
check('OV-clean', 'OV pages: no 5xx, no SQL or PHP error', seen_clean($S0) === [], json_encode(seen_clean($S0)));
