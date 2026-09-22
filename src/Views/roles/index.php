<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.roles.overline') ?></div>
    <h1><?= lang('RoleWarden.panel.roles.indexTitle') ?></h1>
    <p><?= lang('RoleWarden.panel.roles.indexIntro') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.roles.indexTitle') ?></dt><dd class="rw-fig"><?= count($roles) ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.roles.assignments') ?></dt><dd class="rw-fig-sm"><?= array_sum($counts) ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.roles.permissionsDefined') ?></dt><dd class="rw-fig-sm"><?= (int) $totalPermissions ?></dd></div>
  </dl>
</div>

<div class="rw-toolbar">
  <span class="rw-muted"><?= lang('RoleWarden.panel.roles.treeHint') ?></span>
  <div class="rw-grow"></div>
  <?php if (can('roles.create')) : ?>
    <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('rolewarden/roles/create')) ?>"><?= lang('RoleWarden.panel.roles.createTitle') ?></a>
  <?php endif ?>
</div>

<table class="rw-t-roles">
  <thead>
    <tr>
      <th class="rw-m-num" scope="col"></th>
      <th class="rw-c-role rw-header" scope="col"><?= lang('RoleWarden.panel.roles.name') ?></th>
      <th class="rw-c-parent rw-header" scope="col"><?= lang('RoleWarden.panel.roles.inheritsFrom') ?></th>
      <th class="rw-c-n rw-header" scope="col"><?= lang('RoleWarden.panel.nav.users') ?></th>
      <th class="rw-c-perm rw-header" scope="col"><?= lang('RoleWarden.panel.nav.permissions') ?></th>
      <th class="rw-c-act" scope="col"><span hidden><?= lang('RoleWarden.panel.roles.actions') ?></span></th>
      <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php if ($roles === []) : ?>
      <tr class="rw-t-empty"><td colspan="7"><?= lang('RoleWarden.panel.roles.empty') ?></td></tr>
    <?php endif ?>
    <?php foreach ($roles as $i => $role) : ?>
      <?php
      $ownCount = $permissionCounts[$role['id']] ?? 0;
      $inheritedCount = $inheritedCounts[$role['id']] ?? 0;
      $roleHolders = $holders[$role['id']] ?? [];
      $note = [];
      if ((int) $role['is_system'] === 1) $note[] = lang('RoleWarden.panel.roles.systemNote');
      ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <td class="rw-c-role">
          <div class="rw-tree">
            <?php for ($d = 0; $d < $role['depth']; $d++) : ?>
              <?= $d === $role['depth'] - 1 ? '<span class="rw-tree-branch" aria-hidden="true"></span>' : '<span class="rw-tree-spacer"></span>' ?>
            <?php endfor ?>
            <div>
              <a href="<?= esc(site_url('rolewarden/roles/' . $role['id'])) ?>"><?= esc($role['name']) ?></a>
              <?php if (($role['description'] ?? '') !== '') : ?><small><?= esc($role['description']) ?></small><?php endif ?>
            </div>
          </div>
        </td>
        <td class="rw-c-parent"><?php if ($role['parent_id'] !== null) : ?><span class="rw-role rw-role--parent"><?= esc($namesById[$role['parent_id']] ?? '—') ?></span><?php else : ?><span class="rw-note"><?= lang('RoleWarden.panel.roles.none') ?></span><?php endif ?></td>
        <td class="rw-c-n"><?= (int) ($counts[$role['id']] ?? 0) ?></td>
        <td class="rw-c-perm">
          <?php if ($role['parent_id'] === null) : ?>
            <?= $ownCount ?> <span><?= lang('RoleWarden.panel.roles.ofTotal') ?> <?= (int) $totalPermissions ?></span>
          <?php else : ?>
            <?= $ownCount ?> <span><?= lang('RoleWarden.panel.roles.own') ?></span> + <?= $inheritedCount ?> <span><?= lang('RoleWarden.panel.roles.inherited') ?></span>
          <?php endif ?>
        </td>
        <td class="rw-c-act">
          <a class="rw-btn rw-btn--ghost rw-btn--sm" href="<?= esc(site_url('rolewarden/roles/' . $role['id'])) ?>"><?= lang('RoleWarden.panel.roles.permissionsAction') ?></a>
          <?php if (can('roles.delete') && (int) $role['is_system'] !== 1) : ?>
            <button type="button" class="rw-btn rw-btn--ghost rw-btn--sm" style="color:var(--denied)" onclick="rwConfirm('confirm-delete-<?= (int) $role['id'] ?>')"><?= lang('RoleWarden.panel.delete') ?></button>
          <?php endif ?>
        </td>
        <td class="rw-anno"><span class="rw-note"><?= esc(implode(' · ', $note)) ?></span></td>
      </tr>
    <?php endforeach ?>
  </tbody>
  <tfoot>
    <tr>
      <td class="rw-m-num"></td>
      <td class="rw-header" style="padding-left:12px"><?= lang('RoleWarden.panel.roles.total') ?></td>
      <td></td>
      <td class="rw-c-n"><?= array_sum($counts) ?></td>
      <td class="rw-c-perm"><?= (int) $totalPermissions ?> <span><?= lang('RoleWarden.panel.roles.defined') ?></span></td>
      <td></td>
      <td class="rw-anno"></td>
    </tr>
  </tfoot>
</table>

<?php foreach ($roles as $role) : ?>
  <?php if (can('roles.delete') && (int) $role['is_system'] !== 1) : ?>
    <dialog id="confirm-delete-<?= (int) $role['id'] ?>" class="rw-confirm">
      <h2><?= esc(sprintf(lang('RoleWarden.panel.roles.confirmDeleteTitle'), $role['name'])) ?></h2>
      <p>
        <?php $roleHolders = $holders[$role['id']] ?? []; ?>
        <?= $roleHolders === []
            ? lang('RoleWarden.panel.roles.confirmDeleteNoUsers')
            : esc(sprintf(lang('RoleWarden.panel.roles.confirmDeleteUsers'), count($roleHolders), implode(', ', $roleHolders))) ?>
      </p>
      <div class="rw-confirm-actions">
        <button type="button" class="rw-btn rw-btn--ghost" onclick="rwCancelConfirm('confirm-delete-<?= (int) $role['id'] ?>')"><?= lang('RoleWarden.panel.cancel') ?></button>
        <form method="post" action="<?= esc(site_url('rolewarden/roles/' . $role['id'] . '/delete')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="rw-btn rw-btn--danger"><?= lang('RoleWarden.panel.delete') ?></button>
        </form>
      </div>
    </dialog>
  <?php endif ?>
<?php endforeach ?>
