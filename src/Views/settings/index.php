<?php
/**
 * Settings screen (docs/design-system/components/Settings). In V1 only the Roles
 * section exists; later milestones add their own sections to the same ledger.
 *
 * @var list<array<string, mixed>> $roles       roles that may be the default (no super admin)
 * @var string                     $defaultRole slug, '' for none
 * @var string|null                $changedAt
 * @var string|null                $changedBy
 * @var bool                       $canUpdate
 */
$settingsConfig = [
    'values' => ['default_role' => $defaultRole],
    'names' => ['default_role' => lang('RoleWarden.panel.settings.defaultRole')],
    'labels' => ['one' => lang('RoleWarden.panel.settings.unsavedOne'), 'many' => lang('RoleWarden.panel.settings.unsavedMany')],
];
$disabled = $canUpdate ? '' : ' disabled';
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header">RoleWarden</div>
    <h1><?= lang('RoleWarden.panel.settings.title') ?></h1>
    <p><?= lang('RoleWarden.panel.settings.intro') ?></p>
    <?php if (! $canUpdate) : ?><p class="rw-note"><?= lang('RoleWarden.panel.settings.readOnly') ?></p><?php endif ?>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.settings.lastChanged') ?></dt><dd><?= $changedAt ? esc(date('d M Y', strtotime((string) $changedAt))) : lang('RoleWarden.panel.settings.never') ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.settings.by') ?></dt><dd><?= $changedBy !== null ? esc($changedBy) : '&mdash;' ?></dd></div>
  </dl>
</div>

<form method="post" action="<?= esc(site_url('rolewarden/settings')) ?>" x-data="rwSettings(<?= esc(json_encode($settingsConfig, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)">
  <?= csrf_field() ?>

  <?php if ($canUpdate) : ?>
    <div class="rw-bar rw-settings-bar" :class="{ 'rw-bar--dirty': changed().length }" role="status" aria-live="polite">
      <template x-if="changed().length">
        <div class="rw-bar-msg">
          <span class="rw-bar-dot" aria-hidden="true"></span>
          <b x-text="summary()"></b>
          <span class="rw-muted" x-text="changedNames()"></span>
        </div>
      </template>
      <div class="rw-bar-msg rw-muted" x-show="! changed().length"><?= lang('RoleWarden.panel.settings.allSaved') ?></div>
      <div class="rw-bar-actions">
        <button type="button" class="rw-btn rw-btn--ghost" x-show="changed().length" x-cloak @click="discard()"><?= lang('RoleWarden.panel.settings.discard') ?></button>
        <button type="submit" class="rw-btn rw-btn--primary" :disabled="! changed().length"><?= lang('RoleWarden.panel.settings.save') ?></button>
      </div>
    </div>
  <?php endif ?>

  <div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.settings.sectionRoles') ?></h2></div>
  <table class="rw-ledger rw-settings">
    <tbody>
      <tr :class="{ 'rw-changed': isChanged('default_role') }">
        <td class="rw-m-num">01</td>
        <th class="rw-l-key" scope="row">
          <label for="rw-default-role"><?= lang('RoleWarden.panel.settings.defaultRole') ?></label>
          <small><?= lang('RoleWarden.panel.settings.defaultRoleHelp') ?></small>
        </th>
        <td class="rw-l-value">
          <select class="rw-select" id="rw-default-role" name="default_role" x-model="cur.default_role"<?= $disabled ?>>
            <option value=""<?= $defaultRole === '' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.settings.none') ?></option>
            <?php foreach ($roles as $role) : ?>
              <option value="<?= esc($role['slug'], 'attr') ?>"<?= $role['slug'] === $defaultRole ? ' selected' : '' ?>><?= esc($role['name']) ?></option>
            <?php endforeach ?>
          </select>
        </td>
        <td class="rw-anno"><span class="rw-note" x-text="isChanged('default_role') ? '<?= esc(lang('RoleWarden.panel.settings.changed'), 'js') ?>' : ''"></span></td>
      </tr>
    </tbody>
  </table>
</form>
