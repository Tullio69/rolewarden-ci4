<?php
/**
 * Appearance: theme customiser (SPEC "Personalizzatore"). Every change is applied
 * to this very page as it is made, sidebar and top bar included; saving writes the
 * theme file of the host application.
 *
 * @var array{base: string, radius: int|null, density: string|null, colors: array{light: array<string, string>, dark: array<string, string>}} $theme
 * @var bool                  $pinned the host keeps app/Config/RoleWarden/theme.json, which wins
 * @var array<string, string> $errors field => message
 */

use RoleWarden\Settings\Theme;

$old = static fn (string $key, $default) => is_string(old($key)) ? old($key) : $default;
// After a rejected save the form shows what was posted (only strings survive).
$posted = old('colors');
$colorsFor = static fn (string $mode): array => array_map('strval', array_filter(
    is_array($posted[$mode] ?? null) ? $posted[$mode] : $theme['colors'][$mode],
    static fn ($value): bool => is_string($value) && $value !== '',
));
$config = [
    'base' => (string) $old('base', $theme['base']),
    'radius' => (string) $old('radius', $theme['radius'] === null ? '' : (string) $theme['radius']),
    'density' => (string) $old('density', $theme['density'] ?? ''),
    'colors' => [
        'light' => $colorsFor('light'),
        'dark' => $colorsFor('dark'),
    ],
    'densities' => Theme::DENSITIES,
    'labels' => [
        'warning' => lang('RoleWarden.panel.toast.warning'),
        'allClear' => lang('RoleWarden.panel.appearance.allClear'),
        'below' => lang('RoleWarden.panel.appearance.below'),
        'pairs' => [
            'ink' => lang('RoleWarden.panel.appearance.pair.ink'),
            'ink-muted' => lang('RoleWarden.panel.appearance.pair.inkMuted'),
            'ink-faint' => lang('RoleWarden.panel.appearance.pair.inkFaint'),
            'accent' => lang('RoleWarden.panel.appearance.pair.accent'),
            'on-accent' => lang('RoleWarden.panel.appearance.pair.onAccent'),
            'granted' => lang('RoleWarden.panel.appearance.pair.granted'),
            'denied' => lang('RoleWarden.panel.appearance.pair.denied'),
        ],
        'modes' => ['light' => lang('RoleWarden.panel.appearance.light'), 'dark' => lang('RoleWarden.panel.appearance.dark')],
    ],
];
$disabled = $pinned ? ' disabled' : '';
$error = static fn (string $field): string => isset($errors[$field])
    ? '<p class="rw-field-error" id="rw-' . $field . '-error"><b>' . lang('RoleWarden.panel.toast.error') . '</b> ' . esc($errors[$field]) . '</p>'
    : '';
$colorNames = ['surface' => lang('RoleWarden.panel.appearance.surface'), 'ink' => lang('RoleWarden.panel.appearance.ink'), 'accent' => lang('RoleWarden.panel.appearance.accent')];
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header">RoleWarden</div>
    <h1><?= lang('RoleWarden.panel.appearance.title') ?></h1>
    <p><?= lang('RoleWarden.panel.appearance.intro') ?></p>
    <?php if ($pinned) : ?><p class="rw-note"><?= lang('RoleWarden.panel.appearance.pinnedNote') ?></p><?php endif ?>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.appearance.savedIn') ?></dt><dd><code class="rw-mono"><?= esc($pinned ? 'app/Config/RoleWarden/theme.json' : 'writable/rolewarden/theme.json') ?></code></dd></div>
    <div><dt><?= lang('RoleWarden.panel.appearance.file') ?></dt><dd><a href="<?= esc(site_url('rolewarden/appearance/download')) ?>"><?= lang('RoleWarden.panel.appearance.download') ?></a></dd></div>
  </dl>
</div>

<form method="post" action="<?= esc(site_url('rolewarden/appearance')) ?>" novalidate x-data="rwAppearance(<?= esc(json_encode($config, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)">
  <?= csrf_field() ?>

  <div class="rw-bar rw-settings-bar" role="status" aria-live="polite">
    <div class="rw-bar-msg">
      <template x-if="warnings().length">
        <span><b class="rw-appearance-warn" x-text="labels.warning"></b> <span x-text="warnings().join(' · ')"></span></span>
      </template>
      <span class="rw-muted" x-show="! warnings().length" x-text="labels.allClear"></span>
    </div>
    <?php if (! $pinned) : ?>
      <div class="rw-bar-actions">
        <button type="submit" class="rw-btn rw-btn--primary"><?= lang('RoleWarden.panel.appearance.save') ?></button>
      </div>
    <?php endif ?>
  </div>

  <div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.appearance.sectionTheme') ?></h2></div>
  <table class="rw-ledger rw-settings">
    <tbody>
      <?php $i = 0; foreach (Theme::bases() as $base => $label) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ++$i, 2, '0', STR_PAD_LEFT) ?></td>
          <th class="rw-l-key" scope="row">
            <label class="rw-check-row"><input class="rw-radio" type="radio" name="base" value="<?= esc($base, 'attr') ?>" x-model="base"<?= $config['base'] === $base ? ' checked' : '' ?><?= $disabled ?>> <?= esc($label) ?></label>
            <small><?= in_array($base, Theme::BASES, true) ? lang('RoleWarden.panel.appearance.theme.' . $base . 'Help') : lang('RoleWarden.panel.appearance.theme.extraHelp') ?></small>
          </th>
          <td class="rw-l-value"></td>
          <td class="rw-anno"></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?= $error('base') ?>

  <div class="rw-section-title"><span class="rw-section-n">B</span><h2><?= lang('RoleWarden.panel.appearance.sectionColors') ?></h2></div>
  <table class="rw-ledger rw-settings rw-appearance-colors">
    <thead>
      <tr><th class="rw-m-num"></th><th class="rw-l-key"></th><th class="rw-header"><?= lang('RoleWarden.panel.appearance.light') ?></th><th class="rw-header"><?= lang('RoleWarden.panel.appearance.dark') ?></th></tr>
    </thead>
    <tbody>
      <?php $n = 0; foreach ($colorNames as $key => $name) : ?>
        <tr>
          <td class="rw-m-num"><?= str_pad((string) ++$n, 2, '0', STR_PAD_LEFT) ?></td>
          <th class="rw-l-key" scope="row"><b><?= esc($name) ?></b><small><?= lang('RoleWarden.panel.appearance.' . $key . 'Help') ?></small></th>
          <?php foreach (['light', 'dark'] as $mode) : ?>
            <td class="rw-appearance-color">
              <input type="color" id="rw-<?= $mode ?>-<?= $key ?>" :value="effective('<?= $mode ?>', '<?= $key ?>')" @input="setColor('<?= $mode ?>', '<?= $key ?>', $event.target.value)" aria-label="<?= esc($name . ', ' . $config['labels']['modes'][$mode], 'attr') ?>"<?= $disabled ?>>
              <code class="rw-mono" x-text="effective('<?= $mode ?>', '<?= $key ?>')"></code>
              <input type="hidden" name="colors[<?= $mode ?>][<?= $key ?>]" :value="colors.<?= $mode ?>['<?= $key ?>'] || ''">
              <?php if (! $pinned) : ?>
                <button type="button" class="rw-btn rw-btn--ghost rw-btn--sm" x-show="colors.<?= $mode ?>['<?= $key ?>']" x-cloak @click="clearColor('<?= $mode ?>', '<?= $key ?>')"><?= lang('RoleWarden.panel.appearance.useTheme') ?></button>
              <?php endif ?>
              <?= $error($mode . '-' . $key) ?>
            </td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <?php foreach (['light', 'dark'] as $mode) : ?>
    <?php foreach (['accent-hover', 'accent-tint', 'on-accent'] as $derived) : ?>
      <input type="hidden" name="colors[<?= $mode ?>][<?= $derived ?>]" :value="colors.<?= $mode ?>['<?= $derived ?>'] || ''">
    <?php endforeach ?>
  <?php endforeach ?>
  <p class="rw-hint rw-note"><?= lang('RoleWarden.panel.appearance.derivedHint') ?></p>

  <div class="rw-section-title"><span class="rw-section-n">C</span><h2><?= lang('RoleWarden.panel.appearance.sectionShape') ?></h2></div>
  <table class="rw-ledger rw-settings">
    <tbody>
      <tr>
        <td class="rw-m-num">01</td>
        <th class="rw-l-key" scope="row"><label for="rw-radius"><?= lang('RoleWarden.panel.appearance.radius') ?></label><small><?= lang('RoleWarden.panel.appearance.radiusHelp') ?></small></th>
        <td class="rw-l-value">
          <select class="rw-select" id="rw-radius" name="radius" x-model="radius"<?= $disabled ?>>
            <option value=""><?= lang('RoleWarden.panel.appearance.fromTheme') ?></option>
            <?php foreach (Theme::RADII as $px) : ?>
              <option value="<?= $px ?>"<?= $config['radius'] === (string) $px ? ' selected' : '' ?>><?= $px ?> px</option>
            <?php endforeach ?>
          </select>
          <?= $error('radius') ?>
        </td>
        <td class="rw-anno"></td>
      </tr>
      <tr>
        <td class="rw-m-num">02</td>
        <th class="rw-l-key" scope="row"><label for="rw-density"><?= lang('RoleWarden.panel.appearance.density') ?></label><small><?= lang('RoleWarden.panel.appearance.densityHelp') ?></small></th>
        <td class="rw-l-value">
          <select class="rw-select" id="rw-density" name="density" x-model="density"<?= $disabled ?>>
            <option value=""><?= lang('RoleWarden.panel.appearance.fromTheme') ?></option>
            <?php foreach (array_keys(Theme::DENSITIES) as $density) : ?>
              <option value="<?= $density ?>"<?= $config['density'] === $density ? ' selected' : '' ?>><?= lang('RoleWarden.panel.appearance.densities.' . $density) ?></option>
            <?php endforeach ?>
          </select>
          <?= $error('density') ?>
        </td>
        <td class="rw-anno"></td>
      </tr>
    </tbody>
  </table>
</form>

<div class="rw-section-title"><span class="rw-section-n">D</span><h2><?= lang('RoleWarden.panel.appearance.sectionSample') ?></h2></div>
<div class="rw-appearance-sample">
  <p class="rw-muted"><?= lang('RoleWarden.panel.appearance.sampleIntro') ?></p>
  <div class="rw-appearance-row">
    <button type="button" class="rw-btn rw-btn--primary"><?= lang('RoleWarden.panel.appearance.sample.primary') ?></button>
    <button type="button" class="rw-btn rw-btn--secondary"><?= lang('RoleWarden.panel.appearance.sample.secondary') ?></button>
    <button type="button" class="rw-btn rw-btn--ghost"><?= lang('RoleWarden.panel.appearance.sample.ghost') ?></button>
    <button type="button" class="rw-btn rw-btn--danger"><?= lang('RoleWarden.panel.appearance.sample.danger') ?></button>
    <input class="rw-input" type="text" value="dana@northwind-studio.test" aria-label="<?= esc(lang('RoleWarden.panel.users.email'), 'attr') ?>">
  </div>
  <div class="rw-appearance-row">
    <span class="rw-role">Editor</span><span class="rw-role rw-role--parent">Author</span>
    <span class="rw-status"><?= lang('RoleWarden.panel.users.active') ?></span><span class="rw-status rw-status--disabled"><?= lang('RoleWarden.panel.users.inactive') ?></span>
    <code class="rw-mono">users.delete</code>
    <a href="#rw-appearance-sample"><?= lang('RoleWarden.panel.appearance.sample.link') ?></a>
  </div>
  <div class="rw-toast rw-toast--error rw-appearance-toast"><p><span class="rw-toast__kind"><?= lang('RoleWarden.panel.toast.error') ?></span> <?= lang('RoleWarden.panel.appearance.sample.toast') ?></p></div>
</div>

<?php if (! $pinned) : ?>
  <div class="rw-section-title"><span class="rw-section-n">E</span><h2><?= lang('RoleWarden.panel.appearance.sectionReset') ?></h2></div>
  <table class="rw-ledger">
    <tbody>
      <tr>
        <td class="rw-m-num"></td>
        <th class="rw-l-key" scope="row"><b><?= lang('RoleWarden.panel.appearance.reset') ?></b><small><?= lang('RoleWarden.panel.appearance.resetHelp') ?></small></th>
        <td class="rw-l-value"></td>
        <td class="rw-l-ctl"><button class="rw-btn rw-btn--danger rw-btn--sm" type="button" onclick="rwConfirm('confirm-reset-theme')"><?= lang('RoleWarden.panel.appearance.reset') ?></button></td>
        <td class="rw-anno"><span class="rw-note"><?= lang('RoleWarden.panel.appearance.resetNote') ?></span></td>
      </tr>
    </tbody>
  </table>
  <dialog id="confirm-reset-theme" class="rw-confirm">
    <h2><?= lang('RoleWarden.panel.appearance.resetTitle') ?></h2>
    <p><?= lang('RoleWarden.panel.appearance.resetBody') ?></p>
    <div class="rw-confirm-actions">
      <button type="button" class="rw-btn rw-btn--ghost" onclick="rwCancelConfirm('confirm-reset-theme')"><?= lang('RoleWarden.panel.cancel') ?></button>
      <form method="post" action="<?= esc(site_url('rolewarden/appearance/reset')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="rw-btn rw-btn--danger"><?= lang('RoleWarden.panel.appearance.reset') ?></button>
      </form>
    </div>
  </dialog>
<?php endif ?>
