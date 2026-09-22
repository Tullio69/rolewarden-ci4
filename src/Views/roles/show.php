<?php
$matrixConfig = [
    'saveUrl' => site_url('rolewarden/roles/' . $role['id'] . '/permissions'),
    'csrfName' => csrf_token(),
    'csrfHash' => csrf_hash(),
    'rows' => $rows,
];
?>
<div class="rw-header">
  <h1><?= esc($role['name']) ?></h1>
  <div class="rw-inline">
    <?php if (can('roles.update')) : ?>
      <a class="rw-btn" href="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/edit')) ?>"><?= lang('RoleWarden.panel.edit') ?></a>
    <?php endif ?>
    <?php if (can('roles.delete') && (int) $role['is_system'] !== 1) : ?>
      <form method="post" action="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/delete')) ?>" onsubmit="return confirm('<?= esc(lang('RoleWarden.panel.roles.confirmDelete'), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="rw-btn rw-btn--danger" type="submit"><?= lang('RoleWarden.panel.delete') ?></button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="rw-card">
  <p class="rw-muted"><?= esc($role['description'] ?? '') ?></p>
  <?php if ((int) $role['is_system'] === 1) : ?>
    <span class="rw-badge"><?= lang('RoleWarden.panel.roles.system') ?></span>
  <?php endif ?>
  <?php if ((int) $role['is_super_admin'] === 1) : ?>
    <span class="rw-badge rw-badge--success"><?= lang('RoleWarden.panel.roles.superAdmin') ?></span>
  <?php endif ?>
</div>

<div class="rw-card">
  <h2><?= lang('RoleWarden.panel.roles.matrix') ?></h2>
  <p class="rw-field__hint"><?= lang('RoleWarden.panel.roles.matrixHint') ?></p>

  <script type="application/json" id="rw-matrix-data"><?= json_encode($matrixConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

  <?php if (can('roles.update')) : ?>
    <table class="rw-matrix" x-data="rwMatrix(JSON.parse(document.getElementById('rw-matrix-data').textContent))">
      <thead>
        <tr>
          <th></th>
          <?php foreach ($actions as $action) : ?>
            <th>
              <?= esc($action) ?><br>
              <button type="button" class="rw-btn rw-btn--sm" @click="toggleColumn('<?= esc($action, 'js') ?>', true)"><?= lang('RoleWarden.panel.roles.selectAll') ?></button>
              <button type="button" class="rw-btn rw-btn--sm" @click="toggleColumn('<?= esc($action, 'js') ?>', false)"><?= lang('RoleWarden.panel.roles.clearAll') ?></button>
            </th>
          <?php endforeach ?>
        </tr>
      </thead>
      <tbody>
        <template x-for="(row, rowIndex) in rows" :key="row.area">
          <tr>
            <td>
              <span x-text="row.area"></span><br>
              <button type="button" class="rw-btn rw-btn--sm" @click="toggleRow(row, true)"><?= lang('RoleWarden.panel.roles.selectAll') ?></button>
              <button type="button" class="rw-btn rw-btn--sm" @click="toggleRow(row, false)"><?= lang('RoleWarden.panel.roles.clearAll') ?></button>
            </td>
            <template x-for="cell in row.cells" :key="cell.action">
              <td :class="{ 'rw-matrix__cell--empty': cell.permissionId === null }">
                <template x-if="cell.permissionId !== null">
                  <div>
                    <input type="checkbox" :checked="cell.checked" :disabled="cell.locked" @change="toggleCell(cell, $event.target.checked)">
                    <span class="rw-matrix__origin" x-show="cell.origin" x-text="cell.origin"></span>
                  </div>
                </template>
              </td>
            </template>
          </tr>
        </template>
      </tbody>
    </table>
  <?php else : ?>
    <table class="rw-matrix">
      <thead>
        <tr>
          <th></th>
          <?php foreach ($actions as $action) : ?><th><?= esc($action) ?></th><?php endforeach ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row) : ?>
          <tr>
            <td><?= esc($row['area']) ?></td>
            <?php foreach ($row['cells'] as $cell) : ?>
              <td class="<?= $cell['permissionId'] === null ? 'rw-matrix__cell--empty' : '' ?>">
                <?php if ($cell['permissionId'] !== null) : ?>
                  <input type="checkbox" disabled <?= $cell['checked'] ? 'checked' : '' ?>>
                  <?php if ($cell['origin'] !== null) : ?>
                    <span class="rw-matrix__origin"><?= esc($cell['origin']) ?></span>
                  <?php endif ?>
                <?php endif ?>
              </td>
            <?php endforeach ?>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
