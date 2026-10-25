<?php
/**
 * Shell of a dashboard block of the application (RoleWarden\Dashboard\Block):
 * the module draws the section, its letter and the title, the block's own
 * view draws the body with what data() returned, plus $period and $userId.
 *
 * @var RoleWarden\Dashboard\Block $block
 * @var array<string, mixed>       $data
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
    <?= view($block->view(), $data) ?>
  </div>
</section>
