<?php
/**
 * The browser installer, one step per page, on the sign-in screen's sheet.
 * Links and form actions are relative: until .env is written, the
 * application's baseURL may not point at this server yet.
 *
 * @var string                                                                    $step
 * @var list<array{key: string, ok: bool, detail: string, blocking: bool}>|null   $checks
 * @var array<string, string>                                                     $values
 * @var array<string, string>                                                     $errors field => message
 * @var string|null                                                               $error
 * @var string|null                                                               $codeFile
 * @var string|null                                                               $env
 * @var string|null                                                               $email
 */
$t = static fn (string $key, array $args = []): string => lang('RoleWarden.install.' . $key, $args);
$n = 0;
$num = static function () use (&$n): string {
    return str_pad((string) ++$n, 2, '0', STR_PAD_LEFT);
};
$field = static function (string $name, string $label, string $type, string $autocomplete, bool $required = true) use ($values, $errors, $t, $num): string {
    $error = $errors[$name] ?? null;
    $id = 'rw-install-' . $name;

    return '<div class="rw-auth-row' . ($error !== null ? ' rw-auth-row--invalid' : '') . '"><span class="rw-m-num">' . $num() . '</span><div class="rw-field">'
        . '<label class="rw-label" for="' . $id . '">' . esc($t($label)) . '</label>'
        . '<input class="rw-input" type="' . $type . '" id="' . $id . '" name="' . $name . '" autocomplete="' . $autocomplete . '"'
        . ($type !== 'password' ? ' value="' . esc($values[$name] ?? '', 'attr') . '"' : '')
        . ($required ? ' required' : '')
        . ($error !== null ? ' aria-invalid="true" aria-describedby="' . $id . '-error"' : '') . '>'
        . ($error !== null ? '<span class="rw-auth-err" id="' . $id . '-error">' . esc($error) . '</span>' : '')
        . '</div></div>';
};
$steps = ['requirements' => 1, 'code' => 1, 'database' => 2, 'envManual' => 2, 'envSaved' => 2, 'migrate' => 3, 'admin' => 4, 'done' => 4, 'installed' => 4];
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale(), 'attr') ?>" data-rw-theme="console">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= esc($t('title')) ?> &middot; RoleWarden</title>
  <link rel="stylesheet" href="assets/css/panel.css">
</head>
<body>
<div class="rw rw-auth-screen">
  <div class="rw-auth-sheet">
    <div class="rw-auth-row rw-auth-head">
      <span class="rw-m-num"></span>
      <div>
        <div class="rw-header"><?= esc($t('overline', [$steps[$step], 4])) ?></div>
        <h1><?= esc($t($step . 'Title')) ?></h1>
        <p><?= esc($t($step . 'Intro')) ?></p>
      </div>
    </div>

    <?php if ($step === 'requirements' || $step === 'code') : ?>
      <div class="rw-auth-row rw-install-block">
        <span class="rw-m-num"><?= $num() ?></span>
        <ul class="rw-install-checks" aria-label="<?= esc($t('requirements'), 'attr') ?>">
          <?php foreach ($checks as $check) : ?>
            <li<?= $check['ok'] ? '' : ' class="is-missing"' ?>>
              <span><?= esc($t('check.' . $check['key'], [$check['detail'], RoleWarden\Install\Installer::MIN_PHP])) ?></span>
              <b><?= esc($t($check['ok'] ? 'ok' : ($check['blocking'] ? 'missing' : 'manual'))) ?></b>
            </li>
          <?php endforeach ?>
        </ul>
      </div>
    <?php endif ?>

    <?php if ($step === 'code') : ?>
      <form class="rw-auth-form" method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="code">
        <div class="rw-auth-row<?= $error !== null ? ' rw-auth-row--invalid' : '' ?>">
          <span class="rw-m-num"><?= $num() ?></span>
          <div class="rw-field">
            <label class="rw-label" for="rw-install-code"><?= esc($t('code')) ?></label>
            <input class="rw-input rw-mono" id="rw-install-code" name="code" autocomplete="off" spellcheck="false" required<?= $error !== null ? ' aria-invalid="true" aria-describedby="rw-install-code-error"' : '' ?>>
            <?php if ($error !== null) : ?><span class="rw-auth-err" id="rw-install-code-error"><?= esc($error) ?></span><?php endif ?>
            <p class="rw-field-hint"><?= esc($t('codeHint')) ?> <code class="rw-mono"><?= esc($codeFile) ?></code></p>
          </div>
        </div>
        <div class="rw-auth-row rw-auth-submit">
          <span class="rw-m-num"></span>
          <div><button class="rw-btn rw-btn--primary" type="submit"><?= esc($t('continue')) ?></button></div>
        </div>
      </form>

    <?php elseif ($step === 'database') : ?>
      <form class="rw-auth-form" method="post" action="" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="database">
        <?= $field('baseURL', 'baseURL', 'url', 'url') ?>
        <?= $field('hostname', 'hostname', 'text', 'off') ?>
        <?= $field('port', 'port', 'text', 'off') ?>
        <?= $field('database', 'database', 'text', 'off') ?>
        <?= $field('username', 'dbUsername', 'text', 'off') ?>
        <?= $field('password', 'dbPassword', 'password', 'off', false) ?>
        <div class="rw-auth-row rw-auth-submit">
          <span class="rw-m-num"></span>
          <div>
            <button class="rw-btn rw-btn--primary" type="submit"><?= esc($t('connect')) ?></button>
            <?php if ($error !== null) : ?><div class="rw-auth-alert" role="alert"><b><?= lang('RoleWarden.panel.toast.error') ?></b> <?= esc($error) ?></div><?php endif ?>
          </div>
        </div>
      </form>

    <?php elseif ($step === 'envManual') : ?>
      <div class="rw-auth-row rw-install-block">
        <span class="rw-m-num"><?= $num() ?></span>
        <div class="rw-field">
          <label class="rw-label" for="rw-install-env"><?= esc($t('envFile')) ?></label>
          <textarea class="rw-input rw-textarea rw-install-env" id="rw-install-env" rows="14" readonly><?= esc($env) ?></textarea>
        </div>
      </div>
      <div class="rw-auth-row rw-auth-submit">
        <span class="rw-m-num"></span>
        <div><a class="rw-btn rw-btn--primary" href=""><?= esc($t('continue')) ?></a></div>
      </div>

    <?php elseif ($step === 'envSaved') : ?>
      <div class="rw-auth-row rw-auth-submit">
        <span class="rw-m-num"></span>
        <div><a class="rw-btn rw-btn--primary" href=""><?= esc($t('continue')) ?></a></div>
      </div>

    <?php elseif ($step === 'migrate') : ?>
      <form class="rw-auth-form" method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="migrate">
        <div class="rw-auth-row rw-auth-submit">
          <span class="rw-m-num"></span>
          <div>
            <button class="rw-btn rw-btn--primary" type="submit"><?= esc($t('migrate')) ?></button>
            <?php if ($error !== null) : ?><div class="rw-auth-alert" role="alert"><b><?= lang('RoleWarden.panel.toast.error') ?></b> <?= esc($error) ?></div><?php endif ?>
          </div>
        </div>
      </form>

    <?php elseif ($step === 'admin') : ?>
      <form class="rw-auth-form" method="post" action="" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="admin">
        <?= $field('username', 'adminUsername', 'text', 'username') ?>
        <?= $field('email', 'adminEmail', 'email', 'email') ?>
        <?= $field('password', 'adminPassword', 'password', 'new-password') ?>
        <?= $field('password_confirm', 'adminPasswordConfirm', 'password', 'new-password') ?>
        <div class="rw-auth-row rw-auth-submit">
          <span class="rw-m-num"></span>
          <div>
            <button class="rw-btn rw-btn--primary" type="submit"><?= esc($t('createAdmin')) ?></button>
            <?php if ($error !== null) : ?><div class="rw-auth-alert" role="alert"><b><?= lang('RoleWarden.panel.toast.error') ?></b> <?= esc($error) ?></div><?php endif ?>
          </div>
        </div>
      </form>

    <?php elseif ($step === 'done') : ?>
      <div class="rw-auth-row rw-auth-submit">
        <span class="rw-m-num"></span>
        <div>
          <p><?= esc($t('doneAs', [$email])) ?></p>
          <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('login'), 'attr') ?>"><?= esc($t('signIn')) ?></a>
        </div>
      </div>
    <?php endif ?>
  </div>
  <span class="rw-auth-corner rw-note">RoleWarden</span>
</div>
</body>
</html>
