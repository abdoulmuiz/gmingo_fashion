<?php
$title = (string) $message['label'];
ob_start();
?>
<p class="message-show-meta">
    <a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><strong><?= e($message['order_number']) ?></strong></a>
    <span aria-hidden="true">·</span>
    <span><?= e($message['customer_name']) ?></span>
</p>
<?php
$subtitle = ob_get_clean();
$back_href = '/messages?order=' . $message['order_id'];
$back_label = t('messages');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$statusTone = $message['already_sent'] ? 'success' : 'orange';
$statusLabel = $message['already_sent'] ? t('sent_messages') : t('to_send');
?>
<div class="detail-layout messages-detail">
    <div class="detail-main">
        <section class="card message-detail-card">
            <div class="message-detail-badges">
                <?php
                $label = $statusLabel;
                $tone = $statusTone;
                require __DIR__ . '/../partials/ui_status_badge.php';
                ?>
            </div>
            <p class="ui-help"><?= e(t('whatsapp_prepare_note')) ?></p>
            <div class="message-detail-body"><?= e($message['body']) ?></div>
            <?php if ($message['change_label'] !== null): ?>
                <p class="message-card-note"><?= e($message['change_label']) ?></p>
            <?php endif; ?>
            <p class="meta"><?= e(t('prepared_by')) ?> <?= e($message['user_name']) ?> · <?= e($message['created_at']) ?></p>
        </section>
    </div>
    <aside class="detail-side">
        <section class="card message-detail-actions">
            <?php if ($message['problem'] === 'no_phone'): ?>
                <p class="ui-error"><?= e(t('no_phone')) ?></p>
            <?php elseif ($message['problem'] === 'phone_unusable'): ?>
                <p class="ui-error"><?= e(t('phone_unusable')) ?></p>
            <?php endif; ?>
            <?php if ($message['problem'] !== null): ?>
                <div class="message-copy-block">
                    <?php require __DIR__ . '/copy.php'; ?>
                </div>
            <?php endif; ?>
            <?php if ($message['status'] === 'prepared' && !$message['already_sent']): ?>
                <form method="post" action="<?= e(url('/messages/' . $message['id'] . '/sent')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="/messages">
                    <button class="btn" type="submit"><?= e(t('mark_sent')) ?></button>
                </form>
            <?php endif; ?>
            <?php if ($message['open_href'] !== null && !$message['already_sent']): ?>
                <a class="btn btn-whatsapp" href="<?= e($message['open_href']) ?>"><?= e(t('open_whatsapp')) ?></a>
            <?php endif; ?>
        </section>
    </aside>
</div>