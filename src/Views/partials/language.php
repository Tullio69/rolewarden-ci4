<?php
// Language switch for the top bar; the choice is saved in the user's profile, as there.
$languages = RoleWarden\Settings\UserLanguage::available();
$current = service('language')->getLocale();
?>
<?php if (count($languages) > 1) : ?>
<form class="rw-mode" method="post" action="<?= esc(site_url('rolewarden/profile/language')) ?>" aria-label="<?= esc(lang('RoleWarden.panel.account.language'), 'attr') ?>">
  <?= csrf_field() ?>
  <?php foreach ($languages as $locale => $name) : ?>
    <button type="submit" name="language" value="<?= esc($locale, 'attr') ?>" lang="<?= esc($locale, 'attr') ?>" title="<?= esc($name, 'attr') ?>" aria-label="<?= esc($name, 'attr') ?>" aria-pressed="<?= $locale === $current ? 'true' : 'false' ?>"><?= esc(strtoupper($locale)) ?></button>
  <?php endforeach ?>
</form>
<?php endif ?>
