<?php
/**
 * Dashboard block "roles" (dashboard.roles): who holds which role, as one bar
 * split by share with a legend of the names under it and the numbers in a
 * <details>, as under every chart. A user holding two roles counts in both.
 *
 * @var array{roles: list<array{name: string, total: int}>, users: int, count: int} $roles
 * @var string $letter
 * @var bool   $half
 */

use RoleWarden\Dashboard\Dashboard;

$n = static fn (int $value): string => lang('RoleWarden.panel.dashboard.number', [$value]);
$shares = Dashboard::shares($roles['roles'], Dashboard::SHARES);
$sum = array_sum(array_column($shares, 'total'));
$name = static fn (?string $role): string => $role ?? lang('RoleWarden.panel.dashboard.roles.other');
$label = $shares === []
    ? lang('RoleWarden.panel.dashboard.roles.empty')
    : implode(', ', array_map(static fn (array $s): string => $name($s['name']) . ' ' . $s['total'], $shares));
?>
<section class="<?= $half ? 'rw-dash-half' : 'rw-dash-full' ?>" aria-labelledby="rw-dash-roles">
  <div class="rw-section-title">
    <span class="rw-section-n"><?= $letter ?></span>
    <h2 id="rw-dash-roles"><?= lang('RoleWarden.panel.dashboard.roles.title') ?></h2>
    <span class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.roles.note', [$roles['users'], $roles['count']])) ?></span>
  </div>
  <div class="rw-dash-body">
    <?php if ($shares === []) : ?>
      <p class="rw-muted"><?= esc($label) ?></p>
    <?php else : ?>
      <div class="rw-dash-split" role="img" aria-label="<?= esc($label, 'attr') ?>">
        <?php foreach ($shares as $i => $share) : ?><i class="rw-share-<?= $i ?>" style="flex-grow: <?= (int) $share['total'] ?>"></i><?php endforeach ?>
      </div>
      <ul class="rw-legend rw-dash-shares" aria-hidden="true">
        <?php foreach ($shares as $i => $share) : ?><li><i class="rw-dash-key rw-share-<?= $i ?>"></i><?= esc($name($share['name'])) ?></li><?php endforeach ?>
      </ul>
      <details class="rw-dash-numbers">
        <summary><?= lang('RoleWarden.panel.dashboard.numbers') ?></summary>
        <table class="rw-dash-ledger">
          <caption class="rw-visually-hidden"><?= lang('RoleWarden.panel.dashboard.roles.title') ?></caption>
          <tbody>
            <?php foreach ($shares as $i => $share) : ?>
              <tr>
                <th scope="row"><i class="rw-share-<?= $i ?>" aria-hidden="true"></i><?= esc($name($share['name'])) ?></th>
                <td class="rw-note"><?= esc(lang('RoleWarden.panel.dashboard.percent', [$sum > 0 ? (int) round($share['total'] / $sum * 100) : 0])) ?></td>
                <td><?= esc($n($share['total'])) ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </details>
    <?php endif ?>
  </div>
</section>
