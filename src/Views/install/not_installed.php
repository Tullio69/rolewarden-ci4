<?php
/**
 * Any panel page while the module's tables do not exist yet (filter "rw-ready").
 */
$t = static fn (string $key): string => lang('RoleWarden.install.notInstalled.' . $key);
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale(), 'attr') ?>" data-rw-theme="console">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= esc($t('title')) ?> &middot; RoleWarden</title>
  <link rel="stylesheet" href="<?= esc(site_url('rolewarden/assets/css/panel.css'), 'attr') ?>">
</head>
<body>
<div class="rw rw-auth-screen">
  <div class="rw-auth-sheet">
    <div class="rw-auth-row rw-auth-head">
      <span class="rw-m-num"></span>
      <div>
        <h1><?= esc($t('title')) ?></h1>
        <p><?= esc($t('body')) ?></p>
      </div>
    </div>
    <div class="rw-auth-row rw-auth-submit">
      <span class="rw-m-num"></span>
      <div>
        <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('rolewarden/install'), 'attr') ?>"><?= esc($t('link')) ?></a>
      </div>
    </div>
  </div>
  <span class="rw-auth-corner rw-note">RoleWarden</span>
</div>
</body>
</html>
