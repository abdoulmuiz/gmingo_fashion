<h1><?= e($shop_name) ?></h1>
<p><?= e(t('phone')) ?>: <?= e($shop_phone) ?></p>
<p><?= e(t('order_number')) ?>: <?= e($order_number) ?></p>
<p><?= e(t('tracking_status')) ?>: <?= e($status) ?></p>
<section class="invoice">
    <h2><?= e(t('invoice')) ?></h2>
    <p><?= e(t('order_number')) ?>: <?= e($order_number) ?></p>
    <p><?= e(t('invoice_work')) ?>: <?= e($invoice['description']) ?></p>
    <p><?= e(t('total')) ?>: <?= e($invoice['total']) ?></p>
    <p><?= e(t('invoice_deposit')) ?>: <?= e($invoice['deposit']) ?></p>
    <p><?= e(t('paid_so_far')) ?>: <?= e($invoice['paid']) ?></p>
    <p><?= e(t('balance')) ?>: <?= e($invoice['balance']) ?></p>
    <?php if ($invoice['paid_full']): ?>
        <p><?= e(t('paid_in_full')) ?></p>
    <?php endif; ?>
    <?php if ($invoice['payments'] !== []): ?>
        <h3><?= e(t('payments_made')) ?></h3>
        <ul>
            <?php foreach ($invoice['payments'] as $payment): ?>
                <li>
                    <?= e($payment['date']) ?>
                    <?= e($payment['amount']) ?>
                    <?= e($payment['method']) ?>
                    <a href="<?= e(url('/track/' . $track_token . '/receipt/' . $payment['id'] . '.png')) ?>"><?= e(t('download_receipt')) ?></a>
                    <a href="<?= e(url('/track/' . $track_token . '/receipt/' . $payment['id'] . '.pdf')) ?>"><?= e(t('download_receipt')) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p><a href="<?= e(url('/track/' . $track_token . '/invoice.png')) ?>"><?= e(t('download_invoice_png')) ?></a></p>
    <p><a href="<?= e(url('/track/' . $track_token . '/invoice.pdf')) ?>"><?= e(t('download_invoice_pdf')) ?></a></p>
</section>
<p><?= e(t('due_date')) ?>: <?= e($expected_date) ?></p>
<ol class="track-steps">
    <?php foreach ($steps as $step): ?>
        <li<?php if ($step['current']): ?> class="current"<?php endif; ?>><?= e($step['name']) ?><?php if ($step['current']): ?> (<?= e(t('tracking_current')) ?>)<?php endif; ?></li>
    <?php endforeach; ?>
</ol>
<p><?= e(t('balance_due')) ?>: <?= e($balance) ?></p>
<?php if (!$invoice['paid_full'] && ($payment_channel === 'bank' || $payment_channel === 'opay')): ?>
    <h2><?= e(t('payment_details')) ?></h2>
    <p><?= e($payment_channel === 'opay' ? t('payment_opay') : t('payment_account_name')) ?>: <?= e($payment_bank) ?></p>
    <p><?= e(t('payment_account_number')) ?>: <?= e($payment_account) ?></p>
    <?php if ($payment_note !== ''): ?>
        <p><?= e(t('payment_reference')) ?>: <?= e($payment_note) ?></p>
    <?php endif; ?>
<?php elseif (!$invoice['paid_full']): ?>
    <p><?= e(t('tracking_no_payment')) ?></p>
<?php endif; ?>
<?php if ($chat_href !== null): ?>
    <p><a class="btn btn-whatsapp" href="<?= e($chat_href) ?>"><?= e(t('chat_with_shop')) ?></a></p>
<?php endif; ?>
<div class="pay-balance-slot">
    <!-- Paystack Pay balance button goes here. -->
    <p><?= e(t('pay_balance_reserved')) ?></p>
</div>
<form method="post" action="<?= e($language_action) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-quiet" name="lang" value="en" type="submit"><?= e(t('english')) ?></button>
    <button class="btn btn-quiet" name="lang" value="ha" type="submit"><?= e(t('hausa')) ?></button>
</form>
