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

// 16px glyphs, 1.5px stroke, square caps (design system, Iconography).
$icons = [
    'users' => '<circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4"/>',
    'roles' => '<path d="M8 2.5l5.5 3-5.5 3-5.5-3z"/><path d="M2.5 8.5l5.5 3 5.5-3"/><path d="M2.5 11.5l5.5 3 5.5-3"/>',
    'permissions' => '<path d="M2.5 2.5h11v11h-11z"/><path d="M2.5 6.5h11M2.5 10h11M6.5 2.5v11"/>',
    'activity' => '<path d="M5.5 4h8M5.5 8h8M5.5 12h8M2.5 4h.5M2.5 8h.5M2.5 12h.5"/>',
    'settings' => '<path d="M2.5 4.5h7M12 4.5h1.5M2.5 11.5h2M7 11.5h6.5"/><circle cx="10.5" cy="4.5" r="1.5"/><circle cx="5.5" cy="11.5" r="1.5"/>',
    'appearance' => '<circle cx="8" cy="8" r="5.5"/><path d="M8 2.5v11"/>',
    'collapse' => '<path d="M10 3.5L5.5 8l4.5 4.5"/>',
    'expand' => '<path d="M6 3.5l4.5 4.5L6 12.5"/>',
];
$icon = static fn (string $key): string => '<svg class="rw-side-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true">' . $icons[$key] . '</svg>';
?>
<aside class="rw-sidebar rw-side" data-sidebar="<?= esc(Sidebar::style(), 'attr') ?>" aria-label="<?= esc(lang('RoleWarden.panel.a11y.main'), 'attr') ?>">
  <div class="rw-brand"><b>RoleWarden</b></div>

  <?php foreach ($groups as $group) : ?>
    <div class="rw-side-group">
      <span class="rw-side-label"><?= lang('RoleWarden.panel.nav.groups.' . $group['key']) ?></span>
      <ul class="rw-side-nav">
        <?php foreach ($group['items'] as $item) : ?>
          <?php $label = lang('RoleWarden.panel.nav.' . $item['key']); ?>
          <li>
            <a href="<?= esc($item['url']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?><?= $collapsed ? ' title="' . esc($label, 'attr') . '"' : '' ?>>
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
