<?php
$success = session()->getFlashdata('rw_success');
$error = session()->getFlashdata('rw_error');
?>
<?php if ($success) : ?>
  <div class="rw-flash rw-flash--success" role="status"><?= esc($success) ?></div>
<?php endif ?>
<?php if ($error) : ?>
  <div class="rw-flash rw-flash--error" role="alert"><?= esc($error) ?></div>
<?php endif ?>
