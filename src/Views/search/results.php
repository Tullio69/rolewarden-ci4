<?php
/**
 * Command search results (docs/design-system/components/CommandSearch), grouped
 * under Permission, Go to and Actions. In the overlay they are the options of
 * its listbox; on the search page, plain links.
 *
 * @var string                                  $query
 * @var array<string, list<array<string, mixed>>> $groups
 * @var bool                                    $page   rendered on the search page, not in the overlay
 */

use RoleWarden\Settings\Sidebar;

$n = 0;
?>
<?php if ($groups === []) : ?>
  <p class="rw-cmd-empty"><?= esc(lang('RoleWarden.panel.search.empty', [$query])) ?></p>
<?php endif ?>
<?php foreach ($groups as $key => $items) : ?>
  <div class="rw-cmd-group"<?= $page ? '' : ' role="group" aria-labelledby="rw-cmd-g-' . $key . '"' ?>>
    <span class="rw-cmd-label" id="rw-cmd-g-<?= $key ?>"><?= lang('RoleWarden.panel.search.groups.' . $key) ?></span>
    <?php foreach ($items as $item) : ?>
      <a class="rw-cmd-opt" id="rw-cmd-o-<?= $n++ ?>" href="<?= esc($item['url']) ?>"<?= $page ? '' : ' role="option" aria-selected="false"' ?>>
        <?= Sidebar::icon($item['icon']) ?>
        <span class="rw-cmd-text">
          <?php if (isset($item['slug'])) : ?>
            <span><code class="rw-mono"><?= esc($item['slug']) ?></code> <span class="rw-muted">&middot; <?= lang('RoleWarden.panel.search.who') ?></span></span>
            <?php if (isset($item['answer'])) : ?><small><?= esc($item['answer']) ?></small><?php endif ?>
          <?php else : ?>
            <span><?= esc($item['label']) ?></span>
          <?php endif ?>
        </span>
        <?php if (($item['count'] ?? null) !== null) : ?><span class="rw-note"><?= esc($item['count']) ?></span><?php endif ?>
        <kbd class="rw-cmd-enter"><?= lang('RoleWarden.panel.search.enterKey') ?></kbd>
      </a>
    <?php endforeach ?>
  </div>
<?php endforeach ?>
