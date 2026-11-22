<?php
/**
 * Toast host + boot payload for cookie flashes and optional $page_toasts.
 *
 * @var list<array{type?:string,tone?:string,message?:string,title?:string,detail?:string,id?:string}>|null $page_toasts
 */
$pageToasts = is_array($page_toasts ?? null) ? $page_toasts : [];
$boot = [];
foreach (['success', 'error', 'warning', 'info'] as $flashKey) {
    $message = flash_get($flashKey);
    if ($message === null || $message === '') {
        continue;
    }
    $type = $flashKey === 'error' ? 'error' : $flashKey;
    $boot[] = [
        'id' => bin2hex(random_bytes(8)),
        'type' => $type,
        'title' => $message,
    ];
}
foreach ($pageToasts as $item) {
    if (!is_array($item)) {
        continue;
    }
    $title = (string) ($item['title'] ?? $item['message'] ?? '');
    if ($title === '') {
        continue;
    }
    $type = (string) ($item['type'] ?? $item['tone'] ?? 'info');
    if ($type === 'danger') {
        $type = 'error';
    }
    $boot[] = [
        'id' => (string) ($item['id'] ?? bin2hex(random_bytes(8))),
        'type' => $type,
        'title' => $title,
        'detail' => (string) ($item['detail'] ?? ''),
    ];
}
?>
<div id="ui-toast-host" class="ui-toast-host" aria-live="polite"></div>
<?php if ($boot !== []): ?>
    <script type="application/json" data-toast-boot><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<?php endif; ?>
<script src="<?= e(url('/assets/toast.js')) ?>?v=<?= e((string) @filemtime(dirname(__DIR__, 4) . '/public/assets/toast.js')) ?>" defer></script>
