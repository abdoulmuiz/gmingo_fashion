<?php require dirname(__DIR__) . '/paper_actions.php'; ?>
<article class="paper">
    <h1><?= e($invoice['shop_name']) ?></h1>
    <p><?= e(t('phone')) ?>: <?= e($invoice['shop_phone']) ?></p>
    <h2><?= e(t('invoice')) ?></h2>
    <p><?= e(t('order_number')) ?>: <?= e($invoice['order_number']) ?></p>
    <p><?= e(t('customer')) ?>: <?= e($invoice['customer_name']) ?></p>
    <h3><?= e(t('invoice_work')) ?></h3>
    <ul>
        <?php foreach ($invoice['lines'] as $line): ?>
            <li><?= e($line['description']) ?> · <?= e(money((int) $line['amount_kobo'])) ?></li>
        <?php endforeach; ?>
    </ul>
    <p><?= e(t('total')) ?>: <?= e(money((int) $invoice['total_kobo'])) ?></p>
    <p><?= e(t('deposit_due')) ?>: <?= e(money((int) $invoice['deposit_kobo'])) ?></p>
    <p><?= e(t('paid_so_far')) ?>: <?= e(money((int) $invoice['paid_kobo'])) ?></p>
    <p><?= e(t('balance')) ?>: <?= e(money((int) $invoice['balance_kobo'])) ?></p>
</article>
<?php if ($messages_on): ?>
    <div class="no-print">
        <?php if ($invoice['whatsapp'] !== null): ?>
            <p><a class="btn btn-whatsapp" href="<?= e($invoice['whatsapp']) ?>"><?= e(t('share_whatsapp')) ?></a></p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/orders/' . $invoice['order_id'] . '/invoice/share')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-whatsapp" type="submit"><?= e(t('share_whatsapp')) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
