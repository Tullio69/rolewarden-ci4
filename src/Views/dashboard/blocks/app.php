<?php
/**
 * Shell of a dashboard block of the application (RoleWarden\Dashboard\Block):
 * the module draws the section, its letter and the title around the body the
 * block's own view rendered (DashboardController::body()), or says the block
 * could not be loaded when it threw.
 *
 * @var RoleWarden\Dashboard\Block $block
 * @var string|null                $body
 * @var string                     $letter
 */

$id = 'rw-dash-app-' . $block->key();
?>
<section class="rw-dash-full" aria-labelledby="<?= esc($id, 'attr') ?>">
  <div class="rw-section-title">
    <span class="rw-section-n"><?= $letter ?></span>
    <h2 id="<?= esc($id, 'attr') ?>"><?= esc($block->title()) ?></h2>
  </div>
  <div class="rw-dash-body">
    <?php if ($body === null) : ?>
      <p class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.customize.failed')) ?></p>
    <?php else : ?>
      <?= $body ?>
    <?php endif ?>
  </div>
</section>
