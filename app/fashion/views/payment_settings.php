<section class="card">
    <h1><?= e(t('payment_settings')) ?></h1>
    <form method="post" action="<?= e(url('/settings/payments')) ?>">
        <?= csrf_field() ?>
        <label class="check">
            <input type="radio" name="mode" value="manual" <?= $settings['mode'] === 'manual' ? 'checked' : '' ?>>
            <?= e(t('mode_manual')) ?>
        </label>
        <label class="check">
            <input type="radio" name="mode" value="paystack" <?= $settings['mode'] === 'paystack' ? 'checked' : '' ?>>
            <?= e(t('mode_paystack')) ?>
        </label>
        <?php if (!$settings['domain_ready']): ?>
            <p><?= e(t('payment_domain_missing')) ?></p>
        <?php endif; ?>
        <?php if ($settings['mode'] === 'manual' && $settings['secret_mask'] !== ''): ?>
            <p class="meta"><?= e(t('paystack_paused')) ?></p>
        <?php endif; ?>
        <?php if (!$online_payments): ?>
            <p><?= e(t('online_payments_off')) ?></p>
        <?php else: ?>
            <label><?= e(t('paystack_public_key')) ?>
                <input name="public_key" value="<?= e($settings['public_key']) ?>" autocomplete="off">
            </label>
            <?php if ($settings['secret_mask'] !== ''): ?>
                <p class="meta"><?= e($settings['secret_mask']) ?></p>
            <?php endif; ?>
            <label><?= e(t('paystack_secret_key')) ?>
                <input name="secret_key" type="password" value="" autocomplete="off">
            </label>
            <label><?= e(t('paystack_webhook')) ?>
                <input readonly value="<?= e($settings['webhook_url']) ?>">
            </label>
            <p><?= e(t('paystack_guide')) ?></p>
            <p><?= e(t('paystack_test_event')) ?></p>
        <?php endif; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
