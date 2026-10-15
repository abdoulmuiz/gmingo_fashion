<?php if ($orders_off): ?>
    <section class="card">
        <h1><?= e(t('board')) ?></h1>
        <p><?= e(t('board_needs_orders')) ?></p>
    </section>
<?php endif; ?>
<?php if (!$orders_off): ?>
    <?php foreach ($groups as $group): ?>
        <section class="card">
            <h2><?= e($group['name']) ?></h2>
            <?php if ($group['orders'] === []): ?>
                <p class="meta"><?= e(t('no_orders')) ?></p>
            <?php endif; ?>
            <?php foreach ($group['orders'] as $order): ?>
                <a class="line" href="<?= e(url('/orders/' . $order['id'])) ?>">
                    <strong><?= e($order['order_number']) ?></strong>
                    <span><?= e($order['customer_name']) ?></span>
                    <span><?= e($order['due_date']) ?></span>
                </a>
                <p class="meta"><?= e($order['assigned_name'] ?? t('unassigned')) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
