<?php
/**
 * After a completed apply: what was done, the host files changed (from
 * SetupState::edits()) and what is left to the owner of the application.
 *
 * @var list<array{path: string, before: string, after: string, at: string, checked: bool}> $edits
 * @var bool                                                                               $providerChanged
 */
?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <span class="rw-label"><?= esc($t('doneSteps')) ?></span>
    <ul>
      <?php foreach (RoleWarden\Install\Setup::STEPS as $s) : ?>
        <li><?= esc($t('step.' . $s)) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
</div>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <span class="rw-label"><?= esc($t('doneFiles')) ?></span>
    <?php if ($edits === []) : ?>
      <p class="rw-field-hint"><?= esc($t('doneNoFiles')) ?></p>
    <?php else : ?>
      <ul>
        <?php foreach ($edits as $e) : ?>
          <?php $file = 'app/Config/' . basename($e['path']) ?>
          <li><code><?= esc($e['checked'] ? $file : $t('doneUnchecked', [$file])) ?></code></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </div>
</div>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <p><?= esc($t('doneLink')) ?></p>
    <?php if ($providerChanged) : ?><p class="rw-field-hint" role="status"><?= esc($t('doneSignInAgain')) ?></p><?php endif ?>
  </div>
</div>
<div class="rw-auth-row rw-auth-submit">
  <span class="rw-m-num"></span>
  <div><a class="rw-btn rw-btn--primary" href="<?= esc(site_url('login'), 'attr') ?>"><?= esc(lang('RoleWarden.install.signIn')) ?></a></div>
</div>
