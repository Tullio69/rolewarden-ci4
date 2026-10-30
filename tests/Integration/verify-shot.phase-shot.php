<?php
/**
 * SHOT phase (Collaudatore ad Hoc, Claude): panel polish before the CodeCanyon screenshots (commit 782fb64, parent 603a94c),
 * in black box: served HTML, DOM and computed styles through Chrome DevTools Protocol (verify-motion.browser.mjs), direct
 * queries on rolewarden_test, screenshots at 1440x900 and 1600x900, light and dark, English and Italian.
 * Sources of the assertions: the definition of done of the caller's prompt (7 behaviours), docs/design-system/README.md and
 * components (PermissionMatrix, CommandSearch, Button, UserDetail), CHANGELOG.md. No src/, resources/ or unit test read.
 * Every measure is also written to verify-shot.measure.<label>.json; the run on the parent (RW_SHOT_LABEL=parent) only
 * measures, and the run on the commit compares against it where the definition of done says "before/after".
 * Sections: MX (dense matrix), SA (super admin user), CK (Check a permission), K (command search, dark), ACT (activity),
 * DB (dashboard), V (various), A11Y (contrast, names, focus, console), LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
date_default_timezone_set('UTC');
$LBL = getenv('RW_SHOT_LABEL') ?: 'commit';
$PARENT = $LBL === 'parent';
$PART = getenv('RW_SHOT_PART') ?: '';   // '' all, 'a' English views and command search, 'b' Italian, eight actions and the checks (reads part a)
$DO_A = $PART !== 'b'; $DO_B = $PART !== 'a';
echo "\n== SHOT: panel polish ($LBL, " . (getenv('RW_V8_NEW') ?: '?') . ")\n";
serve8($APP, 'SHOT');
$S0 = count($GLOBALS['SEEN']);
$OUTP = __DIR__ . '/verify-shot.';
$MEAS = [];

function sb(array $steps, string $tag, int $timeout = 400000): array
{
    global $WORK;
    static $n = 0; $n++;
    $jf = "$WORK/shjob-$n.json";
    file_put_contents($jf, json_encode(['profile' => "$WORK/chrome-sh-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 350]));
    $p = proc_open(['node', __DIR__ . '/verify-motion.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'consoleErrors' => ['no result'], 'consoleCalls' => [], 'profileRemoved' => false]; }
    if ($r['errors']) info("browser $tag driver errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    $GLOBALS['JERR'][$tag] = array_merge($r['consoleErrors'] ?? [], $r['consoleCalls'] ?? [], $r['logEntries'] ?? []);
    return $r;
}
function v(array $r, string $tag) { foreach ($r['steps'] as $s) if (($s['tag'] ?? '') === $tag) return $s['value'] ?? null; return null; }
function j($x): string { return short(json_encode($x, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 700); }
function rgbn(?string $c): ?array { if (! $c || ! preg_match('/rgba?\(([^)]+)\)/', $c, $m)) return null; $p = array_map('floatval', preg_split('/[ ,\/]+/', trim($m[1]))); return [$p[0], $p[1], $p[2], $p[3] ?? 1.0]; }
function same_col(?string $a, ?string $b): bool { $x = rgbn($a); $y = rgbn($b); return $x && $y && abs($x[0] - $y[0]) < 2 && abs($x[1] - $y[1]) < 2 && abs($x[2] - $y[2]) < 2 && abs($x[3] - $y[3]) < 0.02; }
function lum(array $c): float { $f = fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]); }
function login_steps(): array { return [blogin('owner@v8b.test', OPW)]; }
function png(string $name): string { global $OUTP, $LBL; return $OUTP . "$LBL.$name.png"; }

const KEY_TAB = ['op' => 'key', 'key' => 'Tab', 'code' => 'Tab', 'keyCode' => 9, 'wait' => 120];
const KEY_ESC = ['op' => 'key', 'key' => 'Escape', 'code' => 'Escape', 'keyCode' => 27, 'wait' => 500];
const KEY_DOWN = ['op' => 'key', 'key' => 'ArrowDown', 'code' => 'ArrowDown', 'keyCode' => 40, 'wait' => 200];
const KEY_ENTER = ['op' => 'key', 'key' => 'Enter', 'code' => 'Enter', 'keyCode' => 13, 'text' => "\r"];
const CTRLK = ['op' => 'key', 'key' => 'k', 'code' => 'KeyK', 'keyCode' => 75, 'modifiers' => 2, 'wait' => 900];

// ---------------------------------------------------------------- page-side measures (no knowledge of the module's code)
const MEASURE = <<<'JS'
(() => {
  const q = (s, r) => (r || document).querySelector(s), qa = (s, r) => [...(r || document).querySelectorAll(s)];
  const cs = (e, p) => getComputedStyle(e, p || null);
  const rect = (e) => { const b = e.getBoundingClientRect(); return [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)]; };
  const probe = document.createElement('span'); document.body.appendChild(probe);
  const tok = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const T = {}; for (const t of ['accent', 'accent-tint', 'ink', 'ink-muted', 'ink-faint', 'granted', 'denied', 'focus', 'surface', 'surface-raised', 'rule', 'rule-strong']) T[t] = tok('--' + t);
  probe.remove();
  const txt = (e) => e ? e.textContent.replace(/\s+/g, ' ').trim() : null;
  const main = q('main');
  const o = { url: location.pathname + location.search, vw: innerWidth, docW: document.documentElement.scrollWidth, rail: !!q('.rw-shell--rail'), lang: document.documentElement.lang, T,
    dark: matchMedia('(prefers-color-scheme: dark)').matches, mode: document.documentElement.getAttribute('data-rw-mode'), theme: document.documentElement.getAttribute('data-rw-theme'),
    eyebrow: txt(q('main .rw-page-head .rw-header')), h1: txt(q('main h1')), bodyFs: parseFloat(cs(main).fontSize) };
  o.hscroll = qa('main, main *').filter(e => { const s = cs(e); return (s.overflowX === 'auto' || s.overflowX === 'scroll') && e.scrollWidth > e.clientWidth + 1; }).map(e => e.tagName + '.' + String(e.className).slice(0, 40) + ' ' + e.scrollWidth + '>' + e.clientWidth);
  const m = q('table.rw-matrix');
  if (m) {
    const mr = main.getBoundingClientRect(), tr = m.getBoundingClientRect();
    const lim = Math.min(innerWidth, mr.right);
    const acts = qa('thead th.rw-m-act', m);
    o.mx = { cls: m.className, right: Math.round(tr.right), mainRight: Math.round(mr.right), cols: acts.length, actW: acts.map(t => Math.round(t.getBoundingClientRect().width)),
      outside: qa('thead th, tbody td, tbody th, tfoot td', m).filter(t => { const b = t.getBoundingClientRect(); return b.width > 0 && cs(t).display !== 'none' && b.right > lim + 1; }).length,
      labels: acts.map(t => { const l = q('.rw-header', t) || t; const b = l.getBoundingClientRect(), tb = t.getBoundingClientRect(); const s = cs(l);
        return { t: txt(l), w: Math.round(b.width), h: Math.round(b.height), wm: s.writingMode, tf: s.transform, rot: s.rotate, vertical: b.height > b.width,
          cut: l.scrollWidth > l.clientWidth + 1 || l.scrollHeight > l.clientHeight + 1 || b.left < tb.left - 1 || b.right > tb.right + 1 }; }),
      anno: (() => { const a = q('thead th.rw-m-anno', m); return a ? { display: cs(a).display, w: Math.round(a.getBoundingClientRect().width), visibility: cs(a).visibility } : null; })(),
      annoTd: qa('tbody td.rw-m-anno, tfoot td.rw-m-anno', m).filter(e => cs(e).display !== 'none' && e.getBoundingClientRect().width > 0).length,
      cells: qa('button.rw-cell', m).length, rows: qa('tbody tr', m).length,
      cellTitles: qa('button.rw-cell', m).slice(0, 400).map(b => b.getAttribute('title')).filter(t => !t).length,
      colChecks: qa('thead input[type=checkbox]', m).map(i => i.getAttribute('aria-label')), rowChecks: qa('tbody td.rw-m-all input[type=checkbox]', m).map(i => i.getAttribute('aria-label')) };
  }
  // roles ledger of a user page, super admin sentence (found by text, not by class)
  const led = q('main table.rw-ledger');
  if (led) o.roles = qa('tbody tr', led).map(tr => [txt(q('.rw-l-key b', tr)), txt(q('.rw-l-value', tr))]).filter(r => r[0]);
  const sa = qa('main p').find(p => /super admin/i.test(p.textContent) && p.closest('main') && !p.closest('table'));
  o.saNote = sa ? { text: txt(sa), html: sa.innerHTML.trim().slice(0, 400), before: (() => { const t = q('main table.rw-matrix'); return t ? !!(sa.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_FOLLOWING) : null; })() } : null;
  // Check a permission
  // the verdict: the .rw-verdict block, or (parent commit) the first block after the "Outcome" section title
  const ver = q('.rw-verdict') || (() => { const h = qa('main h2').find(h => /^(Outcome|Esito)$/.test(h.textContent.trim())); if (!h) return null; return (h.closest('.rw-section-title') || h).nextElementSibling; })();
  if (ver) {
    const svg = q('svg', ver), sp = q('span', ver);
    o.verdict = { text: txt(ver), svg: svg ? { cls: String(svg.className.baseVal), color: cs(svg).color, parts: qa('path, line, polyline', svg).length, html: svg.outerHTML.slice(0, 500), w: Math.round(svg.getBoundingClientRect().width) } : null,
      fs: parseFloat(cs(sp || ver).fontSize), fw: cs(sp || ver).fontWeight, verFs: parseFloat(cs(ver).fontSize) };
    const cur = q('ol li[aria-current]');
    const dec = cur ? [...cur.querySelectorAll('*')].find(e => /^(decides|decide)$/i.test(txt(e))) : null;
    o.decides = cur ? { li: txt(cur), label: dec ? { text: txt(dec), color: cs(dec).color, bg: cs(dec).backgroundColor, border: cs(dec).borderColor } : null, count: qa('ol li[aria-current]').length } : null;
    const tbl = q('main table.rw-t-check') || qa('main table').pop();
    o.holders = tbl ? qa('tbody tr', tbl).map(tr => { const tds = qa('td', tr); const u = q('a', tds[1] || tr); const last = q('a', tds[tds.length - 1]); return { user: txt(u), userHref: u ? u.getAttribute('href') : null, why: txt(tds[2]), link: txt(last), href: last ? last.getAttribute('href') : null }; }) : [];
    o.holdersHead = tbl ? qa('thead th', tbl).map(th => txt(th)) : [];
  }
  // activity log
  const act = q('main table.rw-t-activity');
  if (act) o.act = qa('tbody tr', act).map(tr => { const tds = qa('td', tr); const a = tds[3], d = tds[5];
    const slugs = []; if (d) { const w = document.createTreeWalker(d, NodeFilter.SHOW_TEXT); let n; while ((n = w.nextNode())) { const re = /\b[a-z][a-z0-9-]*\.[a-z][a-z0-9-]+\b/g; let mm; while ((mm = re.exec(n.textContent))) slugs.push({ s: mm[0], font: cs(n.parentElement).fontFamily, tag: n.parentElement.tagName }); } }
    return { action: txt(a), color: a ? cs(a).color : null, aWeight: a ? cs(a).fontWeight : null, svg: a ? qa('svg', tr).map(s => String(s.className.baseVal) + '|' + cs(s).color) : [], details: txt(d), slugs, rowColor: cs(tr).color, opacity: cs(tr).opacity }; });
  // dashboard
  const dh = q('main .rw-dash-head');
  if (dh) {
    const hb = dh.getBoundingClientRect();
    const lines = [];
    const shown = (e) => { if (e.closest('details:not([open]) > :not(summary)')) return false; for (let x = e; x; x = x.parentElement) { const st = cs(x); if (st.display === 'none' || st.visibility === 'hidden' || Number(st.opacity) === 0) return false; } return true; };
    for (const e of qa('main *')) { const b = e.getBoundingClientRect(); if (b.width < 300 || !shown(e)) continue; const s = cs(e);
      for (const [side, y] of [['Top', b.top], ['Bottom', b.bottom]]) { const w = parseFloat(s['border' + side + 'Width']); const c = rgbx(s['border' + side + 'Color']); if (w > 0 && s['border' + side + 'Style'] !== 'none' && c > 0 && y >= hb.top + 4 && y <= hb.bottom + 70) lines.push({ y: Math.round(y), w, el: e.tagName + '.' + String(e.className).slice(0, 30), side, color: s['border' + side + 'Color'] }); }
      if (e.tagName === 'HR' && b.top >= hb.top + 4 && b.top <= hb.bottom + 70) lines.push({ y: Math.round(b.top), w: b.height, el: 'HR', side: 'hr' }); }
    const ys = []; for (const l of lines.sort((a, b) => a.y - b.y)) if (!ys.length || l.y - ys[ys.length - 1] > 3) ys.push(l.y);
    o.dash = { head: rect(dh), lines, distinct: ys,
      sign: qa('section[aria-labelledby=rw-dash-signins] svg').map(s => rect(s)), signSection: (() => { const s = q('section[aria-labelledby=rw-dash-signins]'); return s ? rect(s) : null; })(),
      changes: (() => { const s = q('section[aria-labelledby=rw-dash-changes]'); if (!s) return null; const items = qa('li, tbody tr', s); return { items: items.map(i => txt(i)), slugFonts: qa('code', s).map(c => cs(c).fontFamily).slice(0, 3), raw: txt(q('.rw-dash-body', s) || s).slice(0, 600) }; })() };
  }
  function rgbx(c) { const mm = String(c).match(/rgba?\(([^)]+)\)/); if (!mm) return 0; const p = mm[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return p.length > 3 ? p[3] : 1; }
  // users list roles column
  const ut = q('main td.rw-c-roles') ? q('main td.rw-c-roles').closest('table') : null;
  if (ut) o.users = qa('tbody tr', ut).map(tr => [txt(q('.rw-c-name', tr)), txt(q('.rw-c-roles', tr)), q('.rw-c-roles *', tr) ? cs(q('.rw-c-roles *', tr)).color : null]);
  // role selector label on a role page
  const rj = q('#role-jump'); if (rj) { const l = q('label[for=role-jump]'); o.roleJump = { label: txt(l) }; }
  // primary buttons, disabled or not
  o.primary = qa('main button.rw-btn--primary, main .rw-btn--primary').map(b => { const s = cs(b); return { text: txt(b), disabled: b.disabled || b.getAttribute('aria-disabled') === 'true', bg: s.backgroundColor, color: s.color, border: s.borderTopColor, bw: s.borderTopWidth, bs: s.borderTopStyle, opacity: s.opacity, cursor: s.cursor, shadow: s.boxShadow }; });
  // appearance: right edges of the sections, "Saved in" on one line
  if (/appearance/.test(location.pathname)) {
    o.sections = qa('main .rw-section-title').map(t => { const b = t.getBoundingClientRect(); return { t: txt(t), right: Math.round(b.right), left: Math.round(b.left), w: Math.round(b.width) }; });
    o.blocks = qa('main > *, main form > *, main section > *').filter(e => e.getBoundingClientRect().width > 200 && cs(e).display !== 'none' && !e.closest('.rw-toasts, dialog, template')).map(e => ({ el: e.tagName + '.' + String(e.className).slice(0, 30), right: Math.round(e.getBoundingClientRect().right) }));
    const dt = qa('main dt').find(d => /Saved in|Salvato in/i.test(d.textContent));
    if (dt) { const dd = dt.nextElementSibling; const code = q('code', dd) || dd; const lh = parseFloat(cs(code).lineHeight) || parseFloat(cs(code).fontSize) * 1.5;
      const dl = dt.closest('dl'); const rule = q('main .rw-section-title');
      o.savedIn = { codeRight: Math.round(code.getBoundingClientRect().right), ddRight: Math.round(dd.getBoundingClientRect().right), dlRight: dl ? Math.round(dl.getBoundingClientRect().right) : null, ruleRight: rule ? Math.round(rule.getBoundingClientRect().right) : null,
        rowsRight: dl ? [...dl.querySelectorAll('dd')].map(x => Math.round(x.getBoundingClientRect().right)) : [], ddOverflow: dd.scrollWidth > dd.clientWidth + 1, dt: txt(dt), dd: txt(dd), ddRects: code.getClientRects().length, ddH: Math.round(dd.getBoundingClientRect().height), codeH: Math.round(code.getBoundingClientRect().height), lh, ws: cs(code).whiteSpace, dtRects: dt.getClientRects().length, dtH: Math.round(dt.getBoundingClientRect().height), dtLh: parseFloat(cs(dt).lineHeight) || 0 }; }
  }
  return o;
})()
JS;
const CMDM = <<<'JS'
(() => {
  const d = document.querySelector('dialog.rw-cmd'); if (!d) return null;
  const qi = document.getElementById('rw-cmd-q'); const a = document.activeElement; const cs = (e, p) => getComputedStyle(e, p || null);
  const probe = document.createElement('span'); document.body.appendChild(probe); const tok = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const T = { focus: tok('--focus'), accent: tok('--accent'), surface: tok('--surface'), rule: tok('--rule'), ink: tok('--ink') }; probe.remove();
  const bd = cs(d, '::backdrop');
  const rings = [];
  for (let e = qi; e && e !== document.documentElement; e = e.parentElement) { const s = cs(e); const ow = parseFloat(s.outlineWidth) || 0;
    if (s.outlineStyle !== 'none' && ow > 0) rings.push({ el: e.tagName + '.' + e.className, kind: 'outline', w: ow, c: s.outlineColor });
    if (s.boxShadow && s.boxShadow !== 'none') rings.push({ el: e.tagName + '.' + e.className, kind: 'shadow', v: s.boxShadow });
    for (const side of ['Top', 'Right', 'Bottom', 'Left']) { const w = parseFloat(s['border' + side + 'Width']) || 0; if (w > 0 && s['border' + side + 'Style'] !== 'none' && (s['border' + side + 'Color'] === T.focus || s['border' + side + 'Color'] === T.accent)) rings.push({ el: e.tagName + '.' + e.className, kind: 'border' + side, w, c: s['border' + side + 'Color'] }); }
    if (e === d) break; }
  const opts = [...d.querySelectorAll('[role=option]')];
  return { open: d.open, focusQ: a === qi, focusVisible: qi ? qi.matches(':focus-visible') : null, backdrop: bd.backgroundColor, backdropFilter: bd.backdropFilter, T, rings,
    q: qi ? { outline: cs(qi).outlineStyle + ' ' + cs(qi).outlineWidth + ' ' + cs(qi).outlineColor, bb: cs(qi).borderBottomWidth + ' ' + cs(qi).borderBottomStyle + ' ' + cs(qi).borderBottomColor, shadow: cs(qi).boxShadow, role: qi.getAttribute('role'), label: qi.getAttribute('aria-label') || (qi.labels && qi.labels[0] ? qi.labels[0].textContent.trim() : null) } : null,
    ad: qi ? qi.getAttribute('aria-activedescendant') : null, sel: opts.filter(o => o.getAttribute('aria-selected') === 'true').map(o => o.id), n: opts.length, first: opts[0] ? opts[0].getAttribute('href') : null,
    active: a ? a.tagName + '.' + String(a.className).slice(0, 30) : null, panel: (() => { const r = d.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)]; })() };
})()
JS;
const FOCUSNOW = <<<'JS'
(() => { const a = document.activeElement; if (!a || a === document.body) return null; const s = getComputedStyle(a); const r = a.getBoundingClientRect();
  return { el: a.tagName + '.' + String(a.className).slice(0, 40), name: (a.getAttribute('aria-label') || a.getAttribute('title') || a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50), fv: a.matches(':focus-visible'),
    outline: s.outlineStyle, ow: parseFloat(s.outlineWidth) || 0, oc: s.outlineColor, shadow: s.boxShadow, bb: s.borderBottomColor + ' ' + s.borderBottomWidth, visible: r.width > 0 && r.height > 0 }; })()
JS;
const CELLS = <<<'JS'
(() => { const o = {}; for (const c of document.querySelectorAll('table.rw-matrix button.rw-cell')) { const slug = (c.getAttribute('title') || '').split(' ')[0]; const m = c.querySelector('.rw-mark'); o[slug] = { pressed: c.getAttribute('aria-pressed'), mark: m ? [...m.classList].filter(x => x.startsWith('rw-mark--')).join(',') : 'blank', changed: c.classList.contains('rw-cell--changed') }; }
  const bar = document.querySelector('.rw-bar'); const save = bar ? bar.querySelector('button.rw-btn--primary') : null;
  return { cells: o, bar: bar ? bar.textContent.replace(/\s+/g, ' ').trim() : null, dirty: !!document.querySelector('.rw-bar--dirty'), saveDisabled: save ? save.disabled : null,
    notes: [...document.querySelectorAll('table.rw-matrix tbody td.rw-m-anno .rw-note')].filter(n => getComputedStyle(n).display !== 'none').map(n => n.textContent.trim()),
    cols: [...document.querySelectorAll('table.rw-matrix thead input[type=checkbox]')].map(i => (i.checked ? 'c' : i.indeterminate ? 'i' : '-')).join('') }; })()
JS;
const SAVE_AND_TOAST = "(async () => { const before = document.querySelectorAll('.rw-toasts .rw-toast').length; const b = document.querySelector('.rw-bar button.rw-btn--primary'); if (!b || b.disabled) return 'no save'; b.click(); const t0 = performance.now(); while (document.querySelectorAll('.rw-toasts .rw-toast').length <= before && performance.now() - t0 < 6000) await new Promise(r => setTimeout(r, 50)); await new Promise(r => setTimeout(r, 400)); const t = [...document.querySelectorAll('.rw-toasts .rw-toast')].pop(); return t ? t.className + ' | ' + t.textContent.replace(/\\s+/g, ' ').trim() : 'none'; })()";
function cell(string $slug): string { return 'table.rw-matrix button.rw-cell[title^="' . $slug . ' "]'; }
function ax_main(string $tag): array { return ['op' => 'ax', 'selector' => 'main button, main a[href], main input:not([type=hidden]), main select, main summary', 'tag' => $tag]; }
function audit(string $tag): array { return ['op' => 'audit', 'tokens' => ['--surface', '--ink', '--accent'], 'semantic' => ['--surface'], 'tag' => $tag]; }

// ---------------------------------------------------------------- data of the test (rolewarden_test only, restored by the runner)
$SAHTML = mkuser('sa-html@shot.test', 'super-admin');
q('UPDATE users SET username=? WHERE id=?', ['<i>Zed</i> & "Q"', $SAHTML]);
$SADENY = mkuser('sa-deny@shot.test', 'super-admin', ['users.delete' => 0]);
q('UPDATE users SET username=? WHERE id=?', ['sadeny', $SADENY]);
$NOROLE = mkuser('norole@shot.test', '');
q('UPDATE users SET username=? WHERE id=?', ['norole', $NOROLE]);
$APPROVER = role_id('demo-approver');
info("users: owner=$OWNER admin=$ADMIN plain=$PLAIN sa-html=$SAHTML sa-deny=$SADENY norole=$NOROLE; role Approver=$APPROVER");
// a failed sign-in (wrong password), through Shield's form
$cf = c8('shot-fail'); $hf = G($cf, 'login', 'fail form'); $ff = form_with($hf, 'password');
if ($ff) { $vv = array_replace($ff['fields'], ['email' => 'plain@v8b.test', 'password' => 'Wrong-Password-000']); unset($vv['remember']); P($cf, 'login', $vv, 'failed sign-in'); }
check('PRE-1', 'A failed sign-in is recorded (auth_logins success=0)', (int) q1('SELECT COUNT(*) FROM auth_logins WHERE success=0') >= 1);

// ---------------------------------------------------------------- 1. matrix interactions (role and user), in English, light, 1440
$r = sb(array_merge(login_steps(), [
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$APPROVER", 'tag' => 'r0'], ['op' => 'wait', 'ms' => 700],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c0'],
    ['op' => 'eval', 'expr' => MEASURE, 'tag' => 'm0'],
    ['op' => 'mouse', 'selector' => cell('users.view'), 'click' => false, 'wait' => 300, 'tag' => 'hover'],
    ['op' => 'eval', 'expr' => "(() => { const c = document.querySelector('" . cell('users.view') . "'); return { title: c.getAttribute('title'), tips: [...document.querySelectorAll('[role=tooltip]')].filter(t => getComputedStyle(t).display !== 'none' && t.getBoundingClientRect().width > 0).map(t => t.textContent.trim()) }; })()", 'tag' => 'tip'],
    ['op' => 'mouse', 'selector' => cell('users.view'), 'click' => true, 'wait' => 500, 'tag' => 'k1'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c1'],
    ['op' => 'mouse', 'selector' => 'table.rw-matrix thead input[aria-label="Grant view on every area"]', 'click' => true, 'wait' => 500, 'tag' => 'k2'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c2'],
    ['op' => 'click', 'selector' => '.rw-bar button.rw-btn--ghost', 'wait' => 500, 'tag' => 'discard'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c3'],
    ['op' => 'mouse', 'selector' => 'table.rw-matrix tbody td.rw-m-all input[aria-label="Grant every action on Sessions"]', 'click' => true, 'wait' => 500, 'tag' => 'k3'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c4'],
    ['op' => 'eval', 'expr' => MEASURE, 'tag' => 'm-dirty'],
    ['op' => 'screenshot', 'path' => png('role-dirty.1440.light.en'), 'tag' => 'sd'],
    ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'save'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c5'],
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$APPROVER", 'tag' => 'r1'], ['op' => 'wait', 'ms' => 600],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c6'],
    // user overrides: deny a role grant on the admin, grant on the plain user
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/users/$ADMIN", 'tag' => 'u0'], ['op' => 'wait', 'ms' => 700],
    ['op' => 'mouse', 'selector' => cell('users.delete'), 'click' => true, 'wait' => 500, 'tag' => 'uk1'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'uc1'],
    ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'usave'],
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/users/$PLAIN", 'tag' => 'p0'], ['op' => 'wait', 'ms' => 700],
    ['op' => 'mouse', 'selector' => cell('roles.view'), 'click' => true, 'wait' => 500, 'tag' => 'pk1'],
    ['op' => 'eval', 'expr' => CELLS, 'tag' => 'pc1'],
    ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'psave'],
]), 'mtx');
$c0 = v($r, 'c0'); $c1 = v($r, 'c1'); $c2 = v($r, 'c2'); $c3 = v($r, 'c3'); $c4 = v($r, 'c4'); $c5 = v($r, 'c5'); $c6 = v($r, 'c6');
$MEAS['mtx'] = compact('c0', 'c1', 'c2', 'c3', 'c4', 'c5', 'c6') + ['tip' => v($r, 'tip'), 'save' => v($r, 'save'), 'usave' => v($r, 'usave'), 'psave' => v($r, 'psave'), 'uc1' => v($r, 'uc1'), 'pc1' => v($r, 'pc1')];
$m0 = v($r, 'm0');
check('MX-F1', 'Role matrix at rest: "All changes saved.", Save changes disabled, no cell changed', is_array($c0) && $c0['saveDisabled'] === true && ! $c0['dirty'] && str_contains((string) $c0['bar'], 'All changes saved.') && ! array_filter(array_column($c0['cells'], 'changed')), j([$c0['bar'] ?? null, $c0['saveDisabled'] ?? null]));
check('MX-F2', 'Clicking users.view grants it (aria-pressed true, changed), bar "1 unsaved change to Approver users.view", note "1 changed", Save enabled', is_array($c1) && ($c1['cells']['users.view']['pressed'] ?? '') === 'true' && $c1['cells']['users.view']['changed'] && str_contains((string) $c1['bar'], '1 unsaved change to Approver') && str_contains((string) $c1['bar'], 'users.view') && $c1['saveDisabled'] === false && in_array('1 changed', $c1['notes'], true), j([$c1['bar'] ?? null, $c1['notes'] ?? null, $c1['cells']['users.view'] ?? null]));
$colOn = is_array($c2) ? array_filter($c2['cells'], fn ($x, $k) => str_ends_with($k, '.view') && $x['pressed'] !== null, ARRAY_FILTER_USE_BOTH) : [];
check('MX-F3', 'The "view" column box grants view on every editable area (all pressed) and the bar counts more changes', $colOn && ! array_filter($colOn, fn ($x) => $x['pressed'] !== 'true') && preg_match('/(\d+) unsaved changes/', (string) $c2['bar'], $mm) && (int) $mm[1] > 1, j([$c2['bar'] ?? null, $c2['cols'] ?? null]));
check('MX-F4', 'Discard returns every cell to the saved state and the bar to "All changes saved."', is_array($c3) && $c3['cells'] == $c0['cells'] && str_contains((string) $c3['bar'], 'All changes saved.'), j($c3['bar'] ?? null));
$sess = is_array($c4) ? array_filter($c4['cells'], fn ($x, $k) => str_starts_with($k, 'sessions.') && $x['pressed'] !== null, ARRAY_FILTER_USE_BOTH) : [];
check('MX-F5', 'The Sessions row box grants every editable action on Sessions; bar "N unsaved changes to Approver"', $sess && ! array_filter($sess, fn ($x) => $x['pressed'] !== 'true') && str_contains((string) $c4['bar'], 'unsaved change'), j([$c4['bar'] ?? null, array_keys($sess)]));
check('MX-F6', 'Save changes gives a success toast and the bar returns to "All changes saved."', str_contains((string) v($r, 'save'), 'success') && is_array($c5) && str_contains((string) $c5['bar'], 'All changes saved.'), j([v($r, 'save'), $c5['bar'] ?? null]));
$dbSess = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug LIKE "sessions.%" ORDER BY p.slug', [$APPROVER]), 'slug');
check('MX-F7', 'The database holds exactly what was saved (sessions.revoke, sessions.view on Approver) and the reloaded matrix shows it', $dbSess === ['sessions.revoke', 'sessions.view'] && is_array($c6) && ($c6['cells']['sessions.view']['pressed'] ?? '') === 'true' && ($c6['cells']['users.view']['pressed'] ?? '') !== 'true', j([$dbSess, $c6['cells']['sessions.view'] ?? null]));
$tip = v($r, 'tip');
check('MX-F8', 'Cell tooltip names the permission and its state ("users.view · Not granted")', is_array($tip) && $tip['title'] === 'users.view · Not granted', j($tip));
check('MX-F9', 'User override: clicking a role grant on the admin marks it denied for the user, bar "1 unsaved change to v8badmin’s overrides", Save overrides gives success and the database holds the denial', str_contains((string) (v($r, 'uc1')['bar'] ?? ''), '1 unsaved change to v8badmin') && str_contains((string) v($r, 'usave'), 'success') && (string) q1('SELECT up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.slug="users.delete"', [$ADMIN]) === '0', j([v($r, 'uc1')['bar'] ?? null, v($r, 'uc1')['cells']['users.delete'] ?? null, v($r, 'usave')]));
check('MX-F10', 'User override: clicking an empty cell on the plain user grants it for the user and Save overrides stores it', str_contains((string) v($r, 'psave'), 'success') && (string) q1('SELECT up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.slug="roles.view"', [$PLAIN]) === '1', j([v($r, 'pc1')['cells']['roles.view'] ?? null, v($r, 'psave')]));
check('MX-F11', 'No JavaScript error in the matrix interactions', ! $GLOBALS['JERR']['mtx'] && ! $r['errors'], j([$GLOBALS['JERR']['mtx'], $r['errors']]));

// ---------------------------------------------------------------- 2. views, measured and photographed: en light/dark at 1440 and 1600
$CHK = 'rolewarden/permissions/check?permission=users.delete&user=';
$VIEWS = [
    'role' => "rolewarden/roles/$APPROVER", 'user-sa' => "rolewarden/users/$SAHTML", 'user-admin' => "rolewarden/users/$ADMIN",
    'check-granted' => $CHK . 'v8bowner', 'check-denied' => $CHK . $ADMIN, 'check-sadeny' => $CHK . 'sa-deny@shot.test',
    'activity' => 'rolewarden/activity', 'dashboard' => 'rolewarden?period=30', 'users' => 'rolewarden/users',
    'appearance' => 'rolewarden/appearance', 'settings' => 'rolewarden/settings',
];
function views_job(string $lang, array $modes, array $widths, bool $shots, bool $extra): array
{
    global $VIEWS;
    $s = login_steps();
    foreach ($modes as $mode) {
        $s[] = ['op' => 'emulate', 'scheme' => $mode];
        foreach ($widths as $w) {
            $s[] = ['op' => 'viewport', 'width' => $w, 'height' => 900];
            foreach ($VIEWS as $k => $u) {
                if ($w === 1600 && ! in_array($k, ['role', 'user-sa', 'user-admin', 'appearance', 'dashboard', 'check-granted'], true)) continue;
                $s[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "g-$k-$w-$mode"];
                $s[] = ['op' => 'wait', 'ms' => $k === 'dashboard' ? 1500 : 600];
                $s[] = ['op' => 'eval', 'expr' => MEASURE, 'tag' => "m-$k-$w-$mode"];
                if ($shots) $s[] = ['op' => 'screenshot', 'path' => png("$k.$w.$mode.$lang"), 'tag' => "s-$k-$w-$mode"];
                if ($extra && $w === 1440) { $s[] = audit("a-$k-$mode"); if ($mode === 'light') $s[] = ax_main("x-$k"); }
            }
        }
    }
    return $s;
}
$EN = [];
if ($DO_A) foreach (['light', 'dark'] as $mode) {
    $r = sb(views_job('en', [$mode], $PARENT ? [1440] : [1440, 1600], ! $PARENT || $mode === 'light', ! $PARENT), "views-en-$mode", 500000);
    foreach ($r['steps'] as $st) if (is_string($st['tag'] ?? null) && preg_match('/^[max]-/', $st['tag'])) $EN[$st['tag']] = $st['value'] ?? ['error' => $st['error'] ?? null];
}
$MEAS['en'] = $EN;

// ---------------------------------------------------------------- 3. command search in dark mode, keyboard only; focus walk; rail and 1599/1600
$CMDJOB = array_merge(login_steps(), [
    ['op' => 'emulate', 'scheme' => 'dark'], ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'cu'], ['op' => 'wait', 'ms' => 600],
    ['op' => 'screenshot', 'path' => png('cmd-before.1440.dark.en'), 'tag' => 'cs0'],
    CTRLK + ['tag' => 'ck'], ['op' => 'wait', 'ms' => 700],
    ['op' => 'eval', 'expr' => CMDM, 'tag' => 'k1'],
    ['op' => 'screenshot', 'path' => png('cmd-open.1440.dark.en'), 'tag' => 'cs1'],
    ['op' => 'type', 'text' => 'users', 'wait' => 900], ['op' => 'eval', 'expr' => CMDM, 'tag' => 'k2'],
    KEY_DOWN + ['tag' => 'kd'], ['op' => 'eval', 'expr' => CMDM, 'tag' => 'k3'],
    ['op' => 'screenshot', 'path' => png('cmd-typed.1440.dark.en'), 'tag' => 'cs2'],
    KEY_ESC + ['tag' => 'esc'], ['op' => 'eval', 'expr' => "(() => { const d = document.querySelector('dialog.rw-cmd'); const a = document.activeElement; return { open: d ? d.open : null, active: a ? a.tagName + '.' + String(a.className).slice(0, 40) : null }; })()", 'tag' => 'k4'],
    // reach the sidebar search by keyboard: focus walks with Tab from the top of the page
    ['op' => 'eval', 'expr' => "(() => { document.activeElement && document.activeElement.blur(); window.scrollTo(0, 0); return true; })()", 'tag' => 'blur'],
]);
for ($i = 0; $i < 8; $i++) { $CMDJOB[] = KEY_TAB + ['tag' => "t$i"]; $CMDJOB[] = ['op' => 'eval', 'expr' => FOCUSNOW, 'tag' => "f$i"]; }
$CMDJOB = array_merge($CMDJOB, [
    ['op' => 'eval', 'expr' => "(() => { const l = document.querySelector('.rw-side-search'); if (!l) return null; l.focus(); return document.activeElement === l; })()", 'tag' => 'sfocus'],
    KEY_ENTER + ['wait' => 900, 'tag' => 'senter'], ['op' => 'eval', 'expr' => CMDM, 'tag' => 'k5'],
    ['op' => 'type', 'text' => 'roles', 'wait' => 900], KEY_DOWN + ['tag' => 'kd2'], ['op' => 'eval', 'expr' => CMDM, 'tag' => 'k6'],
    KEY_ENTER + ['nav' => true, 'tag' => 'kenter'],
    ['op' => 'eval', 'expr' => 'location.pathname', 'tag' => 'k7'],
    ['op' => 'emulate', 'scheme' => 'light'], ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'cu2'], ['op' => 'wait', 'ms' => 500],
    CTRLK + ['tag' => 'ckl'], ['op' => 'eval', 'expr' => CMDM, 'tag' => 'kl'], ['op' => 'screenshot', 'path' => png('cmd-open.1440.light.en'), 'tag' => 'csl'], KEY_ESC,
    // focus walk on the role matrix and on Check a permission (light)
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$APPROVER", 'tag' => 'fr'], ['op' => 'wait', 'ms' => 600],
    ['op' => 'eval', 'expr' => "(() => { const s = [...document.querySelectorAll('table.rw-matrix thead input[type=checkbox]')].pop(); s.focus(); return document.activeElement === s; })()", 'tag' => 'fr0'],
]);
for ($i = 0; $i < 14; $i++) { $CMDJOB[] = KEY_TAB + ['tag' => "rt$i"]; $CMDJOB[] = ['op' => 'eval', 'expr' => FOCUSNOW, 'tag' => "rf$i"]; }
$CMDJOB[] = ['op' => 'goto', 'url' => U1BASE . $CHK . 'v8bowner', 'tag' => 'fc']; $CMDJOB[] = ['op' => 'wait', 'ms' => 500];
$CMDJOB[] = ['op' => 'eval', 'expr' => "(() => { const s = document.getElementById('rw-check-user'); s.focus(); return document.activeElement === s; })()", 'tag' => 'fc0'];
for ($i = 0; $i < 8; $i++) { $CMDJOB[] = KEY_TAB + ['tag' => "ct$i"]; $CMDJOB[] = ['op' => 'eval', 'expr' => FOCUSNOW, 'tag' => "cf$i"]; }
// notes column around the 1600 threshold, and with the rail
foreach ([1599, 1600, 1920] as $w) { $CMDJOB[] = ['op' => 'viewport', 'width' => $w, 'height' => 900]; $CMDJOB[] = ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$APPROVER", 'tag' => "w$w"]; $CMDJOB[] = ['op' => 'wait', 'ms' => 400]; $CMDJOB[] = ['op' => 'eval', 'expr' => MEASURE, 'tag' => "mw-$w"];
    $CMDJOB[] = ['op' => 'goto', 'url' => U1BASE . "rolewarden/users/$ADMIN", 'tag' => "wu$w"]; $CMDJOB[] = ['op' => 'wait', 'ms' => 400]; $CMDJOB[] = ['op' => 'eval', 'expr' => MEASURE, 'tag' => "mwu-$w"]; }
$CMDJOB[] = ['op' => 'viewport', 'width' => 1440, 'height' => 900];
$K = [];
if ($DO_A) { $r = sb($CMDJOB, 'cmd', 500000);
foreach ($r['steps'] as $st) if (isset($st['tag']) && $st['tag'] !== null && array_key_exists('value', $st)) $K[$st['tag']] = $st['value'];
}
$MEAS['cmd'] = $K;
if (! $DO_B) {
    $MEAS['jerr'] = $GLOBALS['JERR'];
    file_put_contents($OUTP . "measure.$LBL.a.json", json_encode($MEAS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    info("part a: measures written to verify-shot.measure.$LBL.a.json");
    $bad = seen_clean($S0);
    check('LOG-1a', 'Part a: no HTTP response >= 500, no SQL text, no PHP error (' . (count($GLOBALS['SEEN']) - $S0) . ')', ! $bad, j($bad));
    $ll = bad_log_lines(logs_of($APP));
    check('LOG-2a', 'Part a: no WARNING/ERROR/CRITICAL line in the application log', ! $ll, j(array_slice($ll, 0, 4)));
    return;
}
if ($PART === 'b') { $A = json_decode((string) file_get_contents($OUTP . "measure.$LBL.a.json"), true); $EN = $A['en']; $K = $A['cmd']; $MEAS['en'] = $EN; $MEAS['cmd'] = $K; foreach ($A['jerr'] as $t => $e) $GLOBALS['JERR']["a:$t"] = $e; }

// ---------------------------------------------------------------- 4. Italian
$cl = c8('lang-owner2'); login8($cl, 'owner@v8b.test', OPW); postform($cl, 'rolewarden/profile', '#profile/language$#', ['language' => 'it'], 'lang it');
$IT = [];
foreach (['light', 'dark'] as $mode) {
    $r = sb(views_job('it', [$mode], $PARENT ? [1440] : [1440, 1600], ! $PARENT && true, false), "views-it-$mode", 500000);
    foreach ($r['steps'] as $st) if (is_string($st['tag'] ?? null) && str_starts_with($st['tag'], 'm-')) $IT[$st['tag']] = $st['value'] ?? ['error' => $st['error'] ?? null];
}
$MEAS['it'] = $IT;
postform($cl, 'rolewarden/profile', '#profile/language$#', ['language' => 'en'], 'lang en');
// English Appearance again, with the right edges of "Saved in" (added after part a had run)
$sx = login_steps(); foreach ([1440, 1600, 1920] as $w) { $sx[] = ['op' => 'viewport', 'width' => $w, 'height' => 900]; $sx[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/appearance', 'tag' => "ga$w"]; $sx[] = ['op' => 'wait', 'ms' => 400]; $sx[] = ['op' => 'eval', 'expr' => MEASURE, 'tag' => "ap-$w"]; }
$r = sb($sx, 'appearance-en');
$XA = []; foreach ([1440, 1600, 1920] as $w) $XA[$w] = v($r, "ap-$w");
$MEAS['appearance-en'] = $XA;

// ---------------------------------------------------------------- 5. eight actions or fewer (data of the test): every permission with another action is removed
$KEEP = ['view', 'update', 'create', 'delete', 'access', 'assign', 'override', 'revoke'];
$drop = array_column(q('SELECT id, slug FROM acl_permissions'), 'slug', 'id');
$drop = array_filter($drop, fn ($s) => ! in_array(explode('.', $s)[1], $KEEP, true));
foreach (array_keys($drop) as $pid) { q('DELETE FROM acl_role_permissions WHERE permission_id=?', [$pid]); q('DELETE FROM acl_user_permissions WHERE permission_id=?', [$pid]); q('DELETE FROM acl_permissions WHERE id=?', [$pid]); }
$acts = array_unique(array_map(fn ($s) => explode('.', $s)[1], array_column(q('SELECT slug FROM acl_permissions'), 'slug')));
info('eight-or-fewer: ' . count($acts) . ' actions left: ' . implode(', ', $acts));
$s8 = login_steps();
foreach ([1440, 1600] as $w) { $s8[] = ['op' => 'viewport', 'width' => $w, 'height' => 900];
    foreach (['role' => "rolewarden/roles/$APPROVER", 'user-admin' => "rolewarden/users/$ADMIN"] as $k => $u) { $s8[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "e-$k-$w"]; $s8[] = ['op' => 'wait', 'ms' => 500]; $s8[] = ['op' => 'eval', 'expr' => MEASURE, 'tag' => "m8-$k-$w"]; $s8[] = ['op' => 'screenshot', 'path' => png("le8-$k.$w.light.en"), 'tag' => "s8-$k-$w"]; } }
$r = sb($s8, 'le8');
$E8 = []; foreach ($r['steps'] as $st) if (is_string($st['tag'] ?? null) && str_starts_with($st['tag'], 'm8-')) $E8[$st['tag']] = $st['value'] ?? null;
$MEAS['le8'] = $E8 + ['actions' => array_values($acts)];

file_put_contents($OUTP . "measure.$LBL.json", json_encode($MEAS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
info("measures written: verify-shot.measure.$LBL.json");
if ($PARENT) { info('parent run: measures only, no checks'); return; }
$P = is_file($OUTP . 'measure.parent.json') ? json_decode((string) file_get_contents($OUTP . 'measure.parent.json'), true) : null;
if (! $P) info('no parent measures: the before/after checks are skipped');

// ================================================================ CHECKS
$g = fn (string $k, int $w = 1440, string $mode = 'light', string $lang = 'en') => ($lang === 'en' ? $EN : $IT)["m-$k-$w-$mode"] ?? null;
check('PRE-2', 'The Italian pages are served in Italian (html lang) and the English ones in English', ($IT['m-role-1440-light']['lang'] ?? '') === 'it' && ($EN['m-role-1440-light']['lang'] ?? '') === 'en', j([$IT['m-role-1440-light']['lang'] ?? null, $EN['m-role-1440-light']['lang'] ?? null]));
// ---- MX: dense matrix
foreach (['role', 'user-admin'] as $k) {
    $m = $g($k); $mx = $m['mx'] ?? null;
    check("MX-1-$k", "$k at 1440x900, sidebar open: more than 8 actions (" . ($mx['cols'] ?? '?') . '), the matrix is marked dense and every column is inside the content area (no cell past the right edge)', $mx && ! $m['rail'] && $mx['cols'] > 8 && str_contains($mx['cls'], 'dense') && $mx['outside'] === 0 && $mx['right'] <= $mx['mainRight'] + 1, j([$mx['cols'] ?? null, $mx['cls'] ?? null, $mx['outside'] ?? null, $mx['right'] ?? null, $mx['mainRight'] ?? null, $m['rail'] ?? null]));
    check("MX-2-$k", "$k at 1440: no horizontal scroll (page scrollWidth <= viewport, no scrolling container in main)", $m && $m['docW'] <= $m['vw'] && $m['hscroll'] === [], j([$m['docW'] ?? null, $m['vw'] ?? null, $m['hscroll'] ?? null]));
    $bad = $mx ? array_filter($mx['labels'], fn ($l) => ! $l['vertical'] || $l['cut']) : [1];
    check("MX-3-$k", "$k at 1440: every action label is vertical (taller than wide) and not cut", $mx && ! $bad, j(array_slice(array_values($bad), 0, 4) ?: array_slice($mx['labels'] ?? [], 0, 2)));
    foreach ([['light', 'dark'], ['en', 'it']] as $_) {}
    foreach ([1440 => 0, 1599 => 0, 1600 => 1, 1920 => 1] as $w => $want) {
        $mm = in_array($w, [1599, 1920], true) ? ($K[$k === 'role' ? "mw-$w" : "mwu-$w"] ?? null) : $g($k, $w);
        if ($w === 1600 && ! $mm) $mm = $K[$k === 'role' ? 'mw-1600' : 'mwu-1600'] ?? null;
        $a = $mm['mx']['anno'] ?? null;
        $vis = $a && $a['display'] !== 'none' && $a['w'] > 0 ? 1 : 0;
        check("MX-4-$k-$w", "$k at {$w}px: the Notes column is " . ($want ? 'shown' : 'hidden'), $a !== null && $vis === $want && ($want ? $mm['mx']['annoTd'] > 0 : $mm['mx']['annoTd'] === 0), j([$a, $mm['mx']['annoTd'] ?? null]));
    }
    foreach (['dark'] as $mode) { $md = $g($k, 1440, $mode); check("MX-5-$k-$mode", "$k at 1440 in dark mode: same fit (no cell outside, no horizontal scroll)", ($md['mx']['outside'] ?? 1) === 0 && ($md['docW'] ?? 9e9) <= ($md['vw'] ?? 0), j([$md['mx']['outside'] ?? null, $md['docW'] ?? null])); }
    $mi = $g($k, 1440, 'light', 'it');
    check("MX-6-$k-it", "$k at 1440 in Italian: no cell outside, no horizontal scroll, labels vertical and not cut", ($mi['mx']['outside'] ?? 1) === 0 && ($mi['docW'] ?? 9e9) <= ($mi['vw'] ?? 0) && ! array_filter($mi['mx']['labels'] ?? [1], fn ($l) => ! is_array($l) || ! $l['vertical'] || $l['cut']), j([$mi['mx']['outside'] ?? null, $mi['docW'] ?? null]));
    $m16 = $g($k, 1600);
    check("MX-7-$k-1600", "$k at 1600: no cell outside, no horizontal scroll with the Notes column back", ($m16['mx']['outside'] ?? 1) === 0 && ($m16['docW'] ?? 9e9) <= ($m16['vw'] ?? 0) && $m16['hscroll'] === [], j([$m16['mx']['outside'] ?? null, $m16['docW'] ?? null, $m16['hscroll'] ?? null]));
}
$mr = $g('role')['mx'] ?? [];
check('MX-8', 'Accessible names of the matrix controls: every cell has its tooltip, column boxes "Grant <action> on every area", row boxes "Grant every action on <area>"', ($mr['cellTitles'] ?? 1) === 0 && $mr['colChecks'] && ! array_filter($mr['colChecks'], fn ($l) => ! preg_match('/^Grant .+ on every area$/', (string) $l)) && ! array_filter($mr['rowChecks'], fn ($l) => ! preg_match('/^Grant every action on .+/', (string) $l)), j([$mr['cellTitles'] ?? null, array_slice($mr['colChecks'] ?? [], 0, 3), array_slice($mr['rowChecks'] ?? [], 0, 3)]));
$axr = $EN['x-role'] ?? [];
$axCells = array_filter($axr, fn ($n) => ($n['role'] ?? '') === 'button' || ($n['role'] ?? '') === 'checkbox');
check('MX-9', 'Chrome computes a non-empty accessible name for every button and checkbox of the role page (' . count($axCells) . ')', $axCells && ! array_filter($axCells, fn ($n) => ! $n['ignored'] && trim((string) $n['name']) === ''), j(array_values(array_filter($axCells, fn ($n) => trim((string) $n['name']) === ''))));
$le = $MEAS['le8'];
foreach (['role', 'user-admin'] as $k) {
    $a = $le["m8-$k-1440"]['mx'] ?? null;
    check("MX-10-$k", "$k with 8 actions or fewer (" . count($le['actions']) . '): not dense, labels horizontal (wider than tall); widths compared with the parent in MX-11', $a && $a['cols'] <= 8 && ! str_contains($a['cls'], 'dense') && ! array_filter($a['labels'], fn ($l) => $l['vertical']), j([$a['cols'] ?? null, $a['cls'] ?? null, $a['actW'] ?? null, array_slice($a['labels'] ?? [], 0, 2)]));
    if ($P && isset($P['le8']["m8-$k-1440"]['mx'])) {
        $b = $P['le8']["m8-$k-1440"]['mx'];
        $sameW = $a && count($a['actW']) === count($b['actW']) && ! array_filter(array_map(fn ($x, $y) => abs($x - $y) > 2, $a['actW'], $b['actW']));
        check("MX-11-$k", "$k with 8 actions or fewer: same layout as the parent 603a94c (column widths, label orientation, Notes column at 1440)", $sameW && $a['anno'] == $b['anno'] && array_column($a['labels'], 'vertical') === array_column($b['labels'], 'vertical'), j(['now' => [$a['actW'] ?? null, $a['anno'] ?? null], 'parent' => [$b['actW'], $b['anno']]]));
    }
}
// ---- SA: super admin user
$sa = $g('user-sa'); $saIt = $g('user-sa', 1440, 'light', 'it'); $ad = $g('user-admin'); $adIt = $g('user-admin', 1440, 'light', 'it');
check('SA-1', 'Super admin user, Roles: the Super admin row reads "All permissions, as super admin" (no "own + inherited" count)', ($sa['roles'] ?? null) && $sa['roles'][0][1] === 'All permissions, as super admin', j($sa['roles'] ?? null));
check('SA-2', 'Same in Italian: "Tutti i permessi, come super admin"', ($saIt['roles'][0][1] ?? '') === 'Tutti i permessi, come super admin', j($saIt['roles'] ?? null));
$want = '<i>Zed</i> & "Q" is a super admin and holds every permission, except the ones denied for this user below.';
check('SA-3', 'Above the matrix: "<name> is a super admin and holds every permission, except the ones denied for this user below. …", with the name escaped (shown as text, no <i> element)', ($sa['saNote'] ?? null) && str_starts_with($sa['saNote']['text'], $want) && $sa['saNote']['before'] === true && ! str_contains($sa['saNote']['html'], '<i>'), j($sa['saNote'] ?? null));
check('SA-4', 'The sentence exists in Italian (translated, with the name, above the matrix)', ($saIt['saNote'] ?? null) && str_contains($saIt['saNote']['text'], '<i>Zed</i> & "Q"') && ! str_contains($saIt['saNote']['text'], 'holds every permission') && $saIt['saNote']['before'] === true, j($saIt['saNote']['text'] ?? null));
check('SA-5', 'A user without a super admin role (admin): the role row still reads "N own + M inherited permissions" and no super admin sentence', ($ad['roles'] ?? null) && preg_match('/^\d+ own \+ \d+ inherited permissions$/', $ad['roles'][0][1]) && $ad['saNote'] === null && ($adIt['saNote'] ?? null) === null, j([$ad['roles'] ?? null, $ad['saNote'] ?? null]));
if ($P) { $pa = $P['en']['m-user-admin-1440-light']['roles'] ?? null; check('SA-6', 'Admin user, Roles text identical to the parent 603a94c (nothing changed for a non super admin)', $pa !== null && $pa === ($ad['roles'] ?? null) && ($P['it']['m-user-admin-1440-light']['roles'] ?? 0) === ($adIt['roles'] ?? 1), j(['now' => $ad['roles'] ?? null, 'parent' => $pa])); }
// ---- CK: Check a permission
foreach (['en', 'it'] as $lang) foreach (['light', 'dark'] as $mode) {
    $cg = $g('check-granted', 1440, $mode, $lang); $cd = $g('check-denied', 1440, $mode, $lang); $cs2 = $g('check-sadeny', 1440, $mode, $lang);
    $T = $cg['T'] ?? [];
    check("CK-1-$lang-$mode", "Granted verdict ($lang, $mode): a mark in the granted colour (green), a single check stroke", ($cg['verdict']['svg'] ?? null) && same_col($cg['verdict']['svg']['color'], $T['granted'] ?? null) && $cg['verdict']['svg']['parts'] === 1, j([$cg['verdict']['svg']['cls'] ?? null, $cg['verdict']['svg']['color'] ?? null, $T['granted'] ?? null, $cg['verdict']['svg']['parts'] ?? null]));
    foreach (['denied (override on admin)' => $cd, 'denied (override on a super admin)' => $cs2] as $lab => $x) {
        $id = str_contains($lab, 'super') ? 'CK-2s' : 'CK-2';
        check("$id-$lang-$mode", "Verdict $lab ($lang, $mode): a mark in the denied colour (red), a check with a strike (more than one stroke), different from the granted one", ($x['verdict']['svg'] ?? null) && same_col($x['verdict']['svg']['color'], $x['T']['denied'] ?? null) && $x['verdict']['svg']['parts'] >= 2 && $x['verdict']['svg']['html'] !== ($cg['verdict']['svg']['html'] ?? ''), j([$x['verdict']['svg']['cls'] ?? null, $x['verdict']['svg']['color'] ?? null, $x['T']['denied'] ?? null, $x['verdict']['svg']['parts'] ?? null]));
    }
    $lb = $cg['decides']['label'] ?? null;
    check("CK-3-$lang-$mode", "The rule that decides carries the label \"" . ($lang === 'en' ? 'decides' : '…') . "\" in the accent (text or fill), on one rule only", $lb && ($cg['decides']['count'] ?? 0) === 1 && (same_col($lb['color'], $T['accent'] ?? null) || same_col($lb['bg'], $T['accent'] ?? null) || same_col($lb['bg'], $T['accent-tint'] ?? null) && same_col($lb['color'], $T['accent'] ?? null)) && ($lang !== 'en' || $lb['text'] === 'decides'), j([$lb, $T['accent'] ?? null]));
    check("CK-4-$lang-$mode", 'The deciding rule is the right one: role (granted to v8bowner? no: super admin), user denial for admin and for the denied super admin', str_contains((string) ($cg['decides']['li'] ?? ''), $lang === 'en' ? 'super admin role' : 'super admin') && preg_match($lang === 'en' ? '/denial on this user/i' : '/./', (string) ($cd['decides']['li'] ?? '')) && ($cd['decides']['li'] ?? 1) === ($cs2['decides']['li'] ?? 2), j([$cg['decides']['li'] ?? null, $cd['decides']['li'] ?? null, $cs2['decides']['li'] ?? null]));
}
$cg = $g('check-granted'); $cgi = $g('check-granted', 1440, 'light', 'it');
$holdOk = fn ($m, $word) => ($m['holders'] ?? []) && ! array_filter($m['holders'], fn ($h) => $h['link'] !== $word || ! preg_match('#/rolewarden/users/(\d+)$#', (string) $h['userHref'], $u) || ! preg_match('#permissions/check\?permission=users\.delete&user=' . $u[1] . '$#', (string) $h['href']));
check('CK-5', 'Who holds it: every row\'s Notes link reads "Explain" and points to Check a permission for that row\'s user (' . count($cg['holders'] ?? []) . ' rows)', $holdOk($cg, 'Explain'), j(array_slice($cg['holders'] ?? [], 0, 3)));
check('CK-6', 'Same in Italian: "Spiega"', $holdOk($cgi, 'Spiega'), j(array_slice($cgi['holders'] ?? [], 0, 2)));
// follow each Explain link over HTTP: the page answers for that user
[$hc, $hok] = u1login('shot-explain', 'owner@v8b.test', OPW);
$expl = [];
foreach ($cg['holders'] ?? [] as $h) { $hh = G($hc, preg_replace('#^.*?/index\.php/#', '', html_entity_decode((string) $h['href'])), 'explain ' . $h['user']); $ver = dom($hh)->query('//*[contains(@class,"rw-verdict")]')->item(0); $expl[] = [$h['user'], $hc->status, $ver ? trim(preg_replace('/\s+/', ' ', $ver->textContent)) : null]; }
check('CK-7', 'Each Explain link opens (200) the outcome for that user, who holds users.delete', $expl && ! array_filter($expl, fn ($e) => $e[1] !== 200 || ! str_starts_with((string) $e[2], $e[0] . ' holds users.delete')), j($expl));
if ($P) {
    $pv = $P['en']['m-check-granted-1440-light']['verdict'] ?? null;
    check('CK-8', 'The verdict text is larger than on the parent 603a94c', $pv && ($cg['verdict']['fs'] ?? 0) > ($pv['fs'] ?? 99), j(['now' => [$cg['verdict']['fs'] ?? null, $cg['verdict']['fw'] ?? null], 'parent' => [$pv['fs'] ?? null, $pv['fw'] ?? null], 'body' => $cg['bodyFs'] ?? null]));
} else check('CK-8', 'The verdict text is larger than the body text', ($cg['verdict']['fs'] ?? 0) > ($cg['bodyFs'] ?? 99), j([$cg['verdict']['fs'] ?? null, $cg['bodyFs'] ?? null]));
// ---- K: command search, dark
$k1 = $K['k1'] ?? null; $k2 = $K['k2'] ?? null; $k3 = $K['k3'] ?? null; $k4 = $K['k4'] ?? null; $k5 = $K['k5'] ?? null; $k6 = $K['k6'] ?? null;
$bd = rgbn($k1['backdrop'] ?? null); $surf = rgbn($k1['T']['surface'] ?? null);
$under = $surf; $over = $bd && $surf ? [$bd[0] * $bd[3] + $surf[0] * (1 - $bd[3]), $bd[1] * $bd[3] + $surf[1] * (1 - $bd[3]), $bd[2] * $bd[3] + $surf[2] * (1 - $bd[3])] : null;
$ratio = $over ? (lum($under) + 0.05) / (lum($over) + 0.05) : 0;
// pixels: mean luminance of the page area left of the panel, before and after opening
$pix = function (string $f, int $x0, int $x1, int $y0, int $y1) { if (! is_file($f) || ! function_exists('imagecreatefrompng')) return null; $im = imagecreatefrompng($f); $s = 0; $n = 0; for ($x = $x0; $x < $x1; $x += 7) for ($y = $y0; $y < $y1; $y += 7) { $c = imagecolorat($im, $x, $y); $s += lum([($c >> 16) & 255, ($c >> 8) & 255, $c & 255]); $n++; } return $n ? $s / $n : null; };
$pb = $pix(png('cmd-before.1440.dark.en'), 260, 400, 300, 880); $pa = $pix(png('cmd-open.1440.dark.en'), 260, 400, 300, 880);
$pxr = $pb !== null && $pa !== null ? ($pb + 0.05) / ($pa + 0.05) : 0;
check('K-1', 'Ctrl K in dark mode opens the search, focus in the field', ($k1['open'] ?? false) && ($k1['focusQ'] ?? false), j([$k1['open'] ?? null, $k1['focusQ'] ?? null, $k1['active'] ?? null]));
check('K-2', 'Dark mode: the backdrop darkens the page visibly (backdrop colour ' . ($k1['backdrop'] ?? '?') . ', page area luminance before/after ' . round((float) $pb, 4) . '/' . round((float) $pa, 4) . ' = ' . round($pxr, 2) . ':1)', $bd && $bd[3] >= 0.3 && $pxr >= 1.3, j(['backdrop' => $k1['backdrop'] ?? null, 'surface' => $k1['T']['surface'] ?? null, 'computedRatio' => round($ratio, 2), 'pixelRatio' => round($pxr, 2)]));
if ($P) { $pk = $P['cmd']['k1'] ?? null; $pbd = rgbn($pk['backdrop'] ?? null); info('K before/after: backdrop parent=' . ($pk['backdrop'] ?? '?') . ' now=' . ($k1['backdrop'] ?? '?')); }
// focus indicators = the rings in the focus colour (the panel's own float shadow is not one); "once" = all on one element, all at its bottom edge
$focusRings = function (?array $k): array { if (! $k) return []; $f = rgbn($k['T']['focus']); $hex = $f ? sprintf('rgb(%d, %d, %d)', $f[0], $f[1], $f[2]) : '#';
    return array_values(array_filter($k['rings'] ?? [], fn ($r) => ($r['kind'] === 'outline' && same_col($r['c'], $k['T']['focus'])) || (str_starts_with($r['kind'], 'border') && same_col($r['c'], $k['T']['focus'])) || ($r['kind'] === 'shadow' && str_contains($r['v'], $hex)))); };
$once = function (?array $k) use ($focusRings): bool { $r = $focusRings($k); if (! $r) return false; $els = array_unique(array_column($r, 'el'));
    $bottom = ! array_filter($r, fn ($x) => ! ($x['kind'] === 'borderBottom' || ($x['kind'] === 'shadow' && preg_match('/0px -\d+(\.\d+)?px 0px 0px inset$/', $x['v']))));
    $o = explode(' ', (string) ($k['q']['outline'] ?? 'x 1px')); $noOutline = $o[0] === 'none' || ($o[1] ?? '') === '0px';
    return count($els) === 1 && $bottom && $noOutline; };
check('K-3', 'Dark: the field shows focus once: the only indicators in the focus colour are the bottom rule of the field row (border and inset line on the same edge), no outline on the field', $once($k1), j(['field outline' => $k1['q']['outline'] ?? null, 'focus' => $k1['T']['focus'] ?? null, 'indicators' => $focusRings($k1), 'all rings' => $k1['rings'] ?? null, 'parent' => $P['cmd']['k1']['rings'] ?? null]));
$kl = $K['kl'] ?? null;
check('K-3l', 'Light: same, one focus indicator at the bottom of the field row', $once($kl), j(['indicators' => $focusRings($kl), 'field outline' => $kl['q']['outline'] ?? null]));
check('K-4', 'Typing filters, Down moves the highlight (aria-activedescendant and aria-selected), all by keyboard', ($k2['n'] ?? 0) > 0 && ($k3['ad'] ?? '') !== '' && ($k3['ad'] ?? null) !== ($k2['ad'] ?? null) && ($k3['sel'] ?? []) === [($k3['ad'] ?? '')], j([$k2['n'] ?? null, $k2['ad'] ?? null, $k3['ad'] ?? null, $k3['sel'] ?? null]));
check('K-5', 'Esc closes the search', ($k4['open'] ?? true) === false, j($k4));
check('K-6', 'The sidebar search is reachable and opens with Enter; typing, Down and Enter open a result', ($K['sfocus'] ?? false) === true && ($k5['open'] ?? false) && ($k5['focusQ'] ?? false) && ($k6['n'] ?? 0) > 0 && is_string($K['k7'] ?? null) && $K['k7'] !== '/index.php/rolewarden/users', j([$K['sfocus'] ?? null, $k5['open'] ?? null, $k6['ad'] ?? null, $K['k7'] ?? null]));
$tabs = []; foreach ($K as $t => $f) if (preg_match('/^(f|cf|rf)\d+$/', $t)) $tabs[$t] = $f;
$side = array_filter($tabs, fn ($f) => is_array($f) && str_contains($f['el'], 'rw-side-search'));
check('K-7', 'Tab reaches the sidebar search link (it is the first stop after the end of the page wraps), with a visible focus', (bool) $side && ! array_filter($side, fn ($f) => ! $f['fv'] || ($f['ow'] < 2 && $f['shadow'] === 'none')), j(['stops' => array_keys($side), 'all' => array_map(fn ($f) => is_array($f) ? $f['el'] . ' ' . $f['name'] : null, $tabs)]));
// ---- ACT: activity
$ac = $g('activity'); $aci = $g('activity', 1440, 'light', 'it'); $acd = $g('activity', 1440, 'dark');
$fail = array_values(array_filter($ac['act'] ?? [], fn ($x) => $x['action'] === 'Failed sign-in'));
check('ACT-1', 'Failed sign-in row: the action in full ink (the ink token, not muted) with the denied mark', $fail && same_col($fail[0]['color'], $ac['T']['ink']) && same_col($fail[0]['rowColor'], $ac['T']['ink']) && (float) $fail[0]['opacity'] === 1.0 && array_filter($fail[0]['svg'], fn ($s) => same_col(explode('|', $s)[1], $ac['T']['denied'])), j([$fail[0] ?? null, $ac['T']['ink'] ?? null, $ac['T']['denied'] ?? null]));
$faild = array_values(array_filter($acd['act'] ?? [], fn ($x) => $x['action'] === 'Failed sign-in'));
check('ACT-1d', 'Same in dark mode', $faild && same_col($faild[0]['color'], $acd['T']['ink']) && array_filter($faild[0]['svg'], fn ($s) => same_col(explode('|', $s)[1], $acd['T']['denied'])), j([$faild[0] ?? null]));
$slugs = []; foreach ($ac['act'] ?? [] as $x) foreach ($x['slugs'] as $s) if (preg_match('/^(users|roles|sessions|permissions|documents|activity|settings|dashboard|admin|appearance|security|beta)\./', $s['s'])) $slugs[] = $s;
check('ACT-2', 'Permission slugs in the Details column are in monospace (' . count($slugs) . ' found)', count($slugs) >= 3 && ! array_filter($slugs, fn ($s) => ! preg_match('/mono|courier|consolas|menlo/i', $s['font'])), j(array_slice($slugs, 0, 4)));
check('ACT-3', 'Eyebrows: Activity "Security", Appearance and Settings "System"', ($ac['eyebrow'] ?? '') === 'Security' && ($g('appearance')['eyebrow'] ?? '') === 'System' && ($g('settings')['eyebrow'] ?? '') === 'System', j([$ac['eyebrow'] ?? null, $g('appearance')['eyebrow'] ?? null, $g('settings')['eyebrow'] ?? null]));
check('ACT-4', 'Eyebrows in Italian: "Sicurezza" and "Sistema"', ($aci['eyebrow'] ?? '') === 'Sicurezza' && ($g('appearance', 1440, 'light', 'it')['eyebrow'] ?? '') === 'Sistema' && ($g('settings', 1440, 'light', 'it')['eyebrow'] ?? '') === 'Sistema', j([$aci['eyebrow'] ?? null, $g('appearance', 1440, 'light', 'it')['eyebrow'] ?? null, $g('settings', 1440, 'light', 'it')['eyebrow'] ?? null]));
// ---- DB: dashboard
$d = $g('dashboard'); $di = $g('dashboard', 1440, 'light', 'it');
if ($P) { $pd = $P['en']['m-dashboard-1440-light']['dash'] ?? null; $hNow = max(array_map(fn ($x) => $x[3], $d['dash']['sign'] ?? [[0, 0, 0, 0]])); $hPar = max(array_map(fn ($x) => $x[3], $pd['sign'] ?? [[0, 0, 0, 0]]));
    check('DB-1', "The sign-ins chart is lower than on the parent 603a94c ({$hNow}px against {$hPar}px)", $hNow > 0 && $hPar > 0 && $hNow < $hPar, j(['now' => $d['dash']['sign'] ?? null, 'parent' => $pd['sign'] ?? null])); }
check('DB-2', 'Under the dashboard header there is one rule only (distinct horizontal rules from the header to 70px below it)', isset($d['dash']['distinct']) && count($d['dash']['distinct']) === 1, j(['distinct' => $d['dash']['distinct'] ?? null, 'lines' => array_slice($d['dash']['lines'] ?? [], 0, 6), 'parent' => $P['en']['m-dashboard-1440-light']['dash']['distinct'] ?? null]));
$strip = fn (array $a) => array_map(fn ($t) => preg_replace('/^\d+\s+/', '', (string) $t), $a);
$ch = $strip($d['dash']['changes']['items'] ?? []); $chi = $strip($di['dash']['changes']['items'] ?? []);
$hasG = array_filter($ch, fn ($t) => preg_match('/^v8bowner granted [a-z-]+\.[a-z-]+ to \S/', $t)); $hasD = array_filter($ch, fn ($t) => preg_match('/^v8bowner denied users\.delete to v8badmin\b/', $t));
check('DB-3', 'Latest permission changes read "<who> granted <slug> to <subject>" and "<who> denied <slug> to <subject>"', $hasG && $hasD, j($ch ?: $d['dash']['changes']['raw'] ?? null));
check('DB-4', 'In Italian: "<chi> ha concesso <slug> a <soggetto>" (and the denial translated too)', array_filter($chi, fn ($t) => preg_match('/^v8bowner ha concesso [a-z-]+\.[a-z-]+ a \S/', $t)) && array_filter($chi, fn ($t) => preg_match('/^v8bowner .+ users\.delete a v8badmin\b/', $t) && ! str_contains($t, 'denied')), j($chi ?: $di['dash']['changes']['raw'] ?? null));
// ---- V: various
$us = $g('users'); $usi = $g('users', 1440, 'light', 'it');
$nr = array_values(array_filter($us['users'] ?? [], fn ($x) => $x[0] === 'norole'));
check('V-1', 'Users list: a user without roles shows "No role"', $nr && $nr[0][1] === 'No role', j($nr));
$nri = array_values(array_filter($usi['users'] ?? [], fn ($x) => $x[0] === 'norole'));
check('V-1it', 'Users list in Italian: the same cell is translated (not empty, not English)', $nri && $nri[0][1] !== '' && $nri[0][1] !== 'No role', j($nri));
check('V-2', 'Role page: the selector is labelled "Role"', ($g('role')['roleJump']['label'] ?? '') === 'Role', j($g('role')['roleJump'] ?? null));
info('V-2 Italian label: ' . j($g('role', 1440, 'light', 'it')['roleJump'] ?? null));
foreach (['light', 'dark'] as $mode) {
    $rp = $g('role', 1440, $mode); $sv = array_values(array_filter($rp['primary'] ?? [], fn ($b) => $b['text'] === 'Save changes'));
    $b = $sv[0] ?? null; $bg = rgbn($b['bg'] ?? null);
    $ok = $b && $b['disabled'] && (($bg[3] ?? 1) < 0.05 || same_col($b['bg'], $rp['T']['surface']) || same_col($b['bg'], $rp['T']['surface-raised'])) && (float) $b['opacity'] === 1.0 && (float) $b['bw'] > 0 && $b['bs'] !== 'none' && ! same_col($b['border'], $rp['T']['accent']);
    check("V-3-$mode", "Disabled primary \"Save changes\" ($mode): a sober outline, not a faded accent (no accent fill, full opacity, a visible border not in accent)", (bool) $ok, j([$b, $rp['T']['accent'] ?? null, 'parent' => $P['en']["m-role-1440-$mode"]['primary'] ?? null]));
}
$ap = $g('appearance');
$rights = array_unique(array_map(fn ($s) => $s['right'], $ap['sections'] ?? []));
check('V-4', 'Appearance: the section titles share one right edge (' . implode(',', $rights) . ')', count($ap['sections'] ?? []) >= 2 && max($rights) - min($rights) <= 1, j(['sections' => $ap['sections'] ?? null, 'parent' => $P['en']['m-appearance-1440-light']['sections'] ?? null]));
$si = $ap['savedIn'] ?? null;
check('V-5', 'Appearance: "Saved in" and its path do not wrap (one line box each)', $si && $si['ddRects'] === 1 && $si['dtRects'] === 1 && $si['codeH'] <= $si['lh'] * 1.4, j([$si, 'parent' => $P['en']['m-appearance-1440-light']['savedIn'] ?? null]));
foreach (['it', 'en'] as $lang) foreach ([1600] as $w) { $x = $g('appearance', $w, 'light', $lang); $sx = $x['savedIn'] ?? null; $rx = array_unique(array_map(fn ($s) => $s['right'], $x['sections'] ?? [])); check("V-6-$lang-$w", "Appearance $lang at {$w}px: same right edge for the sections and no wrap of \"Saved in\"", $sx && $sx['ddRects'] === 1 && $sx['dtRects'] === 1 && $rx && max($rx) - min($rx) <= 1, j([$sx, $rx])); }
$api = $g('appearance', 1440, 'light', 'it'); $sxi = $api['savedIn'] ?? null; $rxi = array_unique(array_map(fn ($s) => $s['right'], $api['sections'] ?? []));
check('V-6-it-1440', 'Appearance it at 1440px: same right edge for the sections and no wrap of "Salvato in"', $sxi && $sxi['ddRects'] === 1 && $sxi['dtRects'] === 1 && $rxi && max($rxi) - min($rxi) <= 1, j([$sxi, $rxi]));
foreach ([['en', 1440, $XA[1440] ?? null], ['en', 1600, $XA[1600] ?? null], ['en', 1920, $XA[1920] ?? null], ['it', 1440, $g('appearance', 1440, 'light', 'it')], ['it', 1600, $g('appearance', 1600, 'light', 'it')]] as [$lang, $w, $x]) {
    $sx = $x['savedIn'] ?? null; $edge = $sx['dlRight'] ?? 0;   // the facts list ends on the page's right margin (as "Download" and the save bar do)
    check("V-7-$lang-$w", "Appearance $lang at {$w}px: the \"Saved in\" path stays inside the right margin of the page head (path right " . ($sx['codeRight'] ?? '?') . "px, margin {$edge}px)", $sx && $edge > 0 && $sx['codeRight'] <= $edge + 1, j([$sx, 'parent' => $lang === 'en' && $w === 1440 ? ($P['en']['m-appearance-1440-light']['savedIn'] ?? null) : null]));
}
// ---- A11Y
$low = []; foreach ($EN as $t => $a) if (str_starts_with($t, 'a-') && is_array($a)) { if (($a['lowCount'] ?? 1) > 0) $low[$t] = [$a['lowCount'] ?? null, array_slice($a['low'] ?? [], 0, 3)]; }
check('A11Y-1', 'Contrast AA (4.5:1) of every visible text on the 11 views, light and dark (' . count(array_filter(array_keys($EN), fn ($t) => str_starts_with($t, 'a-'))) . ' audits)', count(array_filter(array_keys($EN), fn ($t) => str_starts_with($t, 'a-'))) === 22 && ! $low, j($low));
$empty = []; foreach ($EN as $t => $a) if (str_starts_with($t, 'x-') && is_array($a)) foreach ($a as $n) if (! $n['ignored'] && in_array($n['role'], ['button', 'link', 'checkbox', 'combobox', 'textbox', 'searchbox', 'ComboBox', 'DisclosureTriangle', 'radio', 'menuitem'], true) && trim((string) $n['name']) === '') $empty[$t][] = $n['role'];
check('A11Y-2', 'Every button, link, box, field and select of the 11 views has a non-empty accessible name (Chrome AX tree)', count(array_filter(array_keys($EN), fn ($t) => str_starts_with($t, 'x-'))) === 11 && ! $empty, j($empty));
$walk = []; foreach ($K as $t => $f) if (preg_match('/^(rf|cf)\d+$/', $t)) $walk[$t] = $f;
$left = array_filter($walk, fn ($f) => ! is_array($f));   // focus left the document (end of the page, into the browser)
$walk = array_filter($walk, 'is_array');
$nofocus = array_filter($walk, fn ($f) => ! $f['visible'] || ! $f['fv'] || ($f['outline'] === 'none' || $f['ow'] < 2) && $f['shadow'] === 'none');
check('A11Y-3', 'Focus visible: each Tab stop on the role matrix from the last column box (14) and on Check a permission (8) matches :focus-visible with a 2px outline (or ring); ' . count($left) . ' stop(s) outside the document', count($walk) + count($left) === 22 && count($walk) >= 20 && ! $nofocus, j(array_slice($nofocus, 0, 4, true) ?: array_map(fn ($f) => $f['el'] ?? null, array_slice($walk, 0, 6))));
$cellStop = array_filter($walk, fn ($f) => is_array($f) && str_contains($f['el'], 'rw-cell'));
check('A11Y-4', 'The matrix cells are reached by Tab', (bool) $cellStop, j(array_map(fn ($f) => $f['el'] ?? null, $walk)));
$jerr = array_filter($GLOBALS['JERR'], fn ($e) => $e !== []);
check('A11Y-5', 'No JavaScript error or console error in any browser job (' . count($GLOBALS['JERR']) . ' jobs)', ! $jerr, j($jerr));
// ---- LOG
$bad = seen_clean($S0);
check('LOG-1', 'No HTTP response >= 500, no SQL text, no PHP error in the bodies of this phase (' . (count($GLOBALS['SEEN']) - $S0) . ')', ! $bad, j($bad));
$ll = bad_log_lines(logs_of($APP));
check('LOG-2', 'No WARNING/ERROR/CRITICAL line in the application log', ! $ll, j(array_slice($ll, 0, 4)));
