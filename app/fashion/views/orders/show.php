<section class="card">
    <p class="eyebrow"><?= e(t($order['paid'] ? 'paid' : 'unpaid')) ?></p>
    <h1><?= e($order['order_number']) ?></h1>
    <p class="meta">
        <a href="<?= e(url('/customers/' . $order['customer_id'])) ?>"><?= e($order['customer_name']) ?></a>
        · <?= e(t('due_date')) ?> <?= e(date('j M Y', strtotime((string) $order['due_date']))) ?>
        <?php if ($order['urgent']): ?> · <?= e(t('urgent')) ?><?php endif; ?>
    </p>
    <?php if (module_enabled('fashion.production') && $order['stage_name'] !== null): ?>
        <p><?= e(t('stage')) ?>: <?= e($order['stage_name']) ?></p>
    <?php endif; ?>
    <div class="stats">
        <div><span><?= e(t('total')) ?></span><strong><?= e(money((int) $order['total_kobo'])) ?></strong></div>
        <?php if (module_enabled('fashion.finance')): ?>
            <div><span><?= e(t('balance')) ?></span><strong><?= e(money((int) $order['balance_kobo'])) ?></strong></div>
        <?php endif; ?>
    </div>
</section>
<section class="card">
    <h2><?= e(t('add_items')) ?></h2>
    <?php foreach ($order['items'] as $item): ?>
        <div class="line">
            <strong><?= e($item['garment_name']) ?> × <?= e((string) $item['quantity']) ?></strong>
            <span><?= e(money((int) $item['line_kobo'])) ?></span>
        </div>
        <?php if ($item['fabric'] !== '' || $item['notes'] !== ''): ?>
            <p class="meta"><?= e($item['fabric']) ?><?php if ($item['notes'] !== ''): ?> · <?= e($item['notes']) ?><?php endif; ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
