<?php if ($orders_off): ?>
    <section class="card">
        <h1><?= e(t('outstanding')) ?></h1>
        <p><?= e(t('payments_need_orders')) ?></p>
    </section>
<?php endif; ?>
<?php if (!$orders_off): ?>
    <section class="card">
        <h1><?= e(t('outstanding')) ?></h1>
        <p><?= e(money((int) $total_kobo)) ?></p>
        <?php if ($rows === []): ?>
            <p><?= e(t('no_outstanding')) ?></p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <a class="line" href="<?= e(url('/orders/' . $row['id'])) ?>">
                <strong><?= e($row['order_number']) ?></strong>
                <span><?= e($row['customer_name']) ?></span>
                <span><?= e(money((int) $row['balance_kobo'])) ?></span>
            </a>
            <p class="meta"><?= e($row['due_date']) ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
