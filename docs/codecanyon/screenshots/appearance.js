(async () => {
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));
  const r = document.querySelector('input[name=base][value=clarity]'); r.click(); r.dispatchEvent(new Event('change', { bubbles: true }));
  await wait(300);
  const s = document.getElementById('rw-sidebar'); s.value = 'ink'; s.dispatchEvent(new Event('change', { bubbles: true }));
  const c = document.getElementById('rw-light-accent'); c.value = '#f2c94c'; c.dispatchEvent(new Event('input', { bubbles: true }));
  await wait(500);
  return document.querySelector('.rw-appearance-warn')?.closest('span')?.textContent.trim();
})()
