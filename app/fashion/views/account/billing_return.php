<?php
/** @var string $reference */
/** @var string $status */
?>
<section class="card">
    <h1><?= e(t('subscription_billing_return')) ?></h1>
    <?php if ($status === 'paid'): ?>
        <p class="flash" role="status"><?= e(t('subscription_payment_received')) ?></p>
        <p><a class="btn" href="<?= e(url('/')) ?>"><?= e(t('home')) ?></a></p>
    <?php elseif ($status === 'waiting'): ?>
        <p class="meta"><?= e(t('subscription_payment_waiting')) ?></p>
        <p class="meta"><?= e(t('subscription_payment_waiting_help')) ?></p>
        <?php if ($reference !== ''): ?>
            <form method="post" action="<?= e(url('/account/billing/verify')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="reference" value="<?= e($reference) ?>">
                <button class="btn" type="submit"><?= e(t('subscription_payment_verify')) ?></button>
            </form>
        <?php endif; ?>
        <p><a href="<?= e(url('/account/subscription')) ?>"><?= e(t('subscription_account')) ?></a></p>
    <?php else: ?>
        <p class="flash flash-error" role="alert"><?= e(t('subscription_payment_failed')) ?></p>
        <p><a class="btn" href="<?= e(url('/account/subscription')) ?>"><?= e(t('subscription_account')) ?></a></p>
    <?php endif; ?>
</section>
