document.addEventListener('alpine:init', () => {
  Alpine.data('rwToasts', rwToastsController);
  Alpine.data('rwSettings', rwSettingsController);
  Alpine.data('rwMatrix', rwMatrixController);
  Alpine.data('rwUserMatrix', rwUserMatrixController);
  Alpine.data('rwAppearance', rwAppearanceController);
  Alpine.data('rwSearch', rwSearchController);
  Alpine.data('rwDashboard', rwDashboardController);
});

const RW_EASE_EXPO = 'cubic-bezier(0.16, 1, 0.3, 1)'; // --ease-expo in panel.css

function rwReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Runs `fn` once the page is parsed. Deferred, panel.js runs then and blocks the
 * first render (layouts/panel.php), so motion starts from the very first frame.
 */
function rwOnReady(fn) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
  else fn();
}

/**
 * Runs `change` (opening or closing a dialog) as a view transition in which `from`
 * turns into `to`: a dialog grows out of the button that opened it and goes back
 * into it. Without view transitions, or with reduced motion, it just runs `change`.
 */
function rwMorph(change, from, to) {
  if (!document.startViewTransition || rwReducedMotion() || !from || !to || !from.isConnected) {
    change();
    return;
  }
  from.style.viewTransitionName = 'rw-morph';
  document.documentElement.classList.add('rw-morphing');
  const transition = document.startViewTransition(() => {
    from.style.viewTransitionName = '';
    to.style.viewTransitionName = 'rw-morph';
    change();
  });
  transition.finished.finally(() => {
    to.style.viewTransitionName = '';
    document.documentElement.classList.remove('rw-morphing');
  });
}

/**
 * Slides elements from where they were (`before`, their tops before a DOM change)
 * to where they are now, so a reordered list or a closed toast does not jump.
 */
function rwSlide(elements, before) {
  if (rwReducedMotion()) return;
  elements.forEach((el, i) => {
    const dy = before[i] - el.getBoundingClientRect().top;
    if (el.isConnected && dy) el.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 320, easing: RW_EASE_EXPO });
  });
}

/**
 * Dashboard key figures count up to their value (data-rw-count), then show the
 * server's own text again. The width is held so the chip beside does not move.
 */
function rwCountUp() {
  if (rwReducedMotion()) return;
  // Grouped like the server's text, four-digit figures included.
  const format = new Intl.NumberFormat(document.documentElement.lang || undefined, { useGrouping: 'always' });
  document.querySelectorAll('[data-rw-count]').forEach((el) => {
    const target = Number(el.dataset.rwCount);
    const text = el.textContent;
    if (!(target > 1)) return;
    el.style.minWidth = el.getBoundingClientRect().width + 'px';
    let start = null;
    const step = (now) => {
      // From the first frame's own time: performance.now() can run ahead of it.
      start ??= now;
      const p = Math.min((now - start) / 640, 1);
      el.textContent = p < 1 ? format.format(Math.round(target * (1 - 2 ** (-10 * p)))) : text;
      if (p < 1) requestAnimationFrame(step);
      else el.style.minWidth = '';
    };
    requestAnimationFrame(step);
  });
}

rwOnReady(rwCountUp);

/**
 * The sidebar's active highlight travels from the item of the page left to the item
 * of the page opened: the old position and item are kept in sessionStorage when the
 * page goes, and the new highlight slides from there (a FLIP across two documents).
 * The two labels change colour with it, so neither is ever unreadable on its way.
 */
const RW_PILL_KEY = 'rw-pill';

window.addEventListener('pagehide', () => {
  const pill = document.querySelector('.rw-side-pill');
  try {
    if (pill) sessionStorage.setItem(RW_PILL_KEY, JSON.stringify({ top: pill.getBoundingClientRect().top, href: pill.parentElement.getAttribute('href') }));
    else sessionStorage.removeItem(RW_PILL_KEY);
  } catch (e) {
    // storage blocked: the highlight simply appears in place
  }
});

function rwPill() {
  let from = null;
  try {
    from = JSON.parse(sessionStorage.getItem(RW_PILL_KEY));
    sessionStorage.removeItem(RW_PILL_KEY);
  } catch (e) {
    return;
  }
  const pill = document.querySelector('.rw-side-pill');
  if (!from || !pill || rwReducedMotion()) return;
  const dy = from.top - pill.getBoundingClientRect().top;
  if (!dy) return;
  const timing = { duration: 420, easing: RW_EASE_EXPO };
  pill.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], timing);
  pill.parentElement.animate([{ color: 'var(--sb-ink)' }, {}], timing);
  const old = [...document.querySelectorAll('.rw-side-nav a')].find((a) => a.getAttribute('href') === from.href);
  if (old) old.animate([{ color: 'var(--sb-act-fg)' }, {}], timing);
}

rwOnReady(rwPill);

/** Shows a toast from anywhere on the page: rwToast('error', 'Could not save this change.'). */
function rwToast(type, message) {
  window.dispatchEvent(new CustomEvent('rw-toast', { detail: { type, message } }));
}

const RW_TOAST_LIFETIME = 5000; // ms; errors stay until closed
const RW_TOAST_MAX = 3;

/**
 * Toast stack (docs/design-system/components/Toast). Starts with the server's
 * flash messages and takes new ones from the `rw-toast` window event. Success
 * and warning leave after RW_TOAST_LIFETIME, paused while the pointer or focus
 * is inside the stack; errors stay until closed.
 */
function rwToastsController(config) {
  return {
    kinds: config.kinds,
    items: [],
    paused: false,
    nextId: 1,

    init() {
      for (const message of config.messages) this.push(message);
      setInterval(() => this.tick(100), 100);
    },

    push(detail) {
      if (!detail || !detail.message || !(detail.type in this.kinds)) return;
      const visible = this.items.filter((t) => !t.leaving);
      if (visible.length >= RW_TOAST_MAX) this.close(visible[0].id);
      this.items.push({ id: this.nextId++, type: detail.type, message: detail.message, left: RW_TOAST_LIFETIME, leaving: false });
    },

    tick(ms) {
      if (this.paused) return;
      for (const toast of this.items) {
        if (toast.type === 'error' || toast.leaving) continue;
        toast.left -= ms;
        if (toast.left <= 0) this.close(toast.id);
      }
    },

    close(id) {
      const toast = this.items.find((t) => t.id === id);
      if (!toast || toast.leaving) return;
      toast.leaving = true;
      setTimeout(() => {
        // The toasts left in the stack slide into the gap instead of jumping.
        const others = [...this.$root.querySelectorAll('.rw-toast:not(.is-leaving)')];
        const tops = others.map((el) => el.getBoundingClientRect().top);
        this.items = this.items.filter((t) => t.id !== id);
        this.$nextTick(() => rwSlide(others, tops));
      }, 160);
    },
  };
}

/**
 * Settings screen (docs/design-system/components/Settings): marks each changed
 * row and lists the changed settings in the bar with Discard and Save. Saving
 * is a plain form post, so the screen also works without JavaScript.
 */
function rwSettingsController(config) {
  return {
    saved: { ...config.values },
    cur: { ...(config.current || config.values) },
    names: config.names,
    labels: config.labels,

    changed() {
      return Object.keys(this.saved).filter((k) => this.cur[k] !== this.saved[k]);
    },

    isChanged(key) {
      return this.cur[key] !== this.saved[key];
    },

    summary() {
      const n = this.changed().length;
      return (n === 1 ? this.labels.one : this.labels.many).replace('%d', String(n));
    },

    changedNames() {
      return this.changed().map((k) => this.names[k]).join(', ');
    },

    discard() {
      this.cur = { ...this.saved };
    },
  };
}

/** Opens a confirm dialog out of the button that asked for it. */
function rwConfirm(id) {
  const dialog = document.getElementById(id);
  if (!dialog) return;
  dialog.rwTrigger = document.activeElement;
  rwMorph(() => dialog.showModal(), dialog.rwTrigger, dialog);
}

function rwCancelConfirm(id) {
  const dialog = document.getElementById(id);
  if (dialog) rwMorph(() => dialog.close(), dialog, dialog.rwTrigger);
}

const RW_MARK_SVG = {
  role: '<svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path pathLength="1" d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>',
  inherit: '<svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path pathLength="1" d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
  grant: '<svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" aria-hidden="true"><circle pathLength="1" cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path pathLength="1" d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
  deny: '<svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path pathLength="1" d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path pathLength="1" d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg>',
  none: '<span class="rw-mark-blank" aria-hidden="true"></span>',
};

function rwMarkSvg(state) {
  return RW_MARK_SVG[state] || '';
}

/**
 * Role permission matrix: click toggles only between "granted by role" and
 * "not granted"; inherited and override-driven cells are locked. Changes
 * queue in `cur` against `saved` and post one at a time when Save is
 * pressed, reusing the same per-cell endpoint the role controller already
 * exposes — no separate batch endpoint needed.
 */
function rwMatrixController(config) {
  return {
    saveUrl: config.saveUrl,
    csrfName: config.csrfName,
    csrfHash: config.csrfHash,
    messages: config.messages,
    roleName: config.roleName,
    rows: config.rows,
    saving: false,
    saved: {},
    cur: {},

    init() {
      for (const row of this.rows) {
        for (const cell of row.cells) {
          if (cell.permissionId === null) continue;
          this.saved[cell.action + '@' + row.area] = cell.state;
        }
      }
      this.cur = { ...this.saved };
    },

    key(area, action) {
      return action + '@' + area;
    },

    cellFor(area, action) {
      const row = this.rows.find((r) => r.area === area);
      return row ? row.cells.find((c) => c.action === action) : null;
    },

    state(area, action) {
      return this.cur[this.key(area, action)] ?? 'none';
    },

    editable(area, action) {
      const cell = this.cellFor(area, action);
      return !!cell && cell.permissionId !== null && (cell.state === 'role' || cell.state === 'none');
    },

    mark(area, action) {
      return rwMarkSvg(this.state(area, action));
    },

    tooltip(area, action) {
      const cell = this.cellFor(area, action);
      return cell ? cell.tooltip : '';
    },

    toggleCell(area, action) {
      if (!this.editable(area, action)) return;
      const k = this.key(area, action);
      this.cur[k] = this.cur[k] === 'role' ? 'none' : 'role';
    },

    changes() {
      return Object.keys(this.saved).filter((k) => this.saved[k] !== this.cur[k]);
    },

    changedLabels() {
      return this.changes().map((k) => {
        const [action, area] = k.split('@');
        const row = this.rows.find((r) => r.area === area);
        const cell = row ? row.cells.find((c) => c.action === action) : null;
        return area + '.' + action + ' (id ' + (cell ? cell.permissionId : '?') + ')';
      });
    },

    rowKeys(area) {
      const row = this.rows.find((r) => r.area === area);
      return row ? row.cells.filter((c) => this.editable(area, c.action)).map((c) => this.key(area, c.action)) : [];
    },

    triState(keys) {
      if (keys.length === 0) return { checked: false, indeterminate: false };
      const on = keys.filter((k) => this.cur[k] === 'role').length;
      return { checked: on === keys.length, indeterminate: on > 0 && on < keys.length };
    },

    toggleMany(keys, turnOn) {
      keys.forEach((k) => { this.cur[k] = turnOn ? 'role' : 'none'; });
    },

    toggleColumn(action, turnOn) {
      const keys = this.rows.map((r) => this.key(r.area, action)).filter((k) => {
        const [a, area] = k.split('@');
        return this.editable(area, a);
      });
      this.toggleMany(keys, turnOn);
    },

    toggleRow(area, turnOn) {
      this.toggleMany(this.rowKeys(area), turnOn);
    },

    discard() {
      this.cur = { ...this.saved };
    },

    async save() {
      if (this.saving) return;
      this.saving = true;
      let savedAny = false;

      try {
        for (const k of this.changes()) {
          const [action, area] = k.split('@');
          const cell = this.cellFor(area, action);
          if (!cell) continue;

          const body = new URLSearchParams();
          body.set('permission_id', String(cell.permissionId));
          body.set('granted', this.cur[k] === 'role' ? '1' : '0');
          body.set(this.csrfName, this.csrfHash);

          const response = await fetch(this.saveUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
          });
          const data = await response.json().catch(() => null);

          if (data && data.csrfHash) this.csrfHash = data.csrfHash;

          if (!response.ok || !data || !data.ok) {
            rwToast('error', (data && data.message) || this.messages.failed);
            return;
          }

          this.saved[k] = this.cur[k];
          cell.state = this.cur[k];
          cell.editable = this.cur[k] === 'role' || this.cur[k] === 'none';
          savedAny = true;
        }
        if (savedAny) rwToast('success', this.messages.saved);
      } catch (e) {
        rwToast('error', this.messages.failed);
      } finally {
        this.saving = false;
      }
    },
  };
}

/**
 * A user's effective permissions with her own overrides. Clicking a cell
 * only ever adds or removes her own override: a denial on top of a role
 * grant, or a grant where none of her roles reach. `base` (role/inherit/
 * none, never touched by this screen) stays fixed; `cur` holds only the
 * override layer.
 */
function rwUserMatrixController(config) {
  return {
    saveUrl: config.saveUrl,
    csrfName: config.csrfName,
    csrfHash: config.csrfHash,
    messages: config.messages,
    rows: config.rows,
    saving: false,
    base: {},
    saved: {},
    cur: {},

    init() {
      for (const row of this.rows) {
        for (const cell of row.cells) {
          if (cell.permissionId === null) continue;
          const k = this.key(row.area, cell.action);
          if (cell.state === 'grant' || cell.state === 'deny') {
            this.base[k] = 'none';
            this.saved[k] = cell.state;
          } else {
            this.base[k] = cell.state;
          }
        }
      }
      this.cur = { ...this.saved };
    },

    key(area, action) {
      return action + '@' + area;
    },

    cellFor(area, action) {
      const row = this.rows.find((r) => r.area === area);
      return row ? row.cells.find((c) => c.action === action) : null;
    },

    state(area, action) {
      const k = this.key(area, action);
      return this.cur[k] ?? this.base[k] ?? 'none';
    },

    mark(area, action) {
      return rwMarkSvg(this.state(area, action));
    },

    tooltip(area, action) {
      const cell = this.cellFor(area, action);
      return cell ? cell.tooltip : '';
    },

    toggleCell(area, action) {
      const cell = this.cellFor(area, action);
      if (!cell || cell.permissionId === null) return;
      const k = this.key(area, action);

      if (k in this.cur) {
        delete this.cur[k];
      } else {
        this.cur[k] = (this.base[k] && this.base[k] !== 'none') ? 'deny' : 'grant';
      }
    },

    changes() {
      const keys = new Set([...Object.keys(this.saved), ...Object.keys(this.cur)]);
      return [...keys].filter((k) => this.saved[k] !== this.cur[k]);
    },

    overrideCount() {
      return Object.keys(this.cur).length;
    },

    discard() {
      this.cur = { ...this.saved };
    },

    async save() {
      if (this.saving) return;
      this.saving = true;
      let savedAny = false;

      try {
        for (const k of this.changes()) {
          const [action, area] = k.split('@');
          const cell = this.cellFor(area, action);
          if (!cell) continue;

          const body = new URLSearchParams();
          body.set('permission_id', String(cell.permissionId));
          if (k in this.cur) body.set('granted', this.cur[k] === 'grant' ? '1' : '0');
          body.set(this.csrfName, this.csrfHash);

          const response = await fetch(this.saveUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
          });
          const data = await response.json().catch(() => null);

          if (data && data.csrfHash) this.csrfHash = data.csrfHash;

          if (!response.ok || !data || !data.ok) {
            rwToast('error', (data && data.message) || this.messages.failed);
            return;
          }

          if (k in this.cur) {
            this.saved[k] = this.cur[k];
          } else {
            delete this.saved[k];
          }
          savedAny = true;
        }
        if (savedAny) rwToast('success', this.messages.saved);
      } catch (e) {
        rwToast('error', this.messages.failed);
      } finally {
        this.saving = false;
      }
    },
  };
}

/**
 * Appearance screen: applies the form to this very page while it is edited
 * (theme on <html data-rw-theme>, overrides as inline custom properties), reads
 * the starting theme's own values from the computed style rather than keeping a
 * copy, derives hover, tint and on-accent from the accent, and lists every text
 * colour that falls under WCAG AA on its background. Saving is a plain post.
 */
function rwAppearanceController(config) {
  const KEYS = ['surface', 'surface-raised', 'ink', 'ink-muted', 'ink-faint', 'accent', 'accent-hover', 'accent-tint', 'on-accent', 'granted', 'denied'];
  const EDITABLE = ['surface', 'ink', 'accent', 'accent-hover', 'accent-tint', 'on-accent'];
  const PREFIX = { light: 'l', dark: 'd' };
  const PAIRS = [['ink', 'surface', 4.5], ['ink-muted', 'surface', 4.5], ['ink-faint', 'surface', 4.5], ['accent', 'surface', 4.5], ['on-accent', 'accent', 4.5], ['granted', 'surface', 4.5], ['denied', 'surface', 4.5]];

  return {
    base: config.base,
    radius: config.radius,
    density: config.density,
    sidebar: config.sidebar,
    colors: { light: { ...config.colors.light }, dark: { ...config.colors.dark } },
    labels: config.labels,
    defaults: { light: {}, dark: {} },
    tick: 0,

    init() {
      // The saved overrides are replaced by the form state while this page is open.
      const saved = document.querySelector('link[href*="rolewarden/theme.css"]');
      if (saved) saved.disabled = true;
      this.readDefaults();
      this.apply();
      this.$watch('base', () => { this.readDefaults(); this.apply(); });
      this.$watch('radius', () => this.apply());
      this.$watch('density', () => this.apply());
      this.$watch('sidebar', () => this.apply());
    },

    readDefaults() {
      const root = document.documentElement;
      const inline = root.getAttribute('style');
      root.dataset.rwTheme = this.base;
      root.removeAttribute('style');
      const computed = getComputedStyle(root);
      for (const mode of ['light', 'dark']) {
        for (const key of KEYS) {
          this.defaults[mode][key] = rwHex(computed.getPropertyValue('--' + PREFIX[mode] + '-' + key).trim());
        }
      }
      if (inline) root.setAttribute('style', inline);
      this.tick++;
    },

    effective(mode, key) {
      this.tick;
      return this.colors[mode][key] || this.defaults[mode][key] || '#000000';
    },

    setColor(mode, key, value) {
      this.colors[mode][key] = value;
      if (key === 'accent' || (key === 'surface' && this.colors[mode].accent)) this.derive(mode);
      this.apply();
    },

    clearColor(mode, key) {
      delete this.colors[mode][key];
      if (key === 'accent') ['accent-hover', 'accent-tint', 'on-accent'].forEach((k) => delete this.colors[mode][k]);
      else if (key === 'surface' && this.colors[mode].accent) this.derive(mode);
      this.apply();
    },

    derive(mode) {
      const accent = this.colors[mode].accent;
      this.colors[mode]['accent-hover'] = rwMix(accent, mode === 'light' ? '#000000' : '#ffffff', 0.18);
      this.colors[mode]['accent-tint'] = rwMix(accent, this.effective(mode, 'surface'), 0.86);
      this.colors[mode]['on-accent'] = rwContrast(accent, '#ffffff') >= rwContrast(accent, '#0a0a0b') ? '#ffffff' : '#0a0a0b';
    },

    apply() {
      const root = document.documentElement;
      root.dataset.rwTheme = this.base;
      for (const mode of ['light', 'dark']) {
        for (const key of EDITABLE) {
          const name = '--' + PREFIX[mode] + '-' + key;
          if (this.colors[mode][key]) root.style.setProperty(name, this.colors[mode][key]);
          else root.style.removeProperty(name);
        }
      }
      if (this.radius !== '') {
        root.style.setProperty('--radius-md', this.radius + 'px');
        root.style.setProperty('--radius-sm', Math.floor(Number(this.radius) / 2) + 'px');
      } else {
        root.style.removeProperty('--radius-md');
        root.style.removeProperty('--radius-sm');
      }
      const side = document.querySelector('.rw-side');
      if (side) side.dataset.sidebar = this.sidebar || 'paper';
      const sizes = config.densities[this.density] || null;
      for (const key of Object.keys(config.densities.compact)) {
        if (sizes) root.style.setProperty('--size-' + key, sizes[key] + 'px');
        else root.style.removeProperty('--size-' + key);
      }
      this.tick++;
    },

    warnings() {
      this.tick;
      const found = [];
      for (const mode of ['light', 'dark']) {
        for (const [fg, bg, threshold] of PAIRS) {
          const ratio = rwContrast(this.effective(mode, fg), this.effective(mode, bg));
          if (ratio < threshold) {
            found.push(this.labels.pairs[fg] + ' (' + this.labels.modes[mode] + ') ' + ratio.toFixed(1) + ':1, ' + this.labels.below);
          }
        }
      }
      return found;
    },
  };
}

/**
 * Command search (docs/design-system/components/CommandSearch). Ctrl K, Cmd K
 * or the sidebar link opens a modal <dialog>, which closes on Esc and gives
 * focus back by itself. Each query asks the server, which sends only the
 * results the user may reach, as HTML; Up and Down move through them, Enter
 * opens the highlighted one.
 */
function rwSearchController(config) {
  return {
    active: '',
    mac: /Mac|iPhone|iPad/.test(navigator.platform),
    ticket: 0,

    hotkey(event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        this.open();
      }
    },

    open() {
      if (this.$refs.dialog.open) {
        return;
      }
      // The dialog grows out of the sidebar's search box, also when Ctrl K opened it.
      rwMorph(() => {
        this.$refs.dialog.showModal();
        this.$refs.q.select();
      }, this.$root.querySelector('.rw-side-search'), this.$refs.dialog);
      this.$refs.list.dataset.enter = '';
      setTimeout(() => { delete this.$refs.list.dataset.enter; }, 600);
      this.load();
    },

    async load() {
      const ticket = ++this.ticket;
      try {
        const response = await fetch(config.url + '?q=' + encodeURIComponent(this.$refs.q.value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (response.redirected) {
          window.location.href = response.url; // signed out meanwhile: the login page
          return;
        }
        if (!response.ok || ticket !== this.ticket) {
          return; // a later query is on its way, or this one failed: keep what is shown
        }
        this.$refs.list.innerHTML = await response.text();
        this.select(0);
      } catch (e) {
        // offline: keep the last results
      }
    },

    options() {
      return [...this.$refs.list.querySelectorAll('[role="option"]')];
    },

    select(index) {
      const options = this.options();
      options.forEach((option, i) => option.setAttribute('aria-selected', i === index ? 'true' : 'false'));
      this.active = options[index] ? options[index].id : '';
      options[index]?.scrollIntoView({ block: 'nearest' });
    },

    move(step) {
      const options = this.options();
      if (options.length > 0) {
        const current = options.findIndex((option) => option.id === this.active);
        this.select((current + step + options.length) % options.length);
      }
    },

    go() {
      const option = this.active !== '' ? document.getElementById(this.active) : null;
      if (option) {
        window.location.href = option.href;
      }
    },
  };
}

/**
 * Dashboard customize panel (views/dashboard/customize.php). Up and down swap
 * a row with its neighbour by moving the neighbour, so the focused button stays
 * put; when it can't move further, focus goes to the row's other button. The
 * order posted is the rows' order. Nothing moves above a pinned row.
 */
function rwDashboardController() {
  return {
    moves: 0,
    neighbour(row, step) {
      return step < 0 ? row.previousElementSibling : row.nextElementSibling;
    },
    movable(row, step) {
      this.moves; // re-evaluated after every move
      const other = this.neighbour(row, step);
      return other !== null && !other.hasAttribute('data-pinned');
    },
    move(row, step, button) {
      if (!this.movable(row, step)) return;
      const other = this.neighbour(row, step);
      const rows = [...row.parentNode.children];
      const tops = rows.map((el) => el.getBoundingClientRect().top);
      row.parentNode.insertBefore(other, step < 0 ? row.nextElementSibling : row);
      rwSlide(rows, tops);
      row.animate([{ backgroundColor: 'var(--accent-tint)' }, { backgroundColor: 'transparent' }], { duration: 900, easing: 'ease-out' });
      this.moves++;
      this.$nextTick(() => {
        if (button.disabled) row.querySelector('.rw-dash-move button:not(:disabled)')?.focus();
      });
    },
  };
}

/** #rgb, #rrggbb or rgb(...) as #rrggbb (computed custom properties keep what was written). */
function rwHex(value) {
  if (/^#[0-9a-f]{6}$/i.test(value)) return value.toLowerCase();
  if (/^#[0-9a-f]{3}$/i.test(value)) return '#' + value.slice(1).split('').map((c) => c + c).join('').toLowerCase();
  const m = value.match(/rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/i);
  return m ? '#' + [m[1], m[2], m[3]].map((n) => Number(n).toString(16).padStart(2, '0')).join('') : '';
}

/** Mixes two #rrggbb colours: weight is the share of the second one. */
function rwMix(a, b, weight) {
  const pa = [1, 3, 5].map((i) => parseInt(a.slice(i, i + 2), 16));
  const pb = [1, 3, 5].map((i) => parseInt(b.slice(i, i + 2), 16));
  return '#' + pa.map((v, i) => Math.round(v + (pb[i] - v) * weight).toString(16).padStart(2, '0')).join('');
}

/** WCAG 2.1 contrast ratio of two #rrggbb colours. */
function rwContrast(a, b) {
  if (!/^#[0-9a-f]{6}$/i.test(a) || !/^#[0-9a-f]{6}$/i.test(b)) return 21;
  const lum = (hex) => {
    const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
      .map((x) => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4));
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  };
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
}
