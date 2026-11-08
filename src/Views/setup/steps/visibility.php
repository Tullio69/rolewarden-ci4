<?php
$visibility = $posted['visibility'] ?? $choices['visibility'] ?? 'admins';
?>
<div class="rw-auth-row<?= isset($errors['visibility']) ? ' rw-auth-row--invalid' : '' ?>">
  <span class="rw-m-num"><?= $num() ?></span>
  <fieldset class="rw-setup-fieldset"<?= $invalid('visibility') ?>>
    <legend class="rw-label"><?= esc($t('visibilityTitle')) ?></legend>
    <label class="rw-setup-option"><input class="rw-radio" type="radio" name="visibility" value="all"<?= $visibility === 'all' ? ' checked' : '' ?>><span><b><?= esc($t('visibilityAll')) ?></b><span class="rw-field-hint"><?= esc($t('visibilityAllHint')) ?></span></span></label>
    <label class="rw-setup-option"><input class="rw-radio" type="radio" name="visibility" value="admins"<?= $visibility === 'admins' ? ' checked' : '' ?>><span><b><?= esc($t('visibilityAdmins')) ?></b> <span class="rw-note"><?= esc($t('recommendedInUse')) ?></span><span class="rw-field-hint"><?= esc($t('visibilityAdminsHint')) ?></span></span></label>
    <?= $errorFor('visibility') ?>
  </fieldset>
</div>
