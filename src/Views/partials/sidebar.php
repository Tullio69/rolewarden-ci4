<?php
/**
 * The panel's sidebar (docs/design-system/components/Sidebar), included by the
 * layout. Override this file to change the navigation; a layout overridden
 * before 1.1 keeps its own sidebar and still works.
 *
 * @var string $active    key of the current section
 * @var bool   $collapsed the signed-in user chose the rail of icons
 */

use RoleWarden\Settings\Sidebar;

$userId = auth()->loggedIn() ? (int) auth()->id() : null;
$groups = $userId !== null ? Sidebar::groups($userId) : [];
$today = $userId !== null && ! $collapsed && Sidebar::showsToday() && can('activity.view') ? Sidebar::today() : null;
$icon = Sidebar::icon(...);
$searchLabel = lang('RoleWarden.panel.search.open');
?>
<aside class="rw-sidebar rw-side" data-sidebar="<?= esc(Sidebar::style(), 'attr') ?>" aria-label="<?= esc(lang('RoleWarden.panel.a11y.main'), 'attr') ?>"<?= $userId !== null ? ' x-data="rwSearch(' . esc(json_encode(['url' => site_url('rolewarden/search')]), 'attr') . ')" @keydown.window="hotkey($event)"' : '' ?>>
  <div class="rw-brand"><b>RoleWarden</b></div>

  <?php if ($userId !== null) : ?>
    <?php // Command search (CommandSearch): without JavaScript the link opens the search page. ?>
    <div class="rw-side-group">
      <a class="rw-side-search" href="<?= esc(site_url('rolewarden/search')) ?>" @click.prevent="open()"<?= $collapsed ? ' title="' . esc($searchLabel, 'attr') . '"' : '' ?>>
        <?= $icon('search') ?>
        <span class="rw-side-text"><?= esc($searchLabel) ?></span>
        <kbd x-text="mac ? '⌘ K' : 'Ctrl K'">Ctrl K</kbd>
      </a>
    </div>
    <dialog class="rw-cmd" x-ref="dialog" aria-label="<?= esc($searchLabel, 'attr') ?>" @click="$event.target === $el && $el.close()">
      <div class="rw-cmd-field">
        <?= $icon('search') ?>
        <label class="rw-visually-hidden" for="rw-cmd-q"><?= lang('RoleWarden.panel.search.field') ?></label>
        <input id="rw-cmd-q" x-ref="q" type="text" role="combobox" aria-expanded="true" aria-controls="rw-cmd-results" aria-autocomplete="list" :aria-activedescendant="active" autocomplete="off" spellcheck="false" @input.debounce.150ms="load()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()">
        <kbd><?= lang('RoleWarden.panel.search.esc') ?></kbd>
      </div>
      <div class="rw-cmd-results" id="rw-cmd-results" role="listbox" aria-label="<?= esc(lang('RoleWarden.panel.search.results'), 'attr') ?>" x-ref="list"></div>
      <div class="rw-cmd-foot"><span><?= lang('RoleWarden.panel.search.move') ?></span><span><?= lang('RoleWarden.panel.search.enter') ?></span><span><?= lang('RoleWarden.panel.search.scope') ?></span></div>
    </dialog>
  <?php endif ?>

  <?php foreach ($groups as $group) : ?>
    <div class="rw-side-group">
      <span class="rw-side-label"><?= lang('RoleWarden.panel.nav.groups.' . $group['key']) ?></span>
      <ul class="rw-side-nav">
        <?php foreach ($group['items'] as $item) : ?>
          <?php $label = lang('RoleWarden.panel.nav.' . $item['key']); ?>
          <li>
            <a href="<?= esc($item['url']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?><?= $collapsed ? ' title="' . esc($label, 'attr') . '"' : '' ?>>
              <?php // The active highlight is its own element, so it can travel between pages. ?>
              <?= $active === $item['key'] ? '<span class="rw-side-pill" aria-hidden="true"></span>' : '' ?>
              <?= $icon($item['icon']) ?>
              <span class="rw-side-text"><?= esc($label) ?></span>
              <?php if ($item['count'] !== null) : ?>
                <span class="rw-side-count<?= $item['hot'] ? ' rw-side-count--hot' : '' ?>"><?= esc($item['count']) ?></span>
              <?php endif ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  <?php endforeach ?>

  <div class="rw-side-foot">
    <?php if ($today !== null) : ?>
      <?php
      $peak = max($today['hours']);
      $peakHour = (int) array_search($peak, $today['hours'], true);
      $points = [];
      foreach ($today['hours'] as $h => $n) {
          $points[] = round($h * 176 / 23, 1) . ',' . round(28 - ($peak > 0 ? $n / $peak * 26 : 0), 1);
      }
      $chartLabel = $peak > 0 ? lang('RoleWarden.panel.nav.todayHours', [str_pad((string) $peakHour, 2, '0', STR_PAD_LEFT), $peak]) : lang('RoleWarden.panel.nav.todayNone');
      ?>
      <div class="rw-today">
        <span class="rw-side-label"><?= lang('RoleWarden.panel.nav.today') ?></span>
        <p><b><?= $today['ok'] ?></b> <small><?= esc(lang('RoleWarden.panel.nav.todaySignIns', [$today['ok'], $today['failed']])) ?></small></p>
        <svg class="rw-today-spark" viewBox="0 0 176 30" preserveAspectRatio="none" role="img" aria-label="<?= esc($chartLabel, 'attr') ?>"><polyline points="<?= implode(' ', $points) ?>" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>
      </div>
    <?php endif ?>
    <?php if ($userId !== null) : ?>
      <form method="post" action="<?= esc(site_url('rolewarden/sidebar')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="collapsed" value="<?= $collapsed ? '0' : '1' ?>">
        <?php $toggle = lang('RoleWarden.panel.nav.' . ($collapsed ? 'expand' : 'collapse')); ?>
        <button type="submit" class="rw-side-toggle" aria-label="<?= esc($toggle, 'attr') ?>" title="<?= esc($toggle, 'attr') ?>"><?= $icon($collapsed ? 'expand' : 'collapse') ?></button>
      </form>
    <?php endif ?>
  </div>
</aside>
