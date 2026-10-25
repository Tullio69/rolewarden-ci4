<!doctype html>
<html lang="<?= esc(service('language')->getLocale(), 'attr') ?>" <?= rw_html_attributes() ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($title) ?> &middot; RoleWarden</title>
  <?= rw_stylesheets() ?>
  <?php // panel.js registers its components on alpine:init, so it must run before Alpine starts.
        // It blocks the first render, so the sidebar highlight starts from the item just left. ?>
  <script defer blocking="render" src="<?= esc(site_url('rolewarden/assets/js/panel.js')) ?>"></script>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/vendor/alpine.min.js')) ?>"></script>
</head>
<body>
<?php $collapsed = RoleWarden\Settings\Sidebar::collapsed(auth()->loggedIn() ? (int) auth()->id() : null); ?>
<div class="rw rw-shell<?= $collapsed ? ' rw-shell--rail' : '' ?>">
  <?= view('RoleWarden\Views\partials\sidebar', ['active' => $active, 'collapsed' => $collapsed]) ?>

  <div>
    <header class="rw-topbar">
      <nav class="rw-crumbs" aria-label="<?= esc(lang('RoleWarden.panel.a11y.breadcrumb'), 'attr') ?>"><?= $crumbs !== '' ? $crumbs : '<span aria-current="page">' . esc($title) . '</span>' ?></nav>
      <?php $me = auth()->user(); ?>
      <?php if ($me !== null) : ?>
        <?php $scheme = RoleWarden\Settings\ColorScheme::forUser((int) $me->id); ?>
        <div class="rw-topbar-end">
        <?= view('RoleWarden\Views\partials\language') ?>
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
        <a class="rw-signout" href="<?= esc(url_to('logout')) ?>"><?= lang('RoleWarden.panel.signOut') ?></a>
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
