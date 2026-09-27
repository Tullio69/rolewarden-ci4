<?php
/**
 * Signed-in sessions of one user as a ledger, with revoke buttons. Used by the
 * user's own Sessions screen and by the user detail screen.
 *
 * @var array{sessions: list<array<string, mixed>>, remembered: list<array<string, mixed>>} $list
 * @var string $base      route prefix of the revoke actions (rolewarden/sessions or rolewarden/users/{id}/sessions)
 * @var bool   $canRevoke
 */

use CodeIgniter\I18n\Time;

$when = static fn (?string $at): string => $at !== null ? Time::parse($at)->toLocalizedString('d MMM y, HH:mm') : '—';
$n = 0;
?>
<table class="rw-ledger">
  <tbody>
    <?php foreach ($list['sessions'] as $session) : ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ++$n, 2, '0', STR_PAD_LEFT) ?></td>
        <th class="rw-l-key" scope="row">
          <b><?= esc(sprintf(lang('RoleWarden.panel.account.device'), $session['browser'] !== '' ? $session['browser'] : lang('RoleWarden.panel.account.unknownBrowser'), $session['platform'] !== '' ? $session['platform'] : lang('RoleWarden.panel.account.unknownPlatform'))) ?></b>
          <small><?= esc($session['ip']) ?> &middot; <?= esc(lang('RoleWarden.panel.account.signedIn') . ' ' . $when($session['createdAt'])) ?></small>
        </th>
        <td class="rw-l-value rw-muted">
          <?= esc(lang('RoleWarden.panel.account.lastSeen') . ' ' . Time::parse($session['lastSeenAt'])->humanize()) ?>
          <?php if ($session['rememberedUntil'] !== null) : ?><br><?= esc(lang('RoleWarden.panel.account.rememberedUntil') . ' ' . $when($session['rememberedUntil'])) ?><?php endif ?>
        </td>
        <td class="rw-l-ctl">
          <?php if ($canRevoke && ! $session['current']) : ?>
            <form method="post" action="<?= esc(site_url($base . '/' . $session['id'] . '/revoke')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <button class="rw-btn rw-btn--secondary rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.account.signOut') ?></button>
            </form>
          <?php endif ?>
        </td>
        <td class="rw-anno"><?php if ($session['current']) : ?><span class="rw-note"><?= lang('RoleWarden.panel.account.thisBrowser') ?></span><?php endif ?></td>
      </tr>
    <?php endforeach ?>
    <?php foreach ($list['remembered'] as $token) : ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ++$n, 2, '0', STR_PAD_LEFT) ?></td>
        <th class="rw-l-key" scope="row">
          <b><?= lang('RoleWarden.panel.account.rememberedBrowser') ?></b>
          <small><?= lang('RoleWarden.panel.account.rememberedHint') ?></small>
        </th>
        <td class="rw-l-value rw-muted"><?= esc(lang('RoleWarden.panel.account.rememberedUntil') . ' ' . $when($token['expires'])) ?></td>
        <td class="rw-l-ctl">
          <?php if ($canRevoke) : ?>
            <form method="post" action="<?= esc(site_url($base . '/remembered/' . $token['id'] . '/revoke')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <button class="rw-btn rw-btn--secondary rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.account.forget') ?></button>
            </form>
          <?php endif ?>
        </td>
        <td class="rw-anno"></td>
      </tr>
    <?php endforeach ?>
    <?php if ($n === 0) : ?>
      <tr>
        <td class="rw-m-num"></td>
        <td class="rw-l-key rw-muted" colspan="4"><?= lang('RoleWarden.panel.account.noSessions') ?></td>
      </tr>
    <?php elseif ($canRevoke) : ?>
      <tr>
        <td class="rw-m-num"></td>
        <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.account.everywhere') ?></b><small><?= lang('RoleWarden.panel.account.everywhereHint') ?></small></th>
        <td class="rw-l-value"></td>
        <td class="rw-l-ctl">
          <form method="post" action="<?= esc(site_url($base . '/revoke-all')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button class="rw-btn rw-btn--danger rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.account.signOutAll') ?></button>
          </form>
        </td>
        <td class="rw-anno"></td>
      </tr>
    <?php endif ?>
  </tbody>
</table>
