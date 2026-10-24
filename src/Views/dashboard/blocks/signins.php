<?php
/**
 * Dashboard block "signins" (dashboard.signins): sign-ins per day of the
 * period (successful as an accent line over its tint, failed as ink bars) and
 * successful ones by weekday and hour as a heat map. Server-side SVG from the
 * tokens; each chart says its takeaway in its label and offers its numbers as
 * a table.
 *
 * @var array{ok: array<string, int>, failed: array<string, int>, heat: list<list<int>>} $buckets
 * @var array<string, mixed> $period
 * @var string               $letter
 * @var bool                 $half
 */

use CodeIgniter\I18n\Time;
use RoleWarden\Dashboard\Dashboard;

$n = static fn (int $value): string => lang('RoleWarden.panel.dashboard.number', [$value]);
$day = static fn (string $date, string $format = 'd MMM'): string => Time::parse($date)->toLocalizedString($format);
$ok = array_values($buckets['ok']);
$failed = array_values($buckets['failed']);
$dates = array_keys($buckets['ok']);
$count = count($ok);
$totalOk = array_sum($ok);
$totalFailed = array_sum($failed);

// The y axis ends at the next 1, 2 or 5 times a power of ten above the busiest day.
$top = max(1, max($ok), max($failed));
$step = 10 ** (int) floor(log10($top));
$scale = (int) (array_values(array_filter([1, 2, 5, 10], static fn (int $m): bool => $m * $step >= $top))[0] * $step);
$x = static fn (int $i): float => $count > 1 ? $i * 1000 / ($count - 1) : 500;
$y = static fn (int $v): float => 200 - $v * 200 / $scale;
$line = implode(' ', array_map(static fn (int $i): string => sprintf('%.1f,%.1f', $x($i), $y($ok[$i])), array_keys($ok)));
$bar = min(10.0, 600 / $count);
$busiest = $totalOk > 0 ? array_search(max($ok), $ok, true) : null;
$worst = $totalFailed > 0 ? array_search(max($failed), $failed, true) : null;
$chartLabel = $totalOk + $totalFailed === 0
    ? lang('RoleWarden.panel.dashboard.signins.empty')
    : lang('RoleWarden.panel.dashboard.signins.chartLabel', [
        $totalOk, $period['days'], $busiest !== null ? $ok[$busiest] : 0, $busiest !== null ? $day($dates[$busiest]) : '—',
        $totalFailed, $worst !== null ? $failed[$worst] : 0, $worst !== null ? $day($dates[$worst]) : '—',
    ]);
$ticks = array_unique([0, (int) round(($count - 1) / 4), (int) round(($count - 1) / 2), (int) round(3 * ($count - 1) / 4), $count - 1]);

$heat = $buckets['heat'];
$max = max(array_map('max', $heat));
$weekdays = array_map(static fn (int $d): string => lang('RoleWarden.panel.dashboard.weekdays.' . $d), range(0, 6));
$peakCell = null;

foreach ($heat as $d => $hours) {
    foreach ($hours as $h => $v) {
        if ($v > 0 && ($peakCell === null || $v > $peakCell[2])) {
            $peakCell = [$d, $h, $v];
        }
    }
}

$heatLabel = $peakCell === null
    ? lang('RoleWarden.panel.dashboard.signins.empty')
    : lang('RoleWarden.panel.dashboard.signins.heatLabel', [$weekdays[$peakCell[0]], $peakCell[1], $peakCell[2]]);
$second = chr(ord($letter) + 1);
?>
<section class="rw-dash-full" aria-labelledby="rw-dash-signins">
  <div class="rw-section-title">
    <span class="rw-section-n"><?= $letter ?></span>
    <h2 id="rw-dash-signins"><?= esc(lang('RoleWarden.panel.dashboard.signins.title', [$period['days']])) ?></h2>
    <ul class="rw-legend">
      <li><i class="rw-dash-key rw-dash-key--line" aria-hidden="true"></i><?= esc(lang('RoleWarden.panel.dashboard.signins.ok', [$totalOk])) ?></li>
      <li><i class="rw-dash-key rw-dash-key--bar" aria-hidden="true"></i><?= esc(lang('RoleWarden.panel.dashboard.signins.failed', [$totalFailed])) ?></li>
    </ul>
  </div>
  <div class="rw-dash-body">
    <div class="rw-dash-chart">
      <div class="rw-dash-yaxis rw-note" aria-hidden="true"><span><?= esc($n($scale)) ?></span><span><?= esc($n(intdiv($scale, 2))) ?></span><span>0</span></div>
      <div>
        <svg class="rw-dash-svg" role="img" aria-label="<?= esc($chartLabel, 'attr') ?>" viewBox="0 0 1000 200" preserveAspectRatio="none">
          <polygon points="0,200 <?= $line ?> 1000,200" class="rw-dash-area"/>
          <line x1="0" y1="100" x2="1000" y2="100" class="rw-dash-mid" vector-effect="non-scaling-stroke"/>
          <?php foreach ($failed as $i => $v) : ?>
            <?php if ($v > 0) : ?><rect x="<?= sprintf('%.1f', $x($i) - $bar / 2) ?>" y="<?= sprintf('%.1f', $y($v)) ?>" width="<?= sprintf('%.1f', $bar) ?>" height="<?= sprintf('%.1f', 200 - $y($v)) ?>" class="rw-dash-bar"/><?php endif ?>
          <?php endforeach ?>
          <line x1="0" y1="199.5" x2="1000" y2="199.5" class="rw-dash-base" vector-effect="non-scaling-stroke"/>
          <polyline points="<?= $line ?>" class="rw-dash-line" vector-effect="non-scaling-stroke"/>
        </svg>
        <div class="rw-dash-xaxis rw-note" aria-hidden="true"><?php foreach ($ticks as $i) : ?><span><?= esc($day($dates[$i])) ?></span><?php endforeach ?></div>
      </div>
    </div>
    <details class="rw-dash-numbers">
      <summary><?= lang('RoleWarden.panel.dashboard.numbers') ?></summary>
      <table class="rw-table">
        <thead><tr><th scope="col"><?= lang('RoleWarden.panel.dashboard.signins.day') ?></th><th scope="col"><?= lang('RoleWarden.panel.dashboard.signins.okColumn') ?></th><th scope="col"><?= lang('RoleWarden.panel.dashboard.signins.failedColumn') ?></th></tr></thead>
        <tbody>
          <?php foreach ($dates as $i => $date) : ?>
            <tr><th scope="row"><?= esc($day($date, 'EEE d MMM')) ?></th><td><?= esc($n($ok[$i])) ?></td><td><?= esc($n($failed[$i])) ?></td></tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </details>
  </div>
</section>

<section class="<?= $half ? 'rw-dash-half' : 'rw-dash-full' ?>" aria-labelledby="rw-dash-heat">
  <div class="rw-section-title">
    <span class="rw-section-n"><?= $second ?></span>
    <h2 id="rw-dash-heat"><?= lang('RoleWarden.panel.dashboard.signins.heatTitle') ?></h2>
    <?php if ($peakCell !== null) : ?><span class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.signins.busiest', [$weekdays[$peakCell[0]], $peakCell[1]])) ?></span><?php endif ?>
  </div>
  <div class="rw-dash-body">
    <div class="rw-dash-heat">
      <div class="rw-dash-heat-days rw-note" aria-hidden="true"><?php foreach ($weekdays as $weekday) : ?><span><?= esc($weekday) ?></span><?php endforeach ?></div>
      <svg class="rw-dash-svg rw-dash-heat-svg" role="img" aria-label="<?= esc($heatLabel, 'attr') ?>" viewBox="0 0 240 147" preserveAspectRatio="none">
        <?php foreach ($heat as $d => $hours) : ?>
          <?php foreach ($hours as $h => $v) : ?><rect x="<?= $h * 10 ?>" y="<?= $d * 21 ?>" width="8.8" height="18" class="rw-heat-<?= Dashboard::heatLevel($v, $max) ?>"><title><?= esc(lang('RoleWarden.panel.dashboard.signins.cell', [$v, $weekdays[$d], $h])) ?></title></rect><?php endforeach ?>
        <?php endforeach ?>
      </svg>
      <div class="rw-dash-heat-hours rw-note" aria-hidden="true"><span>00</span><span>06</span><span>12</span><span>18</span></div>
    </div>
    <div class="rw-dash-heat-key rw-note" aria-hidden="true"><?= lang('RoleWarden.panel.dashboard.signins.fewer') ?><?php foreach (range(0, 4) as $level) : ?><i class="rw-heat-<?= $level ?>"></i><?php endforeach ?><?= lang('RoleWarden.panel.dashboard.signins.more') ?></div>
    <details class="rw-dash-numbers">
      <summary><?= lang('RoleWarden.panel.dashboard.numbers') ?></summary>
      <div class="rw-dash-scroll">
        <table class="rw-table rw-dash-heat-table">
          <thead><tr><th scope="col"><span class="rw-visually-hidden"><?= lang('RoleWarden.panel.dashboard.signins.day') ?></span></th><?php foreach (range(0, 23) as $h) : ?><th scope="col"><?= sprintf('%02d', $h) ?></th><?php endforeach ?></tr></thead>
          <tbody>
            <?php foreach ($heat as $d => $hours) : ?>
              <tr><th scope="row"><?= esc($weekdays[$d]) ?></th><?php foreach ($hours as $v) : ?><td><?= (int) $v ?></td><?php endforeach ?></tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>
</section>
