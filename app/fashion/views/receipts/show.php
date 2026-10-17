<?php require dirname(__DIR__) . '/paper_actions.php'; ?>
<article class="paper">
    <?php if ($receipt['voided']): ?>
        <p class="void-mark"><?= e(t('void_mark')) ?></p>
    <?php endif; ?>
    <h1><?= e($receipt['shop_name']) ?></h1>
    <p><?= e(t('phone')) ?>: <?= e($receipt['shop_phone']) ?></p>
    <h2><?= e(t('receipt')) ?></h2>
    <p><?= e(t('receipt_number')) ?>: <?= e($receipt['receipt_number']) ?></p>
    <p><?= e(t('date')) ?>: <?= e($receipt['when']) ?></p>
    <p><?= e(t('customer')) ?>: <?= e($receipt['customer_name']) ?></p>
    <p><?= e(t('order_number')) ?>: <?= e($receipt['order_number']) ?></p>
    <p><?= e(t('receipt_amount')) ?>: <?= e(money((int) $receipt['amount_kobo'])) ?></p>
    <p><?= e(t('method')) ?>: <?= e($receipt['method_label']) ?></p>
    <p><?= e(t('balance_after')) ?>: <?= e(money((int) $receipt['balance_after_kobo'])) ?></p>
</article>
<?php if ($messages_on): ?>
    <div class="no-print">
        <?php if ($receipt['whatsapp'] !== null): ?>
            <p><a class="btn btn-whatsapp" href="<?= e($receipt['whatsapp']) ?>"><?= e(t('share_whatsapp')) ?></a></p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/receipts/' . $receipt['id'] . '/share')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-whatsapp" type="submit"><?= e(t('share_whatsapp')) ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
