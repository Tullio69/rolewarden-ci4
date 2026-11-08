<?php
/**
 * @var list<array{id: int, username: string, email: string}> $users active users only
 */
$saved = is_array($choices['superAdmin'] ?? null) ? $choices['superAdmin'] : [];
$kind = $posted['kind'] ?? $saved['kind'] ?? ($users === [] ? 'new' : 'existing');
$chosen = $posted['id'] ?? $saved['id'] ?? '';
$chosen = is_scalar($chosen) ? (string) $chosen : '';
$value = static fn (string $key): string => is_string($posted[$key] ?? $saved[$key] ?? null) ? ($posted[$key] ?? $saved[$key]) : '';
$field = static function (string $name, string $label, string $type, string $autocomplete) use ($value, $errorFor, $invalid): string {
    return '<label class="rw-label" for="rw-setup-' . $name . '">' . esc(lang('RoleWarden.install.' . $label)) . '</label>'
        . '<input class="rw-input" type="' . $type . '" id="rw-setup-' . $name . '" name="' . $name . '" autocomplete="' . $autocomplete . '"'
        . ($type !== 'password' ? ' value="' . esc($value($name), 'attr') . '"' : '') . $invalid($name) . '>' . $errorFor($name);
};
?>
<div class="rw-auth-row<?= $errors !== [] ? ' rw-auth-row--invalid' : '' ?>">
  <span class="rw-m-num"><?= $num() ?></span>
  <fieldset class="rw-setup-fieldset"<?= $invalid('kind') ?>>
    <legend class="rw-label"><?= esc($t('superAdminQuestion')) ?></legend>
    <label class="rw-setup-option"><input class="rw-radio" type="radio" name="kind" value="existing"<?= $kind === 'existing' ? ' checked' : '' ?><?= $users === [] ? ' disabled' : '' ?>><span><b><?= esc($t('existing')) ?></b><span class="rw-field-hint"><?= esc($t($users === [] ? 'existingNone' : 'existingHint')) ?></span></span></label>
    <?php if ($users !== []) : ?>
      <div class="rw-setup-sub">
        <label class="rw-label" for="rw-setup-id"><?= esc($t('existingUser')) ?></label>
        <select class="rw-select" id="rw-setup-id" name="id"<?= $invalid('id') ?>>
          <?php foreach ($users as $u) : ?>
            <option value="<?= $u['id'] ?>"<?= $chosen === (string) $u['id'] ? ' selected' : '' ?>><?= esc($u['username'] !== '' ? $u['username'] . ' · ' . $u['email'] : $u['email']) ?></option>
          <?php endforeach ?>
        </select>
        <?= $errorFor('id') ?>
      </div>
    <?php endif ?>
    <label class="rw-setup-option"><input class="rw-radio" type="radio" name="kind" value="new"<?= $kind === 'new' ? ' checked' : '' ?>><span><b><?= esc($t('newUser')) ?></b><span class="rw-field-hint"><?= esc($t('newUserHint')) ?></span></span></label>
    <div class="rw-setup-sub">
      <?= $field('username', 'adminUsername', 'text', 'username') ?>
      <?= $field('email', 'adminEmail', 'email', 'email') ?>
      <?= $field('password', 'adminPassword', 'password', 'new-password') ?>
      <?= $field('password_confirm', 'adminPasswordConfirm', 'password', 'new-password') ?>
    </div>
    <?= $errorFor('kind') ?>
  </fieldset>
</div>
