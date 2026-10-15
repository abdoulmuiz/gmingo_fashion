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
        <?php if ($has_previous || $has_next): ?>
            <nav class="pager" aria-label="<?= e(t('outstanding')) ?>">
                <?php if ($has_previous): ?>
                    <a href="<?= e(url('/outstanding?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
                <?php endif; ?>
                <?php if ($has_next): ?>
                    <a href="<?= e(url('/outstanding?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
