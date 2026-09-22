<?php
$matrixConfig = [
    'saveUrl' => site_url('rolewarden/users/' . $user->id . '/permissions'),
    'csrfName' => csrf_token(),
    'csrfHash' => csrf_hash(),
    'rows' => $matrix['rows'],
];
$displayName = (string) ($user->username ?? $user->email);
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.users.overline') ?></div>
    <h1><?= esc($displayName) ?></h1>
    <p><?= esc($user->email) ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.users.status') ?></dt><dd><?php if ($user->active) : ?><span class="rw-status"><?= lang('RoleWarden.panel.users.active') ?></span><?php else : ?><span class="rw-status rw-status--disabled"><?= lang('RoleWarden.panel.users.inactive') ?></span><?php endif ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.users.lastLogin') ?></dt><dd><?= $user->last_active !== null ? esc($user->last_active->toLocalizedString('d MMM y, HH:mm')) : lang('RoleWarden.panel.users.never') ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.users.memberSince') ?></dt><dd><?= $user->created_at !== null ? esc($user->created_at->toLocalizedString('d MMM y')) : '—' ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.users.overrides') ?></dt><dd class="rw-fig-sm"><?= (int) $overrideCount ?></dd></div>
  </dl>
</div>

<div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.nav.roles') ?></h2></div>
<table class="rw-ledger">
  <tbody>
    <?php foreach ($roleSummaries as $i => $summary) : ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <th class="rw-l-key" scope="row">
          <b><?= esc($summary['name']) ?></b>
          <?php if ($summary['parentChain'] !== []) : ?><small><?= esc(lang('RoleWarden.panel.users.inheritsFrom') . ' ' . implode(', then ', $summary['parentChain'])) ?></small><?php endif ?>
        </th>
        <td class="rw-l-value rw-muted"><?= esc(sprintf(lang('RoleWarden.panel.users.permissionsSummary'), $summary['ownCount'], $summary['inheritedCount'])) ?></td>
        <td class="rw-l-ctl">
          <a class="rw-btn rw-btn--ghost rw-btn--sm" href="<?= esc(site_url('rolewarden/roles/' . $summary['id'])) ?>"><?= lang('RoleWarden.panel.roles.permissionsAction') ?></a>
          <?php if (can('roles.assign')) : ?>
            <?php if ($summary['id'] === $lastSuperAdminRoleId) : ?>
              <button class="rw-btn rw-btn--ghost rw-btn--sm" type="button" disabled title="<?= esc(lang('RoleWarden.protection.lastSuperAdmin')) ?>"><?= lang('RoleWarden.panel.users.revoke') ?></button>
            <?php else : ?>
              <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/roles/' . $summary['id'] . '/revoke')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="rw-btn rw-btn--ghost rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.revoke') ?></button>
              </form>
            <?php endif ?>
          <?php endif ?>
        </td>
        <td class="rw-anno"></td>
      </tr>
    <?php endforeach ?>
    <?php if (can('roles.assign') && $availableRoles !== []) : ?>
      <tr>
        <td class="rw-m-num"></td>
        <td class="rw-l-key"><label class="rw-label" for="add-role"><?= lang('RoleWarden.panel.users.addRole') ?></label></td>
        <td class="rw-l-value">
          <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/roles')) ?>" class="rw-add-role">
            <?= csrf_field() ?>
            <select class="rw-select" id="add-role" name="role_id">
              <?php foreach ($availableRoles as $role) : ?>
                <option value="<?= (int) $role['id'] ?>"><?= esc($role['name']) ?></option>
              <?php endforeach ?>
            </select>
            <button class="rw-btn rw-btn--secondary rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.addRoleAction') ?></button>
          </form>
        </td>
        <td class="rw-l-ctl"></td>
        <td class="rw-anno"></td>
      </tr>
    <?php endif ?>
  </tbody>
</table>

<div class="rw-section-title"><span class="rw-section-n">B</span><h2><?= lang('RoleWarden.panel.users.effectivePermissions') ?></h2></div>
<?php if (can('permissions.override')) : ?>
  <div x-data="rwUserMatrix(<?= esc(json_encode($matrixConfig, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)">
    <div class="rw-matrix-tools">
      <span class="rw-muted"><?= esc(sprintf(lang('RoleWarden.panel.users.matrixHint'), $displayName)) ?></span>
      <ul class="rw-legend" aria-label="Legend">
        <li><svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg><?= lang('RoleWarden.panel.roles.legendRole') ?></li>
        <li><svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg><?= lang('RoleWarden.panel.roles.legendInherit') ?></li>
        <li><svg class="rw-mark rw-mark--grant" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.3l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?= lang('RoleWarden.panel.roles.legendGrant') ?></li>
        <li><svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg><?= lang('RoleWarden.panel.roles.legendDeny') ?></li>
        <li><span class="rw-mark-blank" aria-hidden="true"></span><?= lang('RoleWarden.panel.roles.legendNone') ?></li>
      </ul>
    </div>

    <div class="rw-bar" :class="{ 'rw-bar--dirty': changes().length }" role="status" aria-live="polite">
      <template x-if="changes().length">
        <div class="rw-bar-msg">
          <span class="rw-bar-dot" aria-hidden="true"></span>
          <span><b x-text="changes().length + ' unsaved ' + (changes().length > 1 ? 'changes' : 'change')"></b> to <?= esc($displayName) ?>&rsquo;s overrides</span>
        </div>
      </template>
      <template x-if="!changes().length">
        <div class="rw-bar-msg rw-muted"><?= lang('RoleWarden.panel.roles.allSaved') ?></div>
      </template>
      <div class="rw-bar-actions">
        <button type="button" class="rw-btn rw-btn--ghost" @click="discard()" x-show="changes().length"><?= lang('RoleWarden.panel.roles.discard') ?></button>
        <button type="button" class="rw-btn rw-btn--primary" @click="save()" :disabled="!changes().length || saving"><?= lang('RoleWarden.panel.users.saveOverrides') ?></button>
      </div>
    </div>

    <table class="rw-matrix">
      <thead>
        <tr>
          <th class="rw-m-num" scope="col"></th>
          <th class="rw-m-area rw-header" scope="col"><?= lang('RoleWarden.panel.roles.area') ?></th>
          <?php foreach ($matrix['actions'] as $action) : ?><th class="rw-m-act rw-header"><?= esc($action) ?></th><?php endforeach ?>
          <th class="rw-m-anno rw-m-anno--wide" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
        </tr>
      </thead>
      <tbody>
        <template x-for="(row, i) in rows" :key="row.area">
          <tr>
            <td class="rw-m-num" x-text="String(i + 1).padStart(2, '0')"></td>
            <th class="rw-m-area" scope="row"><b x-text="row.area"></b><code x-text="row.area + '.*'"></code></th>
            <template x-for="cell in row.cells" :key="cell.action">
              <td class="rw-m-act">
                <template x-if="cell.permissionId !== null">
                  <button type="button" class="rw-cell" :class="{ 'rw-cell--changed': saved[key(row.area, cell.action)] !== cur[key(row.area, cell.action)] }" :title="tooltip(row.area, cell.action)" @click="toggleCell(row.area, cell.action)" x-html="mark(row.area, cell.action)"></button>
                </template>
              </td>
            </template>
            <td class="rw-m-anno rw-m-anno--wide"></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
<?php else : ?>
  <table class="rw-matrix">
    <thead><tr><th class="rw-m-num"></th><th class="rw-m-area rw-header"><?= lang('RoleWarden.panel.roles.area') ?></th><?php foreach ($matrix['actions'] as $action) : ?><th class="rw-m-act rw-header"><?= esc($action) ?></th><?php endforeach ?></tr></thead>
    <tbody>
      <?php foreach ($matrix['rows'] as $i => $row) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <th class="rw-m-area" scope="row"><b><?= esc($row['area']) ?></b><code><?= esc($row['area']) ?>.*</code></th>
          <?php foreach ($row['cells'] as $cell) : ?>
            <td class="rw-m-act"><?php if ($cell['permissionId'] !== null && $cell['state'] !== 'none') : ?><span class="rw-cell" title="<?= esc($cell['tooltip']) ?>"><?= match ($cell['state']) {
                'role', 'grant' => '<svg class="rw-mark rw-mark--role" viewBox="0 0 20 20" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>',
                'inherit' => '<svg class="rw-mark rw-mark--inherit" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.6 5.5l-1.6 6M13.6 5.5l-1.6 6" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
                'deny' => '<svg class="rw-mark rw-mark--deny" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.5 3.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1.5 10.5h17" stroke="currentColor" stroke-width="1.8"/></svg>',
                default => '',
            } ?></span><?php endif ?></td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>
<p class="rw-hint rw-note"><?= lang('RoleWarden.panel.users.overrideHint') ?></p>

<div class="rw-section-title"><span class="rw-section-n">C</span><h2><?= lang('RoleWarden.panel.users.account') ?></h2></div>
<table class="rw-ledger">
  <tbody>
    <?php if (can('users.update')) : ?>
      <tr>
        <td class="rw-m-num">01</td>
        <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.users.password') ?></b></th>
        <td class="rw-l-value rw-muted"><?= lang('RoleWarden.panel.users.passwordHint2') ?></td>
        <td class="rw-l-ctl"><a class="rw-btn rw-btn--secondary rw-btn--sm" href="<?= esc(site_url('rolewarden/users/' . $user->id . '/edit')) ?>"><?= lang('RoleWarden.panel.users.changePassword') ?></a></td>
        <td class="rw-anno"></td>
      </tr>
    <?php endif ?>
    <?php if (can('users.activate')) : ?>
      <tr>
        <td class="rw-m-num">02</td>
        <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.users.status') ?></b></th>
        <td class="rw-l-value rw-muted"><?= lang('RoleWarden.panel.users.statusHint') ?></td>
        <td class="rw-l-ctl">
          <?php if ($user->active) : ?>
            <?php if ($lastSuperAdminRoleId !== null || $isSelf) : ?>
              <button class="rw-btn rw-btn--danger rw-btn--sm" type="button" disabled title="<?= esc($isSelf ? lang('RoleWarden.panel.users.cannotDisableSelf') : lang('RoleWarden.protection.lastSuperAdmin')) ?>"><?= lang('RoleWarden.panel.users.deactivate') ?></button>
            <?php else : ?>
              <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/deactivate')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="rw-btn rw-btn--danger rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.deactivate') ?></button>
              </form>
            <?php endif ?>
          <?php else : ?>
            <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/activate')) ?>" style="display:inline">
              <?= csrf_field() ?>
              <button class="rw-btn rw-btn--secondary rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.users.activate') ?></button>
            </form>
          <?php endif ?>
        </td>
        <td class="rw-anno"><span class="rw-note"><?= lang('RoleWarden.panel.users.reversible') ?></span></td>
      </tr>
    <?php endif ?>
    <?php if (can('users.delete')) : ?>
      <tr>
        <td class="rw-m-num">03</td>
        <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.delete') ?></b></th>
        <td class="rw-l-value rw-muted"><?= lang('RoleWarden.panel.users.deleteHint') ?></td>
        <td class="rw-l-ctl">
          <?php if ($lastSuperAdminRoleId !== null || $isSelf) : ?>
            <button class="rw-btn rw-btn--danger rw-btn--sm" type="button" disabled title="<?= esc($isSelf ? lang('RoleWarden.panel.users.cannotDisableSelf') : lang('RoleWarden.protection.lastSuperAdmin')) ?>"><?= lang('RoleWarden.panel.delete') ?></button>
          <?php else : ?>
            <button class="rw-btn rw-btn--danger rw-btn--sm" type="button" onclick="rwConfirm('confirm-delete-user')"><?= lang('RoleWarden.panel.delete') ?></button>
          <?php endif ?>
        </td>
        <td class="rw-anno"></td>
      </tr>
    <?php endif ?>
  </tbody>
</table>

<?php if (can('users.delete') && $lastSuperAdminRoleId === null && ! $isSelf) : ?>
  <dialog id="confirm-delete-user" class="rw-confirm">
    <h2><?= esc(sprintf(lang('RoleWarden.panel.users.confirmDeleteTitle'), $displayName)) ?></h2>
    <p><?= lang('RoleWarden.panel.users.confirmDeleteBody') ?></p>
    <div class="rw-confirm-actions">
      <button type="button" class="rw-btn rw-btn--ghost" onclick="rwCancelConfirm('confirm-delete-user')"><?= lang('RoleWarden.panel.cancel') ?></button>
      <form method="post" action="<?= esc(site_url('rolewarden/users/' . $user->id . '/delete')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="rw-btn rw-btn--danger"><?= lang('RoleWarden.panel.delete') ?></button>
      </form>
    </div>
  </dialog>
<?php endif ?>
