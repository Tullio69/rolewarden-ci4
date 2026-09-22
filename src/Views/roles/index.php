<div class="rw-header">
  <h1><?= lang('RoleWarden.panel.roles.indexTitle') ?></h1>
  <?php if (can('roles.create')) : ?>
    <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('rolewarden/roles/create')) ?>"><?= lang('RoleWarden.panel.roles.createTitle') ?></a>
  <?php endif ?>
</div>

<div class="rw-card">
  <table class="rw-table">
    <thead>
      <tr>
        <th><?= lang('RoleWarden.panel.roles.name') ?></th>
        <th><?= lang('RoleWarden.panel.roles.parent') ?></th>
        <th><?= lang('RoleWarden.panel.roles.users') ?></th>
        <th></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($roles === []) : ?>
        <tr class="rw-table--empty"><td colspan="5"><?= lang('RoleWarden.panel.roles.empty') ?></td></tr>
      <?php endif ?>
      <?php foreach ($roles as $role) : ?>
        <tr>
          <td>
            <a href="<?= esc(site_url('rolewarden/roles/' . $role['id'])) ?>"><?= esc($role['name']) ?></a>
            <?php if ((int) $role['is_system'] === 1) : ?>
              <span class="rw-badge"><?= lang('RoleWarden.panel.roles.system') ?></span>
            <?php endif ?>
            <?php if ((int) $role['is_super_admin'] === 1) : ?>
              <span class="rw-badge rw-badge--success"><?= lang('RoleWarden.panel.roles.superAdmin') ?></span>
            <?php endif ?>
          </td>
          <td><?= $role['parent_id'] !== null ? esc($namesById[$role['parent_id']] ?? '—') : '—' ?></td>
          <td><?= (int) ($counts[$role['id']] ?? 0) ?></td>
          <td><a class="rw-btn rw-btn--sm" href="<?= esc(site_url('rolewarden/roles/' . $role['id'])) ?>"><?= lang('RoleWarden.panel.view') ?></a></td>
          <td>
            <?php if (can('roles.update')) : ?>
              <a class="rw-btn rw-btn--sm" href="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/edit')) ?>"><?= lang('RoleWarden.panel.edit') ?></a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
