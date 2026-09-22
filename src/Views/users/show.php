<div class="rw-header">
  <h1><?= esc($user->username ?? $user->email) ?></h1>
  <div class="rw-inline">
    <?php if (can('users.update')) : ?>
      <a class="rw-btn" href="<?= esc(site_url('rolewarden/users/' . $user->id . '/edit')) ?>"><?= lang('RoleWarden.panel.edit') ?></a>
    <?php endif ?>
    <?php if (can('users.activate')) : ?>
      <?php if ($user->active) : ?>
        <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/deactivate')) ?>">
          <?= csrf_field() ?>
          <button class="rw-btn" type="submit"><?= lang('RoleWarden.panel.users.deactivate') ?></button>
        </form>
      <?php else : ?>
        <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/activate')) ?>">
          <?= csrf_field() ?>
          <button class="rw-btn" type="submit"><?= lang('RoleWarden.panel.users.activate') ?></button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <?php if (can('users.delete')) : ?>
      <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/delete')) ?>" onsubmit="return confirm('<?= esc(lang('RoleWarden.panel.users.confirmDelete'), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="rw-btn rw-btn--danger" type="submit"><?= lang('RoleWarden.panel.delete') ?></button>
      </form>
    <?php endif ?>
  </div>
</div>

<div class="rw-card">
  <p><strong><?= lang('RoleWarden.panel.users.email') ?>:</strong> <?= esc($user->email) ?></p>
  <p>
    <strong><?= lang('RoleWarden.panel.users.status') ?>:</strong>
    <?php if ($user->active) : ?>
      <span class="rw-badge rw-badge--success"><?= lang('RoleWarden.panel.users.active') ?></span>
    <?php else : ?>
      <span class="rw-badge rw-badge--danger"><?= lang('RoleWarden.panel.users.inactive') ?></span>
    <?php endif ?>
  </p>
</div>

<div class="rw-card">
  <h2><?= lang('RoleWarden.panel.users.roles') ?></h2>
  <table class="rw-table">
    <tbody>
      <?php if ($assignedRoles === []) : ?>
        <tr class="rw-table--empty"><td><?= lang('RoleWarden.panel.users.noRoles') ?></td></tr>
      <?php endif ?>
      <?php foreach ($assignedRoles as $role) : ?>
        <tr>
          <td><a href="<?= esc(site_url('rolewarden/roles/' . $role['id'])) ?>"><?= esc($role['name']) ?></a></td>
          <td>
            <?php if (can('roles.assign')) : ?>
              <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/roles/' . $role['id'] . '/revoke')) ?>">
                <?= csrf_field() ?>
                <button class="rw-btn rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.revoke') ?></button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if (can('roles.assign') && $availableRoles !== []) : ?>
    <form class="rw-inline" method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/roles')) ?>">
      <?= csrf_field() ?>
      <select name="role_id">
        <?php foreach ($availableRoles as $role) : ?>
          <option value="<?= (int) $role['id'] ?>"><?= esc($role['name']) ?></option>
        <?php endforeach ?>
      </select>
      <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.users.assignRole') ?></button>
    </form>
  <?php endif ?>
</div>

<div class="rw-card">
  <h2><?= lang('RoleWarden.panel.users.overrides') ?></h2>
  <table class="rw-table">
    <tbody>
      <?php if ($overrides === []) : ?>
        <tr class="rw-table--empty"><td colspan="3"><?= lang('RoleWarden.panel.users.noOverrides') ?></td></tr>
      <?php endif ?>
      <?php foreach ($overrides as $override) : ?>
        <tr>
          <td><?= esc($override['slug']) ?></td>
          <td>
            <?php if ((int) $override['granted'] === 1) : ?>
              <span class="rw-badge rw-badge--success"><?= lang('RoleWarden.panel.users.granted') ?></span>
            <?php else : ?>
              <span class="rw-badge rw-badge--danger"><?= lang('RoleWarden.panel.users.denied') ?></span>
            <?php endif ?>
          </td>
          <td>
            <?php if (can('permissions.override')) : ?>
              <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/permissions/' . $override['id'] . '/clear')) ?>">
                <?= csrf_field() ?>
                <button class="rw-btn rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.clearOverride') ?></button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if (can('permissions.override') && $availablePermissions !== []) : ?>
    <form class="rw-inline" method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/permissions')) ?>">
      <?= csrf_field() ?>
      <select name="permission_id">
        <?php foreach ($availablePermissions as $permission) : ?>
          <option value="<?= (int) $permission['id'] ?>"><?= esc($permission['slug']) ?></option>
        <?php endforeach ?>
      </select>
      <select name="granted">
        <option value="1"><?= lang('RoleWarden.panel.users.granted') ?></option>
        <option value="0"><?= lang('RoleWarden.panel.users.denied') ?></option>
      </select>
      <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.users.saveOverride') ?></button>
    </form>
  <?php endif ?>
</div>
