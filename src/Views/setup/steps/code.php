<?php
/**
 * @var string|null $codeFile
 * @var string|null $error
 */
?>
<div class="rw-auth-row<?= $error !== null ? ' rw-auth-row--invalid' : '' ?>">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <label class="rw-label" for="rw-setup-code"><?= esc($t('code')) ?></label>
    <input class="rw-input rw-mono" id="rw-setup-code" name="code" autocomplete="off" spellcheck="false" required<?= $error !== null ? ' aria-invalid="true" aria-describedby="rw-setup-code-error"' : '' ?>>
    <?php if ($error !== null) : ?><span class="rw-auth-err" id="rw-setup-code-error"><?= esc($error) ?></span><?php endif ?>
    <p class="rw-field-hint"><?= esc($t('codeHint')) ?> <code class="rw-mono"><?= esc($codeFile) ?></code></p>
    <p class="rw-field-hint"><?= esc($t('codeSignedIn')) ?></p>
  </div>
</div>
