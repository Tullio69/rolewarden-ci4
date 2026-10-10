<?php
/**
 * Every document, with the actions the signed-in user may take on each.
 * Buttons appear by permission (can()) and, for editing, by ownership: this
 * is what the demo is for.
 *
 * @var list<array<string, mixed>> $documents
 */
$me = (int) auth()->id();
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('Demo.overline') ?></div>
    <h1><?= lang('Demo.documents') ?></h1>
    <p><?= lang('Demo.intro') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('Demo.documents') ?></dt><dd class="rw-fig"><?= count($documents) ?></dd></div>
    <div><dt><?= lang('Demo.yourPermissions') ?></dt><dd class="rw-fig-sm"><?= count(array_filter(permissions(), static fn (string $slug): bool => str_starts_with($slug, 'documents.'))) ?></dd></div>
  </dl>
</div>

<div class="rw-toolbar">
  <span class="rw-muted"><?= lang('Demo.hint') ?></span>
  <div class="rw-grow"></div>
  <?php if (can('documents.create')) : ?>
    <a class="rw-btn rw-btn--primary" href="<?= esc(site_url('demo/create')) ?>"><?= lang('Demo.new') ?></a>
  <?php endif ?>
</div>

<table class="rw-table">
  <thead>
    <tr>
      <th class="rw-num" scope="col"></th>
      <th scope="col"><?= lang('Demo.fieldTitle') ?></th>
      <th scope="col"><?= lang('Demo.owner') ?></th>
      <th scope="col"><?= lang('Demo.status') ?></th>
      <th scope="col"><span hidden><?= lang('Demo.actions') ?></span></th>
    </tr>
  </thead>
  <tbody>
    <?php if ($documents === []) : ?>
      <tr><td colspan="5" class="rw-muted"><?= lang('Demo.empty') ?></td></tr>
    <?php endif ?>
    <?php foreach ($documents as $i => $document) : ?>
      <?php $mine = (int) $document['owner_id'] === $me; ?>
      <tr>
        <td class="rw-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <td><b><?= esc($document['title']) ?></b><br><small class="rw-muted"><?= esc(mb_strimwidth((string) $document['body'], 0, 90, '…')) ?></small></td>
        <td><?= esc((string) ($document['owner_name'] ?? '—')) ?><?= $mine ? ' <span class="rw-note">(' . lang('RoleWarden.panel.users.you') . ')</span>' : '' ?></td>
        <td>
          <span class="rw-status<?= $document['status'] === 'draft' ? ' rw-status--disabled' : '' ?>"><?= lang('Demo.statuses.' . $document['status']) ?></span>
          <?php if ($document['status'] === 'approved' && ! empty($document['approver_name'])) : ?><br><small class="rw-muted"><?= esc(lang('Demo.approvedBy', [$document['approver_name']])) ?></small><?php endif ?>
        </td>
        <td style="white-space:nowrap">
          <?php if ($mine && can('documents.update')) : ?>
            <a class="rw-btn rw-btn--ghost rw-btn--sm" href="<?= esc(site_url('demo/' . $document['id'] . '/edit')) ?>"><?= lang('RoleWarden.panel.edit') ?></a>
            <?php if ($document['status'] === 'draft') : ?>
              <form method="post" action="<?= esc(site_url('demo/' . $document['id'] . '/submit')) ?>" style="display:inline"><?= csrf_field() ?><button class="rw-btn rw-btn--secondary rw-btn--sm" type="submit"><?= lang('Demo.submit') ?></button></form>
            <?php endif ?>
          <?php endif ?>
          <?php if ($document['status'] === 'submitted' && can('documents.approve')) : ?>
            <form method="post" action="<?= esc(site_url('demo/' . $document['id'] . '/approve')) ?>" style="display:inline"><?= csrf_field() ?><button class="rw-btn rw-btn--primary rw-btn--sm" type="submit"><?= lang('Demo.approve') ?></button></form>
          <?php endif ?>
          <?php if (can('documents.delete')) : ?>
            <form method="post" action="<?= esc(site_url('demo/' . $document['id'] . '/delete')) ?>" style="display:inline" onsubmit="return confirm(<?= esc(json_encode(lang('Demo.confirmDelete', [$document['title']])), 'attr') ?>)"><?= csrf_field() ?><button class="rw-btn rw-btn--danger rw-btn--sm" type="submit"><?= lang('RoleWarden.panel.delete') ?></button></form>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
