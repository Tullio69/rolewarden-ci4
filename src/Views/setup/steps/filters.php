<?php
/**
 * @var array{filter: string, except: list<string>}|null      $filter
 * @var list<string>                                           $excepts
 * @var list<array{file: string, diff: string, manual: bool}> $files   Filters.php only
 */
$f = $files[0];
?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <?php if ($filter === null) : ?>
      <p><?= esc($t('filtersNone')) ?></p>
    <?php else : ?>
      <p><?= esc($t('filtersFound', [$filter['filter'], $filter['except'] === [] ? $t('filtersNoExcept') : implode(', ', $filter['except'])])) ?></p>
      <?php if ($f['manual']) : ?>
        <p class="rw-field-hint"><?= esc($t('filtersManual')) ?></p>
        <pre class="rw-setup-diff" tabindex="0"><?= esc(implode("\n", $excepts)) ?></pre>
      <?php elseif ($f['diff'] === '') : ?>
        <p class="rw-field-hint"><?= esc($t('filtersAlready')) ?></p>
      <?php else : ?>
        <p class="rw-field-hint"><?= esc($t('filtersChange')) ?></p>
        <?= $preview($f) ?>
      <?php endif ?>
    <?php endif ?>
  </div>
</div>
