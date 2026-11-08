<?php
/**
 * The install wizard for an existing application, one step per page, on the
 * installer's sheet. Each step's content is in steps/<step>.php, inside the
 * one form of the page (hidden `step`, Back, Continue).
 *
 * The stylesheet is inlined: until the wizard wires RouteRegistrar, the
 * module's asset route does not exist yet. Font URLs point at that route and
 * work once it does; before, the system fonts stand in.
 *
 * @var string                $step
 * @var array<string, string> $languages locale => name
 * @var array<string, mixed>  $choices   as saved in SetupState
 * @var array<string, string> $errors    field => message
 * @var array<string, mixed>  $posted    the POST of a refused answer
 * @var string|null           $error
 * @var array{ok: bool, failedStep: ?string, messages: list<string>}|null $result a failed apply (confirm)
 * @var bool                  $locked    an apply step is done: answers can no longer change
 * @var bool                  $reopen    locked, but the super admin can still be chosen again (R20)
 */
$t = static fn (string $key, array $args = []): string => lang('RoleWarden.setup.' . $key, $args);
$n = 0;
$num = static function () use (&$n): string {
    return str_pad((string) ++$n, 2, '0', STR_PAD_LEFT);
};
$errorFor = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<span class="rw-auth-err" id="rw-setup-' . esc($field, 'attr') . '-error">' . esc($errors[$field]) . '</span>' : '';
};
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="rw-setup-' . esc($field, 'attr') . '-error"' : '';
/** @param array{file: string, diff: string, manual: bool} $f */
$preview = static function (array $f) use ($t): string {
    if ($f['manual']) {
        return '<p class="rw-field-hint">' . esc($t('fileManual', [$f['file']])) . '</p>';
    }

    if ($f['diff'] === '') {
        return '<p class="rw-field-hint">' . esc($t('fileUnchanged', [$f['file']])) . '</p>';
    }

    return '<span class="rw-label">' . esc($t('fileChange', [$f['file']])) . '</span><pre class="rw-setup-diff" tabindex="0">' . esc($f['diff']) . '</pre>';
};
$steps = ['code' => 1, 'requirements' => 1, 'filters' => 2, 'roles' => 3, 'collisions' => 3, 'newUsers' => 4, 'superAdmin' => 5, 'visibility' => 6, 'confirm' => 7, 'done' => 7];
$locale = service('language')->getLocale();
// A saved answer that no longer holds (R12) cannot be applied again unchanged: only Back helps, unless answers are locked (R15)
// and it is not the super admin, which stays open until its step is done (R20).
$answerInvalid = $result !== null && ! in_array($result['failedStep'], RoleWarden\Install\Setup::STEPS, true) && (! $locked || ($reopen && $result['failedStep'] === 'superAdmin'));
$css = str_replace('../fonts/', 'assets/fonts/', (string) file_get_contents(__DIR__ . '/../../Assets/css/panel.css'));
?>
<!doctype html>
<html lang="<?= esc($locale, 'attr') ?>" data-rw-theme="console">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= esc($t('title')) ?> &middot; RoleWarden</title>
  <style><?= $css ?></style>
</head>
<body>
<div class="rw rw-auth-screen">
  <form class="rw-setup-lang" method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="locale">
    <label class="rw-label" for="rw-setup-locale"><?= esc($t('language')) ?></label>
    <select class="rw-select" id="rw-setup-locale" name="locale">
      <?php foreach ($languages as $code => $name) : ?>
        <option value="<?= esc($code, 'attr') ?>"<?= $code === $locale ? ' selected' : '' ?>><?= esc($name) ?></option>
      <?php endforeach ?>
    </select>
    <button class="rw-btn rw-btn--secondary" type="submit"><?= esc($t('languageApply')) ?></button>
  </form>

  <div class="rw-auth-sheet rw-setup-sheet">
    <div class="rw-auth-row rw-auth-head">
      <span class="rw-m-num"></span>
      <div>
        <?php if ($step !== 'completed') : ?><div class="rw-header"><?= esc($t('overline', [$steps[$step], 7])) ?></div><?php endif ?>
        <h1><?= esc($t($step . 'Title')) ?></h1>
        <p><?= esc($t($step . 'Intro')) ?></p>
      </div>
    </div>

    <?php if ($step === 'done') : ?>
      <?php include __DIR__ . '/steps/done.php' ?>
    <?php elseif ($step === 'completed') : ?>
      <div class="rw-auth-row rw-auth-submit">
        <span class="rw-m-num"></span>
        <div><a class="rw-btn rw-btn--primary" href="<?= esc(site_url('login'), 'attr') ?>"><?= esc(lang('RoleWarden.install.signIn')) ?></a></div>
      </div>
    <?php else : ?>
      <form class="rw-auth-form" method="post" action="" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="<?= esc($step, 'attr') ?>">
        <?php include __DIR__ . '/steps/' . $step . '.php' ?>
        <div class="rw-auth-row rw-auth-submit">
          <span class="rw-m-num"></span>
          <div>
            <div class="rw-setup-actions">
              <?php if (! $answerInvalid) : ?>
              <button class="rw-btn rw-btn--primary" type="submit"<?= $step === 'requirements' && ! RoleWarden\Install\Installer::requirementsMet($checks) ? ' disabled' : '' ?>><?= esc($t($step !== 'confirm' ? 'continue' : (($result['messages'] ?? []) === [] ? 'apply' : (count($result['messages']) > 1 ? 'manualContinue' : 'retry')))) ?></button>
              <?php endif ?>
              <?php if (! in_array($step, ['code', 'requirements'], true) && (! $locked || ($step === 'confirm' && $reopen))) : ?>
                <button class="rw-btn rw-btn--secondary" type="submit" name="back" value="1"><?= esc($t('back')) ?></button>
              <?php endif ?>
            </div>
            <?php if (isset($errors['step'])) : ?><div class="rw-auth-alert" role="alert"><b><?= esc(lang('RoleWarden.panel.toast.error')) ?></b> <?= esc($errors['step']) ?></div><?php endif ?>
          </div>
        </div>
      </form>
    <?php endif ?>
  </div>
  <span class="rw-auth-corner rw-note">RoleWarden</span>
</div>
</body>
</html>
