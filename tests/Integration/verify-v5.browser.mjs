// V5 headless browser driver (Collaudatore ad Hoc). Node >= 22 (global WebSocket), Chrome via CDP.
// Usage: node verify-v5.browser.mjs <job.json>   -> prints one JSON line with the results.
// The job is a list of steps written by verify-v5.php from the served HTML; nothing here knows the
// module's code. Chrome runs headless with a throwaway --user-data-dir inside the work folder and is
// closed by its own PID (process tree) at the end; no other Chrome process is ever touched.
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const job = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const profile = job.profile;
mkdirSync(profile, { recursive: true });

const chrome = spawn(CHROME, [
  '--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile, '--no-first-run',
  '--no-default-browser-check', '--disable-extensions', '--disable-background-networking',
  '--disable-sync', '--disable-component-update', '--force-color-profile=srgb', '--hide-scrollbars',
  '--window-size=1440,900', 'about:blank',
], { stdio: 'ignore', windowsHide: true });
const out = { pid: chrome.pid, steps: [], errors: [] };

function killChrome() {
  if (chrome.exitCode !== null || chrome.signalCode !== null) return; // exited on Browser.close
  try { execFileSync('taskkill', ['/PID', String(chrome.pid), '/T', '/F'], { stdio: 'ignore' }); } catch { /* already gone */ }
}

let ws, seq = 0;
const pending = new Map();
const listeners = [];
function send(method, params = {}, sessionId) {
  const id = ++seq;
  ws.send(JSON.stringify({ id, method, params, sessionId }));
  return new Promise((resolve, reject) => pending.set(id, { resolve, reject, method }));
}
function waitEvent(name, sessionId, ms = 20000) {
  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('timeout waiting ' + name)), ms);
    listeners.push({ name, sessionId, fn: (p) => { clearTimeout(t); resolve(p); } });
  });
}

// Page-side audit: token resolution, theme/mode attributes, and the contrast of every visible text node.
const AUDIT = `(tokens, semantic) => {
  const root = document.documentElement, cs = getComputedStyle(root);
  const unresolved = tokens.filter((t) => cs.getPropertyValue(t).trim() === '');
  const probe = document.createElement('span'); document.body.appendChild(probe);
  const resolve = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const values = {}; for (const t of semantic) values[t] = resolve(t);
  probe.remove();
  const parse = (c) => { const m = String(c).match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(/[ ,\\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lin = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
  const over = (top, under) => ({ r: top.r * top.a + under.r * (1 - top.a), g: top.g * top.a + under.g * (1 - top.a), b: top.b * top.a + under.b * (1 - top.a), a: 1 });
  const canvas = parse(values['--surface']) || { r: 255, g: 255, b: 255, a: 1 };
  const bgOf = (el) => { const layers = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c.a > 0) { layers.push(c); if (c.a >= 1) break; } } let base = canvas; for (let i = layers.length - 1; i >= 0; i--) base = over(layers[i], base); return base; };
  const dimmed = (el) => { for (let e = el; e; e = e.parentElement) { const s = getComputedStyle(e); if (Number(s.opacity) < 1 || s.visibility === 'hidden' || s.display === 'none') return true; } return false; };
  const low = []; let texts = 0, minRatio = 99;
  for (const el of document.querySelectorAll('body *')) {
    if (['SCRIPT', 'STYLE', 'OPTION', 'TEMPLATE', 'NOSCRIPT', 'TITLE'].includes(el.tagName)) continue;
    if (el.closest('#debug-bar, .kint-rich, [id^=debugbar], dialog:not([open]), noscript, template')) continue;
    const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
    if (!own || !el.getClientRects().length || dimmed(el)) continue;
    const fg = parse(getComputedStyle(el).color); if (!fg) continue;
    const bg = bgOf(el); const r = ratio(over(fg, bg), bg); texts++; minRatio = Math.min(minRatio, r);
    if (r < 4.5) low.push({ el: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\\s+/).join('.') : ''), text: el.textContent.trim().slice(0, 40), ratio: Math.round(r * 100) / 100 });
  }
  const rw = document.querySelector('.rw');
  return { theme: root.getAttribute('data-rw-theme'), mode: root.getAttribute('data-rw-mode'), unresolved, values,
    rwBg: rw ? getComputedStyle(rw).backgroundColor : null, rwColor: rw ? getComputedStyle(rw).color : null,
    texts, minRatio: Math.round(minRatio * 100) / 100, low: low.slice(0, 40), lowCount: low.length,
    title: document.title, links: [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.href) };
}`;

async function main() {
  const portFile = profile + '/DevToolsActivePort';
  for (let i = 0; i < 100 && !existsSync(portFile); i++) await sleep(100);
  const [port, path] = readFileSync(portFile, 'utf8').split('\n');
  ws = new WebSocket('ws://127.0.0.1:' + port.trim() + path.trim());
  await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
  ws.onmessage = (m) => {
    const msg = JSON.parse(m.data);
    if (msg.id && pending.has(msg.id)) {
      const p = pending.get(msg.id); pending.delete(msg.id);
      msg.error ? p.reject(new Error(p.method + ': ' + msg.error.message)) : p.resolve(msg.result);
    } else if (msg.method) {
      for (let i = listeners.length - 1; i >= 0; i--) {
        const l = listeners[i];
        if (l.name === msg.method && (!l.sessionId || l.sessionId === msg.sessionId)) { listeners.splice(i, 1); l.fn(msg.params); }
      }
    }
  };
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
  const { sessionId: s } = await send('Target.attachToTarget', { targetId, flatten: true });
  await send('Page.enable', {}, s);
  await send('Runtime.enable', {}, s);
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, s);
  const consoleErrors = [];
  const onConsole = () => listeners.push({ name: 'Runtime.exceptionThrown', sessionId: s, fn: (p) => { consoleErrors.push(p.exceptionDetails.exception ? p.exceptionDetails.exception.description : p.exceptionDetails.text); onConsole(); } });
  onConsole();

  const evaluate = async (expression) => {
    const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, s);
    if (r.exceptionDetails) throw new Error('eval: ' + (r.exceptionDetails.exception ? r.exceptionDetails.exception.description : r.exceptionDetails.text));
    return r.result.value;
  };
  const settle = async () => { await evaluate('document.fonts ? document.fonts.ready.then(() => true) : true'); await sleep(job.settle ?? 250); };
  const navStatus = () => evaluate("(performance.getEntriesByType('navigation')[0] || {}).responseStatus || 0");

  for (const step of job.steps) {
    const rec = { op: step.op, tag: step.tag };
    try {
      if (step.op === 'emulate') {
        await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: step.scheme || '' }] }, s);
      } else if (step.op === 'goto') {
        const loaded = waitEvent('Page.loadEventFired', s);
        await send('Page.navigate', { url: step.url }, s);
        await loaded; await settle();
        rec.status = await navStatus(); rec.url = await evaluate('location.href');
      } else if (step.op === 'login') {
        const loaded = waitEvent('Page.loadEventFired', s);
        await send('Page.navigate', { url: step.url }, s); await loaded;
        const after = waitEvent('Page.loadEventFired', s);
        await evaluate(`(() => { const f = document.querySelector('form[action$="/login"]'); f.email.value = ${JSON.stringify(step.email)}; f.password.value = ${JSON.stringify(step.password)}; if (f.remember) f.remember.checked = false; f.submit(); return true; })()`);
        await after; await settle();
        rec.url = await evaluate('location.href'); rec.status = await navStatus();
      } else if (step.op === 'click') {
        const nav = step.nav ? waitEvent('Page.loadEventFired', s) : null;
        rec.value = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(step.selector)}); if (!e) return false; e.click(); return true; })()`);
        if (nav && rec.value) { await nav; await settle(); rec.url = await evaluate('location.href'); rec.status = await navStatus(); }
        else await sleep(step.wait ?? 200);
      } else if (step.op === 'eval') {
        rec.value = await evaluate(step.expr);
        if (step.wait) await sleep(step.wait);
      } else if (step.op === 'audit') {
        rec.value = await evaluate('(' + AUDIT + ')(' + JSON.stringify(step.tokens) + ',' + JSON.stringify(step.semantic) + ')');
      } else if (step.op === 'screenshot') {
        const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!step.full }, s);
        writeFileSync(step.path, Buffer.from(shot.data, 'base64'));
        rec.value = step.path;
      } else if (step.op === 'wait') {
        await sleep(step.ms);
      }
    } catch (e) {
      rec.error = String(e.message || e);
      out.errors.push((step.tag || step.op) + ': ' + rec.error);
    }
    out.steps.push(rec);
  }
  out.consoleErrors = consoleErrors;
  try { await send('Browser.close'); } catch { /* closing */ }
}

try {
  await Promise.race([main(), sleep(job.timeout ?? 600000).then(() => { throw new Error('job timeout'); })]);
} catch (e) {
  out.errors.push('driver: ' + String(e.message || e));
} finally {
  await sleep(500);
  killChrome();
  await sleep(500);
  for (let i = 0; i < 20 && existsSync(profile); i++) {
    try { rmSync(profile, { recursive: true, force: true }); } catch (e) { if (i === 19) out.cleanupError = e.message; await sleep(500); }
  }
  out.profileRemoved = !existsSync(profile);
  process.stdout.write(JSON.stringify(out) + '\n');
  process.exit(0);
}
