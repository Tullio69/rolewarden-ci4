document.addEventListener('alpine:init', () => {
  Alpine.data('rwToasts', rwToastsController);
  Alpine.data('rwSettings', rwSettingsController);
  Alpine.data('rwMatrix', rwMatrixController);
  Alpine.data('rwUserMatrix', rwUserMatrixController);
});

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
      setTimeout(() => { this.items = this.items.filter((t) => t.id !== id); }, 120);
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
    cur: { ...config.values },
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

function rwConfirm(id) {
  const dialog = document.getElementById(id);
  if (dialog) dialog.showModal();
}

function rwCancelConfirm(id) {
  const dialog = document.getElementById(id);
  if (dialog) dialog.close();
}

const RW_MARK_SVG = {
  role: '<svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>',
  inherit: '<svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
  grant: '<svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
  deny: '<svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg>',
  none: '',
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
