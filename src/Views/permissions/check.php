<?php
/**
 * Check a permission (SPEC, v1.1 decisions): read only, every answer from the
 * resolver's explain().
 *
 * @var list<array<string, mixed>>                                                      $permissions
 * @var string                                                                          $slug        a known slug, or ''
 * @var bool                                                                            $unknown
 * @var string                                                                          $who
 * @var \RoleWarden\Entities\User|null                                                  $user
 * @var array{allowed: bool, rule: string, via: list<list<int>>}|null                   $verdict
 * @var list<string>                                                                    $rules       in order of precedence
 * @var list<array{user: \RoleWarden\Entities\User, why: array{allowed: bool, rule: string, via: list<list<int>>}}> $holders
 * @var int                                                                             $total
 * @var array<int, string>                                                              $roleNames
 * @var \CodeIgniter\Pager\Pager                                                        $pager
 * @var int                                                                             $start
 */

use RoleWarden\Models\SeededText;

$canSeeUsers = can('users.view');
$canSeeRoles = can('roles.view');
$name = static fn ($u): string => (string) ($u->username ?? $u->email);
$userLink = static fn ($u): string => $canSeeUsers
    ? '<a href="' . esc(site_url('rolewarden/users/' . $u->id)) . '">' . esc($name($u)) . '</a>'
    : esc($name($u));
// A chain reads from the assigned role up to the one the permission is granted to: "Editor ← Author".
$chain = static fn (array $ids): string => implode(' &larr; ', array_map(
    static fn (int $id): string => $canSeeRoles
        ? '<a href="' . esc(site_url('rolewarden/roles/' . $id)) . '">' . esc($roleNames[$id] ?? '#' . $id) . '</a>'
        : esc($roleNames[$id] ?? '#' . $id),
    $ids,
));
$byArea = [];
foreach ($permissions as $p) {
    $byArea[$p['area']][] = $p['slug'];
}
?>
<div class="rw-page-head rw-page-head--single">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.roles.overline') ?></div>
    <h1><?= lang('RoleWarden.panel.check.title') ?></h1>
    <p><?= lang('RoleWarden.panel.check.intro') ?></p>
  </div>
</div>

<form class="rw-toolbar" method="get" action="<?= esc(site_url('rolewarden/permissions/check')) ?>">
  <div class="rw-field">
    <label class="rw-label" for="rw-check-permission"><?= lang('RoleWarden.panel.check.permission') ?></label>
    <select class="rw-select" id="rw-check-permission" name="permission" required>
      <option value=""><?= lang('RoleWarden.panel.check.choose') ?></option>
      <?php foreach ($byArea as $area => $slugs) : ?>
        <optgroup label="<?= esc(SeededText::area((string) $area), 'attr') ?>">
          <?php foreach ($slugs as $s) : ?>
            <option value="<?= esc($s, 'attr') ?>"<?= $s === $slug ? ' selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </optgroup>
      <?php endforeach ?>
    </select>
  </div>
  <div class="rw-field">
    <label class="rw-label" for="rw-check-user"><?= lang('RoleWarden.panel.check.user') ?></label>
    <input class="rw-input" id="rw-check-user" name="user" type="search" value="<?= esc($who, 'attr') ?>" placeholder="<?= esc(lang('RoleWarden.panel.check.userPlaceholder'), 'attr') ?>" autocomplete="off">
  </div>
  <button class="rw-btn rw-btn--secondary" type="submit"><?= lang('RoleWarden.panel.check.submit') ?></button>
</form>

<?php if ($unknown) : ?>
  <p class="rw-check-block rw-muted" role="status"><?= esc(lang('RoleWarden.panel.check.unknown', [rw_query('permission')])) ?></p>
<?php endif ?>
<?php if ($who !== '' && $user === null) : ?>
  <p class="rw-check-block rw-muted" role="status"><?= esc(lang('RoleWarden.panel.check.noUser', [$who])) ?></p>
<?php endif ?>

<?php if ($verdict !== null && $user !== null) : ?>
  <div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.check.outcome') ?></h2></div>
  <p class="rw-check-block">
    <strong><?= lang('RoleWarden.panel.check.' . ($verdict['allowed'] ? 'allowed' : 'denied'), [$userLink($user), '<code class="rw-mono">' . esc($slug) . '</code>']) ?></strong>
  </p>
  <h3 class="rw-check-block rw-header"><?= lang('RoleWarden.panel.check.precedence') ?></h3>
  <ol class="rw-check-block rw-check-rules">
    <?php foreach ($rules as $rule) : ?>
      <li<?= $rule === $verdict['rule'] ? ' aria-current="true"' : '' ?>>
        <?= $rule === $verdict['rule'] ? '<strong>' : '' ?><?= lang('RoleWarden.panel.check.rules.' . $rule) ?><?= $rule === $verdict['rule'] ? '</strong> <span class="rw-note">' . lang('RoleWarden.panel.check.decides') . '</span>' : '' ?>
      </li>
    <?php endforeach ?>
  </ol>
  <?php if ($verdict['via'] !== []) : ?>
    <p class="rw-check-block"><span class="rw-header"><?= lang('RoleWarden.panel.check.alsoRoles') ?></span><br>
      <?= implode('<br>', array_map($chain, $verdict['via'])) ?>
    </p>
  <?php endif ?>
<?php endif ?>

<div class="rw-section-title"><span class="rw-section-n"><?= $verdict !== null ? 'B' : 'A' ?></span><h2><?= lang('RoleWarden.panel.check.holders') ?></h2><?php if ($slug !== '') : ?><span class="rw-note"><?= lang('RoleWarden.panel.check.holdersCount') ?> <span class="rw-fig-sm"><?= (int) $total ?></span></span><?php endif ?></div>
<?php if ($slug === '') : ?>
  <p class="rw-check-block rw-muted"><?= lang('RoleWarden.panel.check.pick') ?></p>
<?php else : ?>
  <table class="rw-t-users rw-t-check">
    <thead>
      <tr>
        <th class="rw-m-num" scope="col"></th>
        <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.check.user') ?></th>
        <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.check.why') ?></th>
        <th class="rw-anno rw-header" scope="col" style="color:var(--ink-faint)"><?= lang('RoleWarden.panel.notes') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($holders === []) : ?>
        <tr class="rw-t-empty"><td colspan="4"><?= lang('RoleWarden.panel.check.noHolders') ?></td></tr>
      <?php endif ?>
      <?php foreach ($holders as $i => $row) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ($start + $i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <td><?= $userLink($row['user']) ?></td>
          <td>
            <?= lang('RoleWarden.panel.check.short.' . $row['why']['rule']) ?><?php if ($row['why']['rule'] === 'role') : ?>: <?= implode(', ', array_map($chain, $row['why']['via'])) ?><?php endif ?>
          </td>
          <td class="rw-anno"><a class="rw-note" href="<?= esc(site_url('rolewarden/permissions/check') . '?' . http_build_query(['permission' => $slug, 'user' => $row['user']->id])) ?>"><?= lang('RoleWarden.panel.check.explain') ?></a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?= view('RoleWarden\Views\partials\pager', ['pager' => $pager]) ?>
<?php endif ?>
