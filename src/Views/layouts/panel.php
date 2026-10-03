<!doctype html>
<html lang="en" <?= rw_html_attributes() ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?> &middot; RoleWarden</title>
  <?= rw_stylesheets() ?>
  <?php // panel.js registers its components on alpine:init, so it must run before Alpine starts. ?>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/panel.js')) ?>"></script>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/vendor/alpine.min.js')) ?>"></script>
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
      <?php if (can('settings.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/settings')) ?>"<?= $active === 'settings' ? ' aria-current="page"' : '' ?>><span>04</span><?= lang('RoleWarden.panel.nav.settings') ?></a></li>
      <?php endif ?>
      <?php if (can('activity.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/activity')) ?>"<?= $active === 'activity' ? ' aria-current="page"' : '' ?>><span>05</span><?= lang('RoleWarden.panel.nav.activity') ?></a></li>
      <?php endif ?>
      <?php if (can('appearance.update')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/appearance')) ?>"<?= $active === 'appearance' ? ' aria-current="page"' : '' ?>><span>06</span><?= lang('RoleWarden.panel.nav.appearance') ?></a></li>
      <?php endif ?>
    </ul>
  </aside>

  <div>
    <header class="rw-topbar">
      <nav class="rw-crumbs" aria-label="Breadcrumb"><?= $crumbs !== '' ? $crumbs : '<span aria-current="page">' . esc(ucfirst($active)) . '</span>' ?></nav>
      <?php $me = auth()->user(); ?>
      <?php if ($me !== null) : ?>
        <?php $scheme = RoleWarden\Settings\ColorScheme::forUser((int) $me->id); ?>
        <div class="rw-topbar-end">
        <form class="rw-mode" method="post" action="<?= esc(site_url('rolewarden/appearance/mode')) ?>" aria-label="<?= esc(lang('RoleWarden.panel.appearance.modeLabel'), 'attr') ?>">
          <?= csrf_field() ?>
          <?php foreach (RoleWarden\Settings\ColorScheme::CHOICES as $choice) : ?>
            <button type="submit" name="mode" value="<?= $choice ?>" aria-pressed="<?= $scheme === $choice ? 'true' : 'false' ?>"><?= lang('RoleWarden.panel.appearance.mode.' . $choice) ?></button>
          <?php endforeach ?>
        </form>
        <a class="rw-who" href="<?= esc(site_url('rolewarden/profile')) ?>"<?= in_array($active, ['profile', 'sessions'], true) ? ' aria-current="page"' : '' ?>>
          <span><?= esc($me->username ?? $me->email) ?><small><?= esc(($me->getGroups()[0] ?? '') !== '' ? ucfirst($me->getGroups()[0]) : '') ?></small></span>
          <i aria-hidden="true"><?= esc(strtoupper(substr((string) ($me->username ?? $me->email), 0, 2))) ?></i>
        </a>
        </div>
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
