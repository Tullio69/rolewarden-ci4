<?php $isEdit = $role !== null; ?>
<div class="rw-page-head rw-page-head--single">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.nav.roles') ?></div>
    <h1><?= $isEdit ? lang('RoleWarden.panel.roles.editTitle') : lang('RoleWarden.panel.roles.createTitle') ?></h1>
  </div>
</div>

<?php if ($errors !== []) : ?>
  <div class="rw-flash rw-flash--error rw-flash--inset">
    <b>Error:</b>
    <ul>
      <?php foreach ($errors as $error) : ?>
        <li><?= esc($error) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>

<form class="rw-form" method="post" action="<?= esc($isEdit ? site_url('rolewarden/roles/' . $role['id']) : site_url('rolewarden/roles')) ?>">
  <?= csrf_field() ?>

  <?php if (! $isEdit) : ?>
    <div class="rw-field">
      <label class="rw-label" for="slug"><?= lang('RoleWarden.panel.roles.slug') ?></label>
      <input class="rw-input" type="text" id="slug" name="slug" value="<?= esc(old('slug')) ?>" required pattern="[a-z][a-z0-9-]*">
      <p class="rw-field-hint"><?= lang('RoleWarden.panel.roles.slugHint') ?></p>
    </div>
  <?php endif ?>

  <div class="rw-field">
    <label class="rw-label" for="name"><?= lang('RoleWarden.panel.roles.name') ?></label>
    <input class="rw-input" type="text" id="name" name="name" value="<?= esc(old('name', $isEdit ? (string) $role['name'] : '')) ?>" required minlength="2">
  </div>

  <div class="rw-field">
    <label class="rw-label" for="description"><?= lang('RoleWarden.panel.roles.description') ?></label>
    <textarea class="rw-input rw-textarea" id="description" name="description" rows="3"><?= esc(old('description', $isEdit ? (string) ($role['description'] ?? '') : '')) ?></textarea>
  </div>

  <div class="rw-field">
    <label class="rw-label" for="parent_id"><?= lang('RoleWarden.panel.roles.parent') ?></label>
    <select class="rw-select" id="parent_id" name="parent_id">
      <option value=""><?= lang('RoleWarden.panel.roles.noParent') ?></option>
      <?php foreach ($roles as $option) : ?>
        <option value="<?= (int) $option['id'] ?>"<?= $isEdit && (int) ($role['parent_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>><?= esc($option['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="rw-check-field rw-field--last">
    <label class="rw-check-row">
      <input class="rw-check" type="checkbox" name="is_super_admin" value="1"<?= $isEdit && (int) $role['is_super_admin'] === 1 ? ' checked' : '' ?>>
      <?= lang('RoleWarden.panel.roles.superAdmin') ?>
    </label>
    <p class="rw-field-hint"><?= lang('RoleWarden.panel.roles.superAdminHint') ?></p>
  </div>

  <div class="rw-form-actions">
    <button class="rw-btn rw-btn--primary" type="submit"><?= lang('RoleWarden.panel.save') ?></button>
    <a class="rw-btn rw-btn--secondary" href="<?= esc(site_url('rolewarden/roles')) ?>"><?= lang('RoleWarden.panel.cancel') ?></a>
  </div>
</form>
