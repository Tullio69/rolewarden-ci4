<?php
/** @var \CodeIgniter\Pager\Pager $pager */
$pages = $pager->getPageCount();
$current = $pager->getCurrentPage();
?>
<?php if ($pages > 1) : ?>
  <nav aria-label="Pagination">
    <ul class="rw-pager">
      <li>
        <?php if ($current > 1) : ?>
          <a class="rw-page" href="<?= esc($pager->getPreviousPageURI() ?? '#') ?>"><?= lang('RoleWarden.panel.previous') ?></a>
        <?php else : ?>
          <span class="rw-page" aria-disabled="true"><?= lang('RoleWarden.panel.previous') ?></span>
        <?php endif ?>
      </li>
      <?php $gap = false; ?>
      <?php for ($page = 1; $page <= $pages; $page++) : ?>
        <?php // First, last and two either side of the current page; a gap shows as an ellipsis.
        if ($page !== 1 && $page !== $pages && abs($page - $current) > 2) {
            if (! $gap) {
                echo '<li><span class="rw-page" aria-hidden="true">&hellip;</span></li>';
                $gap = true;
            }
            continue;
        }
        $gap = false; ?>
        <li>
          <?php if ($page === $current) : ?>
            <span class="rw-page" aria-current="page"><?= $page ?></span>
          <?php else : ?>
            <a class="rw-page" href="<?= esc($pager->getPageURI($page) ?? '#') ?>" aria-label="Page <?= $page ?>"><?= $page ?></a>
          <?php endif ?>
        </li>
      <?php endfor ?>
      <li>
        <?php if ($current < $pages) : ?>
          <a class="rw-page" href="<?= esc($pager->getNextPageURI() ?? '#') ?>"><?= lang('RoleWarden.panel.next') ?></a>
        <?php else : ?>
          <span class="rw-page" aria-disabled="true"><?= lang('RoleWarden.panel.next') ?></span>
        <?php endif ?>
      </li>
    </ul>
  </nav>
<?php endif ?>
