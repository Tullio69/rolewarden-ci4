<?php
// MOTION probe X: the setup, RM and CNT sections of verify-motion.phase-motion.php only (generated, for checking the instrument).
/**
 * MOTION phase (Collaudatore ad Hoc, Claude): motion across the panel (commit b38e350) in black box, over HTTP, direct
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
echo "\n== MOTION: motion across the panel (b38e350)\n";
$S0 = count($GLOBALS['SEEN']);
$ENVN = getenv('RW_V8B_ENV') ?: 'development';
$OUTP = __DIR__ . '/verify-motion.';

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
const SAVE_AND_TIME = "(async () => { const vis = (el) => { if (!el.isConnected) return false; const s = getComputedStyle(el); return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.05; }; const before = new Set(document.querySelectorAll('.rw-toasts .rw-toast')); document.querySelector('.rw-bar button.rw-btn--primary').click(); const t0 = performance.now(); let el = null; while (!el && performance.now() - t0 < 5000) { await new Promise(r => setTimeout(r, 25)); el = [...document.querySelectorAll('.rw-toasts .rw-toast')].find(t => !before.has(t)); } if (!el) return { appeared: false }; const tc = performance.now(); while (!vis(el) && performance.now() - tc < 3000) await new Promise(r => setTimeout(r, 25)); const ta = performance.now(); while (vis(el) && performance.now() - ta < 15000) await new Promise(r => setTimeout(r, 50)); return { appeared: true, kind: el.className, ms: Math.round(performance.now() - ta) }; })()";
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
    check("RM-$k", "Reduced motion: no moving animation starts or runs ($k)", is_array($v) && $v['count'] === 0 && is_array($e) && $e['count'] === 0, json_encode([$v, $e]));
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
    $out = []; $nvals = 0; $num = fn ($t) => (float) str_replace([',', '.', ' ', "\u{2212}"], ['', '', '', '-'], $t);
    foreach (['seen-1', 'seen-2', 'seen-3'] as $sk) foreach ((bval($r, $sk)['seen'] ?? []) as $e) { [$fin, $txt] = explode('=', $e, 2) + [1 => '']; $nvals++; $v = $num($txt); if ($v < 0 || $v > (float) $fin || ! preg_match('/^-?[\d.,]+$/u', str_replace("\u{2212}", '-', $txt))) $out[] = $e; }
    info("CNT $lg: $nvals values written during three counts; out of range: " . json_encode(array_slice($out, 0, 12)));
    check("CNT-$lg-range", "Counters ($lg): while counting up, every value shown lies between 0 and the figure (three loads)", $nvals > 20 && $out === [], json_encode(['n' => $nvals, 'out' => array_slice($out, 0, 12), 'count' => count($out)]));
}
postform($own, 'rolewarden/profile', '#rolewarden/profile/language$#', ['language' => 'en'], 'lang en');

server_stop();
