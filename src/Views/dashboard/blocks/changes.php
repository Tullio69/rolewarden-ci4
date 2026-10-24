<?php
/**
 * Dashboard block "changes" (dashboard.changes): the latest permission
 * changes of the period, with the same marks as the permission matrix, and
 * the way to all of them in the activity log.
 *
 * @var array{rows: list<array<string, mixed>>, total: int} $changes
 * @var array<string, mixed> $period
 * @var string               $letter
 */

use CodeIgniter\I18n\Time;

$marks = [
    'role' => '<svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" role="img" aria-label="%s"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>',
    'grant' => '<svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" role="img" aria-label="%s"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
    'deny' => '<svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" role="img" aria-label="%s"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg>',
    'none' => '<span class="rw-mark-blank" role="img" aria-label="%s"></span>',
];
$markOf = [
    'role.permission_granted' => 'role', 'user.role_assigned' => 'role', 'user.override_granted' => 'grant',
    'user.override_denied' => 'deny', 'role.permission_revoked' => 'none', 'user.role_revoked' => 'none', 'user.override_cleared' => 'none',
];
$canLog = can('activity.view');
?>
<section class="rw-dash-full" aria-labelledby="rw-dash-changes">
  <div class="rw-section-title">
    <span class="rw-section-n"><?= $letter ?></span>
    <h2 id="rw-dash-changes"><?= lang('RoleWarden.panel.dashboard.changes.title') ?></h2>
    <?php if ($canLog && $changes['total'] > 0) : ?>
      <a href="<?= esc(site_url('rolewarden/activity') . '?' . http_build_query(['from' => substr($period['from'], 0, 10), 'to' => substr($period['to'], 0, 10)])) ?>"><?= esc(lang('RoleWarden.panel.dashboard.changes.all', [$changes['total']])) ?></a>
    <?php endif ?>
  </div>
  <div class="rw-dash-body">
    <?php if ($changes['rows'] === []) : ?>
      <p class="rw-muted"><?= esc(lang('RoleWarden.panel.dashboard.changes.empty', [$period['days']])) ?></p>
    <?php else : ?>
      <ol class="rw-dash-changes">
        <?php foreach ($changes['rows'] as $i => $row) : ?>
          <?php
          $details = json_decode((string) $row['details'], true);
          $details = is_array($details) ? $details : [];
          $what = isset($details['permission'])
              ? '<code class="rw-mono">' . esc((string) $details['permission']) . '</code>'
              : '<b>' . esc((string) ($details['role'] ?? '')) . '</b>';
          $mark = $markOf[$row['action']];
          ?>
          <li>
            <span class="rw-note"><?= sprintf('%02d', $i + 1) ?></span>
            <span class="rw-dash-mark"><?= sprintf($marks[$mark], esc(lang('RoleWarden.panel.dashboard.changes.marks.' . $mark), 'attr')) ?></span>
            <span class="rw-dash-what"><?= lang('RoleWarden.panel.dashboard.changes.actions.' . str_replace('.', '_', (string) $row['action']), [
                '<b>' . esc((string) ($row['actor_label'] ?? lang('RoleWarden.panel.dashboard.changes.system'))) . '</b>',
                esc((string) $row['subject_label']),
                $what,
            ]) ?></span>
            <span class="rw-note"><?= esc(Time::parse((string) $row['created_at'])->toLocalizedString('d MMM, HH:mm')) ?></span>
          </li>
        <?php endforeach ?>
      </ol>
    <?php endif ?>
  </div>
</section>
