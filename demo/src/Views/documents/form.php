<?php
/**
 * New document, or edit one of your own.
 *
 * @var array<string, mixed>|null $document
 * @var array<string, string>     $errors field => message
 */
$isEdit = $document !== null;
$value = static fn (string $field): string => (string) old($field, $document[$field] ?? '');
?>
<div class="rw-page-head rw-page-head--single">
  <div>
    <div class="rw-header"><?= lang('Demo.overline') ?></div>
    <h1><?= $isEdit ? lang('Demo.editTitle') : lang('Demo.new') ?></h1>
    <?php if ($isEdit && $document['status'] !== 'draft') : ?><p><?= lang('Demo.backToDraft') ?></p><?php endif ?>
  </div>
</div>

<?php if ($errors !== []) : ?>
  <div class="rw-flash rw-flash--error rw-flash--inset">
    <b><?= lang('RoleWarden.panel.toast.error') ?></b>
    <ul>
      <?php foreach ($errors as $error) : ?>
        <li><?= esc($error) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>

<form class="rw-form" method="post" action="<?= esc(site_url($isEdit ? 'demo/' . $document['id'] : 'demo')) ?>">
  <?= csrf_field() ?>
  <div class="rw-field">
    <label class="rw-label" for="demo-title"><?= lang('Demo.fieldTitle') ?></label>
    <input class="rw-input" id="demo-title" name="title" maxlength="160" required value="<?= esc($value('title'), 'attr') ?>">
  </div>
  <div class="rw-field">
    <label class="rw-label" for="demo-body"><?= lang('Demo.body') ?></label>
    <textarea class="rw-input rw-textarea" id="demo-body" name="body" rows="8" required><?= esc($value('body')) ?></textarea>
  </div>
  <div class="rw-form-actions">
    <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.save') ?></button>
    <a class="rw-btn rw-btn--secondary" href="<?= esc(site_url('demo')) ?>"><?= lang('RoleWarden.panel.cancel') ?></a>
  </div>
</form>
