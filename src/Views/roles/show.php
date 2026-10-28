<?php
$matrixConfig = [
    'saveUrl' => site_url('rolewarden/roles/' . $role['id'] . '/permissions'),
    'csrfName' => csrf_token(),
    'csrfHash' => csrf_hash(),
    'messages' => ['saved' => lang('RoleWarden.panel.toast.changesSaved'), 'failed' => lang('RoleWarden.panel.roles.matrixSaveFailed')],
    'roleName' => $role['name'],
    'rows' => $rows,
];
$canEdit = can('roles.update');
$matrixClass = count($actions) > 8 ? 'rw-matrix rw-matrix--dense' : 'rw-matrix';
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.roles.overline') ?></div>
    <h1><?= esc($role['name']) ?></h1>
    <p><?= esc($role['description'] ?? '') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.roles.parent') ?></dt><dd><?= $parentName !== null ? '<a href="' . esc(site_url('rolewarden/roles')) . '">' . esc($parentName) . '</a>' : lang('RoleWarden.panel.roles.none') ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.roles.usersWithRole') ?></dt><dd class="rw-fig"><?= (int) $userCount ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.roles.lastSaved') ?></dt><dd class="rw-muted"><?= $role['updated_at'] !== null ? esc($role['updated_at']) : lang('RoleWarden.panel.roles.never') ?></dd></div>
  </dl>
</div>

<div class="rw-toolbar">
  <div class="rw-field">
    <label class="rw-label" for="role-jump"><?= lang('RoleWarden.panel.roles.jump') ?></label>
    <select class="rw-select" id="role-jump" onchange="if(this.value) window.location = this.value">
      <?php foreach ($allRoles as $option) : ?>
        <option value="<?= esc(site_url('rolewarden/roles/' . $option['id'])) ?>"<?= (int) $option['id'] === (int) $role['id'] ? ' selected' : '' ?>><?= esc($option['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="rw-grow"></div>
  <?php if (can('roles.update')) : ?>
    <a class="rw-btn rw-btn--secondary" href="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/edit')) ?>"><?= lang('RoleWarden.panel.edit') ?></a>
  <?php endif ?>
  <?php if (can('roles.delete') && (int) $role['is_system'] !== 1) : ?>
    <button type="button" class="rw-btn rw-btn--danger" onclick="rwConfirm('confirm-delete-role')"><?= lang('RoleWarden.panel.delete') ?></button>
  <?php endif ?>
  <ul class="rw-legend" aria-label="<?= esc(lang('RoleWarden.panel.a11y.legend'), 'attr') ?>">
    <li><svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg><?= lang('RoleWarden.panel.roles.legendRole') ?></li>
    <li><svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg><?= lang('RoleWarden.panel.roles.legendInherit') ?></li>
    <li><svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?= lang('RoleWarden.panel.roles.legendGrant') ?></li>
    <li><svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg><?= lang('RoleWarden.panel.roles.legendDeny') ?></li>
    <li><span class="rw-mark-blank" aria-hidden="true"></span><?= lang('RoleWarden.panel.roles.legendNone') ?></li>
  </ul>
</div>

<?php if ($canEdit) : ?>
  <div x-data="rwMatrix(<?= esc(json_encode($matrixConfig, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)">
    <div class="rw-bar" :class="{ 'rw-bar--dirty': changes().length }" role="status" aria-live="polite">
      <template x-if="changes().length">
        <div class="rw-bar-msg">
          <span class="rw-bar-dot" aria-hidden="true"></span>
          <span x-text="(changes().length > 1 ? <?= esc(json_encode(str_replace('%s', $role['name'], lang('RoleWarden.panel.roles.unsavedMany'))), 'attr') ?> : <?= esc(json_encode(str_replace('%s', $role['name'], lang('RoleWarden.panel.roles.unsavedOne'))), 'attr') ?>).replace('%d', changes().length)"></span>
          <span class="rw-bar-ids" x-text="changedLabels().join(', ')"></span>
        </div>
      </template>
      <template x-if="!changes().length">
        <div class="rw-bar-msg rw-muted"><?= lang('RoleWarden.panel.roles.allSaved') ?></div>
      </template>
      <div class="rw-bar-actions">
        <button type="button" class="rw-btn rw-btn--ghost" @click="discard()" x-show="changes().length"><?= lang('RoleWarden.panel.roles.discard') ?></button>
        <button type="button" class="rw-btn rw-btn--primary" @click="save()" :disabled="!changes().length || saving"><?= lang('RoleWarden.panel.roles.saveChanges') ?></button>
      </div>
    </div>

    <p class="rw-matrix-help rw-note"><?= lang('RoleWarden.panel.roles.matrixHelp') ?></p>

    <table class="<?= $matrixClass ?>">
      <thead>
        <tr>
          <th class="rw-m-num" scope="col"></th>
          <th class="rw-m-area rw-header" scope="col"><?= lang('RoleWarden.panel.roles.area') ?></th>
          <?php foreach ($actions as $action) : ?>
            <th class="rw-m-act" scope="col">
              <div class="rw-colhead">
                <span class="rw-header rw-m-vlabel"><?= esc(RoleWarden\Models\SeededText::action($action)) ?></span>
                <input type="checkbox" class="rw-check" :checked="triState(rows.map(r => key(r.area, '<?= esc($action, 'js') ?>'))).checked" :indeterminate.prop="triState(rows.map(r => key(r.area, '<?= esc($action, 'js') ?>'))).indeterminate" @change="toggleColumn('<?= esc($action, 'js') ?>', $event.target.checked)" aria-label="<?= esc(sprintf(lang('RoleWarden.panel.roles.grantColumn'), RoleWarden\Models\SeededText::action($action))) ?>">
              </div>
            </th>
          <?php endforeach ?>
          <th class="rw-m-all" scope="col"><span class="rw-header"><?= lang('RoleWarden.panel.roles.row') ?></span></th>
          <th class="rw-m-anno" scope="col"><span class="rw-header" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></span></th>
        </tr>
      </thead>
      <tbody>
        <template x-for="(row, i) in rows" :key="row.area">
          <tr>
            <td class="rw-m-num" x-text="String(i + 1).padStart(2, '0')"></td>
            <th class="rw-m-area" scope="row"><b x-text="row.name"></b><code x-text="row.area + '.*'"></code></th>
            <template x-for="cell in row.cells" :key="cell.action">
              <td class="rw-m-act">
                <template x-if="cell.permissionId !== null">
                  <button type="button" class="rw-cell" :class="{ 'rw-cell--changed': saved[key(row.area, cell.action)] !== cur[key(row.area, cell.action)] }" :aria-pressed="editable(row.area, cell.action) ? (state(row.area, cell.action) === 'role') : null" :aria-disabled="!editable(row.area, cell.action)" :title="tooltip(row.area, cell.action)" @click="toggleCell(row.area, cell.action)" x-html="mark(row.area, cell.action)"></button>
                </template>
              </td>
            </template>
            <td class="rw-m-all">
              <input type="checkbox" class="rw-check" :checked="triState(rowKeys(row.area)).checked" :indeterminate.prop="triState(rowKeys(row.area)).indeterminate" @change="toggleRow(row.area, $event.target.checked)" :aria-label="<?= esc(json_encode(lang('RoleWarden.panel.roles.grantRow')), 'attr') ?>.replace('%s', row.name)">
            </td>
            <td class="rw-m-anno">
              <span class="rw-note" x-show="row.cells.some(c => c.permissionId !== null && saved[key(row.area, c.action)] !== cur[key(row.area, c.action)])">
                <span class="rw-chg" x-text="row.cells.filter(c => c.permissionId !== null && saved[key(row.area, c.action)] !== cur[key(row.area, c.action)]).length + <?= esc(json_encode(' ' . lang('RoleWarden.panel.roles.rowChanged')), 'attr') ?>"></span>
              </span>
            </td>
          </tr>
        </template>
      </tbody>
      <tfoot>
        <tr>
          <td class="rw-m-num"></td>
          <td class="rw-header rw-c-label"><?= lang('RoleWarden.panel.roles.effective') ?></td>
          <?php foreach ($actions as $action) : ?>
            <td class="rw-m-act"><?= (int) $effective[$action] ?><span class="rw-muted rw-unit-sm"> / <?= count($areas) ?></span></td>
          <?php endforeach ?>
          <td class="rw-m-all" style="text-align:center;color:var(--ink)"><?= (int) $totalGranted ?></td>
          <td class="rw-m-anno"><span class="rw-note"><?= esc(sprintf(lang('RoleWarden.panel.roles.grantedOfTotal'), $role['name'], $totalDefined)) ?></span></td>
        </tr>
      </tfoot>
    </table>
  </div>
<?php else : ?>
  <table class="<?= $matrixClass ?>">
    <thead>
      <tr>
        <th class="rw-m-num" scope="col"></th>
        <th class="rw-m-area rw-header" scope="col"><?= lang('RoleWarden.panel.roles.area') ?></th>
        <?php foreach ($actions as $action) : ?><th class="rw-m-act" scope="col"><span class="rw-header rw-m-vlabel"><?= esc(RoleWarden\Models\SeededText::action($action)) ?></span></th><?php endforeach ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $row) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <th class="rw-m-area" scope="row"><b><?= esc($row['name']) ?></b><code><?= esc($row['area']) ?>.*</code></th>
          <?php foreach ($row['cells'] as $cell) : ?>
            <td class="rw-m-act"><?php if ($cell['permissionId'] !== null) : ?><span class="rw-cell" title="<?= esc($cell['tooltip']) ?>"><?= match ($cell['state']) {
                'role' => '<svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>',
                'grant' => '<svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
                'inherit' => '<svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
                'deny' => '<svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg>',
                default => '<span class="rw-mark-blank" aria-hidden="true"></span>',
            } ?></span><?php endif ?></td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<p class="rw-matrix-foot rw-note">
  <span><?= lang('RoleWarden.panel.roles.footInherited') ?></span>
  <span><?= lang('RoleWarden.panel.roles.footOverrides') ?></span>
  <span><?= lang('RoleWarden.panel.roles.footSlug') ?> <code class="rw-mono">area.action</code></span>
</p>

<?php if (can('roles.delete') && (int) $role['is_system'] !== 1) : ?>
  <dialog id="confirm-delete-role" class="rw-confirm">
    <h2><?= esc(sprintf(lang('RoleWarden.panel.roles.confirmDeleteTitle'), $role['name'])) ?></h2>
    <p>
      <?= $userCount === 0
          ? lang('RoleWarden.panel.roles.confirmDeleteNoUsers')
          : esc(lang('RoleWarden.panel.roles.confirmDeleteCount', [$userCount])) ?>
    </p>
    <div class="rw-confirm-actions">
      <button type="button" class="rw-btn rw-btn--ghost" onclick="rwCancelConfirm('confirm-delete-role')"><?= lang('RoleWarden.panel.cancel') ?></button>
      <form method="post" action="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="rw-btn rw-btn--danger"><?= lang('RoleWarden.panel.delete') ?></button>
      </form>
    </div>
  </dialog>
<?php endif ?>
