<?php
$title = t('messages');
$subtitle = t('whatsapp_prepare_note');
$back_href = null;
$actions_html = '';
if ($is_owner) {
    ob_start();
    ?>
    <a class="btn btn-quiet" href="<?= e(url('/messages/templates')) ?>">
        <?= ui_icon('settings', 'ui-icon') ?>
        <span><?= e(t('message_templates')) ?></span>
    </a>
    <?php
    $actions_html = ob_get_clean();
}
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card messages-toolbar">
    <form class="ui-filter-bar" method="get" action="<?= e(url('/messages')) ?>">
        <div class="ui-filter-tools">
            <label class="orders-search">
                <span class="visually-hidden"><?= e(t('message_filter_order')) ?></span>
                <?= ui_icon('search', 'ui-icon') ?>
                <input type="search" name="order" value="<?= e($order_filter) ?>" placeholder="<?= e(t('message_filter_order')) ?>">
            </label>
            <button class="btn btn-quiet" type="submit"><?= e(t('search')) ?></button>
        </div>
    </form>
    <nav class="orders-chips" aria-label="<?= e(t('messages')) ?>">
        <span class="orders-chip is-active" role="tab" aria-selected="true">
            <span><?= e(t('to_send')) ?></span>
            <span class="orders-chip-count"><?= e(number_format((int) $to_count)) ?></span>
        </span>
        <span class="orders-chip" role="tab" aria-selected="false">
            <span><?= e(t('sent_messages')) ?></span>
            <span class="orders-chip-count"><?= e(number_format((int) $sent_count)) ?></span>
        </span>
    </nav>
</section>

<div class="messages-split">
    <section class="card messages-pane" aria-labelledby="messages-to-send-heading">
        <header class="messages-pane-head">
            <h2 id="messages-to-send-heading"><?= e(t('to_send')) ?></h2>
            <span class="orders-chip-count"><?= e(number_format((int) $to_count)) ?></span>
        </header>
        <?php if ($to_send === []): ?>
            <?php
            $title = t('no_messages_to_send');
            $help = null;
            $icon = 'mail';
            $cta_href = null;
            $cta_label = null;
            require __DIR__ . '/../partials/ui_empty_state.php';
            ?>
        <?php else: ?>
            <ul class="message-stack">
                <?php foreach ($to_send as $message): ?>
                    <li class="message-card">
                        <header class="message-card-head">
                            <p class="message-card-meta">
                                <a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><strong><?= e($message['order_number']) ?></strong></a>
                                <span aria-hidden="true">·</span>
                                <span><?= e($message['customer_name']) ?></span>
                            </p>
                            <span class="orders-badge is-orange"><?= e($message['label']) ?></span>
                        </header>
                        <p class="message-card-body"><?= e($message['body']) ?></p>
                        <?php if ($message['change_label'] !== null): ?>
                            <p class="message-card-note"><?= e($message['change_label']) ?></p>
                        <?php endif; ?>
                        <p class="meta"><?= e(t('prepared_by')) ?> <?= e($message['user_name']) ?> · <?= e($message['created_at']) ?></p>
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
                        <div class="message-card-actions">
                            <?php if ($message['open_href'] !== null): ?>
                                <a class="btn btn-whatsapp" href="<?= e($message['open_href']) ?>"><?= e(t('open_whatsapp')) ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/messages/' . $message['id'] . '/sent')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="/messages">
                                <button class="btn btn-quiet" type="submit"><?= e(t('mark_sent')) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($to_page > 1 || $to_page * $page_size < $to_count): ?>
                <nav class="pager messages-pager" aria-label="<?= e(t('to_send')) ?>">
                    <?php if ($to_page > 1): ?>
                        <a href="<?= e(url(message_page_path($order_filter, $to_page - 1, $sent_page))) ?>"><?= e(t('previous_page')) ?></a>
                    <?php endif; ?>
                    <?php if ($to_page * $page_size < $to_count): ?>
                        <a href="<?= e(url(message_page_path($order_filter, $to_page + 1, $sent_page))) ?>"><?= e(t('next_page')) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card messages-pane messages-pane-sent" aria-labelledby="messages-sent-heading">
        <header class="messages-pane-head">
            <h2 id="messages-sent-heading"><?= e(t('sent_messages')) ?></h2>
            <span class="orders-chip-count"><?= e(number_format((int) $sent_count)) ?></span>
        </header>
        <?php if ($sent === []): ?>
            <?php
            $title = t('no_sent_messages');
            $help = null;
            $icon = 'circle-check';
            $cta_href = null;
            $cta_label = null;
            require __DIR__ . '/../partials/ui_empty_state.php';
            ?>
        <?php else: ?>
            <ul class="message-stack">
                <?php foreach ($sent as $message): ?>
                    <li class="message-card is-sent">
                        <header class="message-card-head">
                            <p class="message-card-meta">
                                <a href="<?= e(url('/orders/' . $message['order_id'])) ?>"><strong><?= e($message['order_number']) ?></strong></a>
                                <span aria-hidden="true">·</span>
                                <span><?= e($message['customer_name']) ?></span>
                            </p>
                            <span class="orders-badge is-success"><?= e($message['label']) ?></span>
                        </header>
                        <p class="message-card-body"><?= e($message['body']) ?></p>
                        <p class="meta"><?= e(t('prepared_by')) ?> <?= e($message['user_name']) ?> · <?= e($message['created_at']) ?></p>
                        <p><a class="btn btn-quiet btn-small" href="<?= e(url('/messages/' . $message['id'])) ?>"><?= e($message['label']) ?></a></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($sent_page > 1 || $sent_page * $page_size < $sent_count): ?>
                <nav class="pager messages-pager" aria-label="<?= e(t('sent_messages')) ?>">
                    <?php if ($sent_page > 1): ?>
                        <a href="<?= e(url(message_page_path($order_filter, $to_page, $sent_page - 1))) ?>"><?= e(t('previous_page')) ?></a>
                    <?php endif; ?>
                    <?php if ($sent_page * $page_size < $sent_count): ?>
                        <a href="<?= e(url(message_page_path($order_filter, $to_page, $sent_page + 1))) ?>"><?= e(t('next_page')) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>