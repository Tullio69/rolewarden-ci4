<?php
/**
 * Dashboard (SPEC, decisions of 2026-10-18 and 2026-10-21; design system,
 * components/Dashboard): the page of /rolewarden. Each block is its own view
 * under dashboard/blocks and is rendered only when the user holds its
 * permission, so the page closes up around the missing ones.
 *
 * @var list<string>               $blocks  visible blocks, in page order
 * @var array<string, mixed>       $period  see Dashboard::period()
 * @var string                     $name
 * @var int                        $hour
 * @var array<string, mixed>|null  $security
 * @var array<string, mixed>|null  $figures
 * @var array<string, mixed>|null  $buckets
 * @var array<string, mixed>|null  $roles
 * @var array<string, mixed>|null  $changes
 */

use CodeIgniter\I18n\Time;

$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$range = lang('RoleWarden.panel.dashboard.range', [
    Time::parse($period['from'])->toLocalizedString('d MMM'),
    Time::parse($period['to'])->toLocalizedString('d MMM y'),
]);
$data = get_defined_vars();
$letter = 'A';
?>
<div class="rw-page-head rw-dash-head">
  <div>
    <div class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.greeting.' . $greeting, [$name])) ?></div>
    <h1><?= lang('RoleWarden.panel.dashboard.heading') ?></h1>
    <p class="rw-note"><?= esc($range) ?></p>
  </div>
  <form class="rw-mode rw-dash-period" method="get" action="<?= esc(site_url('rolewarden')) ?>" role="group" aria-label="<?= esc(lang('RoleWarden.panel.dashboard.period'), 'attr') ?>">
    <?php foreach (RoleWarden\Dashboard\Dashboard::PERIODS as $days) : ?>
      <button type="submit" name="period" value="<?= $days ?>" aria-pressed="<?= $period['days'] === $days ? 'true' : 'false' ?>"><?= esc(lang('RoleWarden.panel.dashboard.days', [$days])) ?></button>
    <?php endforeach ?>
  </form>
</div>

<?php if ($security !== null) : ?>
  <?= view('RoleWarden\Views\dashboard\blocks\security', $data) ?>
<?php endif ?>

<div class="rw-dash">
<?php foreach (array_diff($blocks, ['security']) as $block) : ?>
  <?= view('RoleWarden\Views\dashboard\blocks\\' . $block, $data + ['letter' => $letter, 'half' => in_array('signins', $blocks, true) && in_array('roles', $blocks, true)]) ?>
  <?php // Sections are lettered in the gutter; the key figures are a strip without a title, sign-ins hold two sections. ?>
  <?php $letter = chr(ord($letter) + (['figures' => 0, 'signins' => 2][$block] ?? 1)) ?>
<?php endforeach ?>
</div>
