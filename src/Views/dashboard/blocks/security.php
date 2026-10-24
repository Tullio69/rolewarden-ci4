<?php
/**
 * Dashboard block "security" (dashboard.security): one accent-tint strip with
 * one line per reason and one action. It informs, it does not alarm: never denied.
 * The action opens the failed sign-ins from the earliest day named to the
 * latest (today when an email is locked).
 *
 * @var array{addresses: list<array{ip: string, day: string, count: int}>, locked: list<string>, threshold: int} $security
 */

use CodeIgniter\I18n\Time;

$days = array_column($security['addresses'], 'day');

if ($security['locked'] !== []) {
    $days[] = date('Y-m-d');
}
?>
<div class="rw-dash-notice" role="status">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true"><path d="M8 1.8l5.2 2v4.1c0 3-2.2 5.3-5.2 6.3-3-1-5.2-3.3-5.2-6.3V3.8z"/><path d="M8 5.5v3M8 10.5v.5"/></svg>
  <ul>
    <?php foreach ($security['addresses'] as $address) : ?>
      <li><strong><?= esc(lang('RoleWarden.panel.dashboard.security.failed', [$address['count']])) ?></strong> <?= esc(lang('RoleWarden.panel.dashboard.security.address', [$address['ip'], Time::parse($address['day'])->toLocalizedString('d MMM'), $security['threshold']])) ?></li>
    <?php endforeach ?>
    <?php if ($security['locked'] !== []) : ?>
      <li><strong><?= esc(lang('RoleWarden.panel.dashboard.security.locked', [count($security['locked'])])) ?></strong> <?= esc(implode(', ', $security['locked'])) ?></li>
    <?php endif ?>
  </ul>
  <?php if (can('activity.view')) : ?>
    <a class="rw-btn rw-btn--primary rw-btn--sm" href="<?= esc(site_url('rolewarden/activity') . '?' . http_build_query(['action' => 'auth.failed', 'from' => min($days), 'to' => max($days)])) ?>"><?= lang('RoleWarden.panel.dashboard.security.review') ?></a>
  <?php endif ?>
</div>
