<div class="rw-header">
  <h1><?= lang('RoleWarden.panel.permissions.indexTitle') ?></h1>
</div>

<?php foreach ($byArea as $area => $items) : ?>
  <div class="rw-card">
    <h2><?= esc($area) ?></h2>
    <table class="rw-table">
      <thead>
        <tr>
          <th><?= lang('RoleWarden.panel.permissions.slug') ?></th>
          <th><?= lang('RoleWarden.panel.permissions.description') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $permission) : ?>
          <tr>
            <td><?= esc($permission['slug']) ?></td>
            <td><?= esc($permission['description'] ?? '') ?></td>
            <td>
              <?php if ((int) $permission['is_system'] === 1) : ?>
                <span class="rw-badge"><?= lang('RoleWarden.panel.roles.system') ?></span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endforeach ?>
