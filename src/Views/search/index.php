<?php
/**
 * The search page: what the sidebar's search opens without JavaScript, with
 * the same results as the Ctrl K overlay.
 *
 * @var string                                  $query
 * @var array<string, list<array<string, mixed>>> $groups
 */
?>
<div class="rw-page-head rw-page-head--single">
  <div>
    <h1><?= lang('RoleWarden.panel.search.title') ?></h1>
    <p><?= lang('RoleWarden.panel.search.scope') ?></p>
  </div>
</div>

<form class="rw-toolbar" method="get" action="<?= esc(site_url('rolewarden/search')) ?>" role="search">
  <div class="rw-field">
    <label class="rw-label" for="rw-search-q"><?= lang('RoleWarden.panel.search.field') ?></label>
    <input class="rw-input" id="rw-search-q" name="q" type="search" value="<?= esc($query, 'attr') ?>" autocomplete="off">
  </div>
  <button class="rw-btn rw-btn--secondary" type="submit"><?= lang('RoleWarden.panel.search.submit') ?></button>
</form>

<div class="rw-cmd-page">
  <?= view('RoleWarden\Views\search\results', ['query' => $query, 'groups' => $groups, 'page' => true]) ?>
</div>
