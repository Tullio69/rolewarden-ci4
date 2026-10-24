<?php
/**
 * Dashboard block "security" (dashboard.security): one accent-tint strip, one
 * sentence and one action. It informs, it does not alarm: never denied.
 *
 * @var array<string, mixed> $security see Dashboard::security()
 */

use CodeIgniter\I18n\Time;

if (isset($security['ip'])) {
    $lead = lang('RoleWarden.panel.dashboard.security.failed', [$security['count']]);
    $rest = lang('RoleWarden.panel.dashboard.security.address', [$security['ip'], Time::parse($security['day'])->toLocalizedString('d MMM'), $security['threshold']]);
    $day = $security['day'];
} else {
    $shown = array_slice($security['locked'], 0, 3);
    $lead = lang('RoleWarden.panel.dashboard.security.locked', [count($security['locked'])]);
    $rest = implode(', ', $shown) . (count($security['locked']) > 3 ? ' …' : '');
    $day = date('Y-m-d');
}
?>
<div class="rw-dash-notice" role="status">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true"><path d="M8 1.8l5.2 2v4.1c0 3-2.2 5.3-5.2 6.3-3-1-5.2-3.3-5.2-6.3V3.8z"/><path d="M8 5.5v3M8 10.5v.5"/></svg>
  <span><strong><?= esc($lead) ?></strong> <?= esc($rest) ?></span>
  <?php if (can('activity.view')) : ?>
    <a class="rw-btn rw-btn--primary rw-btn--sm" href="<?= esc(site_url('rolewarden/activity') . '?' . http_build_query(['action' => 'auth.failed', 'from' => $day, 'to' => $day])) ?>"><?= lang('RoleWarden.panel.dashboard.security.review') ?></a>
  <?php endif ?>
</div>
