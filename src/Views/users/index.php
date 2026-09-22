<div class="rw-header">
  <h1><?= lang('RoleWarden.panel.users.indexTitle') ?></h1>
  <?php if (can('users.create')) : ?>
    <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('rolewarden/users/create')) ?>"><?= lang('RoleWarden.panel.users.createTitle') ?></a>
  <?php endif ?>
</div>

<form class="rw-card rw-toolbar" method="get" action="<?= esc(site_url('rolewarden/users')) ?>">
  <input type="search" name="q" value="<?= esc($search) ?>" placeholder="<?= esc(lang('RoleWarden.panel.users.searchPlaceholder')) ?>">
  <select name="role">
    <option value=""><?= lang('RoleWarden.panel.users.anyRole') ?></option>
    <?php foreach ($roles as $role) : ?>
      <option value="<?= (int) $role['id'] ?>"<?= (string) $roleId === (string) $role['id'] ? ' selected' : '' ?>><?= esc($role['name']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="status">
    <option value=""><?= lang('RoleWarden.panel.users.anyStatus') ?></option>
    <option value="active"<?= $status === 'active' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.users.active') ?></option>
    <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.users.inactive') ?></option>
  </select>
  <button class="rw-btn" type="submit"><?= lang('RoleWarden.panel.filter') ?></button>
</form>

<div class="rw-card">
  <table class="rw-table">
    <thead>
      <tr>
        <th><?= lang('RoleWarden.panel.users.username') ?></th>
        <th><?= lang('RoleWarden.panel.users.email') ?></th>
        <th><?= lang('RoleWarden.panel.users.status') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($users === []) : ?>
        <tr class="rw-table--empty"><td colspan="4"><?= lang('RoleWarden.panel.users.empty') ?></td></tr>
      <?php endif ?>
      <?php foreach ($users as $user) : ?>
        <tr>
          <td><a href="<?= esc(site_url('rolewarden/users/' . $user->id)) ?>"><?= esc($user->username ?? '—') ?></a></td>
          <td><?= esc($user->email) ?></td>
          <td>
            <?php if ($user->active) : ?>
              <span class="rw-badge rw-badge--success"><?= lang('RoleWarden.panel.users.active') ?></span>
            <?php else : ?>
              <span class="rw-badge rw-badge--danger"><?= lang('RoleWarden.panel.users.inactive') ?></span>
            <?php endif ?>
          </td>
          <td><a class="rw-btn rw-btn--sm" href="<?= esc(site_url('rolewarden/users/' . $user->id)) ?>"><?= lang('RoleWarden.panel.view') ?></a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?= $pager->links() ?>
</div>
