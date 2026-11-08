<?php
/**
 * Every choice and every file change, before anything is written. The button posts
 * step=confirm to this same page, which runs Setup::apply. After a failed run
 * the reason is shown first, with the text to paste for each file left to do by
 * hand; posting again resumes.
 *
 * @var array<int, array{id: int, username: string, email: string}> $users active users by id
 * @var list<array{file: string, diff: string, manual: bool, applied: bool}> $files
 * @var list<string>                                                $applied apply steps already done
 * @var array{users: int, permissions: int}                          $direct Shield permissions given to single users
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
<?php if (($result['messages'] ?? []) !== []) : ?>
  <div class="rw-auth-row rw-install-block">
    <span class="rw-m-num"></span>
    <div class="rw-field">
      <div class="rw-auth-alert" role="alert"><b><?= esc(lang('RoleWarden.panel.toast.error')) ?></b> <?= esc($result['messages'][0]) ?></div>
      <?php foreach (array_slice($result['messages'], 1) as $text) : ?>
        <pre class="rw-setup-diff" tabindex="0"><?= esc($text) ?></pre>
      <?php endforeach ?>
    </div>
  </div>
<?php endif ?>
<?php if ($applied !== []) : ?>
  <div class="rw-auth-row rw-install-block">
    <span class="rw-m-num"><?= $num() ?></span>
    <div class="rw-field">
      <span class="rw-label"><?= esc($t('appliedTitle')) ?></span>
      <ul class="rw-install-checks">
        <?php foreach ($applied as $done) : ?>
          <li><span><?= esc($t('step.' . $done)) ?></span><b><?= esc($t('appliedDone')) ?></b></li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
<?php endif ?>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div>
    <dl class="rw-setup-summary">
      <?php foreach ($rows as $label => $value) : ?>
        <dt><?= esc($label) ?></dt><dd><?= esc($value) ?></dd>
      <?php endforeach ?>
    </dl>
    <?php if ($direct['permissions'] > 0) : ?>
      <div class="rw-auth-alert"><?= esc($t('directPermissions', [$direct['users'], $direct['permissions']])) ?></div>
    <?php endif ?>
  </div>
</div>
<div class="rw-auth-row rw-install-block">
  <span class="rw-m-num"><?= $num() ?></span>
  <div class="rw-field">
    <span class="rw-label"><?= esc($t('filesTitle')) ?></span>
    <?php foreach ($files as $f) : ?><?= $preview($f) ?><?php endforeach ?>
  </div>
</div>
