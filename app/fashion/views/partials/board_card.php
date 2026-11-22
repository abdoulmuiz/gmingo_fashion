<?php
/** @var array $order */
/** @var bool $can_move */
$dueTone = (string) ($order['due_tone'] ?? 'normal');
$dueClass = 'board-due is-' . $dueTone;
$paymentTone = (string) ($order['payment_tone'] ?? 'danger');
$canAdvance = !empty($order['can_advance']) && !empty($can_move);
?>
<article
    class="board-card"
    data-board-card
    data-order-id="<?= e((string) $order['id']) ?>"
>
    <div class="board-card-top">
        <a
            class="board-card-number"
            href="<?= e(url('/orders/' . $order['id'])) ?>"
            data-board-card-link
            data-order-id="<?= e((string) $order['id']) ?>"
        ><?= e((string) $order['order_number']) ?></a>
        <?php if (!empty($order['urgent'])): ?>
            <span class="orders-pill is-urgent"><?= e(t('urgent')) ?></span>
        <?php endif; ?>
        <details class="board-card-menu">
            <summary aria-label="<?= e(t('orders_col_actions')) ?>">
                <?= ui_icon('ellipsis-vertical', 'ui-icon') ?>
            </summary>
            <div class="board-card-menu-panel">
                <a href="<?= e(url('/orders/' . $order['id'])) ?>" data-board-card-link data-order-id="<?= e((string) $order['id']) ?>"><?= e(t('view')) ?></a>
                <?php if ($canAdvance): ?>
                    <button type="button" data-board-move-next><?= e(t('move_next')) ?></button>
                <?php endif; ?>
            </div>
        </details>
    </div>
    <div class="board-card-customer">
        <strong><?= e((string) ($order['customer_display_name'] ?? $order['customer_name'])) ?></strong>
        <?php if ((string) ($order['customer_phone'] ?? '') !== ''): ?>
            <span><?= e((string) $order['customer_phone']) ?></span>
        <?php endif; ?>
    </div>
    <p class="board-card-items"><?= e((string) ($order['items_line'] ?? '')) ?></p>
    <div class="board-card-foot">
        <span class="<?= e($dueClass) ?>">
            <?= e((string) $order['due_date']) ?>
            <?php if ($dueTone === 'overdue'): ?>
                <em><?= e(t('overdue')) ?></em>
            <?php endif; ?>
        </span>
        <span class="board-card-meta">
            <?php if (!empty($order['assigned_initials'])): ?>
                <span
                    class="board-assignee"
                    title="<?= e((string) ($order['assigned_name'] ?? '')) ?>"
                ><?= e((string) $order['assigned_initials']) ?></span>
            <?php elseif (!empty($order['assigned_name'])): ?>
                <span class="board-assignee-name meta"><?= e((string) $order['assigned_name']) ?></span>
            <?php endif; ?>
            <span
                class="board-pay-dot is-<?= e($paymentTone) ?>"
                title="<?= e((string) ($order['payment_key'] ?? '')) ?>"
                aria-label="<?= e((string) ($order['payment_key'] ?? '')) ?>"
            ></span>
        </span>
    </div>
</article>
