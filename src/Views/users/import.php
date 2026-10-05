<?php
/**
 * Users import (SPEC, V6 decisions): upload a CSV, read the preview row by row,
 * confirm. One invalid row refuses the whole file and nothing is written.
 *
 * @var array{fileErrors: list<array{0: string, 1: list<int|string>}>, rows: list<array{line: int, email: string, username: string, role: string, hasPassword: bool, errors: list<string>}>, fileName: string}|null $preview
 * @var string|null           $token  set when the preview is valid and can be confirmed
 * @var array<string, string> $errors upload errors, field => message
 */

use RoleWarden\Users\CsvImport;

$refused = $preview !== null && $token === null;
$invalidRows = $preview === null ? [] : array_filter($preview['rows'], static fn (array $r): bool => $r['errors'] !== []);
?>
<div class="rw-page-head">
  <div>
    <div class="rw-header"><?= lang('RoleWarden.panel.nav.users') ?></div>
    <h1><?= lang('RoleWarden.panel.import.title') ?></h1>
    <p><?= lang('RoleWarden.panel.import.intro') ?></p>
  </div>
  <dl class="rw-facts">
    <div><dt><?= lang('RoleWarden.panel.import.limit') ?></dt><dd><?= esc(lang('RoleWarden.panel.import.limitValue', [CsvImport::MAX_ROWS, intdiv(CsvImport::MAX_BYTES, 1024)])) ?></dd></div>
    <div><dt><?= lang('RoleWarden.panel.import.sample') ?></dt><dd><a href="<?= esc(site_url('rolewarden/users/import/sample')) ?>"><?= lang('RoleWarden.panel.import.sampleLink') ?></a></dd></div>
  </dl>
</div>

<div class="rw-section-title"><span class="rw-section-n">A</span><h2><?= lang('RoleWarden.panel.import.sectionColumns') ?></h2></div>
<table class="rw-ledger">
  <tbody>
    <?php foreach (['email', 'username', 'role', 'password'] as $i => $column) : ?>
      <tr>
        <td class="rw-m-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
        <th class="rw-l-key" scope="row"><code class="rw-mono"><?= $column ?></code><small><?= lang(in_array($column, CsvImport::REQUIRED, true) ? 'RoleWarden.panel.import.required' : 'RoleWarden.panel.import.optional') ?></small></th>
        <td class="rw-l-value rw-muted"><?= lang('RoleWarden.panel.import.column.' . $column) ?></td>
        <td class="rw-l-ctl"></td>
        <td class="rw-anno"></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div class="rw-section-title"><span class="rw-section-n">B</span><h2><?= lang('RoleWarden.panel.import.sectionUpload') ?></h2></div>
<form class="rw-form" method="post" action="<?= esc(site_url('rolewarden/users/import/preview')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="rw-field">
    <label class="rw-label" for="rw-csv"><?= lang('RoleWarden.panel.import.file') ?></label>
    <input class="rw-input rw-file" type="file" id="rw-csv" name="csv" accept=".csv,text/csv" required<?= isset($errors['csv']) ? ' aria-invalid="true" aria-describedby="rw-csv-error"' : '' ?>>
    <?php if (isset($errors['csv'])) : ?><p class="rw-field-error" id="rw-csv-error"><b><?= lang('RoleWarden.panel.toast.error') ?></b> <?= esc($errors['csv']) ?></p><?php endif ?>
    <p class="rw-field-hint"><?= lang('RoleWarden.panel.import.fileHint') ?></p>
  </div>
  <div class="rw-form-actions">
    <button class="rw-btn rw-btn--secondary" type="submit"><?= lang('RoleWarden.panel.import.check') ?></button>
  </div>
</form>

<?php if ($preview !== null) : ?>
  <div class="rw-section-title"><span class="rw-section-n">C</span><h2><?= lang('RoleWarden.panel.import.sectionPreview') ?></h2></div>

  <?php if ($preview['fileErrors'] !== []) : ?>
    <div class="rw-flash rw-flash--error rw-flash--inset" role="alert">
      <b><?= lang('RoleWarden.panel.toast.error') ?></b> <?= esc(lang('RoleWarden.panel.import.fileRefused', [$preview['fileName']])) ?>
      <ul>
        <?php foreach ($preview['fileErrors'] as [$code, $args]) : ?>
          <li><?= esc(lang('RoleWarden.panel.import.fileErrors.' . $code, $args)) ?></li>
        <?php endforeach ?>
      </ul>
    </div>
  <?php else : ?>
    <div class="rw-bar rw-import-bar<?= $refused ? '' : ' rw-bar--dirty' ?>" role="status">
      <div class="rw-bar-msg">
        <?php if ($refused) : ?>
          <b class="rw-import-refused"><?= lang('RoleWarden.panel.toast.error') ?></b>
          <span><?= esc(lang('RoleWarden.panel.import.rowsRefused', [count($invalidRows), count($preview['rows']), $preview['fileName']])) ?></span>
        <?php else : ?>
          <span class="rw-bar-dot" aria-hidden="true"></span>
          <b><?= esc(lang('RoleWarden.panel.import.ready', [count($preview['rows'])])) ?></b>
          <span class="rw-muted"><?= esc($preview['fileName']) ?></span>
        <?php endif ?>
      </div>
      <?php if (! $refused) : ?>
        <div class="rw-bar-actions">
          <a class="rw-btn rw-btn--ghost" href="<?= esc(site_url('rolewarden/users/import')) ?>"><?= lang('RoleWarden.panel.cancel') ?></a>
          <form method="post" action="<?= esc(site_url('rolewarden/users/import/confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
            <button class="rw-btn rw-btn--primary" type="submit"><?= esc(lang('RoleWarden.panel.import.confirm', [count($preview['rows'])])) ?></button>
          </form>
        </div>
      <?php endif ?>
    </div>

    <table class="rw-t-users rw-t-import">
      <thead>
        <tr>
          <th class="rw-m-num" scope="col"><span class="rw-visually-hidden"><?= lang('RoleWarden.panel.import.line') ?></span></th>
          <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.users.email') ?></th>
          <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.users.username') ?></th>
          <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.import.role') ?></th>
          <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.users.password') ?></th>
          <th class="rw-header" scope="col"><?= lang('RoleWarden.panel.import.result') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview['rows'] as $row) : ?>
          <tr<?= $row['errors'] !== [] ? ' class="rw-import-bad"' : '' ?>>
            <td class="rw-m-num"><?= (int) $row['line'] ?></td>
            <td><?= esc($row['email']) ?></td>
            <td><?= esc($row['username']) ?></td>
            <td><?= $row['role'] !== '' ? '<span class="rw-role">' . esc($row['role']) . '</span>' : '<span class="rw-faint">' . lang('RoleWarden.panel.import.noRole') . '</span>' ?></td>
            <td class="rw-muted"><?= $row['hasPassword'] ? lang('RoleWarden.panel.import.passwordGiven') : lang('RoleWarden.panel.import.passwordNone') ?></td>
            <td>
              <?php if ($row['errors'] === []) : ?>
                <span class="rw-muted"><?= lang('RoleWarden.panel.import.rowOk') ?></span>
              <?php else : ?>
                <ul class="rw-import-errors">
                  <?php foreach ($row['errors'] as $message) : ?><li><?= esc($message) ?></li><?php endforeach ?>
                </ul>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
<?php endif ?>
