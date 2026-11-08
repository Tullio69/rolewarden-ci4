<?php
/**
 * @var list<array{key: string, ok: bool, detail: string, blocking: bool}> $checks
 */
?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <ul class="rw-install-checks" aria-label="<?= esc(lang('RoleWarden.install.requirements'), 'attr') ?>">
      <?php foreach ($checks as $check) : ?>
        <li<?= $check['ok'] ? '' : ' class="is-missing"' ?>>
          <span><?= esc(lang('RoleWarden.install.check.' . $check['key'], [$check['detail'], RoleWarden\Install\Installer::MIN_PHP])) ?></span>
          <b><?= esc(lang('RoleWarden.install.' . ($check['ok'] ? 'ok' : 'missing'))) ?></b>
        </li>
      <?php endforeach ?>
    </ul>
    <?php if (! RoleWarden\Install\Installer::requirementsMet($checks)) : ?><p class="rw-field-hint"><?= esc($t('requirementsMissing')) ?></p><?php endif ?>
  </div>
</div>
