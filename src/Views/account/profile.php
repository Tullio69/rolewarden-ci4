<?php
/**
 * The signed-in user's profile, with the password change form.
 *
 * @var \CodeIgniter\Shield\Entities\User $user
 * @var int                                $sessionCount
 * @var array<string, string>              $errors field => message
 */
$field = static function (string $name, string $label, string $autocomplete) use ($errors): string {
    $error = $errors[$name] ?? null;
    $id = 'rw-' . str_replace('_', '-', $name);

    return '<div class="rw-field">'
        . '<label class="rw-label" for="' . $id . '">' . $label . '</label>'
        . '<input class="rw-input" type="password" id="' . $id . '" name="' . $name . '" autocomplete="' . $autocomplete . '" required'
        . ($error !== null ? ' aria-invalid="true" aria-describedby="' . $id . '-error"' : '') . '>'
        . ($error !== null ? '<p class="rw-field-error" id="' . $id . '-error"><b>' . lang('RoleWarden.panel.toast.error') . '</b> ' . esc($error) . '</p>' : '')
        . '</div>';
};
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.account.overline') ?></div>
    <h1><?= esc($user->username ?? $user->email) ?></h1>
    <p><?= esc($user->email) ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.users.memberSince') ?></dt><dd><?= $user->created_at !== null ? esc($user->created_at->toLocalizedString('d MMM y')) : '—' ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.account.sessionsTitle') ?></dt><dd class="rw-fig-sm"><a href="<?= esc(site_url('rolewarden/sessions')) ?>"><?= (int) $sessionCount ?></a></dd></div>
  </dl>
</div>

<div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.account.changePassword') ?></h2></div>
<form class="rw-form" method="post" action="<?= esc(site_url('rolewarden/profile/password')) ?>">
  <?= csrf_field() ?>
  <?= $field('current_password', lang('RoleWarden.panel.account.currentPassword'), 'current-password') ?>
  <?= $field('password', lang('RoleWarden.panel.account.newPassword'), 'new-password') ?>
  <?= $field('password_confirm', lang('RoleWarden.panel.account.confirmPassword'), 'new-password') ?>
  <p class="rw-field-hint"><?= lang('RoleWarden.panel.account.passwordHint') ?></p>
  <div class="rw-form-actions">
    <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.account.changePassword') ?></button>
  </div>
</form>

<div class="rw-section-title"><span class="rw-section-n">B</span><h2><?= lang('RoleWarden.panel.account.sessionsTitle') ?></h2></div>
<table class="rw-ledger">
  <tbody>
    <tr>
      <td class="rw-m-num">01</td>
      <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.account.sessionsTitle') ?></b><small><?= lang('RoleWarden.panel.account.sessionsIntro') ?></small></th>
      <td class="rw-l-value rw-muted"><?= esc(sprintf(lang('RoleWarden.panel.account.activeCount'), $sessionCount)) ?></td>
      <td class="rw-l-ctl"><a class="rw-btn rw-btn--secondary rw-btn--sm" href="<?= esc(site_url('rolewarden/sessions')) ?>"><?= lang('RoleWarden.panel.account.manageSessions') ?></a></td>
      <td class="rw-anno"></td>
    </tr>
  </tbody>
</table>
