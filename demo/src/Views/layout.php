<?php
/**
 * The demo's page, on the panel's stylesheet and components.
 *
 * @var string                                                $body
 * @var list<array{id: int, username: string, role: string}>  $showcase accounts for the showcase bar; empty when it is off
 */
$me = auth()->user();
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale(), 'attr') ?>" <?= rw_html_attributes() ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= lang('Demo.documents') ?> &middot; RoleWarden</title>
  <?= rw_stylesheets() ?>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/panel.js')) ?>"></script>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/vendor/alpine.min.js')) ?>"></script>
</head>
<body>
<div class="rw rw-shell">
  <aside class="rw-sidebar" aria-label="<?= esc(lang('RoleWarden.panel.a11y.main'), 'attr') ?>">
    <div class="rw-brand"><b><?= lang('Demo.brand') ?></b></div>
    <ul class="rw-nav">
      <li><a href="<?= esc(site_url('demo')) ?>" aria-current="page"><span>01</span><?= lang('Demo.documents') ?></a></li>
      <?php if (can('users.view')) : ?>
        <li><a href="<?= esc(site_url('rolewarden/users')) ?>"><span>02</span><?= lang('Demo.panel') ?></a></li>
      <?php endif ?>
    </ul>
  </aside>

  <div>
    <header class="rw-topbar">
      <nav class="rw-crumbs" aria-label="<?= esc(lang('RoleWarden.panel.a11y.breadcrumb'), 'attr') ?>"><span aria-current="page"><?= lang('Demo.documents') ?></span></nav>
      <div class="rw-topbar-end">
        <?= view('RoleWarden\Views\partials\language') ?>
        <?php if ($showcase !== []) : ?>
          <form class="rw-mode" method="post" aria-label="<?= esc(lang('Demo.showcase'), 'attr') ?>" action="">
            <?= csrf_field() ?>
            <?php foreach ($showcase as $account) : ?>
              <button type="submit" formaction="<?= esc(site_url('demo/showcase/' . $account['id']), 'attr') ?>" aria-pressed="<?= (int) $me->id === $account['id'] ? 'true' : 'false' ?>"><?= esc($account['role'] !== '' ? $account['role'] : $account['username']) ?></button>
            <?php endforeach ?>
          </form>
        <?php endif ?>
        <a class="rw-who" href="<?= esc(site_url('rolewarden/profile')) ?>">
          <span><?= esc($me->username ?? $me->email) ?></span>
          <i aria-hidden="true"><?= esc(strtoupper(substr((string) ($me->username ?? $me->email), 0, 2))) ?></i>
        </a>
      </div>
    </header>

    <main class="rw-content">
      <?= view('RoleWarden\Views\partials\flash') ?>
      <?= $body ?>
    </main>
  </div>
</div>
</body>
</html>
