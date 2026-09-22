<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?> &middot; RoleWarden</title>
  <link rel="stylesheet" href="<?= esc(site_url('rolewarden/assets/css/panel.css')) ?>">
  <script defer src="<?= esc(site_url('rolewarden/assets/js/vendor/alpine.min.js')) ?>"></script>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/panel.js')) ?>"></script>
</head>
<body>
  <div class="rw-layout">
    <aside class="rw-sidebar">
      <div class="rw-sidebar__brand">RoleWarden</div>
      <ul class="rw-nav">
        <?php if (can('users.view')) : ?>
          <li><a href="<?= esc(site_url('rolewarden/users')) ?>"<?= $active === 'users' ? ' aria-current="page"' : '' ?>><?= lang('RoleWarden.panel.nav.users') ?></a></li>
        <?php endif ?>
        <?php if (can('roles.view')) : ?>
          <li><a href="<?= esc(site_url('rolewarden/roles')) ?>"<?= $active === 'roles' ? ' aria-current="page"' : '' ?>><?= lang('RoleWarden.panel.nav.roles') ?></a></li>
        <?php endif ?>
        <?php if (can('permissions.view')) : ?>
          <li><a href="<?= esc(site_url('rolewarden/permissions')) ?>"<?= $active === 'permissions' ? ' aria-current="page"' : '' ?>><?= lang('RoleWarden.panel.nav.permissions') ?></a></li>
        <?php endif ?>
      </ul>
    </aside>
    <main class="rw-main">
      <?= view('RoleWarden\Views\partials\flash') ?>
      <?= $body ?>
    </main>
  </div>
</body>
</html>
