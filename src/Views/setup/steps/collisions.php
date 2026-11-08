<?php
/**
 * One card per host group named like a system role: merge, rename or skip, each with its consequence.
 *
 * @var list<array{slug: string, title: string, users: int, permissions: int}> $groups the colliding ones
 */
?>
<?php foreach ($groups as $g) : ?>
  <?php
    $slug = $g['slug'];
    $base = 'collisions[' . esc($slug, 'attr') . ']';
    $id = 'rw-setup-' . esc($slug, 'attr');
    $action = $posted['collisions'][$slug]['action'] ?? $choices['collisions'][$slug]['action'] ?? '';
    $newSlug = $posted['collisions'][$slug]['newSlug'] ?? $choices['collisions'][$slug]['newSlug'] ?? 'app-' . $slug;
    $newSlug = is_string($newSlug) ? $newSlug : '';
    $radio = static fn (string $value): string => '<input class="rw-radio" type="radio" name="' . $base . '[action]" value="' . $value . '"' . ($action === $value ? ' checked' : '') . '>';
  ?>
  <div class="rw-auth-row rw-install-block<?= isset($errors[$slug]) ? ' rw-auth-row--invalid' : '' ?>">
    <span class="rw-m-num"><?= $num() ?></span>
    <fieldset class="rw-setup-fieldset"<?= $invalid($slug) ?>>
      <legend class="rw-label"><span class="rw-mono"><?= esc($slug) ?></span></legend>
      <p class="rw-field-hint"><?= esc($t('collisionHost', [$slug, $g['users'], $g['permissions']])) ?> <?= esc($t('collisionRole.' . $slug)) ?></p>
      <label class="rw-setup-option"><?= $radio('merge') ?><span><b><?= esc($t('merge')) ?></b><span class="rw-field-hint"><?= esc($t('mergeHint', [$slug])) ?></span></span></label>
      <label class="rw-setup-option"><?= $radio('rename') ?><span><b><?= esc($t('rename')) ?></b><span class="rw-field-hint"><?= esc($t('renameHint', [$slug])) ?></span></span></label>
      <div class="rw-setup-sub">
        <label class="rw-label" for="<?= $id ?>-newSlug"><?= esc($t('newSlug')) ?></label>
        <input class="rw-input rw-mono" id="<?= $id ?>-newSlug" name="<?= $base ?>[newSlug]" value="<?= esc($newSlug, 'attr') ?>" autocomplete="off" spellcheck="false">
      </div>
      <label class="rw-setup-option"><?= $radio('skip') ?><span><b><?= esc($t('skip')) ?></b><span class="rw-field-hint"><?= esc($t('skipHint', [$slug, $g['users'], $g['permissions']])) ?></span></span></label>
      <?= $errorFor($slug) ?>
    </fieldset>
  </div>
<?php endforeach ?>
