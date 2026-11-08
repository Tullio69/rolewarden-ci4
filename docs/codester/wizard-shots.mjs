// Walks the RoleWarden setup wizard in headless Chrome and screenshots chosen steps.
// node wizard-shots.mjs <hostDir> <outDir>
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
const [HOST, OUT] = process.argv.slice(2);
const BASE = 'http://localhost:8000';
mkdirSync(OUT, { recursive: true });
const profile = OUT + '/../chrome-prof-' + Date.now(); mkdirSync(profile, { recursive: true });
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile, '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync', '--force-color-profile=srgb', '--hide-scrollbars', '--lang=en-US', '--window-size=1440,900', 'about:blank'], { stdio: 'ignore', windowsHide: true });
const kill = () => { try { execFileSync('taskkill', ['/PID', String(chrome.pid), '/T', '/F'], { stdio: 'ignore' }); } catch {} };
let ws, seq = 0, s; const pending = new Map(); const log = [];
const send = (method, params = {}, sessionId = s) => { const id = ++seq; ws.send(JSON.stringify({ id, method, params, sessionId })); return new Promise((res, rej) => pending.set(id, { res, rej, method })); };
const ev = async (e) => (await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result.value;
const settle = async () => { for (let i = 0; i < 50; i++) { await sleep(200); if (await ev('document.readyState') === 'complete') break; } await sleep(600); };
const go = async (url) => { await send('Page.navigate', { url: BASE + url }); await settle(); };
const submit = async (sel = 'form.rw-auth-form button.rw-btn--primary') => { await ev(`document.querySelector(${JSON.stringify(sel)}).click()`); await sleep(400); await settle(); };
const shot = async (name, full = false) => {
  let clip;
  if (full) { const h = await ev('Math.max(document.documentElement.scrollHeight, 900)'); clip = { x: 0, y: 0, width: 1440, height: Math.min(h, 4000), scale: 1 }; }
  const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!clip, ...(clip ? { clip } : {}) });
  writeFileSync(OUT + '/' + name + '.png', Buffer.from(r.data, 'base64')); log.push(name + (full ? ' (full)' : ''));
};
const step = () => ev(`(document.querySelector('form.rw-auth-form input[name=step]')||{}).value || ''`);
try {
  const pf = profile + '/DevToolsActivePort'; for (let i = 0; i < 100 && !existsSync(pf); i++) await sleep(100);
  const [port, path] = readFileSync(pf, 'utf8').split('\n');
  ws = new WebSocket('ws://127.0.0.1:' + port.trim() + path.trim()); await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
  ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pending.has(d.id)) { const p = pending.get(d.id); pending.delete(d.id); d.error ? p.rej(new Error(p.method + ': ' + d.error.message)) : p.res(d.result); } };
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' }, undefined);
  ({ sessionId: s } = await send('Target.attachToTarget', { targetId, flatten: true }, undefined));
  await send('Page.enable'); await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
  await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }, { name: 'prefers-color-scheme', value: 'light' }] });

  // Sign in to the host (its global session filter guards /rolewarden/setup).
  await go('/login');
  await ev(`(()=>{document.querySelector('input[name=email]').value='maya@northwind.test';document.querySelector('input[name=password]').value='Shots-Pass-2026!';document.querySelector('form').submit();})()`);
  await settle();
  await go('/rolewarden/setup');
  const seen = new Set();
  for (let guard = 0; guard < 20; guard++) {
    const st = await step(); log.push('step: ' + st);
    if (st === '' ) { await shot('wizard-done'); break; }
    if (seen.has(st) && st !== 'confirm') { log.push('stuck on ' + st + ': ' + await ev(`(document.querySelector('.rw-field-error,.rw-alert,[role=alert]')||{}).textContent||''`)); await shot('stuck-' + st, true); break; }
    seen.add(st);
    switch (st) {
      case 'code': {
        const code = readFileSync(HOST + '/writable/rolewarden/install-code.txt', 'utf8').trim();
        await ev(`document.querySelector('input[name=code]').value=${JSON.stringify(code)}`); await shot('wizard-01-code'); break; }
      case 'requirements': await shot('wizard-02-requirements'); break;
      case 'filters': await shot('wizard-03-filters'); await shot('wizard-03-filters-full', true); break;
      case 'roles':
        await ev(`(()=>{document.querySelector('input[name=mode][value=adapt]').click();const p=document.querySelector('select[name=panelRole]');if(p){p.value='admin';p.dispatchEvent(new Event('change',{bubbles:true}));}})()`);
        await sleep(300); await shot('wizard-04-roles'); await shot('wizard-04-roles-full', true); break;
      case 'newUsers': await shot('wizard-05-new-users'); break;
      case 'superAdmin': await ev(`document.querySelector('input[name=kind][value=existing]').click()`); await shot('wizard-06-super-admin'); break;
      case 'visibility': await ev(`document.querySelector('input[name=visibility][value=admins]').click()`); await shot('wizard-07-visibility'); break;
      case 'confirm':
        if (seen.has('applied')) { await shot('stuck-confirm', true); guard = 99; break; }
        await shot('wizard-08-confirm'); await shot('wizard-08-confirm-full', true); seen.add('applied'); break;
    }
    if (guard < 99) await submit();
  }
  // After the wizard: the panel, as the super admin (sign out and in again, as the docs say).
  await go('/logout'); await go('/login');
  await ev(`(()=>{document.querySelector('input[name=email]').value='maya@northwind.test';document.querySelector('input[name=password]').value='Shots-Pass-2026!';document.querySelector('form').submit();})()`);
  await settle(); await go('/rolewarden/roles'); await shot('after-roles');
  log.push('final url: ' + await ev('location.pathname'));
} catch (e) { log.push('ERROR ' + String(e)); } finally { kill(); await sleep(500); try { rmSync(profile, { recursive: true, force: true }); } catch {} }
console.log(log.join('\n'));
