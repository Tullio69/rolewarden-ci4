<div class="rw-page-head">
  <div>
    <div class="rw-header">Directory</div>
    <h1><?= lang('RoleWarden.panel.users.indexTitle') ?></h1>
    <p><?= lang('RoleWarden.panel.users.indexIntro') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.users.indexTitle') ?></dt><dd class="rw-fig"><?= (int) $counts['total'] ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.users.active') ?></dt><dd class="rw-fig-sm"><?= (int) $counts['active'] ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.users.inactive') ?></dt><dd class="rw-fig-sm"><?= (int) $counts['inactive'] ?></dd></div>
  </dl>
</div>

<form class="rw-toolbar" method="get" action="<?= esc(site_url('rolewarden/users')) ?>">
  <div class="rw-field rw-search">
    <label class="rw-label" for="q"><?= lang('RoleWarden.panel.users.searchLabel') ?></label>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="square"/></svg>
    <input class="rw-input" id="q" name="q" type="search" placeholder="<?= esc(lang('RoleWarden.panel.users.searchPlaceholder')) ?>" value="<?= esc($search) ?>" autocomplete="off">
  </div>
  <div class="rw-field">
    <label class="rw-label" for="role"><?= lang('RoleWarden.panel.roles.indexTitle') ?></label>
    <select class="rw-select" id="role" name="role">
      <option value=""><?= lang('RoleWarden.panel.users.anyRole') ?></option>
      <?php foreach ($roles as $role) : ?>
        <option value="<?= (int) $role['id'] ?>"<?= (string) $roleId === (string) $role['id'] ? ' selected' : '' ?>><?= esc($role['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="rw-field">
    <label class="rw-label" for="status"><?= lang('RoleWarden.panel.users.status') ?></label>
    <select class="rw-select" id="status" name="status">
      <option value=""><?= lang('RoleWarden.panel.users.anyStatus') ?></option>
      <option value="active"<?= $status === 'active' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.users.active') ?></option>
      <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.users.inactive') ?></option>
    </select>
  </div>
  <input type="hidden" name="sort" value="<?= esc(strtolower($sort)) ?>">
  <?php if ($search !== '' || $roleId !== null && $roleId !== '' || $status !== '') : ?>
    <a class="rw-btn rw-btn--ghost" href="<?= esc(site_url('rolewarden/users')) ?>"><?= lang('RoleWarden.panel.users.clearFilters') ?></a>
  <?php endif ?>
  <button class="rw-btn rw-btn--secondary" type="submit"><?= lang('RoleWarden.panel.filter') ?></button>
  <div class="rw-grow"></div>
  <?php if (can('users.create')) : ?>
    <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('rolewarden/users/create')) ?>"><?= lang('RoleWarden.panel.users.newUser') ?></a>
  <?php endif ?>
</form>

<?php
$pageInfo = $pager->getDetails();
$startNum = ((int) $pageInfo['currentPage'] - 1) * (int) $pageInfo['perPage'];
$sortHref = site_url('rolewarden/users') . '?' . http_build_query(array_filter([
    'q' => $search, 'role' => $roleId, 'status' => $status, 'sort' => $sort === 'ASC' ? 'desc' : 'asc',
]));
?>
<table class="rw-t-users">
  <thead>
    <tr>
      <th class="rw-m-num" scope="col"></th>
      <th class="rw-c-name rw-header" scope="col"><?= lang('RoleWarden.panel.users.username') ?></th>
      <th class="rw-c-email rw-header" scope="col"><?= lang('RoleWarden.panel.users.email') ?></th>
      <th class="rw-c-roles rw-header" scope="col"><?= lang('RoleWarden.panel.nav.roles') ?></th>
      <th class="rw-c-status rw-header" scope="col"><?= lang('RoleWarden.panel.users.status') ?></th>
      <th class="rw-c-login rw-header" scope="col" aria-sort="<?= $sort === 'DESC' ? 'descending' : 'ascending' ?>">
        <a class="rw-sort" href="<?= esc($sortHref) ?>"><?= lang('RoleWarden.panel.users.lastLogin') ?>
          <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="<?= $sort === 'DESC' ? 'M2 3.5l3 3 3-3' : 'M2 6.5l3-3 3 3' ?>" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
        </a>
      </th>
      <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php if ($users === []) : ?>
      <tr class="rw-t-empty"><td colspan="7"><?= lang('RoleWarden.panel.users.empty') ?></td></tr>
    <?php endif ?>
    <?php foreach ($users as $i => $user) : ?>
      <?php
      $notes = [];
      if ((int) auth()->id() === (int) $user->id) $notes[] = lang('RoleWarden.panel.users.you');
      $ovCount = $overrideCounts[$user->id] ?? 0;
      if ($ovCount > 0) $notes[] = $ovCount . ' ' . lang($ovCount === 1 ? 'RoleWarden.panel.users.override' : 'RoleWarden.panel.users.overridesPlural');
      ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ($startNum + $i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <td class="rw-c-name"><a href="<?= esc(site_url('rolewarden/users/' . $user->id)) ?>"><?= esc($user->username ?? '—') ?></a></td>
        <td class="rw-c-email"><?= esc($user->email) ?></td>
        <td class="rw-c-roles"><?php foreach ($roleNames[$user->id] ?? [] as $roleName) : ?><span class="rw-role"><?= esc($roleName) ?></span><?php endforeach ?></td>
        <td class="rw-c-status">
          <?php if ($user->active) : ?>
            <span class="rw-status"><?= lang('RoleWarden.panel.users.active') ?></span>
          <?php else : ?>
            <span class="rw-status rw-status--disabled"><?= lang('RoleWarden.panel.users.inactive') ?></span>
          <?php endif ?>
        </td>
        <td class="rw-c-login<?= $user->last_active === null ? ' rw-c-login--never' : '' ?>"><?= $user->last_active !== null ? esc($user->last_active->toLocalizedString('d MMM y, HH:mm')) : lang('RoleWarden.panel.users.never') ?></td>
        <td class="rw-anno"><span class="rw-note"><?= esc(implode(' · ', $notes)) ?></span></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div class="rw-list-footer">
  <span class="rw-muted" role="status"><?= $users === [] ? '0 ' . lang('RoleWarden.panel.users.indexTitle') : esc(sprintf(lang('RoleWarden.panel.users.showing'), $startNum + 1, $startNum + count($users), $pageInfo['total'])) ?></span>
  <?= view('RoleWarden\Views\partials\pager', ['pager' => $pager]) ?>
</div>
