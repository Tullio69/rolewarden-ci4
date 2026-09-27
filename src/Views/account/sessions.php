<?php
/**
 * The signed-in user's own sessions.
 *
 * @var array{sessions: list<array<string, mixed>>, remembered: list<array<string, mixed>>} $list
 * @var string $base
 */
?>
<div class="rw-page-head" style="grid-template-columns:1fr">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.account.overline') ?></div>
    <h1><?= lang('RoleWarden.panel.account.sessionsTitle') ?></h1>
    <p><?= lang('RoleWarden.panel.account.sessionsIntro') ?></p>
  </div>
</div>

<?= view('RoleWarden\Views\partials\sessions', ['list' => $list, 'base' => $base, 'canRevoke' => true]) ?>
