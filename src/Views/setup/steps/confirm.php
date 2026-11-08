<?php
/**
 * Every choice and every file change, before anything is written. The button posts
 * step=confirm to this same page; applying is Task 7's.
 *
 * @var array<int, array{id: int, username: string, email: string}> $users active users by id
 * @var list<array{file: string, diff: string, manual: bool}>       $files
 */
$mode = $choices['mode'] ?? '';
$admin = is_array($choices['superAdmin'] ?? null) ? $choices['superAdmin'] : [];
$existing = $users[$admin['id'] ?? 0] ?? null;
$rows = [$t('summaryMode') => $t($mode === 'adapt' ? 'modeAdapt' : 'modeImport')];

if ($mode === 'adapt') {
    $rows[$t('panelRole')] = ($choices['panelRole'] ?? '') !== '' ? (string) $choices['panelRole'] : $t('panelRoleNone');
}

foreach ($mode === 'import' ? ($choices['collisions'] ?? []) : [] as $slug => $c) {
    $rows[$t('summaryCollision', [(string) $slug])] = match ($c['action'] ?? '') {
        'merge' => $t('summaryMerge', [(string) $slug]),
        'rename' => $t('summaryRename', [(string) ($c['newSlug'] ?? '')]),
        default => $t('summarySkip'),
    };
}

$rows[$t('newUsersTitle')] = ($choices['defaultRole'] ?? '') !== '' ? (string) $choices['defaultRole'] : $t('defaultRoleNone');
$rows[$t('superAdminTitle')] = ($admin['kind'] ?? null) === 'existing'
    ? $t('summaryExisting', [$existing === null ? '#' . $admin['id'] : ($existing['username'] !== '' ? $existing['username'] : $existing['email'])])
    : $t('summaryNew', [(string) ($admin['email'] ?? '')]);
$rows[$t('visibilityTitle')] = $t(($choices['visibility'] ?? '') === 'all' ? 'visibilityAll' : 'visibilityAdmins');
?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <dl class="rw-setup-summary">
    <?php foreach ($rows as $label => $value) : ?>
      <dt><?= esc($label) ?></dt><dd><?= esc($value) ?></dd>
    <?php endforeach ?>
  </dl>
</div>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <span class="rw-label"><?= esc($t('filesTitle')) ?></span>
    <?php foreach ($files as $f) : ?><?= $preview($f) ?><?php endforeach ?>
  </div>
</div>
