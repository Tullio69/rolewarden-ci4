<?php
/**
 * Body of the demo's dashboard block (RoleWardenDemo\Dashboard\DocumentsBlock):
 * RoleWarden draws the section and its title around it.
 *
 * @var array<string, int>   $statuses documents by status, empty before demo:install
 * @var int                  $written  documents written in the period
 * @var array<string, mixed> $period   see RoleWarden\Dashboard\Dashboard::period()
 */
?>
<?php if ($statuses === []) : ?>
  <p class="rw-note"><?= esc(lang('Demo.dashboard.notInstalled')) ?></p>
<?php else : ?>
  <dl class="rw-facts">
    <?php foreach ($statuses as $status => $n) : ?>
      <div><dt><?= esc(lang('Demo.statuses.' . $status)) ?></dt><dd class="rw-fig"><?= esc(number_format($n)) ?></dd></div>
    <?php endforeach ?>
  </dl>
  <p class="rw-note"><?= esc(lang('Demo.dashboard.written', [$written, $period['days']])) ?> <a href="<?= esc(site_url('demo')) ?>"><?= esc(lang('Demo.dashboard.open')) ?></a></p>
<?php endif ?>
