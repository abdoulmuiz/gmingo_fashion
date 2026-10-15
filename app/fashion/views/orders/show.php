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
    <?php if (module_enabled('fashion.production')): ?>
        <?php if ($next_stage !== null): ?>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/stage')) ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit"><?= e(t('move_next')) ?></button>
            </form>
        <?php else: ?>
            <p><?= e(t('no_next_stage')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <div class="stats">
        <div><span><?= e(t('total')) ?></span><strong><?= e(money((int) $order['total_kobo'])) ?></strong></div>
        <?php if (module_enabled('fashion.finance')): ?>
            <div><span><?= e(t('amount_paid')) ?></span><strong><?= e(money((int) $order['paid_kobo'])) ?></strong></div>
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
<?php if (module_enabled('fashion.finance')): ?>
    <section class="card">
        <h2><?= e(t('record_payment')) ?></h2>
        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments')) ?>">
            <?= csrf_field() ?>
            <label><?= e(t('amount')) ?>
                <input name="amount" inputmode="decimal" value="">
            </label>
            <label><?= e(t('method')) ?>
                <select name="method">
                    <option value="cash"><?= e(t('cash')) ?></option>
                    <option value="transfer"><?= e(t('transfer')) ?></option>
                </select>
            </label>
            <button class="btn" type="submit"><?= e(t('record_payment')) ?></button>
        </form>
    </section>
    <section class="card">
        <h2><?= e(t('payment_history')) ?></h2>
        <?php if ($payments === []): ?>
            <p><?= e(t('no_payments')) ?></p>
        <?php endif; ?>
        <?php foreach ($payments as $payment): ?>
            <div class="line">
                <strong><?= e(money((int) $payment['amount_kobo'])) ?></strong>
                <span><?= e(t($payment['method'] === 'cash' ? 'cash' : 'transfer')) ?></span>
                <span><?= e(date('j M Y H:i', strtotime((string) $payment['created_at']))) ?></span>
            </div>
            <?php if ($payment['kind'] === 'reversal' || $payment['reversed']): ?>
                <p class="meta"><?= e(t('reversed')) ?></p>
            <?php endif; ?>
            <?php if ($payment['kind'] === 'payment' && !$payment['reversed']): ?>
                <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments/' . $payment['id'] . '/reverse')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-quiet btn-small" type="submit"><?= e(t('reverse')) ?></button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
