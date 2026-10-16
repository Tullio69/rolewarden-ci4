<?php
// Phase Y (S1, point 2): layout in Chrome headless at 1280, 1366, 1440 and 1920 px, with data of its own (an area with
// many long actions, a role with a long name, many users, many overrides, long activity rows).
// docs/design-system/README.md: "Below 1440px the page never scrolls sideways: the content column stops at the window
// and anything wider scrolls inside it, and the permission matrix tightens to 80px action columns and a 200px marginal
// column"; PermissionMatrix README: "five 104px action columns ... a 200px marginal column"; SPEC 2026-10-16.
// Row-number, clipping and matrix-header checks as V8b phase Y.
$now = date('Y-m-d H:i:s');
// first, the matrix with the module's own data only (15 action columns), before any fixture of this phase
$MATW = <<<'JS'
(() => { const t = document.querySelector('table.rw-matrix'); if (!t) return null; const w = (sel) => [...t.querySelectorAll(sel)].map((th) => Math.round(th.getBoundingClientRect().width * 10) / 10);
  const sc = t.parentElement; return { act: w('thead th.rw-m-act'), anno: w('thead th.rw-m-anno'), area: w('thead th.rw-m-area'), tableW: Math.round(t.getBoundingClientRect().width), boxW: sc.clientWidth, boxScrollW: sc.scrollWidth, overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth }; })()
JS;
$cY0 = serve8($APP, 'layout0');
$st0 = [blogin('owner@v8b.test', OPW)];
foreach ([1280, 1366, 1440, 1920] as $w) { $st0[] = ['op' => 'viewport', 'width' => $w, 'height' => 900]; foreach (['demo-approver', 'admin'] as $rs) { $st0[] = ['op' => 'goto', 'url' => BASE . 'rolewarden/roles/' . role_id($rs), 'tag' => "g0 $w $rs"]; $st0[] = ['op' => 'eval', 'expr' => $MATW, 'tag' => "w0 $w $rs"]; } }
$r0 = browser($st0, 'matrix widths, module data');
server_stop();
foreach ([1280, 1366, 1440, 1920] as $w) {
    $want = $w < 1440 ? 80 : 104; $got = []; $ok = true;
    foreach (['demo-approver', 'admin'] as $rs) { $m = bval($r0, "w0 $w $rs"); $got[$rs] = $m; if (! $m || ! $m['act'] || array_filter($m['act'], fn ($x) => abs($x - $want) > 1) || count($m['anno']) !== 1 || abs($m['anno'][0] - 200) > 1) $ok = false; }
    info("$w px, module data only, matrix widths (information; the first round's Y07 against 80/104/200 px, withdrawn by the SPEC correction of 79a0cda): " . json_encode($got));
}
$acts = ['receive-goods', 'dispatch-orders', 'reconcile-inventory', 'approve-transfers', 'export-ledger', 'audit-stock', 'print-labels', 'close-period', 'reopen-period', 'archive', 'forecast-demand', 'override-limits'];
foreach ($acts as $a) q('INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES (?,?,?,0,?,?)', ["warehouse-operations.$a", 'warehouse-operations', 'Lets the holder ' . str_replace('-', ' ', $a) . ' in every warehouse of every region, including the ones still being set up, with a long description on purpose', $now, $now]);
q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['s1-big', 'Regional warehouse supervisor with a deliberately long role name', str_repeat('A long role description written for the layout check of S1. ', 4), $now, $now]);
$BIG = role_id('s1-big');
foreach ($acts as $i => $a) if ($i % 2 === 0) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$BIG, perm_id("warehouse-operations.$a")]);
q('INSERT INTO acl_role_permissions (role_id, permission_id) SELECT ?, id FROM acl_permissions WHERE area IN (?,?)', [$BIG, 'users', 'roles']);
$LU = [];
for ($i = 1; $i <= 26; $i++) $LU[] = make_owner(sprintf('layout-user-%02d-with-a-long-address@s1.test', $i), OPW, sprintf('layout_user_%02d_longname', $i), $i % 2 ? 's1-big' : 'admin');
foreach (['admin', 'user', 'demo-reader', 'demo-author'] as $r) q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$LU[0], role_id($r)]);
foreach (array_slice(array_column(q('SELECT id FROM acl_permissions ORDER BY id'), 'id'), 0, 14) as $k => $pid) q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,?,?,?)', [$LU[0], $pid, $k % 2, $now, $now]);
for ($i = 0; $i < 30; $i++) q('INSERT INTO acl_activity_log (actor_id, actor_label, action, subject_type, subject_id, subject_label, details, ip_address, created_at) VALUES (?,?,?,?,?,?,?,?,?)', [$OWNER, 'v8bowner', 'role.updated', 'role', $BIG, 'Regional warehouse supervisor with a deliberately long role name ' . $i, json_encode(['description' => str_repeat('changed text ', 20)]), '127.0.0.1', $now]);
sp8($APP, ['cache:clear']);

$MEASURE = <<<'JS'
(() => {
  const px = (v) => { const d = document.createElement('div'); d.style.position = 'absolute'; d.style.width = v; document.body.appendChild(d); const w = d.getBoundingClientRect().width; d.remove(); return w; };
  const gutter = px('var(--gutter)');
  const vw = document.documentElement.clientWidth;
  const main = document.querySelector('main');
  const mr = main ? main.getBoundingClientRect() : null;
  const out = { gutter, vw, innerWidth, overflowX: document.documentElement.scrollWidth - vw, bodyOverflowX: document.body.scrollWidth - vw, mainRight: mr ? Math.round(mr.right) : null, tables: [], h1: (document.querySelector('h1') || {}).textContent || '' };
  out.wide = [...document.querySelectorAll('body *')].filter((e) => e.getClientRects().length && e.getBoundingClientRect().right > vw + 1 && !e.closest('#debug-bar, #toolbarContainer, [id^=debugbar], .kint-rich')).map((e) => e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/).join('.') + ' ' + Math.round(e.getBoundingClientRect().right)).slice(0, 8);
  // anything in main wider than main must sit in a scrolling box inside main
  const scroller = (e) => { for (let p = e.parentElement; p && p !== main.parentElement; p = p.parentElement) { const s = getComputedStyle(p); if (['auto', 'scroll'].includes(s.overflowX) && p.scrollWidth > p.clientWidth + 1) return p; if (p === main) break; } return null; };
  out.unreachable = []; out.scrollers = [];
  if (main) {
    const seen = new Set();
    for (const e of main.querySelectorAll('*')) {
      if (!e.getClientRects().length) continue;
      const r = e.getBoundingClientRect();
      if (r.right <= mr.right + 1) continue;
      const sc = scroller(e);
      if (!sc) { out.unreachable.push(e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/).join('.') + ' r' + Math.round(r.right) + ' main' + Math.round(mr.right)); continue; }
      if (!seen.has(sc)) seen.add(sc);
    }
    out.unreachable = out.unreachable.slice(0, 8);
    for (const sc of seen) {
      const b = sc.getBoundingClientRect(); const max = sc.scrollWidth - sc.clientWidth;
      sc.scrollLeft = max; const got = sc.scrollLeft;
      const kids = [...sc.querySelectorAll('th, td, *')].filter((x) => x.getClientRects().length);
      const lastRight = Math.max(...kids.map((x) => x.getBoundingClientRect().right));
      sc.scrollLeft = 0;
      out.scrollers.push({ cls: sc.tagName.toLowerCase() + '.' + String(sc.className).trim().split(/\s+/).join('.'), left: Math.round(b.left), right: Math.round(b.right), scrollW: sc.scrollWidth, clientW: sc.clientWidth, maxReached: Math.abs(got - max) <= 1, lastVisible: lastRight <= b.right + 1 });
    }
  }
  out.debugbar = !!document.querySelector('#debug-bar, #toolbarContainer');
  for (const t of document.querySelectorAll('main table')) {
    if (!t.getClientRects().length) continue;
    const rows = [...t.querySelectorAll('tbody tr')].filter((r) => r.getClientRects().length && r.cells.length > 1);
    const nums = rows.map((r) => r.cells[0]).filter((c) => /^\s*\d{1,3}\s*$/.test(c.textContent));
    const firstW = rows.length ? rows[0].cells[0].getBoundingClientRect().width : null;
    const clipped = nums.filter((c) => c.scrollWidth > c.clientWidth + 1 || (() => { const r = document.createRange(); r.selectNodeContents(c); const b = r.getBoundingClientRect(), cb = c.getBoundingClientRect(); return b.left < cb.left - 0.5 || b.right > cb.right + 0.5; })()).map((c) => c.textContent.trim());
    const cols = rows.length ? [...rows[0].cells].map((c) => Math.round(c.getBoundingClientRect().left * 10) / 10) : [];
    const head = [...t.querySelectorAll('thead th')].map((th) => { const r = th.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.right)]; });
    const ths = [...t.querySelectorAll('thead th')].map((th) => [...th.querySelectorAll('span, input')].map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.right), e.tagName]; }));
    const act = [...t.querySelectorAll('thead th.rw-m-act')].map((th) => Math.round(th.getBoundingClientRect().width * 10) / 10);
    const anno = [...t.querySelectorAll('thead th.rw-m-anno')].map((th) => Math.round(th.getBoundingClientRect().width * 10) / 10);
    const ranno = [...new Set([...t.querySelectorAll('.rw-anno')].filter((e) => e.getClientRects().length).map((e) => Math.round(e.getBoundingClientRect().width)))];
    const actClip = [...t.querySelectorAll('thead th.rw-m-act')].filter((th) => th.scrollWidth > th.clientWidth + 1 || [...th.querySelectorAll('span, input')].some((e) => { const a = e.getBoundingClientRect(), b = th.getBoundingClientRect(); return a.left < b.left - 1 || a.right > b.right + 1; })).length;
    const box = t.parentElement; const fits = box.scrollWidth <= box.clientWidth + 1;
    out.tables.push({ ranno, actClip, fits, boxW: box.clientWidth, cls: t.className, rows: rows.length, numbered: nums.length, firstW, clipped, cols, head, ths, act, anno, tableRight: Math.round(t.getBoundingClientRect().right), scrollW: t.scrollWidth, clientW: t.clientWidth });
  }
  return out;
})()
JS;
$R9 = role_id('demo-approver');
$SCREENS = ['rolewarden/users', 'rolewarden/users?page=2', "rolewarden/users/{$LU[0]}", "rolewarden/users/{$LU[0]}/edit", 'rolewarden/users/create', 'rolewarden/users/import', 'rolewarden/roles', "rolewarden/roles/$BIG", "rolewarden/roles/$BIG/edit", "rolewarden/roles/$R9", 'rolewarden/roles/' . role_id('admin'), 'rolewarden/roles/create', 'rolewarden/permissions', 'rolewarden/activity', 'rolewarden/sessions', 'rolewarden/settings', 'rolewarden/profile', 'rolewarden/appearance', 'demo', 'demo/create'];
$WIDTHS = [1280, 1366, 1440, 1920];
$cY = serve8($APP, 'layout');
$runY = function (string $lang) use ($SCREENS, $WIDTHS, $MEASURE, $BIG, $WORK, $LABEL): array {
    $steps = [blogin('owner@v8b.test', OPW)];
    foreach ($WIDTHS as $w) {
        $steps[] = ['op' => 'viewport', 'width' => $w, 'height' => 900];
        foreach ($SCREENS as $u) {
            $steps[] = ['op' => 'goto', 'url' => BASE . $u, 'tag' => "g $w $u"];
            $steps[] = ['op' => 'eval', 'expr' => $MEASURE, 'tag' => "m $w $u"];
            if (in_array($u, ["rolewarden/roles/$BIG", 'rolewarden/permissions', 'rolewarden/users'], true) && in_array($w, [1280, 1440], true)) $steps[] = ['op' => 'screenshot', 'path' => "$WORK/verify-s1.layout-$lang-$w-" . preg_replace('/\W+/', '-', $u) . '.png', 'full' => false, 'tag' => "s $w $u"];
        }
    }
    return browser($steps, "layout $lang", 1500000);
};
$res = ['en' => $runY('en')];
// the same in Italian (longer labels)
q("INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES ('RoleWarden\\\\Config\\\\RoleWarden', 'locale', 'it', 'string', ?, ?, ?)", ['user:' . $OWNER, $now, $now]);
$res['it'] = $runY('it');
q("DELETE FROM settings WHERE `key`='locale' AND context=?", ['user:' . $OWNER]);
foreach (glob("$WORK/verify-s1.layout-*.png") as $f) @copy($f, __DIR__ . '/' . basename($f));
file_put_contents(__DIR__ . "/verify-s1.$LABEL.layout.json", json_encode(array_map(fn ($r) => $r['steps'], $res), JSON_PRETTY_PRINT));
info("h1 of the users page in the Italian run: " . (bval($res["it"], "m 1280 rolewarden/users")["h1"] ?? "?"));
foreach ($res as $lang => $r) {
    check("Y00-$lang", "[$lang] Chrome headless ran every layout step without driver errors or page exceptions", $r['errors'] === [] && ($r['consoleErrors'] ?? []) === [], json_encode([$r['errors'], $r['consoleErrors'] ?? []]));
    foreach ($WIDTHS as $w) {
        $bad = []; $over = []; $clip = []; $unr = []; $scr = []; $tablesSeen = 0; $nsc = 0;
        foreach ($SCREENS as $u) {
            $m = bval($r, "m $w $u"); $st = bstep($r, "g $w $u");
            if (! is_array($m)) { $bad[] = "$u: no measure"; continue; }
            if (($st['status'] ?? 0) !== 200) $bad[] = "$u: status " . ($st['status'] ?? '?');
            if ($m['overflowX'] > 0 || $m['bodyOverflowX'] > 0) $over[] = "$u +{$m['overflowX']}px (" . implode(', ', array_slice($m['wide'], 0, 4)) . ')';
            if ($m['mainRight'] !== null && $m['mainRight'] > $m['vw'] + 1) $over[] = "$u main right {$m['mainRight']} > {$m['vw']}";
            if ($m['unreachable']) $unr[] = "$u: " . implode(', ', $m['unreachable']);
            foreach ($m['scrollers'] as $s) { $nsc++; if (! $s['maxReached'] || ! $s['lastVisible'] || $s['right'] > $m['vw'] + 1) $scr[] = "$u " . json_encode($s); }
            foreach ($m['tables'] as $t) {
                if ($t['numbered'] === 0) continue;
                $tablesSeen++;
                if (abs($t['firstW'] - $m['gutter']) > 1) $bad[] = "$u {$t['cls']}: number column {$t['firstW']}px, gutter {$m['gutter']}px";
                if ($t['clipped']) $clip[] = "$u {$t['cls']}: " . implode(',', array_slice($t['clipped'], 0, 5));
            }
        }
        check("Y01-$lang-$w", "[$lang] $w px: every numbered table's row-number column is one gutter wide ($tablesSeen tables, " . count($SCREENS) . ' screens, all 200)', $bad === [] && $tablesSeen > 0, implode(' | ', $bad));
        check("Y02-$lang-$w", "[$lang] $w px: no row number clipped", $clip === [], implode(' | ', $clip));
        check("Y03-$lang-$w", "[$lang] $w px: no horizontal page scroll on any screen, the content column stops at the window", $over === [], implode(' | ', $over));
        check("Y06-$lang-$w", "[$lang] $w px: whatever is wider than the content column sits in a box that scrolls inside it, and its far end can be reached ($nsc scrolling boxes)", $unr === [] && $scr === [], implode(' | ', array_merge($unr, $scr)));
        $ov = []; $widths = [];
        foreach (["rolewarden/roles/$R9", "rolewarden/roles/$BIG"] as $u) {
            $m = bval($r, "m $w $u");
            foreach (($m['tables'] ?? []) as $t) {
                if (! str_contains($t['cls'], 'matrix')) continue;
                $widths[$u] = ['act' => array_values(array_unique($t['act'])), 'anno' => $t['anno'], 'n' => count($t['act'])];
                foreach ($t['ths'] as $i => $els) foreach ($els as $e) { [$l, $rr] = $t['head'][$i]; if ($e[0] < $l - 1 || $e[1] > $rr + 1) $ov[] = "$u th$i {$e[2]} [{$e[0]},{$e[1]}] outside [$l,$rr]"; }
            }
        }
        check("Y05-$lang-$w", "[$lang] $w px: role matrices (demo Approver and the role page with the long area), every column header label and box inside its own column", $ov === [] && count($widths) === 2, implode(' | ', array_slice($ov, 0, 8)));
        // Y07, rewritten for 79a0cda and again for f40170e, where the marginal-column sentence was dropped (design system: "Below 1440px ... marginal columns tighten to 160px. The permission
        // matrix widths are what its columns get when they fit; with more actions than fit, the columns shrink to their content")
        $annoBad = []; $annoSeen = []; $matBad = [];
        foreach ($SCREENS as $u) {
            $m = bval($r, "m $w $u");
            foreach (($m['tables'] ?? []) as $t) {
                foreach ($t['ranno'] as $x) $annoSeen["$u {$t['cls']}"][] = $x; // information only since f40170e (the 160px rule is gone from the design system)
                if (str_contains($t['cls'], 'matrix') && $t['actClip'] > 0) $matBad[] = "$u: {$t['actClip']} action headers narrower than their content";
            }
        }
        info("[$lang] $w px, marginal (.rw-anno) widths: " . json_encode($annoSeen));
        check("Y07-$lang-$w", "[$lang] $w px: " . 'matrix action columns never narrower than their header content, the matrix either fits its box or scrolls inside it', $annoBad === [] && $matBad === [] && $annoSeen !== [], implode(' | ', array_merge($annoBad, $matBad)));
        $wantAct = $w < 1440 ? 80 : 104;
        $okW = $widths && ! array_filter($widths, fn ($x) => count($x['act']) !== 1 || abs($x['act'][0] - $wantAct) > 1 || count($x['anno']) !== 1 || abs($x['anno'][0] - 200) > 1);
        info("[$lang] $w px, matrix widths with the 27 action columns of this phase (information): " . json_encode($widths));
    }
}
server_stop();
