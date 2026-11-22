<?php
/** @var list<array> $payments */
/** @var array $filters */
/** @var list<array> $tenants */
?>
<section class="card">
    <h1><?= e(t('platform_payments')) ?></h1>
    <p class="meta"><?= e(t('platform_payments_help')) ?></p>
    <form method="get" action="<?= e(url('/platform/payments')) ?>" class="stack">
        <label><?= e(t('platform_payment_status')) ?>
            <select name="status">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach (['pending', 'succeeded', 'failed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(t('subscription_payment_' . $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('platform_payment_provider')) ?>
            <select name="provider">
                <option value=""><?= e(t('all')) ?></option>
                <option value="paystack" <?= ($filters['provider'] ?? '') === 'paystack' ? 'selected' : '' ?>><?= e(t('provider_paystack')) ?></option>
                <option value="manual" <?= ($filters['provider'] ?? '') === 'manual' ? 'selected' : '' ?>><?= e(t('provider_manual')) ?></option>
            </select>
        </label>
        <label><?= e(t('platform_tenants')) ?>
            <select name="tenant_id">
                <option value=""><?= e(t('all')) ?></option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= e((string) $tenant['id']) ?>" <?= ($filters['tenant_id'] ?? '') === (string) $tenant['id'] ? 'selected' : '' ?>><?= e((string) $tenant['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('platform_payment_from')) ?>
            <input type="date" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>">
        </label>
        <label><?= e(t('platform_payment_to')) ?>
            <input type="date" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>">
        </label>
        <button class="btn btn-quiet" type="submit"><?= e(t('filter')) ?></button>
    </form>
</section>

<section class="card">
    <?php if ($payments === []): ?>
        <p class="meta"><?= e(t('platform_payments_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($payments as $payment): ?>
            <a class="line" href="<?= e(url('/platform/tenants/' . $payment['tenant_id'])) ?>">
                <strong><?= e((string) ($payment['business_name'] ?? $payment['tenant_id'])) ?></strong>
                <span><?= e(money((int) $payment['amount_kobo'])) ?></span>
                <span><?= e(t('subscription_payment_' . (string) $payment['status'])) ?></span>
                <span><?= e(t('provider_' . (string) $payment['provider'])) ?></span>
                <span class="meta"><?= e((string) ($payment['provider_reference'] ?? '—')) ?></span>
                <span class="meta"><?= e((string) $payment['created_at']) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
