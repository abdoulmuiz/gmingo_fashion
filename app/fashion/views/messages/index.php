<section class="card">
    <h1><?= e(t('messages')) ?></h1>
    <p><?= e(t('whatsapp_prepare_note')) ?></p>
    <?php if ($is_owner): ?>
        <p><a href="<?= e(url('/messages/templates')) ?>"><?= e(t('message_templates')) ?></a></p>
    <?php endif; ?>
    <form method="get" action="<?= e(url('/messages')) ?>">
        <label><?= e(t('message_filter_order')) ?>
            <input name="order" value="<?= e($order_filter) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('search')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('to_send')) ?></h2>
    <?php if ($to_send === []): ?>
        <p><?= e(t('no_messages_to_send')) ?></p>
    <?php endif; ?>
    <?php foreach ($to_send as $message): ?>
        <article>
            <p><a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><?= e($message['order_number']) ?></a>
                · <?= e($message['customer_name']) ?></p>
            <p><strong><?= e($message['label']) ?></strong></p>
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
            <?php if ($message['open_href'] !== null): ?>
                <p><a class="btn btn-whatsapp" href="<?= e($message['open_href']) ?>"><?= e(t('open_whatsapp')) ?></a></p>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/messages/' . $message['id'] . '/sent')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="/messages">
                <button class="btn btn-quiet" type="submit"><?= e(t('mark_sent')) ?></button>
            </form>
        </article>
    <?php endforeach; ?>
    <?php if ($to_page > 1): ?>
        <p><a href="<?= e(url(message_page_path($order_filter, $to_page - 1, $sent_page))) ?>"><?= e(t('previous_page')) ?></a></p>
    <?php endif; ?>
    <?php if ($to_page * $page_size < $to_count): ?>
        <p><a href="<?= e(url(message_page_path($order_filter, $to_page + 1, $sent_page))) ?>"><?= e(t('next_page')) ?></a></p>
    <?php endif; ?>
</section>
<section class="card">
    <h2><?= e(t('sent_messages')) ?></h2>
    <?php if ($sent === []): ?>
        <p><?= e(t('no_sent_messages')) ?></p>
    <?php endif; ?>
    <?php foreach ($sent as $message): ?>
        <article>
            <p><a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><?= e($message['order_number']) ?></a>
                · <?= e($message['customer_name']) ?></p>
            <p><strong><?= e($message['label']) ?></strong></p>
            <p><?= e($message['body']) ?></p>
            <p class="meta"><?= e(t('prepared_by')) ?> <?= e($message['user_name']) ?> · <?= e($message['created_at']) ?></p>
            <p><a href="<?= e(url('/messages/' . $message['id'])) ?>"><?= e($message['label']) ?></a></p>
        </article>
    <?php endforeach; ?>
    <?php if ($sent_page > 1): ?>
        <p><a href="<?= e(url(message_page_path($order_filter, $to_page, $sent_page - 1))) ?>"><?= e(t('previous_page')) ?></a></p>
    <?php endif; ?>
    <?php if ($sent_page * $page_size < $sent_count): ?>
        <p><a href="<?= e(url(message_page_path($order_filter, $to_page, $sent_page + 1))) ?>"><?= e(t('next_page')) ?></a></p>
    <?php endif; ?>
</section>
