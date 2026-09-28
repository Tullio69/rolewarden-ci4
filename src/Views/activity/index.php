<?php
/**
 * Activity log (SPEC "Log attivita'"): changes written by the module and
 * Shield's sign-in attempts, newest first.
 *
 * @var list<array<string, mixed>>                        $rows
 * @var int                                               $total
 * @var array{user: string, action: string, from: string, to: string} $filters
 * @var list<string>                                      $actions
 * @var \CodeIgniter\Pager\Pager                          $pager
 * @var int                                               $start
 * @var array{user: array<int, true>, role: array<int, true>} $links
 */

use CodeIgniter\I18n\Time;

$actionLabel = static fn (string $action): string => lang('RoleWarden.panel.activity.actions.' . str_replace('.', '_', $action));
$filtered = array_filter($filters, static fn (string $v): bool => $v !== '') !== [];

$describe = static function (?string $json): string {
    $details = $json !== null ? json_decode($json, true) : null;

    if (! is_array($details)) {
        return '';
    }

    $parts = [];

    foreach ($details as $field => $value) {
        $show = static fn ($v): string => $v === null || $v === '' ? '—' : (string) $v;
        $parts[] = is_array($value) && count($value) === 2
            ? esc($field) . ': ' . esc($show($value[0])) . ' &rarr; ' . esc($show($value[1]))
            : esc($field) . ': ' . esc($show(is_array($value) ? json_encode($value) : $value));
    }

    return implode('<br>', $parts);
};
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header">RoleWarden</div>
    <h1><?= lang('RoleWarden.panel.activity.title') ?></h1>
    <p><?= lang('RoleWarden.panel.activity.intro') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.activity.matching') ?></dt><dd class="rw-fig-sm"><?= (int) $total ?></dd></div>
  </dl>
</div>

<form class="rw-toolbar" method="get" action="<?= esc(site_url('rolewarden/activity')) ?>">
  <div class="rw-field">
    <label class="rw-label" for="rw-activity-user"><?= lang('RoleWarden.panel.activity.user') ?></label>
    <input class="rw-input" id="rw-activity-user" name="user" type="search" value="<?= esc($filters['user']) ?>" placeholder="<?= esc(lang('RoleWarden.panel.activity.userPlaceholder'), 'attr') ?>" autocomplete="off">
  </div>
  <div class="rw-field">
    <label class="rw-label" for="rw-activity-action"><?= lang('RoleWarden.panel.activity.action') ?></label>
    <select class="rw-select" id="rw-activity-action" name="action">
      <option value=""><?= lang('RoleWarden.panel.activity.anyAction') ?></option>
      <?php foreach ($actions as $action) : ?>
        <option value="<?= esc($action, 'attr') ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= esc($actionLabel($action)) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="rw-field">
    <label class="rw-label" for="rw-activity-from"><?= lang('RoleWarden.panel.activity.from') ?></label>
    <input class="rw-input" id="rw-activity-from" name="from" type="date" value="<?= esc($filters['from'], 'attr') ?>">
  </div>
  <div class="rw-field">
    <label class="rw-label" for="rw-activity-to"><?= lang('RoleWarden.panel.activity.to') ?></label>
    <input class="rw-input" id="rw-activity-to" name="to" type="date" value="<?= esc($filters['to'], 'attr') ?>">
  </div>
  <?php if ($filtered) : ?>
    <a class="rw-btn rw-btn--ghost" href="<?= esc(site_url('rolewarden/activity')) ?>"><?= lang('RoleWarden.panel.users.clearFilters') ?></a>
  <?php endif ?>
  <button class="rw-btn rw-btn--secondary" type="submit"><?= lang('RoleWarden.panel.filter') ?></button>
</form>

<table class="rw-t-users rw-t-activity">
  <thead>
    <tr>
      <th class="rw-m-num" scope="col"></th>
      <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.activity.when') ?></th>
      <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.activity.who') ?></th>
      <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.activity.action') ?></th>
      <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.activity.object') ?></th>
      <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.activity.details') ?></th>
      <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.activity.address') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php if ($rows === []) : ?>
      <tr class="rw-t-empty"><td colspan="7"><?= $filtered ? lang('RoleWarden.panel.activity.noMatch') : lang('RoleWarden.panel.activity.empty') ?></td></tr>
    <?php endif ?>
    <?php foreach ($rows as $i => $row) : ?>
      <?php
      $type = (string) $row['subject_type'];
      $id = $row['subject_id'] !== null ? (int) $row['subject_id'] : null;
      $object = esc((string) $row['subject_label']);
      if ($id !== null && isset($links[$type][$id])) {
          $object = '<a href="' . esc(site_url('rolewarden/' . $type . 's/' . $id)) . '">' . $object . '</a>';
      }
      ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ($start + $i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <td style="white-space:nowrap"><?= esc(Time::parse((string) $row['at'])->toLocalizedString('d MMM y, HH:mm')) ?></td>
        <td><?= $row['actor_label'] !== null ? esc((string) $row['actor_label']) : '<span class="rw-muted">' . lang('RoleWarden.panel.activity.system') . '</span>' ?></td>
        <td><span<?= $row['action'] === 'auth.failed' ? ' class="rw-muted"' : '' ?>><?= esc($actionLabel((string) $row['action'])) ?></span></td>
        <td><?= $object ?></td>
        <td class="rw-muted"><?= $describe($row['details']) ?></td>
        <td class="rw-anno"><span class="rw-note"><?= esc((string) ($row['ip_address'] ?? '')) ?></span></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<?= view('RoleWarden\Views\partials\pager', ['pager' => $pager]) ?>
