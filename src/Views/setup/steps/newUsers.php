<?php
/**
 * @var list<string> $roles   the roles the choices will leave
 * @var string       $current prefilled from the host's AuthGroups::$defaultGroup
 */
$current = is_string($posted['defaultRole'] ?? null) ? $posted['defaultRole'] : $current;
?>
<div class="rw-auth-row<?= isset($errors['defaultRole']) ? ' rw-auth-row--invalid' : '' ?>">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <label class="rw-label" for="rw-setup-defaultRole"><?= esc($t('defaultRole')) ?></label>
    <select class="rw-select" id="rw-setup-defaultRole" name="defaultRole"<?= $invalid('defaultRole') ?>>
      <option value=""<?= $current === '' ? ' selected' : '' ?>><?= esc($t('defaultRoleNone')) ?></option>
      <?php foreach ($roles as $role) : ?>
        <option value="<?= esc($role, 'attr') ?>"<?= $current === $role ? ' selected' : '' ?>><?= esc($role) ?></option>
      <?php endforeach ?>
    </select>
    <?= $errorFor('defaultRole') ?>
    <p class="rw-field-hint"><?= esc($t('defaultRoleHint')) ?></p>
  </div>
</div>
