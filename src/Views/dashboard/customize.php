<?php
/**
 * The dashboard's customize panel (SPEC, decisions of 2026-10-25): which of
 * the blocks the user can see are shown, their order, and the period the page
 * opens with. A native <details>, so the form works without JavaScript; the
 * up and down buttons need Alpine and move the rows themselves, so the order
 * posted is the order on screen. The security notice can be hidden but
 * always comes first. "Save as default for everyone" needs settings.update.
 *
 * @var list<array{key: string, title: string, shown: bool, pinned: bool}> $blocks
 * @var int  $period
 * @var bool $global
 */
?>
<details class="rw-dash-customize" x-data="rwDashboard">
  <summary class="rw-btn rw-btn--secondary"><?= lang('RoleWarden.panel.dashboard.customize.open') ?></summary>
  <form class="rw-dash-panel" method="post" action="<?= esc(site_url('rolewarden/dashboard/layout')) ?>" aria-labelledby="rw-dash-customize-title">
    <?= csrf_field() ?>
    <h2 id="rw-dash-customize-title"><?= lang('RoleWarden.panel.dashboard.customize.title') ?></h2>
    <p class="rw-note"><?= lang('RoleWarden.panel.dashboard.customize.intro') ?></p>
    <ol class="rw-dash-order">
      <?php foreach ($blocks as $row) : ?>
        <li<?= $row['pinned'] ? ' data-pinned' : '' ?>>
          <input type="hidden" name="order[]" value="<?= esc($row['key'], 'attr') ?>">
          <label class="rw-dash-show">
            <input class="rw-check" type="checkbox" name="shown[]" value="<?= esc($row['key'], 'attr') ?>"<?= $row['shown'] ? ' checked' : '' ?>>
            <span><?= esc($row['title']) ?></span>
          </label>
          <?php if ($row['pinned']) : ?>
            <span class="rw-note"><?= lang('RoleWarden.panel.dashboard.customize.pinned') ?></span>
          <?php else : ?>
            <span class="rw-dash-move" x-cloak>
              <button type="button" class="rw-btn rw-btn--ghost" @click="move($el.closest('li'), -1, $el)" :disabled="! movable($el.closest('li'), -1)" aria-label="<?= esc(lang('RoleWarden.panel.dashboard.customize.up', [$row['title']]), 'attr') ?>">&uarr;</button>
              <button type="button" class="rw-btn rw-btn--ghost" @click="move($el.closest('li'), 1, $el)" :disabled="! movable($el.closest('li'), 1)" aria-label="<?= esc(lang('RoleWarden.panel.dashboard.customize.down', [$row['title']]), 'attr') ?>">&darr;</button>
            </span>
          <?php endif ?>
        </li>
      <?php endforeach ?>
    </ol>
    <label class="rw-field">
      <span><?= lang('RoleWarden.panel.dashboard.customize.period') ?></span>
      <select class="rw-select" name="period">
        <?php foreach (RoleWarden\Dashboard\Dashboard::PERIODS as $days) : ?>
          <option value="<?= $days ?>"<?= $days === $period ? ' selected' : '' ?>><?= esc(lang('RoleWarden.panel.dashboard.days', [$days])) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <div class="rw-dash-actions">
      <button type="submit" class="rw-btn rw-btn--primary" name="scope" value="me"><?= lang('RoleWarden.panel.dashboard.customize.saveMine') ?></button>
      <?php if ($global) : ?>
        <button type="submit" class="rw-btn rw-btn--secondary" name="scope" value="global"><?= lang('RoleWarden.panel.dashboard.customize.saveGlobal') ?></button>
      <?php endif ?>
      <button type="submit" class="rw-btn rw-btn--ghost" name="scope" value="reset"><?= lang('RoleWarden.panel.dashboard.customize.restore') ?></button>
    </div>
  </form>
</details>
