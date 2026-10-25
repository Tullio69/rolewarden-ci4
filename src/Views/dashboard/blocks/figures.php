<?php
/**
 * Dashboard block "figures" (dashboard.figures): four key figures in a ledger
 * strip, each with its change on the previous period and a note. Never tiles
 * or cards.
 *
 * @var array<string, mixed> $figures see Dashboard::figures()
 * @var array<string, mixed> $period
 */

use CodeIgniter\I18n\Time;

$n = static fn (int $value): string => lang('RoleWarden.panel.dashboard.number', [$value]);
$signed = static fn (int $value): string => ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . $n(abs($value));
$delta = static fn (int $now, int $before): string => $signed($now - $before);
$share = $figures['users'] > 0 ? (int) round($figures['active'] / $figures['users'] * 100) : 0;
$items = [
    [
        lang('RoleWarden.panel.dashboard.figures.active'),
        $figures['active'],
        lang('RoleWarden.panel.dashboard.figures.created', [$signed($figures['created'])]),
        lang('RoleWarden.panel.dashboard.figures.activeNote', [$figures['users'], $share]),
    ],
    [
        lang('RoleWarden.panel.dashboard.figures.signins', [$period['days']]),
        $figures['signins'],
        $delta($figures['signins'], $figures['signinsPrev']),
        $figures['peak'] !== null
            ? lang('RoleWarden.panel.dashboard.figures.peak', [$figures['peak']['count'], Time::parse($figures['peak']['day'])->toLocalizedString('d MMM')])
            : lang('RoleWarden.panel.dashboard.figures.noSignins'),
    ],
    [
        lang('RoleWarden.panel.dashboard.figures.failed', [$period['days']]),
        $figures['failed'],
        $delta($figures['failed'], $figures['failedPrev']),
        lang('RoleWarden.panel.dashboard.figures.previous', [$figures['failedPrev']]),
    ],
    [
        lang('RoleWarden.panel.dashboard.figures.changes'),
        $figures['changes'],
        $delta($figures['changes'], $figures['changesPrev']),
        lang('RoleWarden.panel.dashboard.figures.overrides', [$figures['overrides']]),
    ],
];
?>
<section class="rw-dash-full rw-dash-strip" aria-label="<?= esc(lang('RoleWarden.panel.dashboard.figures.title'), 'attr') ?>">
  <?php foreach ($items as $i => [$label, $value, $chip, $note]) : ?>
    <div class="rw-dash-fig">
      <span class="rw-header"><?= esc($label) ?></span>
      <div class="rw-dash-val">
        <b<?= $i === 0 ? ' class="rw-dash-accent"' : '' ?> data-rw-count="<?= (int) $value ?>"><?= esc($n($value)) ?></b>
        <?php $help = $i === 0 ? lang('RoleWarden.panel.dashboard.figures.createdHelp') : lang('RoleWarden.panel.dashboard.figures.deltaHelp', [$period['days']]) ?>
        <span class="rw-dash-chip" title="<?= esc($help, 'attr') ?>"><?= esc($chip) ?><span class="rw-visually-hidden"> <?= esc($help) ?></span></span>
      </div>
      <span class="rw-note"><?= esc($note) ?></span>
    </div>
  <?php endforeach ?>
</section>
