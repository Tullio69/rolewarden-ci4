<?php
/**
 * Settings screen (docs/design-system/components/Settings). Sign-in arrives in
 * V2, Roles in V1; later milestones add their own sections to the same ledger.
 *
 * @var list<array<string, mixed>> $roles       roles that may be the default (no super admin)
 * @var string                     $defaultRole slug, '' for none
 * @var array<string, int>         $signIn      RoleWarden\Settings\SignIn::values()
 * @var array<string, string>      $errors      field => message
 * @var string|null                $changedAt
 * @var string|null                $changedBy
 * @var bool                       $canUpdate
 */

use RoleWarden\Settings\SignIn;

$saved = ['default_role' => $defaultRole] + array_map('strval', $signIn);
// After a rejected save the controls show what was posted, marked as changed.
$shown = [];
foreach ($saved as $key => $value) {
    $shown[$key] = (string) old($key, $value);
}
$settingsConfig = [
    'values' => $saved,
    'current' => $shown,
    'names' => [
        'session_lifetime' => lang('RoleWarden.panel.settings.sessionLifetime'),
        'remember_length' => lang('RoleWarden.panel.settings.remember'),
        'lock_attempts' => lang('RoleWarden.panel.settings.lockAttempts'),
        'lock_minutes' => lang('RoleWarden.panel.settings.lockMinutes'),
        'sign_in_rate' => lang('RoleWarden.panel.settings.signInRate'),
        'default_role' => lang('RoleWarden.panel.settings.defaultRole'),
    ],
    'labels' => ['one' => lang('RoleWarden.panel.settings.unsavedOne'), 'many' => lang('RoleWarden.panel.settings.unsavedMany')],
];
$disabled = $canUpdate ? '' : ' disabled';

$duration = static function (int $seconds): string {
    if ($seconds === 0) {
        return lang('RoleWarden.panel.settings.off');
    }
    foreach ([86400 => 'days', 3600 => 'hours', 60 => 'minutes'] as $unit => $key) {
        if ($seconds % $unit === 0) {
            return lang('RoleWarden.panel.settings.' . $key, [$seconds / $unit]);
        }
    }

    return lang('RoleWarden.panel.settings.seconds', [$seconds]);
};

$error = static fn (string $field): string => isset($errors[$field])
    ? '<p class="rw-field-error" id="rw-' . $field . '-error"><b>' . lang('RoleWarden.panel.toast.error') . '</b> ' . esc($errors[$field]) . '</p>'
    : '';
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="rw-' . $field . '-error"' : '';

$select = static function (string $field, array $choices) use ($shown, $disabled, $duration, $invalid): string {
    $html = '<select class="rw-select" id="rw-' . $field . '" name="' . $field . '" x-model="cur.' . $field . '"' . $disabled . $invalid($field) . '>';
    foreach ($choices as $seconds) {
        $html .= '<option value="' . $seconds . '"' . ((string) $seconds === $shown[$field] ? ' selected' : '') . '>' . esc($duration($seconds)) . '</option>';
    }

    return $html . '</select>';
};

$number = static function (string $field, string $unit) use ($shown, $disabled, $invalid): string {
    [, $min, $max] = SignIn::NUMBERS[$field];

    return '<span class="rw-unit"><input class="rw-input" type="number" id="rw-' . $field . '" name="' . $field . '" min="' . $min . '" max="' . $max . '" required'
        . ' value="' . esc($shown[$field], 'attr') . '" x-model="cur.' . $field . '"' . $disabled . $invalid($field) . '>'
        . '<span class="rw-muted">' . $unit . '</span></span>';
};

$rows = [
    ['session_lifetime', lang('RoleWarden.panel.settings.sessionLifetimeHelp'), $select('session_lifetime', SignIn::choices(SignIn::LIFETIMES, $signIn['session_lifetime'])), ''],
    ['remember_length', lang('RoleWarden.panel.settings.rememberHelp'), $select('remember_length', SignIn::choices(SignIn::REMEMBER, $signIn['remember_length'])), lang('RoleWarden.panel.settings.rememberNote')],
    ['lock_attempts', lang('RoleWarden.panel.settings.lockAttemptsHelp'), $number('lock_attempts', lang('RoleWarden.panel.settings.attemptsUnit')), ''],
    ['lock_minutes', lang('RoleWarden.panel.settings.lockMinutesHelp'), $number('lock_minutes', lang('RoleWarden.panel.settings.minutesUnit')), ''],
    ['sign_in_rate', lang('RoleWarden.panel.settings.signInRateHelp'), $number('sign_in_rate', lang('RoleWarden.panel.settings.rateUnit')), ''],
];
$changedNote = esc(lang('RoleWarden.panel.settings.changed'), 'js');
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

<form method="post" action="<?= esc(site_url('rolewarden/settings')) ?>" novalidate x-data="rwSettings(<?= esc(json_encode($settingsConfig, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)">
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

  <div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.settings.sectionSignIn') ?></h2></div>
  <table class="rw-ledger rw-settings">
    <tbody>
      <?php foreach ($rows as $i => [$field, $help, $control, $note]) : ?>
        <tr :class="{ 'rw-changed': isChanged('<?= $field ?>') }">
          <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <th class="rw-l-key" scope="row">
            <label for="rw-<?= $field ?>"><?= esc($settingsConfig['names'][$field]) ?></label>
            <small><?= $help ?></small>
          </th>
          <td class="rw-l-value"><?= $control ?><?= $error($field) ?></td>
          <td class="rw-anno"><span class="rw-note" x-text="isChanged('<?= $field ?>') ? '<?= $changedNote ?>' : '<?= esc($note, 'js') ?>'"><?= esc($note) ?></span></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div class="rw-section-title"><span class="rw-section-n">B</span><h2><?= lang('RoleWarden.panel.settings.sectionRoles') ?></h2></div>
  <table class="rw-ledger rw-settings">
    <tbody>
      <tr :class="{ 'rw-changed': isChanged('default_role') }">
        <td class="rw-m-num"><?= str_pad((string) (count($rows) + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <th class="rw-l-key" scope="row">
          <label for="rw-default-role"><?= lang('RoleWarden.panel.settings.defaultRole') ?></label>
          <small><?= lang('RoleWarden.panel.settings.defaultRoleHelp') ?></small>
        </th>
        <td class="rw-l-value">
          <select class="rw-select" id="rw-default-role" name="default_role" x-model="cur.default_role"<?= $disabled ?>>
            <option value=""<?= $shown['default_role'] === '' ? ' selected' : '' ?>><?= lang('RoleWarden.panel.settings.none') ?></option>
            <?php foreach ($roles as $role) : ?>
              <option value="<?= esc($role['slug'], 'attr') ?>"<?= $role['slug'] === $shown['default_role'] ? ' selected' : '' ?>><?= esc($role['name']) ?></option>
            <?php endforeach ?>
          </select>
        </td>
        <td class="rw-anno"><span class="rw-note" x-text="isChanged('default_role') ? '<?= $changedNote ?>' : ''"></span></td>
      </tr>
    </tbody>
  </table>
</form>
