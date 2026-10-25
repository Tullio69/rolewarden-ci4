<?php
/**
 * MOTION phase (Collaudatore ad Hoc, Claude): motion across the panel (commit b38e350; re-verification on a790b3a, RW_V8_NEW) in black box, over HTTP, direct
 * queries on rolewarden_test and headless Chrome (verify-motion.browser.mjs, Chrome DevTools Protocol).
 * Sources of the assertions: docs/design-system/README.md (Motion, rewritten 2026-10-25), components/Toast/README.md,
 * components/UserStatus/README.md and CommandSearch/README.md, CHANGELOG.md (Motion under Changed), the prompt of the
 * caller and the HTML a browser receives. No CSS source, JS, view, controller or unit test read; what the browser does
 * is observed through the DOM, document.getAnimations(), computed styles and real pointer and key events.
 * Sections: ERR (console errors, 3 themes x 3 sidebars), RM (reduced motion), CNT (dashboard counters en/it),
 * DLG (confirm dialogs), SRCH (command search), MTX (role matrix), OVR (user overrides), CUST (customize),
 * TST (toasts), NOJS (without JavaScript), NAV (sidebar, rail, frames), LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
date_default_timezone_set('UTC');
echo "\n== MOTION: motion across the panel (" . (getenv('RW_V8_NEW') ?: '?') . ")\n";
$S0 = count($GLOBALS['SEEN']);
$ENVN = getenv('RW_V8B_ENV') ?: 'development';
$OUTP = __DIR__ . '/verify-motion.';
$RUN = getenv('RW_MOTION_RUN') ?: '';

/** One Chrome job with the motion driver; returns the decoded result. */
function mb(array $steps, string $tag, int $timeout = 400000, int $settle = 300): array
{
    global $WORK;
    static $n = 0; $n++;
    $jf = "$WORK/mojob-$n.json";
    file_put_contents($jf, json_encode(['profile' => "$WORK/chrome-mo-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => $settle]));
    $p = proc_open(['node', __DIR__ . '/verify-motion.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'consoleErrors' => ['no result'], 'consoleCalls' => [], 'profileRemoved' => false]; }
    if ($r['errors']) info("browser $tag driver errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    return $r;
}
function jerr(array $r): array { return array_merge($r['consoleErrors'] ?? [], $r['consoleCalls'] ?? []); }
/** Console errors other than the aborted view transition (reported once, as its own check). */
function jerr_other(array $r): array { return array_values(array_filter(jerr($r), fn ($e) => ! str_contains($e, 'Transition was aborted because of invalid state'))); }
function jerr_vt(array $r): int { return count(array_filter(jerr($r), fn ($e) => str_contains($e, 'Transition was aborted because of invalid state'))); }
function sel_cell(string $slug): string { return 'table.rw-matrix button.rw-cell[title^="' . $slug . ' "]'; }
function mo_login(): array { return blogin('owner@v8b.test', OPW); }

// ---------------------------------------------------------------- page-side probes
const INIT = <<<'JS'
addEventListener('pagereveal', e => { window.__rwReveal = e.viewTransition ? 'vt' : 'none'; });
window.__moSeen = [];
// every animation and transition that starts, recorded with its keyframe properties (read and cleared by MOVED)
window.__moAnim = [];
(() => { const desc = (el) => el ? el.tagName + '.' + String(el.className && el.className.baseVal !== undefined ? el.className.baseVal : el.className).slice(0, 40) : '';
  const rec = (kind, e, pick) => { try { const a = document.getAnimations().find(pick); const ef = a && a.effect; let props = [], dur = -1, delay = 0;
      if (ef) { const t = ef.getComputedTiming(); dur = typeof t.duration === 'number' ? t.duration : 0; delay = ef.getTiming().delay || 0; const s = new Set(); (ef.getKeyframes ? ef.getKeyframes() : []).forEach(k => Object.keys(k).forEach(p => s.add(p))); props = [...s]; }
      if (kind === 'transition') { props.push(e.propertyName.replace(/-([a-z])/g, (m, c) => c.toUpperCase()));
        if (dur < 0) { const cs = getComputedStyle(e.target, e.pseudoElement || null); const tp = cs.transitionProperty.split(',').map(x => x.trim()); const td = cs.transitionDuration.split(',').map(x => x.trim()); let i = tp.indexOf(e.propertyName); if (i < 0) i = tp.indexOf('all'); const v = td[(i < 0 ? 0 : i) % td.length] || '0s'; dur = v.endsWith('ms') ? parseFloat(v) : parseFloat(v) * 1000; } }
      window.__moAnim.push({ kind, name: e.animationName || e.propertyName, el: desc(e.target), pseudo: e.pseudoElement || '', dur, delay, props, at: Math.round(performance.now()) }); } catch (x) {} };
  addEventListener('animationstart', e => rec('animation', e, x => x.animationName === e.animationName && x.effect && x.effect.target === e.target && (x.effect.pseudoElement || '') === (e.pseudoElement || '')), true);
  addEventListener('transitionstart', e => rec('transition', e, x => x.transitionProperty === e.propertyName && x.effect && x.effect.target === e.target && (x.effect.pseudoElement || '') === (e.pseudoElement || '')), true); })();
new MutationObserver(ms => { for (const m of ms) { const el = m.target.nodeType === 3 ? m.target.parentElement : m.target; const c = el && el.closest ? el.closest('[data-rw-count]') : null; if (c) window.__moSeen.push(c.getAttribute('data-rw-count') + '=' + c.textContent); } }).observe(document, { subtree: true, childList: true, characterData: true });
JS;
// running animations that move: anything but colour changes, a fade without delay and the backdrop
const MOVING = <<<'JS'
(() => { const allowed = new Set(['color','backgroundColor','borderColor','borderTopColor','borderRightColor','borderBottomColor','borderLeftColor','outlineColor','fill','stroke','textDecorationColor','caretColor','boxShadow','offset','easing','composite','computedOffset']);
  const bad = []; let all = 0;
  for (const a of document.getAnimations()) { if (a.playState !== 'running' && a.playState !== 'paused') continue; const ef = a.effect; if (!ef) continue; all++;
    const t = ef.getComputedTiming(); const dur = typeof t.duration === 'number' ? t.duration : 0; if (dur <= 1 && t.iterations !== Infinity) continue;
    if (ef.pseudoElement === '::backdrop') continue;
    const props = new Set(); (ef.getKeyframes ? ef.getKeyframes() : []).forEach(k => Object.keys(k).forEach(p => props.add(p)));
    if (a.transitionProperty) props.add(a.transitionProperty.replace(/-([a-z])/g, (m, c) => c.toUpperCase()));
    const delay = ef.getTiming().delay || 0;
    const moving = [...props].filter(p => !allowed.has(p) && !(p === 'opacity' && delay <= 0));
    if (moving.length) { const el = ef.target; bad.push((a.animationName || a.transitionProperty || 'anim') + ' [' + moving.join(',') + '] ' + (el ? el.tagName + '.' + String(el.className && el.className.baseVal !== undefined ? el.className.baseVal : el.className).slice(0, 40) : '') + (ef.pseudoElement || '') + ' ' + Math.round(dur) + 'ms d' + delay); } }
  return { moving: bad.slice(0, 15), count: bad.length, all, reveal: window.__rwReveal || null, url: location.pathname }; })()
JS;
// the started animations that move (same rule as MOVING), then the record is cleared
const MOVED = <<<'JS'
(() => { const allowed = new Set(['color','backgroundColor','borderColor','borderTopColor','borderRightColor','borderBottomColor','borderLeftColor','outlineColor','fill','stroke','textDecorationColor','caretColor','boxShadow','offset','easing','composite','computedOffset']);
  const all = window.__moAnim || []; window.__moAnim = [];
  const bad = all.filter(a => a.dur !== 0 && !(a.dur > 0 && a.dur <= 1) && a.pseudo !== '::backdrop').map(a => ({ ...a, moving: a.props.filter(p => !allowed.has(p) && !(p === 'opacity' && a.delay <= 0)) })).filter(a => a.moving.length);
  return { count: bad.length, all: all.length, moving: bad.slice(0, 12).map(a => a.kind + ' ' + a.name + ' [' + a.moving.join(',') + '] ' + a.el + a.pseudo + ' ' + Math.round(a.dur) + 'ms d' + a.delay), url: location.pathname }; })()
JS;
const COUNTS = "[...document.querySelectorAll('[data-rw-count]')].map(e => e.textContent)";
const SEEN = "({ seen: window.__moSeen || [], now: [...document.querySelectorAll('[data-rw-count]')].map(e => e.getAttribute('data-rw-count') + '=' + e.textContent) })";
const CELLS = <<<'JS'
(() => { const o = {}; for (const c of document.querySelectorAll('table.rw-matrix button.rw-cell')) { const slug = (c.getAttribute('title') || '').split(' ')[0]; const m = c.querySelector('.rw-mark'); o[slug] = { pressed: c.getAttribute('aria-pressed'), mark: m ? [...m.classList].filter(x => x.startsWith('rw-mark--')).join(',') : (c.querySelector('.rw-mark-blank') ? 'blank' : 'none'), changed: c.classList.contains('rw-cell--changed') }; }
  const bar = document.querySelector('.rw-bar'); return { cells: o, bar: bar ? bar.textContent.replace(/\s+/g, ' ').trim() : null, dirty: !!document.querySelector('.rw-bar--dirty') }; })()
JS;
const SETTLED = <<<'JS'
(() => { const bad = []; const id = (t) => t === 'none' || t === 'matrix(1, 0, 0, 1, 0, 0)';
  for (const c of document.querySelectorAll('table.rw-matrix button.rw-cell')) { const name = (c.getAttribute('title') || '?').split(' ')[0];
    const run = c.getAnimations({ subtree: true }).filter(a => a.playState === 'running'); if (run.length) bad.push('running ' + name + ' ' + run.map(a => a.animationName || a.transitionProperty).join(','));
    const cs = getComputedStyle(c); if (Number(cs.opacity) < 0.999) bad.push('opacity ' + cs.opacity + ' ' + name); if (!id(cs.transform) || (cs.scale !== 'none' && cs.scale !== '1')) bad.push('transform ' + cs.transform + ' ' + cs.scale + ' ' + name);
    const svg = c.querySelector('svg'); if (svg) { const ss = getComputedStyle(svg); if (Number(ss.opacity) < 0.999 || !id(ss.transform) || (ss.scale !== 'none' && ss.scale !== '1')) bad.push('svg ' + ss.opacity + ' ' + ss.transform + ' ' + ss.scale + ' ' + name); }
    for (const p of c.querySelectorAll('path, circle, line, polyline')) { const ps = getComputedStyle(p); const off = parseFloat(ps.strokeDashoffset) || 0; if (Math.abs(off) > 0.001) bad.push('dashoffset ' + off + ' ' + name); if (Number(ps.opacity) < 0.999) bad.push('path opacity ' + name); } }
  return bad.slice(0, 20); })()
JS;
const TOASTS = "[...document.querySelectorAll('.rw-toasts .rw-toast')].filter(t => { const s = getComputedStyle(t); return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.05; }).map(t => ({ kind: ([...t.classList].find(c => /--(success|warning|error)$/.test(c)) || '').replace('rw-toast--', ''), text: t.textContent.replace(/\\s+/g, ' ').replace('×', '').trim(), mo: t.dataset.mo || '', top: Math.round(t.getBoundingClientRect().top), bottom: Math.round(t.getBoundingClientRect().bottom) }))";
const TAG_TOASTS = "(() => { let n = window.__moN || 0; for (const t of document.querySelectorAll('.rw-toasts .rw-toast')) if (!t.dataset.mo) t.dataset.mo = 'T' + (++n); window.__moN = n; return n; })()";
// click Save, wait for a new toast, tag it; returns its kind
const SAVE_AND_TOAST = "(async () => { const before = document.querySelectorAll('.rw-toasts .rw-toast').length; const b = document.querySelector('.rw-bar button.rw-btn--primary'); if (!b || b.disabled) return 'no save'; b.click(); const t0 = performance.now(); while (document.querySelectorAll('.rw-toasts .rw-toast').length <= before && performance.now() - t0 < 5000) await new Promise(r => setTimeout(r, 50)); const t = [...document.querySelectorAll('.rw-toasts .rw-toast')].pop(); if (!t) return 'none'; let n = window.__moN || 0; if (!t.dataset.mo) t.dataset.mo = 'T' + (++n); window.__moN = n; return t.dataset.mo + ':' + t.className; })()";
// click Save and measure how long the new toast stays visible
const SAVE_AND_TIME = "(async () => { const vis = (el) => { if (!el.isConnected) return false; const s = getComputedStyle(el); return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.05; }; const before = new Set(document.querySelectorAll('.rw-toasts .rw-toast')); document.querySelector('.rw-bar button.rw-btn--primary').click(); const t0 = performance.now(); let el = null; while (!el && performance.now() - t0 < 5000) { await new Promise(r => setTimeout(r, 25)); el = [...document.querySelectorAll('.rw-toasts .rw-toast')].find(t => !before.has(t)); } if (!el) return { appeared: false }; const kind = el.className; const tc = performance.now(); while (!vis(el) && performance.now() - tc < 3000) await new Promise(r => setTimeout(r, 25)); const ta = performance.now(); while (vis(el) && performance.now() - ta < 15000) await new Promise(r => setTimeout(r, 50)); return { appeared: true, kind, ms: Math.round(performance.now() - ta) }; })()";
function dlg_js(string $id): string
{
    return "(() => { const d = document.getElementById(" . json_encode($id) . "); if (!d) return null; const cs = getComputedStyle(d); const h = document.querySelector('main h1') || document.querySelector('h1'); const r = h ? h.getBoundingClientRect() : null; const a = document.activeElement;
      return { open: d.open, opacity: cs.opacity, transform: cs.transform, scale: cs.scale, translate: cs.translate, running: d.getAnimations({ subtree: true }).filter(x => x.playState === 'running').map(x => x.animationName || x.transitionProperty), h1: r ? [Math.round(r.x), Math.round(r.y)] : null, scrollY: Math.round(window.scrollY),
        active: a ? a.tagName + '|' + (a.getAttribute('onclick') || a.getAttribute('type') || '') + '|' + a.textContent.trim().slice(0, 20) : null, focusVisible: a ? a.matches(':focus-visible') : false }; })()";
}
const CMD = <<<'JS'
(() => { const d = document.querySelector('dialog.rw-cmd'); const q = document.getElementById('rw-cmd-q'); const opts = [...d.querySelectorAll('[role=option]')]; const cs = getComputedStyle(d); const a = document.activeElement;
  return { open: d.open, focusQ: a === q, ad: q.getAttribute('aria-activedescendant'), sel: opts.filter(o => o.getAttribute('aria-selected') === 'true').map(o => o.id), n: opts.length, hrefs: Object.fromEntries(opts.slice(0, 6).map(o => [o.id, o.getAttribute('href')])), opacity: cs.opacity, transform: cs.transform, scale: cs.scale,
    active: a.tagName + '.' + String(a.className).slice(0, 30), activeIsSearchLink: a.classList.contains('rw-side-search') }; })()
JS;
const NAV = <<<'JS'
(() => { const cur = [...document.querySelectorAll('aside a[aria-current]')]; const pills = [...document.querySelectorAll('aside .rw-side-pill')]; const a = cur[0]; const p = pills[0];
  const ar = a ? a.getBoundingClientRect() : null; const pr = p ? p.getBoundingClientRect() : null; const covered = [];
  for (const t of document.querySelectorAll('aside .rw-side-nav .rw-side-text')) { const r = t.getBoundingClientRect(); if (r.width < 1) continue; const e = document.elementFromPoint(r.x + Math.min(r.width / 2, 8), r.y + r.height / 2); if (!(e === t || t.contains(e))) covered.push(t.textContent.trim() + '->' + (e ? e.tagName + '.' + e.className : 'null')); }
  return { url: location.pathname, current: cur.map(x => x.getAttribute('href')), pills: pills.length, pillIn: !!(a && p && a.contains(p)), pillRect: pr && [pr.x, pr.y, pr.width, pr.height].map(Math.round), linkRect: ar && [ar.x, ar.y, ar.width, ar.height].map(Math.round),
    inside: !!(ar && pr && pr.width > 0 && pr.x >= ar.x - 2 && pr.y >= ar.y - 2 && pr.right <= ar.right + 2 && pr.bottom <= ar.bottom + 2), pillTf: p ? getComputedStyle(p).transform : null, pillRunning: p ? p.getAnimations().filter(x => x.playState === 'running').length : 0,
    covered, reveal: window.__rwReveal || null, rail: !!document.querySelector('.rw-shell--rail') }; })()
JS;
// content a reader cannot see: opacity below 1, hidden visibility, a clip or a zero scale on the element or an ancestor
const HIDDEN = <<<'JS'
((viewportOnly) => { const root = document.querySelector('main') || document.body; const skip = 'template, dialog, noscript, script, style, [x-cloak], .rw-visually-hidden, [hidden], .rw-toasts';
  const hidden = []; let n = 0, rows = 0;
  for (const el of root.querySelectorAll('*')) { if (el.closest(skip)) continue; const d = el.parentElement && el.parentElement.closest('details'); if (d && !d.open && !el.closest('summary')) continue;
    const own = [...el.childNodes].some(x => x.nodeType === 3 && x.textContent.trim() !== '') || el.tagName === 'svg' || el.tagName === 'TR'; if (!own) continue;
    const r = el.getBoundingClientRect(); if (viewportOnly && (r.bottom < 0 || r.top > innerHeight)) continue;
    let op = 1, why = '', none = false;
    for (let e = el; e && e !== document.documentElement; e = e.parentElement) { const s = getComputedStyle(e); if (s.display === 'none') { none = true; break; } op *= Number(s.opacity); if (s.visibility === 'hidden') why = 'visibility hidden'; if (s.clipPath && s.clipPath !== 'none' && !(/^inset\(/.test(s.clipPath) && s.clipPath.replace(/^inset\(|\)$/g, '').split(/\s+/).every(v => parseFloat(v) === 0))) why = why || 'clip-path ' + s.clipPath + ' on ' + e.tagName; if (s.scale === '0' || /matrix\(0, 0, 0, 0/.test(s.transform)) why = why || 'scale 0'; }
    if (none) continue; n++; if (el.tagName === 'TR') rows++;
    if (op < 0.99 && !el.closest('button:disabled, [aria-disabled=true], span.rw-page')) why = why || 'opacity ' + op.toFixed(2);   // disabled controls: 50% by design
    if (why) hidden.push(el.tagName.toLowerCase() + '.' + String(el.className && el.className.baseVal !== undefined ? el.className.baseVal : el.className).slice(0, 40) + ' "' + el.textContent.trim().replace(/\s+/g, ' ').slice(0, 30) + '" ' + why); }
  return { n, rows, count: hidden.length, hidden: hidden.slice(0, 20), url: location.pathname }; })
JS;
function hidden_js(bool $viewportOnly): string { return '(' . HIDDEN . ')(' . ($viewportOnly ? 'true' : 'false') . ')'; }
const KEY_ENTER = ['op' => 'key', 'key' => 'Enter', 'code' => 'Enter', 'keyCode' => 13, 'text' => "\r"];
const KEY_ESC = ['op' => 'key', 'key' => 'Escape', 'code' => 'Escape', 'keyCode' => 27];
const KEY_DOWN = ['op' => 'key', 'key' => 'ArrowDown', 'code' => 'ArrowDown', 'keyCode' => 40];
const KEY_UP = ['op' => 'key', 'key' => 'ArrowUp', 'code' => 'ArrowUp', 'keyCode' => 38];
const KEY_TAB = ['op' => 'key', 'key' => 'Tab', 'code' => 'Tab', 'keyCode' => 9];

// ================================================================= data of the phase
$NOW = time();
$RID = mkrole('mo-editor', ['users.view', 'roles.view', 'settings.view']);
$UID = mkuser('mo-user@motion.test', 'mo-editor', ['users.view' => 0, 'roles.view' => 1]);
$RDEL = mkrole('mo-del-role', []);
$UDEL = mkuser('mo-del-user@motion.test', 'user');
// bulk rows so that the counters have four digits (thousands separators differ between en and it)
$batch = function (string $sql, array $rows, int $per) { foreach (array_chunk($rows, $per) as $ch) { $ph = implode(',', array_fill(0, count($ch), '(' . implode(',', array_fill(0, count($ch[0]), '?')) . ')')); q($sql . $ph, array_merge(...$ch)); } };
$u = []; for ($i = 0; $i < 1234; $i++) { $c = date('Y-m-d H:i:s', $NOW - 3 * 86400 - $i); $u[] = ["mo-bulk-$i", 1, $c, $c]; }
$batch('INSERT INTO users (username, active, created_at, updated_at) VALUES ', $u, 400);
$l = []; for ($i = 0; $i < 12345; $i++) $l[] = ['198.51.100.' . (1 + $i % 50), 'mo agent', 'email_password', 'p' . ($i % 90) . '@motion.test', $OWNER, date('Y-m-d H:i:s', $NOW - 120 - ($i * 197) % (28 * 86400)), 1];
for ($i = 0; $i < 2345; $i++) $l[] = ['203.0.113.7', 'mo agent', 'email_password', 'f' . ($i % 3) . '@motion.test', null, date('Y-m-d H:i:s', $NOW - 60 - ($i * 13) % 36000), 0];
$batch('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES ', $l, 500);
$al = []; $acts = ['role.permission_granted', 'role.permission_revoked', 'user.override_granted', 'user.override_denied', 'user.override_cleared', 'user.role_assigned', 'user.role_revoked'];
for ($i = 0; $i < 1111; $i++) $al[] = [$OWNER, 'v8bowner', $acts[$i % 7], 'user', $PLAIN, "Mo subject $i", json_encode(['permission' => 'users.view']), '127.0.0.1', date('Y-m-d H:i:s', $NOW - 300 - $i * 600)];
$batch('INSERT INTO acl_activity_log (actor_id, actor_label, action, subject_type, subject_id, subject_label, details, ip_address, created_at) VALUES ', $al, 300);
info('cache:clear ' . short(sp8($APP, ['cache:clear']), 120));
serve8($APP, 'MOTION');
[$own, $okOwn] = u1login('mo-own', 'owner@v8b.test', OPW);
check('SET-1', 'Owner signs in over HTTP; the test data is in place (1,234 bulk users, 12,345 + 2,345 sign-ins, 1,111 changes)', $okOwn && (int) q1("SELECT COUNT(*) FROM users WHERE username LIKE 'mo-bulk-%'") === 1234);
$served = G($own, U1BASE . 'rolewarden', 'dash served');
preg_match_all('/data-rw-count="([^"]*)"[^>]*>([^<]*)</', $served, $mm);
info('served counters: ' . json_encode(array_map(null, $mm[1], $mm[2])));

$SIDE = ['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions', 'rolewarden/permissions/check', 'rolewarden/activity', 'rolewarden/settings', 'rolewarden/appearance', 'rolewarden/'];
$sideSel = fn (string $p) => $p === 'rolewarden/' ? 'aside .rw-side-nav a[href$="/rolewarden/"]' : 'aside .rw-side-nav a[href$="/' . $p . '"]';
$OTHER = ['role' => "rolewarden/roles/$RID", 'user' => "rolewarden/users/$UID", 'profile' => 'rolewarden/profile', 'search' => 'rolewarden/search?q=users'];

// ================================================================= ERR: no JavaScript error, three themes x three sidebars
$tour = function (string $p, bool $interact = true) use ($SIDE, $sideSel, $OTHER): array {
    $s = [mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => "$p-start"], ['op' => 'wait', 'ms' => 1200]];
    foreach ($SIDE as $sp) { $s[] = ['op' => 'mouse', 'selector' => $sideSel($sp), 'click' => true, 'nav' => true, 'tag' => "$p-$sp"]; $s[] = ['op' => 'wait', 'ms' => 1300]; }
    if ($interact) {
        $s[] = ['op' => 'mouse', 'selector' => 'details.rw-dash-customize > summary', 'click' => true, 'wait' => 700, 'tag' => "$p-cust"];
        $s[] = ['op' => 'key', 'key' => 'k', 'code' => 'KeyK', 'keyCode' => 75, 'modifiers' => 2, 'wait' => 700, 'tag' => "$p-ctrlk"];
        $s[] = ['op' => 'type', 'text' => 'role', 'wait' => 900];
        $s[] = KEY_ESC + ['wait' => 500];
    }
    foreach ($OTHER as $k => $u) { $s[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "$p-$k"]; $s[] = ['op' => 'wait', 'ms' => 1300]; }
    if ($interact) {
        $s[] = ['op' => 'goto', 'url' => U1BASE . $OTHER['role'], 'tag' => "$p-role2"];
        $s[] = ['op' => 'mouse', 'selector' => 'button[onclick*="confirm-delete-role"]', 'click' => true, 'wait' => 700];
        $s[] = ['op' => 'mouse', 'selector' => '#confirm-delete-role button.rw-btn--ghost', 'click' => true, 'wait' => 500];
        $s[] = ['op' => 'mouse', 'selector' => sel_cell('activity.view'), 'click' => true, 'wait' => 700];
        $s[] = ['op' => 'mouse', 'selector' => '.rw-bar button.rw-btn--ghost', 'click' => true, 'wait' => 700];
    }
    return $s;
};
$errAll = []; $vtTotal = 0;
foreach (['console', 'clarity', 'contrast'] as $base) foreach (['' => 'paper', 'ink' => 'ink', 'charcoal' => 'charcoal'] as $sb => $sbn) {
    theme_save($own, ['base' => $base, 'sidebar' => $sb], "theme $base/$sbn");
    $tj = theme_json();
    $r = mb($tour("$base-$sbn", $base === 'console' && $sbn === 'paper'), "err $base/$sbn");
    $shown = bval($r, "$base-$sbn-start") ;
    $other = jerr_other($r); $vtTotal += jerr_vt($r);
    $navOk = count(array_filter($r['steps'], fn ($s) => ($s['op'] ?? '') === 'mouse' && isset($s['status']) && $s['status'] === 200)) >= count($SIDE);
    check("ERR-$base-$sbn", "No JavaScript error (other than the view transition, see VT-err) on the 12 pages, theme $base, sidebar $sbn", ($tj['base'] ?? 'console') === $base && $other === [] && $navOk && ($r['errors'] ?? []) === [], json_encode(['theme' => $tj, 'errors' => $other, 'driver' => $r['errors'] ?? null, 'navOk' => $navOk]));
    $errAll = array_merge($errAll, $other);
}
info("view transition errors over the 9 tours: $vtTotal");
check('VT-err', 'No "Transition was aborted ... ViewTransition opt-in disabled" error when moving between pages of the panel', $vtTotal === 0, "uncaught InvalidStateError on $vtTotal sidebar navigations");
theme_save($own, ['base' => 'console', 'sidebar' => ''], 'theme back');

// ================================================================= NAV: active item, pill, view transition, rail, frames
const PILLX = "(() => { const p = document.querySelector('aside .rw-side-pill'); if (!p) return null; const r = p.getBoundingClientRect(); return { y: Math.round(r.y), h: Math.round(r.height), tf: getComputedStyle(p).transform, url: location.pathname }; })()";
/** D5: from the first animation frame of the new page (/settings, in-page requestAnimationFrame log) the pill starts on the Users item it left (within half its height of where it rested) and moves only downwards (Users is above Settings). */
function slide_ok($log, $users): array
{
    $ys = is_array($log) ? $log : [];
    $uy = is_array($users) ? $users['y'] : null; $uh = is_array($users) ? (int) $users['h'] : 0;
    if ($uy === null || count($ys) < 3) return [false, json_encode(['users' => $users, 'frames' => $ys])];
    $mono = true; for ($i = 1; $i < count($ys); $i++) if ($ys[$i][1] < $ys[$i - 1][1] - 1) $mono = false;
    $last = end($ys)[1]; $dy = $ys[0][1] - $uy;
    return [abs($dy) <= max(4, $uh / 2) && $mono && $last - $uy > 50, json_encode(['usersY' => $uy, 'firstFrameOnNewPage' => $ys[0], 'startOffsetPx' => $dy, 'last' => $last, 'monotonic' => $mono, 'frames' => count($ys), 'ys' => array_slice($ys, 0, 20)])];
}
// in-page log of the pill's position at every animation frame of each document, from its first frame
const PILLLOG = "(() => { const log = []; window.__pillLog = log; const t0 = performance.now(); const tick = () => { const p = document.querySelector('aside .rw-side-pill'); if (p) { const r = p.getBoundingClientRect(); log.push([Math.round(performance.now()), Math.round(r.y)]); } if (performance.now() - t0 < 9000) requestAnimationFrame(tick); }; requestAnimationFrame(tick); })();";
// every visible text of the sidebar with the box of its line, and the pill's box
const LABELS = <<<'JS'
(() => { const p = document.querySelector('aside .rw-side-pill'); const pr = p ? p.getBoundingClientRect() : null; const labels = [];
  const w = document.createTreeWalker(document.querySelector('aside'), NodeFilter.SHOW_TEXT); let n;
  while ((n = w.nextNode())) { if (!n.textContent.trim() || (p && p.contains(n))) continue; const pe = n.parentElement; if (pe.closest('.rw-visually-hidden, [hidden], kbd, input')) continue; const cs = getComputedStyle(pe); if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const rg = document.createRange(); rg.selectNodeContents(n); const r = rg.getBoundingClientRect(); if (r.width < 4 || r.bottom < 0 || r.top > 560) continue;
    labels.push({ t: n.textContent.trim().slice(0, 30), r: [r.x, r.y, r.right, r.bottom].map(Math.round) }); }
  return { pill: pr ? [pr.x, pr.y, pr.right, pr.bottom].map(Math.round) : null, labels, url: location.pathname }; })()
JS;
/**
 * D2 legibility, measured on the real frames (no probe on the page). In each pixel row of a label the background is the row's
 * most frequent colour and the ink its pixel of highest WCAG contrast against it. Rows count only where the label has ink: rows
 * whose contrast reaches 3:1 in a frame where the pill does not touch the label (reference). A label is split into its part under
 * the pill and its parts outside; each part with at least 2 inked rows is judged by its best inked row, and is reported below 3:1
 * (covered, or unreadable over the tint). So a label above a faint tint passes, one under an opaque pill does not.
 */
function label_legibility(array $frames): array
{
    $lum = function (int $c): float { $f = function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * $f(($c >> 16) & 255) + 0.7152 * $f(($c >> 8) & 255) + 0.0722 * $f($c & 255); };
    $rows = function ($im, int $x0, int $x1, int $y0, int $y1) use ($lum): array { $W = imagesx($im); $H = imagesy($im); $out = [];
        for ($y = max(0, $y0); $y < min($H, $y1); $y++) { $cnt = []; $px = [];
            for ($x = max(0, $x0); $x < min($W, $x1); $x++) { $c = imagecolorat($im, $x, $y) & 0xFFFFFF; $px[] = $c; $cnt[$c] = ($cnt[$c] ?? 0) + 1; }
            if (! $px) continue; arsort($cnt); $bg = $lum((int) array_key_first($cnt)); $mx = 1.0;
            foreach (array_unique($px) as $c) { $l = $lum($c); $cr = (max($l, $bg) + 0.05) / (min($l, $bg) + 0.05); if ($cr > $mx) $mx = $cr; }
            $out[$y] = $mx; }
        return $out; };
    $touch = fn ($pl, $r) => $pl && $pl[1] < $r[3] && $pl[3] > $r[1] && $pl[0] < $r[2] && $pl[2] > $r[0];
    // reference: inked row offsets of each label, from frames where the pill does not touch it
    $ink = [];
    foreach ($frames as $f) { $v = $f['v'] ?? null; if (! is_array($v) || ! is_file($f['path'] ?? '')) continue; $im = imagecreatefrompng($f['path']); if (! $im) continue;
        foreach ($v['labels'] as $l) { if ($touch($v['pill'], $l['r'])) continue; foreach ($rows($im, $l['r'][0], $l['r'][2], $l['r'][1], $l['r'][3]) as $y => $c) if ($c >= 3.0) $ink[$l['t']][$y - $l['r'][1]] = true; }
        imagedestroy($im); }
    $low = []; $nOver = 0; $nAll = 0; $minOver = 99.0; $noRef = [];
    foreach ($frames as $f) {
        $v = $f['v'] ?? null; if (! is_array($v) || ! is_file($f['path'] ?? '')) continue;
        $im = imagecreatefrompng($f['path']); if (! $im) continue;
        foreach ($v['labels'] as $l) {
            [$x0, $y0, $x1, $y1] = $l['r']; $pl = $v['pill'];
            if (! isset($ink[$l['t']])) { $noRef[$l['t']] = true; continue; }
            $segs = [];
            if ($touch($pl, $l['r'])) {
                $iy0 = max($y0, $pl[1] + 2); $iy1 = min($y1, $pl[3] - 2);
                $segs['under'] = [max($x0, $pl[0] + 6), min($x1, $pl[2] - 6), $iy0, $iy1];
                $segs['above'] = [$x0, $x1, $y0, $iy0 - 2];
                $segs['below'] = [$x0, $x1, $iy1 + 2, $y1];
            } else $segs['clear'] = [$x0, $x1, $y0, $y1];
            foreach ($segs as $k => $sg) {
                if ($sg[1] - $sg[0] < 4 || $sg[3] <= $sg[2]) continue;
                $rc = array_filter($rows($im, ...$sg), fn ($c, $y) => isset($ink[$l['t']][$y - $y0]), ARRAY_FILTER_USE_BOTH);
                if (count($rc) < 2) continue;
                $c = round(max($rc), 2); $nAll++;
                if ($k === 'under') { $nOver++; $minOver = min($minOver, $c); }
                if ($c < 3.0) $low[] = $f['at'] . 'ms ' . $l['t'] . " $k $c:1"; }
        }
        imagedestroy($im);
    }
    if ($noRef) info('D2: labels never clear of the pill (no reference, not judged): ' . json_encode(array_keys($noRef)));
    return [$low, $nOver, $nAll, $minOver];
}
$steps = [['op' => 'initscript', 'source' => INIT], mo_login(), ['op' => 'eval', 'expr' => "({ url: location.pathname, reveal: window.__rwReveal || null })", 'tag' => 'login-reveal'], ['op' => 'goto', 'url' => U1BASE . 'rolewarden/activity', 'tag' => 'nav-start']];
foreach ($SIDE as $sp) { $steps[] = ['op' => 'mouse', 'selector' => $sideSel($sp), 'click' => true, 'nav' => true, 'tag' => "go $sp"]; $steps[] = ['op' => 'wait', 'ms' => 900]; $steps[] = ['op' => 'eval', 'expr' => NAV, 'tag' => "nav $sp"]; }
// rail
$steps[] = ['op' => 'mouse', 'selector' => 'aside form[action$="/rolewarden/sidebar"] button', 'click' => true, 'nav' => true, 'tag' => 'rail-on'];
$steps[] = ['op' => 'wait', 'ms' => 700];
$steps[] = ['op' => 'eval', 'expr' => NAV, 'tag' => 'rail-0'];
foreach (['rolewarden/roles', 'rolewarden/settings'] as $sp) { $steps[] = ['op' => 'mouse', 'selector' => $sideSel($sp), 'click' => true, 'nav' => true, 'tag' => "rgo $sp"]; $steps[] = ['op' => 'wait', 'ms' => 900]; $steps[] = ['op' => 'eval', 'expr' => NAV, 'tag' => "rail $sp"]; }
$steps[] = ['op' => 'mouse', 'selector' => 'aside form[action$="/rolewarden/sidebar"] button', 'click' => true, 'nav' => true, 'tag' => 'rail-off'];
$steps[] = ['op' => 'wait', 'ms' => 700];
$steps[] = ['op' => 'eval', 'expr' => NAV, 'tag' => 'rail-x'];
$r = mb($steps, 'nav');
$vtSeen = [];
foreach ($SIDE as $sp) {
    $v = bval($r, "nav $sp");
    $vtSeen[] = $v['reveal'] ?? '?';
    $want = $sp === 'rolewarden/' ? '/rolewarden/' : "/$sp";
    $okCur = $v && count($v['current']) === 1 && str_ends_with($v['current'][0], $want);
    check("NAV-$sp", "Sidebar click on $sp: that item alone is current, the highlight sits inside it, settled, and no item text is covered", $okCur && $v['pills'] === 1 && $v['pillIn'] && $v['inside'] && $v['covered'] === [] && ($v['pillRunning'] ?? 1) === 0, json_encode($v));
}
info('pagereveal on sidebar navigations: ' . implode(',', $vtSeen) . '; after the login form: ' . json_encode(bval($r, 'login-reveal')));
check('NAV-vt', 'Moving between pages runs a view transition (pagereveal carries one) in a browser with View Transitions (Chrome 155)', ! in_array('none', $vtSeen, true) && ! in_array('?', $vtSeen, true), implode(',', $vtSeen));
$r0 = bval($r, 'rail-0'); $r1 = bval($r, 'rail rolewarden/roles'); $r2 = bval($r, 'rail rolewarden/settings'); $rx = bval($r, 'rail-x');
check('NAV-rail', 'Rail: collapsing works, navigation in the rail keeps one current item with the highlight inside it, expanding again works', ($r0['rail'] ?? false) && ($r1['rail'] ?? false) && str_ends_with($r1['current'][0] ?? '', '/rolewarden/roles') && ($r1['pillIn'] ?? false) && ($r1['inside'] ?? false) && str_ends_with($r2['current'][0] ?? '', '/rolewarden/settings') && ($r2['pillIn'] ?? false) && ($r2['inside'] ?? false) && ($rx && ! $rx['rail']), json_encode([$r0, $r1, $r2, $rx]));
check('NAV-console', 'No JavaScript error other than the view transition during the navigation and the rail', jerr_other($r) === [], json_encode(jerr_other($r)));
q("DELETE FROM settings WHERE `key` LIKE '%ollapse%' OR `key` LIKE '%rail%'");

// frames: animations slowed ten times, a sidebar click captured every 150 ms, in the three sidebars, plus Ink at real speed
// (development only). D2: every label's legibility measured on the real frames; D5: in-page requestAnimationFrame log.
if ($ENVN === 'development') {
    foreach (['' => 'paper', 'ink' => 'ink', 'charcoal' => 'charcoal'] as $sb => $sbn) {
        theme_save($own, ['base' => 'console', 'sidebar' => $sb], "frames theme $sbn");
        $times = []; for ($t = 0; $t <= 3600; $t += 150) $times[] = $t;
        $r = mb([['op' => 'initscript', 'source' => PILLLOG], mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'f0'], ['op' => 'wait', 'ms' => 800], ['op' => 'eval', 'expr' => PILLX, 'tag' => 'users-pill'], ['op' => 'rate', 'value' => 0.1],
            ['op' => 'frames', 'selector' => $sideSel('rolewarden/settings'), 'times' => $times, 'clip' => ['x' => 0, 'y' => 0, 'width' => 260, 'height' => 560], 'path' => $OUTP . "frames.$ENVN$RUN.$sbn.%d.png", 'expr' => LABELS, 'tag' => 'frames'],
            ['op' => 'rate', 'value' => 1], ['op' => 'wait', 'ms' => 1500], ['op' => 'eval', 'expr' => 'window.__pillLog || null', 'tag' => 'plog'], ['op' => 'eval', 'expr' => NAV, 'tag' => 'after']], "frames $sbn");
        $jobs = [$sbn => $r];
        if ($sbn === 'ink') {
            $t2 = []; for ($t = 0; $t <= 1600; $t += 40) $t2[] = $t;
            $jobs['ink-realtime'] = mb([['op' => 'initscript', 'source' => PILLLOG], mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'f0'], ['op' => 'wait', 'ms' => 800], ['op' => 'eval', 'expr' => PILLX, 'tag' => 'users-pill'],
                ['op' => 'frames', 'selector' => $sideSel('rolewarden/settings'), 'times' => $t2, 'clip' => ['x' => 0, 'y' => 0, 'width' => 260, 'height' => 560], 'path' => $OUTP . "frames.$ENVN$RUN.ink-realtime.%d.png", 'expr' => LABELS, 'tag' => 'frames'],
                ['op' => 'wait', 'ms' => 1000], ['op' => 'eval', 'expr' => 'window.__pillLog || null', 'tag' => 'plog']], 'frames ink realtime');
        }
        foreach ($jobs as $jn => $jr) {
            $frames = bval($jr, 'frames') ?: [];
            [$low, $nOver, $nAll, $minOver] = label_legibility($frames);
            info("D2 $jn: " . count($frames) . " frames, $nAll label measures, $nOver of them under the moving highlight (lowest contrast there " . $minOver . ':1); below 3:1: ' . json_encode(array_slice($low, 0, 20)));
            check("NAV-d2-$jn", "Sidebar click Users -> Settings ($jn): in every frame every sidebar label, those the highlight crosses and the one it lands on included, stays legible (contrast of its glyphs on what lies behind them at least 3:1)", count($frames) >= 10 && $nOver >= 5 && $low === [], json_encode(['frames' => count($frames), 'measures' => $nAll, 'underPill' => $nOver, 'minUnderPill' => $minOver, 'low' => array_slice($low, 0, 20), 'n' => count($low)]));
            [$ok5, $why5] = slide_ok(bval($jr, 'plog'), bval($jr, 'users-pill'));
            info("D5 $jn: $why5");
            check("NAV-d5-$jn", "Sidebar click Users -> Settings ($jn): from the first animation frame of the new page the highlight starts on the item left (Users) and only moves towards Settings", $ok5, $why5);
        }
        $a = bval($r, 'after');
        check("NAV-frames-$sbn", "Frames of a sidebar click captured in $sbn (also looked at by eye, see the report); the highlight ends inside Settings", count(bval($r, 'frames') ?: []) === count($times) && ($a['pillIn'] ?? false) && ($a['inside'] ?? false) && ($a['covered'] ?? ['x']) === [], json_encode($a));
    }
    theme_save($own, ['base' => 'console', 'sidebar' => ''], 'theme back 2');
}

// ================================================================= RM: prefers-reduced-motion
$pagesRM = array_merge(['dash' => 'rolewarden'], array_combine(array_map(fn ($p) => basename($p ?: 'x'), array_slice($SIDE, 0, 7)), array_slice($SIDE, 0, 7)), $OTHER);
$mkRm = function (string $motion) use ($pagesRM, $sideSel): array {
    $s = [['op' => 'initscript', 'source' => INIT], ['op' => 'emulate', 'motion' => $motion], mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'first']];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv dash'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev dash'];
    $s[] = ['op' => 'eval', 'expr' => SEEN, 'tag' => 'seen0'];
    $s[] = ['op' => 'mouse', 'selector' => 'details.rw-dash-customize > summary', 'click' => true, 'wait' => 30, 'tag' => 'cust'];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv cust'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev cust'];
    $s[] = ['op' => 'wait', 'ms' => 1500];
    $s[] = ['op' => 'eval', 'expr' => SEEN, 'tag' => 'seen1'];
    foreach ($pagesRM as $k => $u) {
        if ($k === 'dash') continue;
        if (in_array($u, ['rolewarden/users', 'rolewarden/roles', 'rolewarden/permissions', 'rolewarden/permissions/check', 'rolewarden/activity', 'rolewarden/settings', 'rolewarden/appearance'], true)) $s[] = ['op' => 'mouse', 'selector' => $sideSel($u), 'click' => true, 'nav' => true, 'tag' => "go $k"];
        else $s[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "go $k"];
        $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => "mv $k"];
        $s[] = ['op' => 'wait', 'ms' => 700];
        $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => "ev $k"];
    }
    // dialog on the role page, then a cell and the unsaved bar
    $s[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/roles/' . $GLOBALS['RID'], 'tag' => 'role'];
    $s[] = ['op' => 'mouse', 'selector' => 'button[onclick*="confirm-delete-role"]', 'click' => true, 'wait' => 30, 'tag' => 'dlg'];
    $s[] = ['op' => 'eval', 'expr' => dlg_js('confirm-delete-role'), 'tag' => 'dlg0'];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv dlg'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev dlg'];
    $s[] = KEY_ESC + ['wait' => 300];
    $s[] = ['op' => 'mouse', 'selector' => sel_cell('activity.view'), 'click' => true, 'wait' => 30, 'tag' => 'cell'];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv cell'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev cell'];
    $s[] = ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'toast'];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv toast'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev toast'];
    $s[] = ['op' => 'mouse', 'selector' => 'aside .rw-side-search', 'click' => true, 'wait' => 30, 'tag' => 'cmd'];
    $s[] = ['op' => 'eval', 'expr' => CMD, 'tag' => 'cmd0'];
    $s[] = ['op' => 'eval', 'expr' => MOVING, 'tag' => 'mv cmd'];
    $s[] = ['op' => 'wait', 'ms' => 700];
    $s[] = ['op' => 'eval', 'expr' => MOVED, 'tag' => 'ev cmd'];
    return $s;
};
$before = q('SELECT permission_id FROM acl_role_permissions WHERE role_id=? ORDER BY permission_id', [$RID]);
$r = mb($mkRm('reduce'), 'rm reduce', 400000, 0);
$rc = mb($mkRm(''), 'rm calibration', 400000, 0);
// the cell saved twice (once per run): back where it was
check('RM-db', 'The two saves of the reduced-motion runs leave the role as it was (one grant, one revoke)', q('SELECT permission_id FROM acl_role_permissions WHERE role_id=? ORDER BY permission_id', [$RID]) === $before);
$calib = bval($rc, 'ev dash');
check('RM-calib', 'Calibration: without the setting the recorder does see moving animations start on the dashboard', ($calib['count'] ?? 0) > 0, json_encode($calib));
$cn = 0; foreach ($rc['steps'] as $st) if (str_starts_with((string) ($st['tag'] ?? ''), 'ev ') && (($st['value']['count'] ?? 0) > 0)) $cn++;
info("calibration: moving animations recorded at $cn of the sampled points without the setting");
foreach (array_merge(['dash', 'cust'], array_keys(array_diff_key($pagesRM, ['dash' => 1])), ['dlg', 'cell', 'toast', 'cmd']) as $k) {
    $v = bval($r, "mv $k"); $e = bval($r, "ev $k");
    // A1 (decided 2026-10-25): the 97% give of a pressed control stays; a transform transition (<= 200 ms) on a button, summary or link is allowed, nothing else
    $press = fn ($x) => (bool) preg_match('/^(transition )?transform \[transform(,transform)*\] (BUTTON|SUMMARY|A)\.[^\[\]]*? (\d+)ms d\S+$/', $x, $pm) && (int) $pm[4] <= 200;
    $left = function ($x) use ($press) { if (! is_array($x)) return null; $ex = array_values(array_filter($x['moving'], $press)); return ['count' => $x['count'] - count($ex), 'press' => $ex]; };
    $lv = $left($v); $le = $left($e);
    if (($lv['press'] ?? []) || ($le['press'] ?? [])) info("RM-$k: press transitions allowed by A1: " . json_encode(array_merge($lv['press'] ?? [], $le['press'] ?? [])));
    check("RM-$k", "Reduced motion: no moving animation starts or runs ($k); only the press transform is allowed (A1, decided)", is_array($lv) && $lv['count'] === 0 && is_array($le) && $le['count'] === 0, json_encode([$v, $e]));
}
$s0 = bval($r, 'seen0'); $s1 = bval($r, 'seen1');
$finals = array_map(fn ($x) => $x, $s1['now'] ?? []);
$inter = array_values(array_diff($s0['seen'] ?? [], $finals));
check('RM-count', 'Reduced motion: the figures show their value at once (no intermediate value ever written, first look = final)', $s0 && $s0['now'] === $finals && $inter === [] && count($finals) === 4, json_encode(['first' => $s0['now'] ?? null, 'final' => $finals, 'intermediate' => array_slice($inter, 0, 8)]));
$cal = bval($rc, 'seen1');
info('calibration, values written during the count with motion: ' . count($cal['seen'] ?? []) . ' e.g. ' . json_encode(array_slice($cal['seen'] ?? [], 0, 6)));
$d0 = bval($r, 'dlg0');
check('RM-dialog', 'Reduced motion: 30ms after the click the confirm dialog is open, fully opaque and not scaled or moved', $d0 && $d0['open'] && (float) $d0['opacity'] > 0.999 && in_array($d0['transform'], ['none', 'matrix(1, 0, 0, 1, 0, 0)'], true) && in_array($d0['scale'], ['none', '1'], true) && in_array($d0['translate'], ['none', '0px'], true), json_encode($d0));
$c0 = bval($r, 'cmd0');
check('RM-search', 'Reduced motion: the command search is open at once, not scaled', $c0 && $c0['open'] && (float) $c0['opacity'] > 0.999 && in_array($c0['transform'], ['none', 'matrix(1, 0, 0, 1, 0, 0)'], true) && in_array($c0['scale'], ['none', '1'], true), json_encode($c0));
$rv = array_unique(array_map(fn ($k) => (bval($r, "mv $k")['reveal'] ?? '?'), ['users', 'roles', 'settings']));
check('RM-vt', 'Reduced motion: no page hand-over (no view transition revealed)', ! in_array('vt', $rv, true), json_encode($rv));
check('RM-console', 'Reduced motion: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));
check('RM-toast', 'Reduced motion: the save gives its success toast', str_contains((string) bval($r, 'toast'), 'success'), json_encode(bval($r, 'toast')));

// ================================================================= CNT: counters end on the served text, en and it
$cntJob = fn () => mb([['op' => 'initscript', 'source' => INIT], mo_login(),
    ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'w1'], ['op' => 'wait', 'ms' => 2500], ['op' => 'eval', 'expr' => SEEN, 'tag' => 'seen-1'],
    ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'w2'], ['op' => 'wait', 'ms' => 2500], ['op' => 'eval', 'expr' => SEEN, 'tag' => 'seen-2'],
    ['op' => 'mouse', 'selector' => $GLOBALS['sideSel']('rolewarden/'), 'click' => true, 'nav' => true, 'tag' => 'w3'], ['op' => 'wait', 'ms' => 2500], ['op' => 'eval', 'expr' => SEEN, 'tag' => 'seen-3'],
    ['op' => 'nojs', 'value' => true], ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'nj'], ['op' => 'eval', 'expr' => COUNTS, 'tag' => 'nojs'],
    ['op' => 'nojs', 'value' => false], ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'js'], ['op' => 'eval', 'expr' => COUNTS, 'tag' => 'early'], ['op' => 'wait', 'ms' => 2500], ['op' => 'eval', 'expr' => COUNTS, 'tag' => 'end'],
    ['op' => 'eval', 'expr' => "document.documentElement.lang", 'tag' => 'lang']], 'cnt', 200000, 0);
foreach (['en', 'it'] as $lg) {
    if ($lg === 'it') postform($own, 'rolewarden/profile', '#rolewarden/profile/language$#', ['language' => 'it'], 'lang it');
    $r = $cntJob();
    $h = G($own, U1BASE . 'rolewarden', "served $lg");   // after the browser's own sign-in, which the sign-in figure counts
    preg_match_all('/data-rw-count="[^"]*"[^>]*>([^<]*)</', $h, $m);
    $nj = bval($r, 'nojs'); $end = bval($r, 'end'); $early = bval($r, 'early');
    info("CNT $lg: served " . json_encode($m[1]) . ' nojs ' . json_encode($nj) . ' early ' . json_encode($early) . ' end ' . json_encode($end) . ' lang ' . bval($r, 'lang'));
    $sep = $lg === 'en' ? ',' : '.';
    check("CNT-$lg", "Counters ($lg): at the end of the count each figure reads exactly as without JavaScript (four-digit figures with the $lg separator)", is_array($nj) && count($nj) === 4 && $nj === $end && str_starts_with((string) bval($r, 'lang'), $lg) && count(array_filter($nj, fn ($x) => str_contains($x, $sep))) >= 2, json_encode(['nojs' => $nj, 'end' => $end]));
    check("CNT-$lg-served", "Counters ($lg): the page without JavaScript shows the served text", is_array($nj) && array_map('html_entity_decode', $m[1]) === $nj, json_encode([$m[1], $nj]));
    check("CNT-$lg-console", "Counters ($lg): no JavaScript error", jerr_other($r) === [], json_encode(jerr_other($r)));
    // every value the count writes stays between 0 and the figure (the figures count up)
    $grp = $lg === 'en' ? '/^\d{1,3}(,\d{3})*$/' : '/^\d{1,3}(\.\d{3})*$/';
    $out = []; $nvals = 0; $num = fn ($t) => (float) str_replace([',', '.', ' ', "\u{2212}"], ['', '', '', '-'], $t);
    foreach (['seen-1', 'seen-2', 'seen-3'] as $sk) foreach ((bval($r, $sk)['seen'] ?? []) as $e) { [$fin, $txt] = explode('=', $e, 2) + [1 => '']; $nvals++; $v = $num($txt); if ($v < 0 || $v > (float) $fin || ! preg_match($grp, $txt)) $out[] = $e; }
    info("CNT $lg: $nvals values written during three counts; out of range: " . json_encode(array_slice($out, 0, 12)));
    check("CNT-$lg-range", "Counters ($lg): while counting up, every value shown lies between 0 and the figure and is grouped like the final text (three loads)", $nvals > 20 && $out === [], json_encode(['n' => $nvals, 'out' => array_slice($out, 0, 12), 'count' => count($out)]));
}
postform($own, 'rolewarden/profile', '#rolewarden/profile/language$#', ['language' => 'en'], 'lang en');

// ================================================================= DLG: confirm dialogs (role, user, theme reset)
theme_save($own, ['base' => 'clarity', 'sidebar' => 'ink'], 'theme for reset');
$themeBefore = theme_json();
$dlgSteps = function (string $page, string $id, string $tagp, string $confirm): array {
    $op = "button[onclick*=\"$id\"]";
    $s = [['op' => 'goto', 'url' => U1BASE . $page, 'tag' => "$tagp-page"], ['op' => 'wait', 'ms' => 900],
        ['op' => 'eval', 'expr' => "(() => { document.querySelector('$op').scrollIntoView({ block: 'center' }); return true; })()", 'wait' => 300], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-0"],
        ['op' => 'mouse', 'selector' => $op, 'click' => true, 'wait' => 120, 'tag' => "$tagp-mopen"], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-mid"], ['op' => 'wait', 'ms' => 700], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-m1"],
        ['op' => 'mouse', 'selector' => "#$id button.rw-btn--ghost", 'click' => true, 'wait' => 600, 'tag' => "$tagp-mcancel"], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-m2"],
        ['op' => 'eval', 'expr' => "(() => { document.querySelector('$op').focus(); return document.activeElement.getAttribute('onclick'); })()", 'tag' => "$tagp-focus"],
        KEY_ENTER + ['wait' => 700, 'tag' => "$tagp-kopen"], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-k1"],
        KEY_ESC + ['wait' => 600, 'tag' => "$tagp-kesc"], ['op' => 'eval', 'expr' => dlg_js($id), 'tag' => "$tagp-k2"]];
    if ($confirm === 'mouse') {
        $s[] = ['op' => 'mouse', 'selector' => $op, 'click' => true, 'wait' => 700];
        $s[] = ['op' => 'mouse', 'selector' => "#$id button[type=submit]", 'click' => true, 'nav' => true, 'tag' => "$tagp-confirm"];
    } else {
        $s[] = ['op' => 'eval', 'expr' => "(() => { document.querySelector('$op').focus(); return true; })()"];
        $s[] = KEY_ENTER + ['wait' => 700];
        $s[] = ['op' => 'eval', 'expr' => "(() => { const d = document.getElementById('$id'); let i = 0; while (document.activeElement !== d.querySelector('button[type=submit]') && i++ < 6) { const b = [...d.querySelectorAll('button')]; } return document.activeElement.tagName + '|' + (document.activeElement.getAttribute('type') || ''); })()", 'tag' => "$tagp-kfocus0"];
        $s[] = KEY_TAB + ['wait' => 200];
        $s[] = ['op' => 'eval', 'expr' => "document.activeElement.tagName + '|' + (document.activeElement.getAttribute('type') || '') + '|' + document.activeElement.textContent.trim()", 'tag' => "$tagp-kfocus"];
        $s[] = KEY_ENTER + ['nav' => true, 'tag' => "$tagp-confirm"];
    }
    $s[] = ['op' => 'wait', 'ms' => 600];
    $s[] = ['op' => 'eval', 'expr' => TOASTS, 'tag' => "$tagp-toast"];
    return $s;
};
$r = mb(array_merge([mo_login()], $dlgSteps("rolewarden/roles/$RDEL", 'confirm-delete-role', 'role', 'mouse'), $dlgSteps("rolewarden/users/$UDEL", 'confirm-delete-user', 'user', 'key'), $dlgSteps('rolewarden/appearance', 'confirm-reset-theme', 'theme', 'mouse')), 'dlg');
$id1 = fn ($v) => in_array($v['transform'] ?? '', ['none', 'matrix(1, 0, 0, 1, 0, 0)'], true) && in_array($v['scale'] ?? '', ['none', '1'], true) && in_array($v['translate'] ?? '', ['none', '0px'], true);
foreach (['role' => 'confirm-delete-role', 'user' => 'confirm-delete-user', 'theme' => 'confirm-reset-theme'] as $t => $id) {
    $v0 = bval($r, "$t-0"); $mid = bval($r, "$t-mid"); $m1 = bval($r, "$t-m1"); $m2 = bval($r, "$t-m2"); $k1 = bval($r, "$t-k1"); $k2 = bval($r, "$t-k2");
    info("DLG $t: " . json_encode(['0' => $v0, 'mid' => $mid, 'm1' => $m1, 'm2' => $m2, 'k1' => $k1, 'k2' => $k2]));
    check("DLG-$t-mouse", "Dialog $id: a click opens it; after the motion it is fully open (opacity 1, no scale or offset), the page under it has not moved", $v0 && ! $v0['open'] && $m1 && $m1['open'] && (float) $m1['opacity'] > 0.999 && $id1($m1) && $m1['running'] === [] && $m1['h1'] === $v0['h1'] && $m1['scrollY'] === $v0['scrollY'], json_encode([$v0, $m1]));
    check("DLG-$t-cancel", "Dialog $id: Cancel closes it and the focus is back on the button that opened it", $m2 && ! $m2['open'] && str_contains((string) $m2['active'], $id), json_encode($m2));
    check("DLG-$t-key", "Dialog $id: Enter on the focused button opens it, Esc closes it, the focus returns to the button", $k1 && $k1['open'] && $id1($k1) && $k2 && ! $k2['open'] && str_contains((string) $k2['active'], $id), json_encode([$k1, $k2]));
    $c = bstep($r, "$t-confirm");
    info("DLG $t confirm: " . json_encode([$c, bval($r, "$t-kfocus0"), bval($r, "$t-kfocus"), bval($r, "$t-toast")]));
}
check('DLG-role-done', 'The confirmed role deletion (mouse) still works: the role is deleted and a success toast says so', q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$RDEL]) !== null && (bstep($r, 'role-confirm')['status'] ?? 0) === 200 && in_array('success', array_column(bval($r, 'role-toast') ?? [], 'kind'), true), json_encode([bstep($r, 'role-confirm'), bval($r, 'role-toast')]));
check('DLG-user-done', 'The confirmed user deletion (keyboard: Tab to Delete, Enter) still works: the user is deleted', q1('SELECT deleted_at FROM users WHERE id=?', [$UDEL]) !== null && str_contains((string) bval($r, 'user-kfocus'), 'submit'), json_encode([bstep($r, 'user-confirm'), bval($r, 'user-kfocus'), bval($r, 'user-toast')]));
$themeAfter = theme_json();
check('DLG-theme-done', 'The confirmed theme reset still works: the saved Clarity/Ink theme is gone', ($themeBefore['base'] ?? '') === 'clarity' && ($themeAfter === null || (($themeAfter['base'] ?? 'console') === 'console' && ($themeAfter['sidebar'] ?? '') === '')), json_encode([$themeBefore, $themeAfter]));
check('DLG-console', 'Dialogs: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));

// ================================================================= SRCH: command search
$r = mb([mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'p'], ['op' => 'wait', 'ms' => 900],
    ['op' => 'key', 'key' => 'k', 'code' => 'KeyK', 'keyCode' => 75, 'modifiers' => 2, 'wait' => 700, 'tag' => 'ctrlk'], ['op' => 'eval', 'expr' => CMD, 'tag' => 'k1'],
    ['op' => 'type', 'text' => 'users', 'wait' => 1000], ['op' => 'eval', 'expr' => CMD, 'tag' => 'k2'],
    KEY_DOWN + ['wait' => 300], ['op' => 'eval', 'expr' => CMD, 'tag' => 'k3'],
    KEY_DOWN + ['wait' => 300], KEY_UP + ['wait' => 300], ['op' => 'eval', 'expr' => CMD, 'tag' => 'k4'],
    KEY_DOWN + ['wait' => 300], ['op' => 'eval', 'expr' => CMD, 'tag' => 'k5'],
    KEY_ENTER + ['nav' => true, 'tag' => 'enter'], ['op' => 'wait', 'ms' => 500],
    ['op' => 'mouse', 'selector' => 'aside .rw-side-search', 'click' => true, 'wait' => 700, 'tag' => 'link'], ['op' => 'eval', 'expr' => CMD, 'tag' => 'l1'],
    KEY_ESC + ['wait' => 600], ['op' => 'eval', 'expr' => CMD, 'tag' => 'l2'],
    ['op' => 'eval', 'expr' => "(() => { document.querySelector('aside .rw-side-search').focus(); return true; })()"], KEY_ENTER + ['wait' => 700], ['op' => 'eval', 'expr' => CMD, 'tag' => 'l3'],
    KEY_ESC + ['wait' => 600], ['op' => 'eval', 'expr' => CMD, 'tag' => 'l4'],
], 'srch');
$k1 = bval($r, 'k1'); $k2 = bval($r, 'k2'); $k3 = bval($r, 'k3'); $k4 = bval($r, 'k4'); $k5 = bval($r, 'k5'); $en = bstep($r, 'enter');
info('SRCH: ' . json_encode(compact('k1', 'k2', 'k3', 'k4', 'k5', 'en')));
check('SRCH-ctrlk', 'Ctrl K opens the command search, fully grown, with the focus in the field', $k1 && $k1['open'] && $k1['focusQ'] && (float) $k1['opacity'] > 0.999 && in_array($k1['transform'], ['none', 'matrix(1, 0, 0, 1, 0, 0)'], true) && in_array($k1['scale'], ['none', '1'], true), json_encode($k1));
check('SRCH-move', 'Down and Up move the highlighted result (aria-activedescendant and aria-selected follow)', $k2 && $k2['n'] >= 3 && $k2['ad'] === 'rw-cmd-o-0' && $k3['ad'] === 'rw-cmd-o-1' && $k3['sel'] === ['rw-cmd-o-1'] && $k4['ad'] === 'rw-cmd-o-1' && $k5['ad'] === 'rw-cmd-o-2', json_encode([$k2['ad'] ?? null, $k3['ad'] ?? null, $k4['ad'] ?? null, $k5['ad'] ?? null]));
$want = html_entity_decode((string) ($k5['hrefs']['rw-cmd-o-2'] ?? '-'));
check('SRCH-enter', 'Enter opens the highlighted result', $en && ($en['status'] ?? 0) === 200 && ($en['url'] ?? '') === $want, json_encode([$en, $want]));
$l1 = bval($r, 'l1'); $l2 = bval($r, 'l2'); $l3 = bval($r, 'l3'); $l4 = bval($r, 'l4');
check('SRCH-link', 'The sidebar search link opens it (mouse and Enter); Esc closes it and the focus returns to the link', $l1 && $l1['open'] && $l1['focusQ'] && $l2 && ! $l2['open'] && $l3 && $l3['open'] && $l4 && ! $l4['open'] && $l4['activeIsSearchLink'], json_encode([$l1, $l2, $l3, $l4]));
check('SRCH-console', 'Command search: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));

// ================================================================= MTX: role matrix
// users.view carries a member's deny override on this role: a read-only cell, so the matrix checks use other slugs
$PA = 'activity.view'; $PB = 'settings.view'; $PC = perm_id('roles.create') ? 'roles.create' : 'permissions.view';
$rolePerms = fn () => array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.slug', [$RID]), 'slug');
$P0 = $rolePerms();
$r = mb([mo_login(), ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$RID", 'tag' => 'p'], ['op' => 'wait', 'ms' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c0'],
    ['op' => 'mouse', 'selector' => sel_cell($PA), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c1'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's1'],
    ['op' => 'mouse', 'selector' => sel_cell($PA), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c2'],
    ['op' => 'mouse', 'selector' => sel_cell($PA), 'click' => true, 'wait' => 100], ['op' => 'mouse', 'selector' => sel_cell($PB), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c3'],
    ['op' => 'mouse', 'selector' => '.rw-bar button.rw-btn--ghost', 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c4'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's4'],
    ['op' => 'mouse', 'selector' => sel_cell($PC), 'click' => true, 'wait' => 30], ['op' => 'mouse', 'selector' => sel_cell($PC), 'click' => true, 'wait' => 30], ['op' => 'mouse', 'selector' => sel_cell($PC), 'click' => true, 'wait' => 1000],
    ['op' => 'mouse', 'selector' => sel_cell($PB), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c5'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's5'],
    ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'save'], ['op' => 'wait', 'ms' => 1000], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c6'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's6'],
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$RID", 'tag' => 'reload'], ['op' => 'wait', 'ms' => 1200], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c7'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's7'],
], 'mtx');
$c = fn ($t) => bval($r, $t)['cells'] ?? [];
$c0 = $c('c0'); $c1 = $c('c1'); $c2 = $c('c2'); $c3 = $c('c3'); $c4 = $c('c4'); $c5 = $c('c5'); $c6 = $c('c6'); $c7 = $c('c7');
info('MTX: ' . json_encode(['P0' => $P0, 'c0' => [$c0[$PA] ?? null, $c0[$PB] ?? null, $c0[$PC] ?? null], 'c1' => $c1[$PA] ?? null, 'bar1' => bval($r, 'c1')['bar'] ?? null, 'c5' => [$c5[$PB] ?? null, $c5[$PC] ?? null], 'save' => bval($r, 'save')]));
check('MTX-click', 'Role matrix: a click grants the cell (pressed, role mark, changed) and the bar counts 1 unsaved change; the cell is settled after the motion', ($c0[$PA]['pressed'] ?? '') === 'false' && ($c1[$PA]['pressed'] ?? '') === 'true' && ($c1[$PA]['mark'] ?? '') === 'rw-mark--role' && ($c1[$PA]['changed'] ?? false) && str_contains((string) (bval($r, 'c1')['bar'] ?? ''), '1 unsaved') && bval($r, 's1') === [], json_encode([$c1[$PA] ?? null, bval($r, 'c1')['bar'] ?? null, bval($r, 's1')]));
check('MTX-back', 'A second click puts the cell back (not changed, nothing unsaved)', ($c2[$PA] ?? null) == ($c0[$PA] ?? 'x') && ! (bval($r, 'c2')['dirty'] ?? true), json_encode([$c2[$PA] ?? null, bval($r, 'c2')['bar'] ?? null]));
check('MTX-discard', 'Discard after two changes: every cell back to its saved state, nothing unsaved, no cell left mid-motion', ($c3[$PA]['changed'] ?? false) && ($c3[$PB]['changed'] ?? false) && $c4 == $c0 && ! (bval($r, 'c4')['dirty'] ?? true) && bval($r, 's4') === [], json_encode([array_diff_assoc(array_map('json_encode', $c4), array_map('json_encode', $c0)), bval($r, 's4')]));
check('MTX-rapid', 'Three quick clicks on one cell end granted (odd number), consistent and settled', ($c5[$PC]['pressed'] ?? '') === 'true' && ($c5[$PC]['mark'] ?? '') === 'rw-mark--role' && bval($r, 's5') === [], json_encode([$c5[$PC] ?? null, bval($r, 's5')]));
check('MTX-save', 'Save: success toast, nothing unsaved, cells settled', str_contains((string) bval($r, 'save'), 'success') && ! (bval($r, 'c6')['dirty'] ?? true) && bval($r, 's6') === [] && count(array_filter($c6, fn ($x) => $x['changed'])) === 0, json_encode([bval($r, 'save'), bval($r, 'c6')['bar'] ?? null, bval($r, 's6')]));
$P1 = $rolePerms();
$exp = array_values(array_diff(array_merge($P0, [$PC]), [$PB])); sort($exp);
check('MTX-db', "The database holds exactly the saved state ($PC granted, $PB revoked, nothing from the discarded clicks)", $P1 === $exp, json_encode([$P0, $P1, $exp]));
// legend: a role grant shows the role mark, or "Denied for user" when a member holds a deny override (then read-only)
$mism = []; foreach ($c7 as $slug => $v) { $g = in_array($slug, $P1, true); if ($g !== in_array($v['mark'], ['rw-mark--role', 'rw-mark--deny'], true) || ($v['pressed'] !== null && $g !== ($v['pressed'] === 'true'))) $mism[] = "$slug " . json_encode($v); }
check('MTX-reload', 'After reloading, every cell shows the database state, none changed or mid-motion', $c7 !== [] && $mism === [] && bval($r, 's7') === [] && count($c7) > 20, json_encode([count($c7), array_slice($mism, 0, 5), bval($r, 's7')]));
check('MTX-console', 'Role matrix: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));

// ================================================================= OVR: overrides of a user
$ovr = fn () => array_column(q('SELECT p.slug, up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? ORDER BY p.slug', [$UID]), 'granted', 'slug');
$O0 = $ovr();
$QA = 'permissions.view'; $QB = 'activity.view';
$r = mb([mo_login(), ['op' => 'goto', 'url' => U1BASE . "rolewarden/users/$UID", 'tag' => 'p'], ['op' => 'wait', 'ms' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c0'],
    ['op' => 'mouse', 'selector' => sel_cell($QA), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c1'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's1'],
    ['op' => 'mouse', 'selector' => sel_cell($QA), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c2'],
    ['op' => 'mouse', 'selector' => '.rw-bar button.rw-btn--ghost', 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c3'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's3'],
    ['op' => 'mouse', 'selector' => sel_cell($QA), 'click' => true, 'wait' => 400],
    ['op' => 'mouse', 'selector' => sel_cell($QB), 'click' => true, 'wait' => 30], ['op' => 'mouse', 'selector' => sel_cell($QB), 'click' => true, 'wait' => 1000],
    ['op' => 'mouse', 'selector' => sel_cell('users.view'), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c4'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's4'],
    ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'save'], ['op' => 'wait', 'ms' => 1000], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c5'],
    ['op' => 'goto', 'url' => U1BASE . "rolewarden/users/$UID", 'tag' => 'reload'], ['op' => 'wait', 'ms' => 1200], ['op' => 'eval', 'expr' => CELLS, 'tag' => 'c6'], ['op' => 'eval', 'expr' => SETTLED, 'tag' => 's6'],
], 'ovr');
$c = fn ($t) => bval($r, $t)['cells'] ?? [];
$c0 = $c('c0'); $c1 = $c('c1'); $c2 = $c('c2'); $c3 = $c('c3'); $c4 = $c('c4'); $c6 = $c('c6');
$O1 = $ovr();
info('OVR: ' . json_encode(['O0' => $O0, 'O1' => $O1, 'QA' => [$c0[$QA] ?? null, $c1[$QA] ?? null, $c2[$QA] ?? null, $c4[$QA] ?? null], 'QB' => [$c0[$QB] ?? null, $c4[$QB] ?? null], 'uv' => [$c0['users.view'] ?? null, $c4['users.view'] ?? null], 'save' => bval($r, 'save')]));
check('OVR-click', 'Overrides: a click changes the cell (changed, new mark) and is settled after the motion', ($c1[$QA]['changed'] ?? false) && ($c1[$QA]['mark'] ?? '') !== ($c0[$QA]['mark'] ?? '') && bval($r, 's1') === [], json_encode([$c0[$QA] ?? null, $c1[$QA] ?? null, bval($r, 's1')]));
check('OVR-discard', 'Discard puts every cell back, nothing unsaved, nothing mid-motion', $c3 == $c0 && ! (bval($r, 'c3')['dirty'] ?? true) && bval($r, 's3') === [], json_encode([bval($r, 'c3')['bar'] ?? null, bval($r, 's3')]));
check('OVR-save', 'Save: success toast, nothing unsaved afterwards', str_contains((string) bval($r, 'save'), 'success') && ! (bval($r, 'c5')['dirty'] ?? true) && bval($r, 's4') === [], json_encode([bval($r, 'save'), bval($r, 's4')]));
$mark = fn ($slug) => array_key_exists($slug, $O1) ? ((int) $O1[$slug] === 1 ? 'rw-mark--grant' : 'rw-mark--deny') : null;
$mism = []; foreach ($c6 as $slug => $v) { $m = $mark($slug); $isO = in_array($v['mark'], ['rw-mark--grant', 'rw-mark--deny'], true); if (($m === null && $isO) || ($m !== null && $v['mark'] !== $m)) $mism[] = "$slug " . json_encode($v) . ' db=' . json_encode($O1[$slug] ?? null); }
$changedDb = $O1 != $O0;
$shownBeforeSave = []; foreach ($c4 as $slug => $v) if (in_array($v['mark'], ['rw-mark--grant', 'rw-mark--deny'], true)) $shownBeforeSave[$slug] = $v['mark'] === 'rw-mark--grant' ? '1' : '0';
ksort($shownBeforeSave); $O1s = array_map('strval', $O1); ksort($O1s);
check('OVR-db', 'The database holds exactly the overrides the matrix showed when Save was pressed', $changedDb && $shownBeforeSave === $O1s, json_encode([$shownBeforeSave, $O1s]));
check('OVR-reload', 'After reloading every cell shows the database overrides, none changed or mid-motion', $c6 !== [] && $mism === [] && bval($r, 's6') === [], json_encode([array_slice($mism, 0, 5), bval($r, 's6')]));
check('OVR-console', 'Overrides: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));

// ================================================================= CUST: customize panel, reorder with motion
$ORD = <<<'JS'
(() => { const lis = [...document.querySelectorAll('ol.rw-dash-order > li')]; const a = document.activeElement; const tops = lis.map(l => Math.round(l.getBoundingClientRect().top));
  return { keys: lis.map(l => l.querySelector('input[name="order[]"]').value), tops, visualOk: tops.every((t, i) => i === 0 || t > tops[i - 1]), running: lis.reduce((n, l) => n + l.getAnimations({ subtree: true }).filter(x => x.playState === 'running').length, 0),
    active: a.tagName + '|' + (a.getAttribute('aria-label') || ''), focusVisible: a.matches(':focus-visible'), outline: getComputedStyle(a).outlineStyle + ' ' + getComputedStyle(a).outlineWidth, open: !!(document.querySelector('details.rw-dash-customize') || {}).open }; })()
JS;
$mv = fn (string $key, int $i) => "ol.rw-dash-order > li:has(input[value=\"$key\"]) .rw-dash-move button:nth-of-type(" . ($i + 1) . ')';
$r = mb([mo_login(), ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'p'], ['op' => 'wait', 'ms' => 1500],
    ['op' => 'mouse', 'selector' => 'details.rw-dash-customize > summary', 'click' => true, 'wait' => 700], ['op' => 'eval', 'expr' => $ORD, 'tag' => 'o0'],
    ['op' => 'eval', 'expr' => "(() => { document.querySelector('" . $mv('figures', 1) . "').focus(); return document.activeElement.getAttribute('aria-label'); })()", 'tag' => 'f'],
    KEY_ENTER + ['wait' => 80], ['op' => 'eval', 'expr' => $ORD, 'tag' => 'o1mid'], ['op' => 'wait', 'ms' => 900], ['op' => 'eval', 'expr' => $ORD, 'tag' => 'o1'],
    KEY_ENTER + ['wait' => 900], ['op' => 'eval', 'expr' => $ORD, 'tag' => 'o2'],
    ['op' => 'mouse', 'selector' => $mv('changes', 0), 'click' => true, 'wait' => 900], ['op' => 'eval', 'expr' => $ORD, 'tag' => 'o3'],
    ['op' => 'mouse', 'selector' => 'details.rw-dash-customize button[name=scope][value=me]', 'click' => true, 'nav' => true, 'tag' => 'save'],
    ['op' => 'wait', 'ms' => 600], ['op' => 'eval', 'expr' => "[...document.querySelectorAll('ol.rw-dash-order > li input[name=\"order[]\"]')].map(i => i.value)", 'tag' => 'after'],
], 'cust');
$o0 = bval($r, 'o0'); $o1 = bval($r, 'o1'); $o2 = bval($r, 'o2'); $o3 = bval($r, 'o3');
info('CUST: ' . json_encode(['o0' => $o0['keys'] ?? null, 'mid' => bval($r, 'o1mid'), 'o1' => $o1, 'o2' => $o2['keys'] ?? null, 'o3' => $o3]));
$sw = function (?array $k, string $key, int $d) { if (! $k) return null; $i = array_search($key, $k, true); if ($i === false || ! isset($k[$i + $d])) return $k; [$k[$i], $k[$i + $d]] = [$k[$i + $d], $k[$i]]; return $k; };
$e1 = $sw($o0['keys'] ?? null, 'figures', 1); $e2 = $sw($e1, 'figures', 1); $e3 = $sw($e2, 'changes', -1);
check('CUST-key', 'Customize: Enter on "down" moves the row one place; after the slide the rows stand in their order, the focus stays on the button and is visible', $o0 && $o0['open'] && ($o1['keys'] ?? null) === $e1 && ($o1['visualOk'] ?? false) && ($o1['running'] ?? 1) === 0 && ($o1['active'] ?? '') === 'BUTTON|Move Key figures down' && ($o1['focusVisible'] ?? false) && ! str_starts_with((string) ($o1['outline'] ?? 'none'), 'none'), json_encode($o1));
check('CUST-key2', 'A second Enter moves it again, the focus is still visible', ($o2['keys'] ?? null) === $e2 && ($o2['visualOk'] ?? false) && ($o2['focusVisible'] ?? false) && str_starts_with((string) ($o2['active'] ?? ''), 'BUTTON|'), json_encode($o2));
check('CUST-mouse', 'A click on "up" moves a row up, the rows stand in their new order on screen', ($o3['keys'] ?? null) === $e3 && ($o3['visualOk'] ?? false), json_encode($o3));
$row = q("SELECT value FROM settings WHERE `key` LIKE '%ashboard%' AND context=?", ['user:' . $OWNER]);
$st = $row ? @unserialize((string) $row[0]['value'], ['allowed_classes' => false]) : null;
$stOrder = is_array($st) ? array_values(array_filter($st['order'] ?? [], fn ($k) => in_array($k, $o3['keys'] ?? [], true))) : null;
check('CUST-save', 'Save for me keeps the order shown: the stored order and the page after saving match the panel', (bstep($r, 'save')['status'] ?? 0) === 200 && $stOrder === ($o3['keys'] ?? 'x') && bval($r, 'after') === ($o3['keys'] ?? 'x'), json_encode([$stOrder, bval($r, 'after'), $o3['keys'] ?? null]));
check('CUST-console', 'Customize: no JavaScript error other than the view transition', jerr_other($r) === [], json_encode(jerr_other($r)));
q("DELETE FROM settings WHERE `key` LIKE '%ashboard%' AND context=?", ['user:' . $OWNER]);

// ================================================================= TST: toasts
$cellToggle = ['op' => 'mouse', 'selector' => sel_cell($PA), 'click' => true, 'wait' => 400];
$r = mb([mo_login(), ['op' => 'goto', 'url' => U1BASE . "rolewarden/roles/$RID", 'tag' => 'p'], ['op' => 'wait', 'ms' => 900],
    // 1: by itself after about 5 s
    $cellToggle, ['op' => 'mouse', 'selector' => 'h1', 'wait' => 100], ['op' => 'eval', 'expr' => SAVE_AND_TIME, 'tag' => 'timed'],
    // 2: pointer over the stack pauses it
    $cellToggle, ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'p-new'], ['op' => 'wait', 'ms' => 1000],
    ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast', 'wait' => 6500, 'tag' => 'hover'], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'p-hover'],
    ['op' => 'mouse', 'selector' => 'h1', 'wait' => 7000, 'tag' => 'leave'], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'p-after'],
    // 3: an error stays
    ['op' => 'block', 'urls' => ['*roles/*/permissions*']], $cellToggle, ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'e-new'], ['op' => 'wait', 'ms' => 8000], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'e-8s'],
    ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast--error .rw-toast__close', 'click' => true, 'wait' => 800], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'e-closed'],
    ['op' => 'block', 'urls' => []], ['op' => 'mouse', 'selector' => '.rw-bar button.rw-btn--ghost', 'click' => true, 'wait' => 600],
    // 4: three toasts, the middle one closed, the others keep their order
    $cellToggle, ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'a'], ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast', 'wait' => 200],
    $cellToggle, ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast', 'wait' => 50], ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'b'],
    ['op' => 'block', 'urls' => ['*roles/*/permissions*']], $cellToggle, ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast', 'wait' => 50], ['op' => 'eval', 'expr' => SAVE_AND_TOAST, 'tag' => 'c'],
    ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast', 'wait' => 400], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'three'],
    ['op' => 'mouse', 'selector' => '.rw-toasts .rw-toast[data-mo="T4"] .rw-toast__close', 'click' => true, 'wait' => 900, 'tag' => 'closeB'], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'two'],
    ['op' => 'block', 'urls' => []], ['op' => 'mouse', 'selector' => 'h1', 'wait' => 7500], ['op' => 'eval', 'expr' => TOASTS, 'tag' => 'end'],
], 'toast', 400000);
$tm = bval($r, 'timed');
info('TST: ' . json_encode(['timed' => $tm, 'p-new' => bval($r, 'p-new'), 'p-hover' => bval($r, 'p-hover'), 'p-after' => bval($r, 'p-after'), 'e-new' => bval($r, 'e-new'), 'e-8s' => bval($r, 'e-8s'), 'e-closed' => bval($r, 'e-closed'), 'a' => bval($r, 'a'), 'b' => bval($r, 'b'), 'c' => bval($r, 'c'), 'three' => bval($r, 'three'), 'two' => bval($r, 'two'), 'end' => bval($r, 'end')]));
check('TST-auto', 'A success toast leaves by itself after about 5 s (4.5-6.5 s including its exit)', ($tm['appeared'] ?? false) && str_contains($tm['kind'] ?? '', 'success') && $tm['ms'] >= 4500 && $tm['ms'] <= 6500, json_encode($tm));
$ph = bval($r, 'p-hover') ?? []; $pa = bval($r, 'p-after') ?? [];
$pid = explode(':', (string) bval($r, 'p-new'))[0];
check('TST-pause', 'With the pointer over the stack the timer stops (still there after 7.5 s); when the pointer leaves it goes', in_array($pid, array_column($ph, 'mo'), true) && ! in_array($pid, array_column($pa, 'mo'), true), json_encode([$pid, $ph, $pa]));
$eid = explode(':', (string) bval($r, 'e-new'))[0];
$e8 = bval($r, 'e-8s') ?? [];
check('TST-error', 'An error toast stays (still there after 8 s) until it is closed; closing removes it', str_contains((string) bval($r, 'e-new'), 'error') && in_array($eid, array_column($e8, 'mo'), true) && ! in_array($eid, array_column(bval($r, 'e-closed') ?? [], 'mo'), true), json_encode([bval($r, 'e-new'), $e8, bval($r, 'e-closed')]));
$three = bval($r, 'three') ?? []; $two = bval($r, 'two') ?? [];
$ids3 = array_column($three, 'mo'); $ids2 = array_column($two, 'mo');
$ordered = function (array $t) { for ($i = 1; $i < count($t); $i++) if ($t[$i]['top'] < $t[$i - 1]['bottom'] - 1) return false; return true; };
info("toast ids: three=" . json_encode($ids3) . ' two=' . json_encode($ids2));
check('TST-order', 'Three toasts (two successes, one error, newest at the bottom); closing the middle one leaves the other two in their order, stacked without overlap', count($three) === 3 && $ids3[1] === 'T4' && $ids2 === [$ids3[0], $ids3[2]] && $ordered($two) && $ordered($three) && ($two[1]['kind'] ?? '') === 'error', json_encode([$three, $two]));
$end = bval($r, 'end') ?? [];
check('TST-end', 'Pointer away: the remaining success leaves, the error stays', array_column($end, 'kind') === ['error'], json_encode($end));
check('TST-console', 'Toasts: no JavaScript error other than the view transition and the blocked request', array_values(array_filter(jerr_other($r), fn ($e) => ! preg_match('/ERR_BLOCKED|Failed to fetch|blocked/i', $e))) === [], json_encode(jerr_other($r)));
info('warning toast: no path of the panel produces one over HTTP (V1M1 limit); not tested');

// ================================================================= NOJS: pages without JavaScript; with JavaScript, nothing stays hidden
$pagesNJ = ['dash' => 'rolewarden', 'users' => 'rolewarden/users', 'roles' => 'rolewarden/roles', 'role' => "rolewarden/roles/$RID", 'user' => "rolewarden/users/$UID", 'perms' => 'rolewarden/permissions', 'check' => 'rolewarden/permissions/check?permission=users.view', 'activity' => 'rolewarden/activity', 'settings' => 'rolewarden/settings', 'appearance' => 'rolewarden/appearance', 'profile' => 'rolewarden/profile', 'search' => 'rolewarden/search?q=users'];
$sN = [mo_login(), ['op' => 'nojs', 'value' => true]];
foreach ($pagesNJ as $k => $u) { $sN[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "nj $k"]; $sN[] = ['op' => 'eval', 'expr' => hidden_js(false), 'tag' => "h $k"]; }
$sN[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'nj dash2'];
$sN[] = ['op' => 'eval', 'expr' => "(() => { const m = document.querySelector('main'); const svgs = [...m.querySelectorAll('svg')].filter(s => s.getBoundingClientRect().width > 40); return { counts: " . COUNTS . ", charts: svgs.length, sections: [...m.querySelectorAll('section')].map(s => s.getAttribute('aria-labelledby') || s.getAttribute('aria-label') || s.className), notice: [...m.querySelectorAll('section, div')].filter(e => /secur|notice|alert/i.test(e.className + ' ' + (e.getAttribute('aria-labelledby') || ''))).map(e => e.className + '|' + e.textContent.replace(/\\s+/g, ' ').trim().slice(0, 80)).slice(0, 3) }; })()", 'tag' => 'dashinfo'];
$sN[] = ['op' => 'nojs', 'value' => false];
foreach ($pagesNJ as $k => $u) { $sN[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "js $k"]; $sN[] = ['op' => 'wait', 'ms' => 2500]; $sN[] = ['op' => 'eval', 'expr' => hidden_js(true), 'tag' => "hj $k"]; }
$r = mb($sN, 'nojs');
foreach ($pagesNJ as $k => $u) {
    $v = bval($r, "h $k"); $vj = bval($r, "hj $k");
    check("NOJS-$k", "Without JavaScript $k is complete: nothing transparent, clipped or scaled to nothing ({$v['n']} texts, {$v['rows']} rows)", is_array($v) && $v['count'] === 0 && $v['n'] > 5, json_encode($v));
    check("JSEND-$k", "With JavaScript, 2.5 s after load, nothing in view of $k is left transparent or clipped", is_array($vj) && $vj['count'] === 0, json_encode($vj));
}
$di = bval($r, 'dashinfo');
info('NOJS dashboard: ' . json_encode($di));
check('NOJS-dash-content', 'Without JavaScript the dashboard shows the four figures with their values, the charts and the security notice', $di && count($di['counts']) === 4 && ! in_array('0', $di['counts'], true) && $di['charts'] >= 2 && $di['notice'] !== [], json_encode($di));

// ================================================================= LOG
$bad = seen_clean($S0);
check('LOG-5xx', 'No answer >= 500, no SQL text, no PHP error on any HTTP page of the phase', $bad === [], json_encode(array_slice($bad, 0, 8)));
$bl = bad_log_lines(logs_of($APP));
check('LOG-app', 'The application log has no WARNING/ERROR line', $bl === [], json_encode(array_slice($bl, 0, 6)));
$srv = (string) @file_get_contents("$WORK/server-MOTION.log");
check('LOG-srv', 'No PHP warning in the server log', ! preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/', $srv), short($srv, 300));
server_stop();
