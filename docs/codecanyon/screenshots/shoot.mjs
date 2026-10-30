// Screenshot helper: node shoot.mjs <outdir> <jar> name=url[@dark][@full] ...  -> PNGs + JSON metrics (matrix overflow, page width).
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
const [OUT, JAR, ...JOBS] = process.argv.slice(2);
const sid = readFileSync(JAR, 'utf8').split('\n').map((l) => l.split('\t')).find((p) => p[5] === 'ci_session')[6].trim();
const profile = OUT + '/prof-' + Date.now(); mkdirSync(profile, { recursive: true });
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile, '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync', '--force-color-profile=srgb', '--hide-scrollbars', '--lang=en-US', '--window-size=1440,900', 'about:blank'], { stdio: 'ignore', windowsHide: true });
const kill = () => { try { execFileSync('taskkill', ['/PID', String(chrome.pid), '/T', '/F'], { stdio: 'ignore' }); } catch {} };
let ws, seq = 0; const pending = new Map();
const send = (method, params = {}, sessionId) => { const id = ++seq; ws.send(JSON.stringify({ id, method, params, sessionId })); return new Promise((res, rej) => pending.set(id, { res, rej, method })); };
const out = {};
try {
  const pf = profile + '/DevToolsActivePort'; for (let i = 0; i < 100 && !existsSync(pf); i++) await sleep(100);
  const [port, path] = readFileSync(pf, 'utf8').split('\n');
  ws = new WebSocket('ws://127.0.0.1:' + port.trim() + path.trim()); await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
  ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pending.has(d.id)) { const p = pending.get(d.id); pending.delete(d.id); d.error ? p.rej(new Error(p.method + ': ' + d.error.message)) : p.res(d.result); } };
  for (const job of JOBS) {
    const k = job.indexOf('='); const name = job.slice(0, k), rest = job.slice(k + 1); const [url, ...flags] = rest.split('@');
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId: s } = await send('Target.attachToTarget', { targetId, flatten: true });
    await send('Page.enable', {}, s); await send('Network.enable', {}, s);
    await send('Network.setCookie', { name: 'ci_session', value: sid, domain: 'localhost', path: '/', httpOnly: true }, s);
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, s);
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] }, s);
    await send('Page.navigate', { url: 'http://localhost:8000' + url }, s); await sleep(2000);
    const ev = async (e) => (await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true }, s)).result.value;
    if (flags.includes('dark')) { await ev(`document.documentElement.setAttribute('data-rw-mode','dark')`); }
    if (flags.includes('cmdk')) { await ev(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'k',ctrlKey:true,bubbles:true}))`); await sleep(500); const q = flags.find((f) => f.startsWith('q:')); if (q) { await ev(`(()=>{const i=document.querySelector('dialog[open] input, [role=dialog] input'); if(i){i.value=${JSON.stringify(q.slice(2))}; i.dispatchEvent(new Event('input',{bubbles:true}));} })()`); await sleep(900); } }
    const js = flags.find((f) => f.startsWith('js:')); if (js) { out[name + '.js'] = await ev(readFileSync(js.slice(3), 'utf8')); }
    await ev(`document.querySelectorAll('#debug-icon,#debug-bar,.debug-bar').forEach(e=>e.remove())`);
    await sleep(300);
    out[name] = await ev(`(() => { const m = [...document.querySelectorAll('table.rw-matrix')].map(t => { const b = t.getBoundingClientRect(); return { cls: t.className, left: Math.round(b.left), right: Math.round(b.right) }; }); return { scrollWidth: document.documentElement.scrollWidth, matrices: m }; })()`);
    let clip; if (flags.includes('full')) { const h = await ev('document.documentElement.scrollHeight'); clip = { x: 0, y: 0, width: 1440, height: Math.min(h, 4000), scale: 1 }; }
    const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!clip, ...(clip ? { clip } : {}) }, s);
    writeFileSync(OUT + '/' + name + '.png', Buffer.from(shot.data, 'base64'));
    await send('Target.closeTarget', { targetId });
  }
} catch (e) { out.error = String(e); } finally { kill(); await sleep(500); try { rmSync(profile, { recursive: true, force: true }); } catch {} }
console.log(JSON.stringify(out));
