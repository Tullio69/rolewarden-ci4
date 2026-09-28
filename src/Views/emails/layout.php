<?php
/**
 * Shared frame of the security emails. Plain markup and no colours: mail
 * clients ignore the panel's CSS variables. Override any email by copying it
 * to app/Views/overrides/RoleWarden/Views/emails/.
 *
 * @var string       $title
 * @var list<string> $lines already escaped HTML
 * @var string|null  $action
 * @var string|null  $actionUrl
 * @var string       $app
 */
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><title><?= esc($title) ?></title></head>
<body style="margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:22px">
  <div style="max-width:520px">
    <p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase"><?= esc($app) ?></p>
    <h1 style="margin:0 0 16px;font-size:20px;line-height:28px;font-weight:600"><?= esc($title) ?></h1>
    <?php foreach ($lines as $line) : ?>
      <p style="margin:0 0 12px"><?= $line ?></p>
    <?php endforeach ?>
    <?php if (($action ?? null) !== null) : ?>
      <p style="margin:20px 0"><a href="<?= esc($actionUrl, 'attr') ?>"><?= esc($action) ?></a></p>
    <?php endif ?>
    <p style="margin:24px 0 0;font-size:12px"><?= lang('RoleWarden.mail.footer') ?></p>
  </div>
</body>
</html>
