<div class="rw-page-head" style="grid-template-columns:1fr">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.roles.overline') ?></div>
    <h1><?= lang('RoleWarden.panel.permissions.indexTitle') ?></h1>
    <p><?= lang('RoleWarden.panel.permissions.indexIntro') ?></p>
  </div>
</div>

<?php $n = 0; ?>
<?php foreach ($byArea as $area => $items) : ?>
  <div class="rw-section-title"><span class="rw-section-n"><?= str_pad((string) (++$n), 2, '0', STR_PAD_LEFT) ?></span><h2><?= esc(ucfirst($area)) ?></h2></div>
  <table class="rw-t-users">
    <thead>
      <tr>
        <th class="rw-c-name rw-header" scope="col" style="width:280px"><?= lang('RoleWarden.panel.permissions.slug') ?></th>
        <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.permissions.description') ?></th>
        <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $permission) : ?>
        <tr>
          <td class="rw-c-name"><code class="rw-mono"><?= esc($permission['slug']) ?></code></td>
          <td><?= esc($permission['description'] ?? '') ?></td>
          <td class="rw-anno"><?php if ((int) $permission['is_system'] === 1) : ?><span class="rw-note"><?= lang('RoleWarden.panel.roles.systemNote') ?></span><?php endif ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
<?php endforeach ?>
