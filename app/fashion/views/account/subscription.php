<?php
/** @var ?array $subscription */
/** @var ?array $plan */
/** @var array $plans */
/** @var bool $entitled */
/** @var bool $is_owner */
/** @var bool $billing_enabled */
/** @var array $errors */
$selectedPlanId = (string) ($plan['id'] ?? ($plans[0]['id'] ?? ''));
$selectedInterval = (string) ($subscription['billing_interval'] ?? 'monthly');
?>
<section class="card">
    <h1><?= e(t('subscription_account')) ?></h1>
    <p class="meta"><?= e(t('subscription_account_help')) ?></p>

    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>

    <?php if ($subscription === null): ?>
        <p class="flash flash-error" role="alert"><?= e(t('subscription_missing')) ?></p>
    <?php else: ?>
        <p>
            <strong><?= e(t('subscription_status')) ?>:</strong>
            <?= e(t('subscription_status_' . (string) $subscription['status'])) ?>
        </p>
        <?php if ($plan !== null): ?>
            <p><strong><?= e(t('subscription_plan')) ?>:</strong> <?= e((string) $plan['name']) ?></p>
        <?php endif; ?>
        <p>
            <strong><?= e(t('subscription_access_until')) ?>:</strong>
            <?= e((string) (subscription_access_end_date($subscription) ?? t('subscription_none'))) ?>
        </p>
        <?php if ($entitled): ?>
            <p class="flash" role="status"><?= e(t('subscription_active_note')) ?></p>
            <p><a class="btn" href="<?= e(url('/')) ?>"><?= e(t('home')) ?></a></p>
        <?php else: ?>
            <p class="flash flash-error" role="alert"><?= e(t('subscription_access_blocked')) ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($is_owner): ?>
        <?php if ($billing_enabled): ?>
            <h2><?= e(t('subscription_pay_online')) ?></h2>
            <p class="meta"><?= e(t('subscription_pay_online_help')) ?></p>
            <form method="post" action="<?= e(url('/account/subscription')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="checkout">
                <label><?= e(t('subscription_plan')) ?>
                    <select name="plan_id">
                        <?php foreach ($plans as $option): ?>
                            <option value="<?= e((string) $option['id']) ?>" <?= $selectedPlanId === (string) $option['id'] ? 'selected' : '' ?>>
                                <?= e((string) $option['name']) ?>
                                (<?= e((string) $option['price_monthly_kobo']) ?> / <?= e(t('platform_interval_monthly')) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['plan_id'])): ?><span class="error"><?= e(t((string) $errors['plan_id'])) ?></span><?php endif; ?>
                </label>
                <label><?= e(t('platform_billing_interval')) ?>
                    <select name="billing_interval">
                        <option value="monthly" <?= $selectedInterval === 'monthly' ? 'selected' : '' ?>><?= e(t('platform_interval_monthly')) ?></option>
                        <option value="annual" <?= $selectedInterval === 'annual' ? 'selected' : '' ?>><?= e(t('platform_interval_annual')) ?></option>
                    </select>
                    <?php if (!empty($errors['billing_interval'])): ?><span class="error"><?= e(t((string) $errors['billing_interval'])) ?></span><?php endif; ?>
                </label>
                <button class="btn" type="submit"><?= e(t('subscription_pay_with_paystack')) ?></button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/account/subscription')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="renew">
                <button class="btn" type="submit"><?= e(t('subscription_renew_manual')) ?></button>
            </form>
            <p class="meta"><?= e(t('subscription_renew_manual_help')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
