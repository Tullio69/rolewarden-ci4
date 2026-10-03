<!doctype html>
<?php helper('rolewarden'); ?><html lang="en" <?= rw_html_attributes() ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= lang('Auth.login') ?></title>
  <?= rw_stylesheets() ?>
  <script defer src="<?= esc(site_url('rolewarden/assets/js/vendor/alpine.min.js')) ?>"></script>
</head>
<body>
<div class="rw rw-auth-screen">
  <div class="rw-auth-sheet" x-data="{ show: false }">
    <div class="rw-auth-row rw-auth-head">
      <span class="rw-m-num"></span>
      <div>
        <div class="rw-header"><?= esc(config('App')->baseURL !== '' ? parse_url(config('App')->baseURL, PHP_URL_HOST) : 'RoleWarden') ?></div>
        <h1><?= lang('Auth.login') ?></h1>
        <p><?= lang('RoleWarden.panel.auth.subtitle') ?></p>
      </div>
    </div>

    <?php $error = session('error') ?? (is_array(session('errors')) ? implode(' ', session('errors')) : session('errors')); ?>
    <?php // Shield's wrong-password text differs from the unknown-email one and would reveal registered emails.
    if ($error === lang('Auth.invalidPassword')) {
        $error = lang('Auth.badAttempt');
    } ?>

    <form class="rw-auth-form" action="<?= url_to('login') ?>" method="post" novalidate>
      <?= csrf_field() ?>

      <div class="rw-auth-row<?= $error !== null ? ' rw-auth-row--invalid' : '' ?>">
        <span class="rw-m-num">01</span>
        <div class="rw-field">
          <label class="rw-label" for="email"><?= lang('Auth.email') ?></label>
          <input class="rw-input" id="email" name="email" type="email" inputmode="email" autocomplete="email" value="<?= esc(is_string(old('email')) ? old('email') : '') ?>" required>
        </div>
      </div>

      <div class="rw-auth-row">
        <span class="rw-m-num">02</span>
        <div class="rw-field">
          <div class="rw-auth-labelline"><label class="rw-label" for="password"><?= lang('Auth.password') ?></label><?php if (setting('Auth.allowMagicLinkLogins')) : ?><a href="<?= url_to('magic-link') ?>"><?= lang('Auth.forgotPassword') ?></a><?php endif ?></div>
          <div class="rw-auth-pw">
            <input class="rw-input" type="password" :type="show ? 'text' : 'password'" id="password" name="password" autocomplete="current-password" required>
            <button type="button" class="rw-btn rw-btn--ghost rw-btn--sm" @click="show = !show" :aria-pressed="show" aria-controls="password" x-text="show ? '<?= esc(lang('RoleWarden.panel.auth.hide'), 'js') ?>' : '<?= esc(lang('RoleWarden.panel.auth.show'), 'js') ?>'"></button>
          </div>
        </div>
      </div>

      <?php if (setting('Auth.sessionConfig')['allowRemembering'] ?? false) : ?>
        <div class="rw-auth-row">
          <span class="rw-m-num">03</span>
          <div class="rw-auth-opts">
            <label class="rw-check-row"><input class="rw-check" type="checkbox" name="remember"<?= old('remember') ? ' checked' : '' ?>> <?php $length = (int) setting('Auth.sessionConfig')['rememberLength']; ?><?= $length % 86400 === 0 ? lang('RoleWarden.panel.auth.rememberFor', [intdiv($length, 86400)]) : lang('Auth.rememberMe') ?></label>
          </div>
        </div>
      <?php endif ?>

      <div class="rw-auth-row rw-auth-submit" style="border-bottom:0">
        <span class="rw-m-num"></span>
        <div>
          <button class="rw-btn rw-btn--primary" type="submit"><?= lang('Auth.login') ?></button>
          <?php if ($error !== null) : ?>
            <div class="rw-auth-alert" role="alert"><b>Error:</b> <?= esc($error) ?></div>
          <?php endif ?>
        </div>
      </div>
    </form>

    <div class="rw-auth-row rw-auth-foot">
      <span class="rw-m-num"></span>
      <p class="rw-note" style="margin:0"><?= lang('RoleWarden.panel.auth.trouble') ?></p>
    </div>
  </div>
  <span class="rw-auth-corner rw-note"><?= lang('RoleWarden.panel.auth.credit') ?></span>
</div>
</body>
</html>
