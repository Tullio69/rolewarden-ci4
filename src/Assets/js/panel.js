document.addEventListener('alpine:init', () => {
  Alpine.data('rwMatrix', rwMatrixController);
});

function rwMatrixController(config) {
  return {
    saveUrl: config.saveUrl,
    csrfName: config.csrfName,
    csrfHash: config.csrfHash,
    rows: config.rows,
    savingCount: 0,

    cellByAction(row, action) {
      return row.cells.find((cell) => cell.action === action) ?? null;
    },

    async toggleCell(cell, checked) {
      if (cell.locked || cell.permissionId === null || cell.checked === checked) {
        return;
      }

      const previous = cell.checked;
      cell.checked = checked;
      this.savingCount++;

      try {
        const body = new URLSearchParams();
        body.set('permission_id', String(cell.permissionId));
        body.set('granted', checked ? '1' : '0');
        body.set(this.csrfName, this.csrfHash);

        const response = await fetch(this.saveUrl, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body,
        });
        const data = await response.json().catch(() => null);

        if (data && data.csrfHash) {
          this.csrfHash = data.csrfHash;
        }

        if (!response.ok || !data || !data.ok) {
          cell.checked = previous;
          window.alert((data && data.message) || 'Could not save this change.');
        }
      } catch (error) {
        cell.checked = previous;
        window.alert('Could not save this change.');
      } finally {
        this.savingCount--;
      }
    },

    toggleColumn(action, checked) {
      this.rows.forEach((row) => {
        const cell = this.cellByAction(row, action);

        if (cell) {
          this.toggleCell(cell, checked);
        }
      });
    },

    toggleRow(row, checked) {
      row.cells.forEach((cell) => this.toggleCell(cell, checked));
    },
  };
}
