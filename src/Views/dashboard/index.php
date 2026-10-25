<?php
/**
 * Dashboard (SPEC, decisions of 2026-10-18, 2026-10-21 and 2026-10-25; design
 * system, components/Dashboard): the page of /rolewarden. Each block is its
 * own view under dashboard/blocks and is rendered only when the user holds its
 * permission, so the page closes up around the missing ones. Blocks of the
 * application are rendered inside dashboard/blocks/app, in the user's order.
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
 * @var array<string, array{block: RoleWarden\Dashboard\Block, data: array<string, mixed>}> $app the application's blocks shown
 * @var array<string, mixed>       $customize see dashboard/customize
 */

use CodeIgniter\I18n\Time;

$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$range = lang('RoleWarden.panel.dashboard.range', [
    Time::parse($period['from'])->toLocalizedString('d MMM'),
    Time::parse($period['to'])->toLocalizedString('d MMM y'),
]);
$flow = array_values(array_diff($blocks, ['security']));
// The heat map and the roles share a row only when roles comes right after sign-ins.
$at = array_search('signins', $flow, true);
$half = $at !== false && ($flow[$at + 1] ?? null) === 'roles';
$data = get_defined_vars();
$letter = 'A';
?>
<div class="rw-page-head rw-dash-head">
  <div>
    <div class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.greeting.' . $greeting, [$name])) ?></div>
    <h1><?= lang('RoleWarden.panel.dashboard.heading') ?></h1>
    <p class="rw-note"><?= esc($range) ?></p>
  </div>
  <div class="rw-dash-tools">
    <form class="rw-mode rw-dash-period" method="get" action="<?= esc(site_url('rolewarden')) ?>" role="group" aria-label="<?= esc(lang('RoleWarden.panel.dashboard.period'), 'attr') ?>">
      <?php foreach (RoleWarden\Dashboard\Dashboard::PERIODS as $days) : ?>
        <button type="submit" name="period" value="<?= $days ?>" aria-pressed="<?= $period['days'] === $days ? 'true' : 'false' ?>"><?= esc(lang('RoleWarden.panel.dashboard.days', [$days])) ?></button>
      <?php endforeach ?>
    </form>
    <?= view('RoleWarden\Views\dashboard\customize', $customize) ?>
  </div>
</div>

<?php if ($security !== null) : ?>
  <?= view('RoleWarden\Views\dashboard\blocks\security', $data) ?>
<?php endif ?>

<div class="rw-dash">
<?php foreach ($flow as $block) : ?>
  <?php if (isset($app[$block])) : ?>
    <?= view('RoleWarden\Views\dashboard\blocks\app', ['block' => $app[$block]['block'], 'data' => $app[$block]['data'] + ['period' => $period, 'userId' => (int) auth()->id()], 'letter' => $letter]) ?>
  <?php else : ?>
    <?= view('RoleWarden\Views\dashboard\blocks\\' . $block, $data + ['letter' => $letter]) ?>
  <?php endif ?>
  <?php // Sections are lettered in the gutter; the key figures are a strip without a title, sign-ins hold two sections. ?>
  <?php $letter = chr(ord($letter) + (['figures' => 0, 'signins' => 2][$block] ?? 1)) ?>
<?php endforeach ?>
</div>
