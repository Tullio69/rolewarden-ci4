<?php
// Toasts (docs/design-system/components/Toast). Server-side messages arrive as flashdata
// (rw_success, rw_warning, rw_error); client-side ones as a `rw-toast` window event.
$toasts = [];
foreach (['success' => 'rw_success', 'warning' => 'rw_warning', 'error' => 'rw_error'] as $type => $key) {
    $message = session()->getFlashdata($key);
    if (is_string($message) && $message !== '') {
        $toasts[] = ['type' => $type, 'message' => $message];
    }
}
$toastConfig = [
    'messages' => $toasts,
    'kinds' => [
        'success' => lang('RoleWarden.panel.toast.success'),
        'warning' => lang('RoleWarden.panel.toast.warning'),
        'error' => lang('RoleWarden.panel.toast.error'),
    ],
];
?>
<div class="rw-toasts" aria-live="polite" x-data="rwToasts(<?= esc(json_encode($toastConfig, JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>)"
     @rw-toast.window="push($event.detail)" @mouseenter="paused = true" @mouseleave="paused = false"
     @focusin="paused = true" @focusout="paused = false">
  <template x-for="toast in items" :key="toast.id">
    <div class="rw-toast" :class="'rw-toast--' + toast.type + (toast.leaving ? ' is-leaving' : '')" :role="toast.type === 'error' ? 'alert' : null">
      <p><span class="rw-toast__kind" x-text="kinds[toast.type]"></span> <span x-text="toast.message"></span></p>
      <button class="rw-toast__close" type="button" aria-label="<?= esc(lang('RoleWarden.panel.toast.dismiss'), 'attr') ?>" @click="close(toast.id)">&times;</button>
    </div>
  </template>
</div>
<?php if ($toasts !== []) : ?>
<noscript>
  <div class="rw-toasts rw-toasts--static">
    <?php foreach ($toasts as $toast) : ?>
      <div class="rw-toast rw-toast--<?= esc($toast['type'], 'attr') ?>"<?= $toast['type'] === 'error' ? ' role="alert"' : '' ?>>
        <p><span class="rw-toast__kind"><?= esc($toastConfig['kinds'][$toast['type']]) ?></span> <?= esc($toast['message']) ?></p>
      </div>
    <?php endforeach ?>
  </div>
</noscript>
<?php endif ?>
