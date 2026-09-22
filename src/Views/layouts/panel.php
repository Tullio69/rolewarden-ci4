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
<div class="rw rw-shell">
  <aside class="rw-sidebar" aria-label="Main">
    <div class="rw-brand"><b>RoleWarden</b></div>
    <ul class="rw-nav">
      <?php if (can('users.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/users')) ?>"<?= $active === 'users' ? ' aria-current="page"' : '' ?>><span>01</span><?= lang('RoleWarden.panel.nav.users') ?></a></li>
      <?php endif ?>
      <?php if (can('roles.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/roles')) ?>"<?= $active === 'roles' ? ' aria-current="page"' : '' ?>><span>02</span><?= lang('RoleWarden.panel.nav.roles') ?></a></li>
      <?php endif ?>
      <?php if (can('permissions.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/permissions')) ?>"<?= $active === 'permissions' ? ' aria-current="page"' : '' ?>><span>03</span><?= lang('RoleWarden.panel.nav.permissions') ?></a></li>
      <?php endif ?>
    </ul>
  </aside>

  <div>
    <header class="rw-topbar">
      <nav class="rw-crumbs" aria-label="Breadcrumb"><?= $crumbs !== '' ? $crumbs : '<span aria-current="page">' . esc(ucfirst($active)) . '</span>' ?></nav>
      <?php $me = auth()->user(); ?>
      <?php if ($me !== null) : ?>
        <span class="rw-who">
          <span><?= esc($me->username ?? $me->email) ?><small><?= esc(($me->getGroups()[0] ?? '') !== '' ? ucfirst($me->getGroups()[0]) : '') ?></small></span>
          <i aria-hidden="true"><?= esc(strtoupper(substr((string) ($me->username ?? $me->email), 0, 2))) ?></i>
        </span>
      <?php endif ?>
    </header>

    <main class="rw-content">
      <?= view('RoleWarden\Views\partials\flash') ?>
      <?= $body ?>
    </main>
  </div>
</div>
</body>
</html>
