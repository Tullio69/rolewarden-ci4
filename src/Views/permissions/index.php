<div class="rw-page-head rw-page-head--single">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.roles.overline') ?></div>
    <h1><?= lang('RoleWarden.panel.permissions.indexTitle') ?></h1>
    <p><?= lang('RoleWarden.panel.permissions.indexIntro') ?></p>
  </div>
</div>

<?php $n = 0; $canSeeRoles = can('roles.view'); ?>
<?php foreach ($byArea as $area => $items) : ?>
  <div class="rw-section-title"><span class="rw-section-n"><?= str_pad((string) (++$n), 2, '0', STR_PAD_LEFT) ?></span><h2><?= esc(ucfirst($area)) ?></h2></div>
  <table class="rw-t-users rw-t-permissions">
    <thead>
      <tr>
        <th class="rw-m-num" scope="col"></th>
        <th class="rw-c-name rw-c-slug rw-header" scope="col"><?= lang('RoleWarden.panel.permissions.slug') ?></th>
        <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.permissions.description') ?></th>
        <th class="rw-c-roles rw-header" scope="col"><?= lang('RoleWarden.panel.permissions.grantedBy') ?></th>
        <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $permission) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <td class="rw-c-name"><code class="rw-mono"><?= esc($permission['slug']) ?></code></td>
          <td><?= esc($permission['description'] ?? '') ?></td>
          <td class="rw-c-roles">
            <?php if ($permission['roles'] === []) : ?><span class="rw-muted"><?= lang('RoleWarden.panel.permissions.noRole') ?></span><?php endif ?>
            <?php foreach ($permission['roles'] as $k => $role) : ?><?= $k > 0 ? ', ' : '' ?><?= $canSeeRoles ? '<a href="' . esc(site_url('rolewarden/roles/' . $role['id'])) . '">' . esc($role['name']) . '</a>' : esc($role['name']) ?><?php endforeach ?>
          </td>
          <td class="rw-anno"><?php if ((int) $permission['is_system'] === 1) : ?><span class="rw-note"><?= lang('RoleWarden.panel.permissions.systemNote') ?></span><?php endif ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endforeach ?>
