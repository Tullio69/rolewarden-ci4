<?php $isEdit = $user !== null; ?>
<div class="rw-header">
  <h1><?= $isEdit ? lang('RoleWarden.panel.users.editTitle') : lang('RoleWarden.panel.users.createTitle') ?></h1>
</div>

<?php if ($errors !== []) : ?>
  <div class="rw-flash rw-flash--error">
    <ul>
      <?php foreach ($errors as $error) : ?>
        <li><?= esc($error) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>

<form class="rw-card" method="post" action="<?= esc($isEdit ? site_url('rolewarden/users/' . $user->id) : site_url('rolewarden/users')) ?>">
  <?= csrf_field() ?>

  <div class="rw-field">
    <label for="username"><?= lang('RoleWarden.panel.users.username') ?></label>
    <input type="text" id="username" name="username" value="<?= esc(old('username', $isEdit ? (string) $user->username : '')) ?>" required minlength="3">
  </div>

  <div class="rw-field">
    <label for="email"><?= lang('RoleWarden.panel.users.email') ?></label>
    <input type="email" id="email" name="email" value="<?= esc(old('email', $isEdit ? (string) $user->email : '')) ?>" required>
  </div>

  <div class="rw-field">
    <label for="password"><?= $isEdit ? lang('RoleWarden.panel.users.newPassword') : lang('RoleWarden.panel.users.password') ?></label>
    <input type="password" id="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
    <?php if ($isEdit) : ?>
      <p class="rw-field__hint"><?= lang('RoleWarden.panel.users.passwordHint') ?></p>
    <?php endif ?>
  </div>

  <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.save') ?></button>
  <a class="rw-btn" href="<?= esc(site_url('rolewarden/users')) ?>"><?= lang('RoleWarden.panel.cancel') ?></a>
</form>
