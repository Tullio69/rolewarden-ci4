<?php
// Phase Y: layout measured in Chrome headless at 1280, 1366 and 1920 px (design system: "Every table carries a
// left gutter space-12 wide for row numbers"; documentation/design-guide.html: --gutter "Page margin and
// row-number gutter"; changelog of 07af67a/f8b2f57/11da43e: row-number column one gutter wide on every screen,
// Permissions columns in line across areas, matrix headers keep a gap).
$MEASURE = <<<'JS'
(() => {
  const px = (v) => { const d = document.createElement('div'); d.style.position = 'absolute'; d.style.width = v; document.body.appendChild(d); const w = d.getBoundingClientRect().width; d.remove(); return w; };
  const gutter = px('var(--gutter)');
  const out = { gutter, vw: innerWidth, overflowX: document.documentElement.scrollWidth - innerWidth, tables: [], h1: (document.querySelector('h1') || {}).textContent || '' };
  out.wide = [...document.querySelectorAll('body *')].filter((e) => e.getClientRects().length && e.getBoundingClientRect().right > innerWidth + 1 && !e.closest('#debug-bar, #toolbarContainer, [id^=debugbar], .kint-rich')).map((e) => e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/).join('.') + ' ' + Math.round(e.getBoundingClientRect().right)).slice(0, 8);
  { const w = [...document.querySelectorAll('main *')].filter((e) => e.getClientRects().length && e.getBoundingClientRect().right > innerWidth + 1); out.cause = w.filter((e) => ![...e.children].some((c) => w.includes(c))).map((e) => e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/).join('.') + ' w' + Math.round(e.getBoundingClientRect().width) + ' r' + Math.round(e.getBoundingClientRect().right) + ' "' + e.textContent.trim().slice(0, 30) + '"').slice(0, 6); }
  { const probe = (sel) => { const els = [...document.querySelectorAll(sel)]; els.forEach((e) => { e.dataset.rwd = e.style.display; e.style.display = 'none'; }); const o = document.documentElement.scrollWidth - innerWidth; els.forEach((e) => { e.style.display = e.dataset.rwd; }); return o; };
    out.withoutLanguageSwitch = probe('header form[action$="profile/language"]'); out.withoutTopbarEnd = probe('header .rw-topbar-end'); out.withoutMain = probe('main'); }
  out.debugbar = !!document.querySelector('#debug-bar, #toolbarContainer');
  for (const t of document.querySelectorAll('main table')) {
    if (!t.getClientRects().length) continue;
    const rows = [...t.querySelectorAll('tbody tr')].filter((r) => r.getClientRects().length && r.cells.length > 1);
    const nums = rows.map((r) => r.cells[0]).filter((c) => /^\s*\d{1,3}\s*$/.test(c.textContent));
    const firstW = rows.length ? rows[0].cells[0].getBoundingClientRect().width : null;
    const clipped = nums.filter((c) => c.scrollWidth > c.clientWidth + 1 || (() => { const r = document.createRange(); r.selectNodeContents(c); const b = r.getBoundingClientRect(), cb = c.getBoundingClientRect(); return b.left < cb.left - 0.5 || b.right > cb.right + 0.5; })()).map((c) => c.textContent.trim());
    const cols = rows.length ? [...rows[0].cells].map((c) => Math.round(c.getBoundingClientRect().left * 10) / 10) : [];
    const widths = rows.length ? [...rows[0].cells].map((c) => Math.round(c.getBoundingClientRect().width * 10) / 10) : [];
    const head = [...t.querySelectorAll('thead th')].map((th) => { const r = th.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.right)]; });
    const ths = [...t.querySelectorAll('thead th')].map((th) => [...th.querySelectorAll('span, input')].map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.right), e.tagName]; }));
    out.tables.push({ cls: t.className, rows: rows.length, numbered: nums.length, firstW, clipped, cols, widths, head, ths, tableRight: Math.round(t.getBoundingClientRect().right), scrollW: t.scrollWidth, clientW: t.clientWidth });
  }
  return out;
})()
JS;
$ROLEPAGE = role_id('demo-approver');
$SCREENS = ['rolewarden/users', "rolewarden/users/$PLAIN", 'rolewarden/roles', "rolewarden/roles/$ROLEPAGE", 'rolewarden/roles/' . role_id('admin'), 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/sessions', 'rolewarden/settings', 'rolewarden/profile', 'rolewarden/appearance', 'rolewarden/users/import'];
$cY = serve8($APP, 'layout');
$steps = [blogin('owner@v8b.test', OPW)];
foreach ([1280, 1366, 1920] as $w) {
    $steps[] = ['op' => 'viewport', 'width' => $w, 'height' => 900];
    foreach ($SCREENS as $u) {
        $steps[] = ['op' => 'goto', 'url' => BASE . $u, 'tag' => "g $w $u"];
        $steps[] = ['op' => 'eval', 'expr' => $MEASURE, 'tag' => "m $w $u"];
        if (in_array($u, ["rolewarden/roles/$ROLEPAGE", 'rolewarden/permissions', "rolewarden/users/$PLAIN"], true)) $steps[] = ['op' => 'screenshot', 'path' => "$WORK/verify-v8b.layout-$w-" . preg_replace('/\W+/', '-', $u) . '.png', 'full' => true, 'tag' => "s $w $u"];
    }
}
$r = browser($steps, 'layout', 900000);
file_put_contents("$WORK/verify-v8b.layout.json", json_encode($r['steps'], JSON_PRETTY_PRINT));
foreach (glob("$WORK/verify-v8b.layout-*.png") as $f) @copy($f, __DIR__ . '/' . basename($f));
@copy("$WORK/verify-v8b.layout.json", __DIR__ . "/verify-v8b.$LABEL.layout.json");
check('Y00', 'Chrome headless ran every layout step without driver errors or page exceptions', $r['errors'] === [] && ($r['consoleErrors'] ?? []) === [], json_encode([$r['errors'], $r['consoleErrors'] ?? []]));
foreach ([1280, 1366, 1920] as $w) {
    $bad = []; $badOverflow = []; $clip = []; $permAlign = []; $tablesSeen = 0;
    foreach ($SCREENS as $u) {
        $m = bval($r, "m $w $u");
        $st = bstep($r, "g $w $u");
        if (! is_array($m)) { $bad[] = "$u: no measure"; continue; }
        if (($st['status'] ?? 0) !== 200) $bad[] = "$u: status " . ($st['status'] ?? '?');
        if ($m['overflowX'] > 0) $badOverflow[] = "$u +{$m['overflowX']}px (" . implode(', ', array_slice($m['wide'], 0, 4)) . ' | leaves in main: ' . implode(', ', $m['cause']) . ($m['debugbar'] ? '; debug toolbar present' : '') . "; overflow without the language switch {$m['withoutLanguageSwitch']}px, without the top-bar controls {$m['withoutTopbarEnd']}px, without main {$m['withoutMain']}px)";
        foreach ($m['tables'] as $t) {
            if ($t['numbered'] === 0) continue;
            $tablesSeen++;
            if (abs($t['firstW'] - $m['gutter']) > 1) $bad[] = "$u {$t['cls']}: number column {$t['firstW']}px, gutter {$m['gutter']}px";
            if ($t['clipped']) $clip[] = "$u {$t['cls']}: " . implode(',', array_slice($t['clipped'], 0, 5));
        }
        if ($u === 'rolewarden/permissions') {
            $sets = array_values(array_filter(array_map(fn ($t) => $t['cols'], $m['tables']), fn ($c) => count($c) > 1));
            $first = $sets[0] ?? [];
            foreach ($sets as $i => $c) if ($c !== $first) $permAlign[] = "table $i: " . json_encode($c) . ' vs ' . json_encode($first);
            $GLOBALS['Y_PERM_TABLES'][$w] = count($sets);
        }
    }
    check("Y01-$w", "$w px: every numbered table's row-number column is exactly one gutter wide ($tablesSeen tables on " . count($SCREENS) . ' screens)', $bad === [] && $tablesSeen > 0, implode(' | ', $bad));
    check("Y02-$w", "$w px: no row number clipped", $clip === [], implode(' | ', $clip));
    check("Y03-$w", "$w px: no horizontal page scroll on any screen", $badOverflow === [], implode(' | ', $badOverflow));
    check("Y04-$w", "$w px: Permissions, the columns of every area table start at the same x (" . ($GLOBALS['Y_PERM_TABLES'][$w] ?? 0) . ' tables)', $permAlign === [] && ($GLOBALS['Y_PERM_TABLES'][$w] ?? 0) > 1, implode(' | ', $permAlign));
    // matrix headers: the column header label and its checkbox do not overlap the next header
    $m = bval($r, "m $w rolewarden/roles/$ROLEPAGE");
    $ov = [];
    foreach (($m['tables'] ?? []) as $t) {
        if (! str_contains($t['cls'], 'matrix')) continue;
        foreach ($t['ths'] as $i => $els) foreach ($els as $e) { [$l, $rr] = $t['head'][$i]; if ($e[0] < $l - 1 || $e[1] > $rr + 1) $ov[] = "th$i {$e[2]} [{$e[0]},{$e[1]}] outside [$l,$rr]"; }
        if ($t['scrollW'] > $t['clientW'] + 1) $ov[] = "matrix scrollWidth {$t['scrollW']} > {$t['clientW']}";
    }
    check("Y05-$w", "$w px: role matrix, every column header label and box stays inside its own column", $m !== null && $ov === [], implode(' | ', array_slice($ov, 0, 8)));
}
server_stop();
