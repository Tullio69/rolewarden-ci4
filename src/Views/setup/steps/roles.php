<?php
/**
 * @var list<array{slug: string, title: string, users: int, permissions: int}> $groups
 * @var string                                                                  $suggested adapt|import
 */
$mode = $posted['mode'] ?? $choices['mode'] ?? $suggested;
$panelRole = $posted['panelRole'] ?? $choices['panelRole'] ?? '';
$option = static function (string $value, string $label, string $hint) use ($mode, $suggested, $t): string {
    return '<label class="rw-setup-option"><input class="rw-radio" type="radio" name="mode" value="' . $value . '"' . ($mode === $value ? ' checked' : '') . '>'
        . '<span><b>' . esc($t($label)) . '</b>' . ($suggested === $value ? ' <span class="rw-note">' . esc($t('recommended')) . '</span>' : '')
        . '<span class="rw-field-hint">' . esc($t($hint)) . '</span></span></label>';
};
?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <span class="rw-label"><?= esc($t('hostGroups')) ?></span>
    <?php if ($groups === []) : ?>
      <p class="rw-field-hint"><?= esc($t('hostGroupsNone')) ?></p>
    <?php else : ?>
      <ul class="rw-install-checks">
        <?php foreach ($groups as $g) : ?>
          <li><span class="rw-mono"><?= esc($g['slug']) ?></span><b><?= esc($t('groupCounts', [$g['users'], $g['permissions']])) ?></b></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </div>
</div>
<div class="rw-auth-row<?= isset($errors['mode']) || isset($errors['panelRole']) ? ' rw-auth-row--invalid' : '' ?>">
  <span class="rw-m-num"><?= $num() ?></span>
  <fieldset class="rw-setup-fieldset"<?= $invalid('mode') ?>>
    <legend class="rw-label"><?= esc($t('rolesQuestion')) ?></legend>
    <?= $option('adapt', 'modeAdapt', 'modeAdaptHint') ?>
    <div class="rw-setup-sub">
      <label class="rw-label" for="rw-setup-panelRole"><?= esc($t('panelRole')) ?></label>
      <select class="rw-select" id="rw-setup-panelRole" name="panelRole"<?= $invalid('panelRole') ?>>
        <option value=""><?= esc($t('panelRoleNone')) ?></option>
        <?php foreach ($groups as $g) : ?>
          <option value="<?= esc($g['slug'], 'attr') ?>"<?= $panelRole === $g['slug'] ? ' selected' : '' ?>><?= esc($g['title']) ?> (<?= esc($g['slug']) ?>)</option>
        <?php endforeach ?>
      </select>
      <?= $errorFor('panelRole') ?>
      <span class="rw-field-hint"><?= esc($t('panelRoleHint')) ?></span>
    </div>
    <?= $option('import', 'modeImport', 'modeImportHint') ?>
    <?= $errorFor('mode') ?>
  </fieldset>
</div>
