<section class="card">
    <h1><?= e($message['label']) ?></h1>
    <p class="meta">
        <a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><?= e($message['order_number']) ?></a>
        · <?= e($message['customer_name']) ?>
    </p>
    <p><?= e(t('whatsapp_prepare_note')) ?></p>
    <p><?= e($message['body']) ?></p>
    <?php if ($message['change_label'] !== null): ?>
        <p><?= e($message['change_label']) ?></p>
    <?php endif; ?>
    <p class="meta"><?= e(t('prepared_by')) ?> <?= e($message['user_name']) ?> · <?= e($message['created_at']) ?></p>
    <?php if ($message['problem'] === 'no_phone'): ?>
        <p><?= e(t('no_phone')) ?></p>
    <?php elseif ($message['problem'] === 'phone_unusable'): ?>
        <p><?= e(t('phone_unusable')) ?></p>
    <?php endif; ?>
    <?php if ($message['problem'] !== null): ?>
        <?php require __DIR__ . '/copy.php'; ?>
    <?php endif; ?>
    <?php if ($message['open_href'] !== null && !$message['already_sent']): ?>
        <p><a class="btn btn-whatsapp" href="<?= e($message['open_href']) ?>"><?= e(t('open_whatsapp')) ?></a></p>
    <?php endif; ?>
    <?php if ($message['status'] === 'prepared' && !$message['already_sent']): ?>
        <form method="post" action="<?= e(url('/messages/' . $message['id'] . '/sent')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="/messages">
            <button class="btn" type="submit"><?= e(t('mark_sent')) ?></button>
        </form>
    <?php endif; ?>
    <p><a href="<?= e(url('/messages?order=' . $message['order_id'])) ?>"><?= e(t('messages')) ?></a></p>
</section>
