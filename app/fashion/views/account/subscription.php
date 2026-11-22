<?php
/** @var ?array $subscription */
/** @var ?array $plan */
/** @var list<array> $plans */
/** @var string $interval */
/** @var bool $entitled */
/** @var bool $is_owner */
/** @var bool $billing_enabled */
/** @var list<array> $payments */
/** @var bool $payments_all */
/** @var ?array $latest_payment */
/** @var array $errors */
/** @var ?string $info */
$payments = $payments ?? [];
$plans = $plans ?? [];
$payments_all = !empty($payments_all);
$info = $info ?? ($errors['info'] ?? null);
$interval = in_array(($interval ?? 'monthly'), subscription_intervals(), true) ? $interval : 'monthly';
$status = (string) ($subscription['status'] ?? '');
$accessUntil = $subscription !== null ? subscription_access_end_date($subscription) : null;
$renewInDays = null;
if (is_string($accessUntil) && $accessUntil !== '') {
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $accessUntil);
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today());
    if ($end instanceof DateTimeImmutable && $today instanceof DateTimeImmutable) {
        $renewInDays = (int) $today->diff($end)->format('%r%a');
    }
}
$currentPlanId = (string) ($plan['id'] ?? '');
$currentAmount = $plan !== null ? plan_amount_kobo($plan, (string) ($subscription['billing_interval'] ?? 'monthly')) : 0;
$providerLabel = t('provider_paystack');
if ($latest_payment !== null) {
    $providerLabel = t('provider_' . (string) $latest_payment['provider']);
}
$maskedRef = '—';
if ($latest_payment !== null && !empty($latest_payment['provider_reference'])) {
    $ref = (string) $latest_payment['provider_reference'];
    $maskedRef = strlen($ref) > 4 ? '**** ' . substr($ref, -4) : $ref;
}
$badgeKey = $status === 'trial' ? 'subscription_status_trial' : 'subscription_status_active';
$showActiveBadge = $entitled;
$crownSrc = url('/assets/img/crown.jpeg');
$bannerSrc = url('/assets/img/subscription-banner.jpeg');
$planIcon = static function (array $option): string {
    $code = (string) ($option['code'] ?? '');
    if ($code === 'starter') {
        return 'rocket';
    }
    if ($code === 'professional') {
        return 'gem';
    }
    if ($code === 'enterprise') {
        return 'building-2';
    }
    return 'crown';
};
?>
<section class="bill" aria-label="<?= e(t('subscription_account')) ?>">
    <header class="bill-hero">
        <img
            class="bill-hero-bg-img"
            src="<?= e($bannerSrc) ?>"
            alt="<?= e(t('subscription_account')) ?>"
            width="960"
            height="240"
            loading="lazy"
            onerror="this.onerror=null;this.style.display='none';"
        >
        <div class="bill-hero-shade" aria-hidden="true"></div>
        <div class="bill-hero-copy">
            <div class="bill-hero-title-row">
                <img
                    class="bill-hero-crown-img"
                    src="<?= e($crownSrc) ?>"
                    alt="<?= e(t('subscription_account')) ?>"
                    width="72"
                    height="64"
                    onerror="this.onerror=null;this.style.display='none';"
                >
                <h1><?= e(t('subscription_account')) ?></h1>
            </div>
            <p><?= e(t('subscription_account_help')) ?></p>
        </div>
        <p class="bill-hero-script"><?= e(t('bill_hero_script')) ?></p>
    </header>

    <?php
    $page_toasts = is_array($page_toasts ?? null) ? $page_toasts : [];
    if (is_string($info) && $info !== '') {
        $page_toasts[] = ['type' => 'info', 'message' => t($info)];
    }
    if (!empty($errors['form'])) {
        $page_toasts[] = ['type' => 'error', 'message' => t((string) $errors['form'])];
    }
    if ($subscription === null) {
        $page_toasts[] = ['type' => 'error', 'message' => t('subscription_missing')];
    }
    ?>
    <?php if (is_string($info) && $info === 'subscription_renew_request_sent'): ?>
        <p class="meta bill-inline-note"><?= e(t('subscription_renew_manual_help')) ?></p>
    <?php endif; ?>

    <?php if ($subscription === null): ?>
        <section class="card bill-card">
            <p class="meta"><?= e(t('subscription_missing')) ?></p>
        </section>
    <?php else: ?>
        <section class="card bill-overview" aria-label="<?= e(t('subscription_status')) ?>">
            <div class="bill-overview-grid">
                <div class="bill-overview-item">
                    <span class="bill-overview-icon bill-icon-ok" aria-hidden="true"><?= ui_icon('circle-check', 'ui-icon') ?></span>
                    <div>
                        <p class="bill-overview-label"><?= e(t('bill_current_plan_label')) ?></p>
                        <p class="bill-overview-value">
                            <?= e((string) ($plan['name'] ?? t('subscription_none'))) ?>
                            <?php if ($showActiveBadge): ?>
                                <span class="bill-status bill-status-ok"><?= e(t($badgeKey)) ?></span>
                            <?php else: ?>
                                <span class="bill-status bill-status-bad"><?= e(t('subscription_status_' . $status)) ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="meta">
                            <?php if ($entitled): ?>
                                <?= e(t('bill_active_renew_note')) ?>
                            <?php else: ?>
                                <?= e(t('subscription_access_blocked')) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="bill-overview-item">
                    <span class="bill-overview-icon" aria-hidden="true"><?= ui_icon('calendar', 'ui-icon') ?></span>
                    <div>
                        <p class="bill-overview-label"><?= e(t('bill_next_renewal')) ?></p>
                        <p class="bill-overview-value"><?= e((string) ($accessUntil ?? t('subscription_none'))) ?></p>
                        <?php if ($renewInDays !== null && $renewInDays >= 0): ?>
                            <p class="bill-renew-meta"><?= e(t('bill_renews_in_days', ['days' => (string) $renewInDays])) ?></p>
                        <?php elseif ($renewInDays !== null): ?>
                            <p class="meta"><?= e(t('bill_renewal_passed')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bill-overview-item">
                    <span class="bill-overview-icon" aria-hidden="true"><?= ui_icon('credit-card', 'ui-icon') ?></span>
                    <div>
                        <p class="bill-overview-label"><?= e(t('bill_payment_method')) ?></p>
                        <p class="bill-overview-value"><?= e($providerLabel) ?></p>
                        <p class="meta"><?= e($maskedRef) ?></p>
                    </div>
                </div>
                <?php if ($is_owner): ?>
                    <div class="bill-overview-actions">
                        <form method="post" action="<?= e(url('/account/subscription')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="renew">
                            <button class="btn btn-quiet" type="submit">
                                <?= ui_icon('refresh-cw', 'ui-icon') ?>
                                <?= e(t('subscription_renew_manual')) ?>
                            </button>
                        </form>
                        <a class="btn btn-quiet" href="#bill-plans">
                            <?= ui_icon('arrow-up-right', 'ui-icon') ?>
                            <?= e(t('bill_upgrade_plan')) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($is_owner): ?>
        <section class="bill-plans" id="bill-plans" aria-labelledby="bill-plans-title">
            <div class="bill-plans-head">
                <div>
                    <h2 id="bill-plans-title"><?= e(t('bill_pricing_title')) ?></h2>
                    <p class="meta"><?= e(t('bill_pricing_help')) ?></p>
                </div>
                <div class="pricing-toggle" role="group" aria-label="<?= e(t('platform_billing_interval')) ?>">
                    <a class="pricing-toggle-btn<?= $interval === 'monthly' ? ' is-active' : '' ?>" href="<?= e(url('/account/subscription?interval=monthly')) ?>#bill-plans"><?= e(t('platform_interval_monthly')) ?></a>
                    <a class="pricing-toggle-btn<?= $interval === 'annual' ? ' is-active' : '' ?>" href="<?= e(url('/account/subscription?interval=annual')) ?>#bill-plans"><?= e(t('platform_interval_annual')) ?></a>
                </div>
            </div>

            <?php if ($plans === []): ?>
                <section class="card bill-card"><p class="meta"><?= e(t('pricing_empty')) ?></p></section>
            <?php else: ?>
                <div class="pricing-grid bill-plan-grid">
                    <?php foreach ($plans as $option): ?>
                        <?php
                        $popular = !empty($option['is_popular']);
                        $amount = plan_amount_kobo($option, $interval);
                        $features = is_array($option['features'] ?? null) ? $option['features'] : [];
                        $shown = array_slice($features, 0, 5);
                        $rest = array_slice($features, 5);
                        $isCurrent = $currentPlanId !== '' && $currentPlanId === (string) $option['id'];
                        $cta = t('bill_choose_plan', ['plan' => (string) $option['name']]);
                        if ($isCurrent) {
                            $cta = t('bill_current_plan');
                        } elseif ($currentPlanId !== '' && $amount > $currentAmount) {
                            $cta = t('bill_upgrade_to', ['plan' => (string) $option['name']]);
                        } elseif ($currentPlanId !== '' && $amount < $currentAmount) {
                            $cta = t('bill_downgrade_to', ['plan' => (string) $option['name']]);
                        }
                        ?>
                        <article class="pricing-card bill-plan-card<?= $popular ? ' pricing-card-featured' : '' ?><?= $isCurrent ? ' is-current-plan' : '' ?>">
                            <?php if ($popular): ?>
                                <p class="pricing-badge">
                                    <?= ui_icon('star', 'ui-icon ui-icon-gold') ?>
                                    <?= e(t('pricing_most_popular')) ?>
                                </p>
                            <?php endif; ?>
                            <div class="pricing-icon bill-plan-icon" aria-hidden="true"><?= ui_icon($planIcon($option), 'ui-icon') ?></div>
                            <h3><?= e((string) $option['name']) ?></h3>
                            <p class="pricing-desc"><?= e((string) ($option['description'] ?? '')) ?></p>
                            <p class="pricing-price">
                                <span class="pricing-amount"><?= e(money($amount)) ?></span>
                                <span class="pricing-period">/ <?= e(t('platform_interval_' . $interval)) ?></span>
                            </p>
                            <ul class="pricing-features">
                                <?php foreach ($shown as $feature): ?>
                                    <li><span class="pricing-check" aria-hidden="true"><?= ui_icon('circle-check', 'ui-icon') ?></span><?= e((string) $feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($rest !== []): ?>
                                <details class="pricing-more">
                                    <summary><?= e(t('pricing_view_all_features')) ?> <?= ui_icon('chevron-down', 'ui-icon') ?></summary>
                                    <ul class="pricing-features">
                                        <?php foreach ($rest as $feature): ?>
                                            <li><span class="pricing-check" aria-hidden="true"><?= ui_icon('circle-check', 'ui-icon') ?></span><?= e((string) $feature) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                            <?php if ($billing_enabled): ?>
                                <form method="post" action="<?= e(url('/account/subscription')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="plan_id" value="<?= e((string) $option['id']) ?>">
                                    <input type="hidden" name="billing_interval" value="<?= e($interval) ?>">
                                    <button class="btn <?= $popular ? '' : 'btn-quiet' ?>" type="submit"><?= e($cta) ?> →</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/account/subscription')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="renew">
                                    <button class="btn btn-quiet" type="submit"><?= e($cta) ?> →</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card bill-history" aria-labelledby="bill-history-title">
            <div class="bill-history-head">
                <h2 id="bill-history-title">
                    <?= ui_icon('clock', 'ui-icon') ?>
                    <?= e(t('subscription_payment_history')) ?>
                </h2>
                <?php if (!$payments_all): ?>
                    <a href="<?= e(url('/account/subscription?payments=all')) ?>"><?= e(t('bill_view_all_payments')) ?> →</a>
                <?php endif; ?>
            </div>
            <?php if ($payments === []): ?>
                <p class="meta"><?= e(t('subscription_payments_empty')) ?></p>
            <?php else: ?>
                <div class="bill-table-wrap">
                    <table class="bill-table">
                        <thead>
                            <tr>
                                <th><?= e(t('bill_col_date')) ?></th>
                                <th><?= e(t('bill_col_amount')) ?></th>
                                <th><?= e(t('bill_col_plan')) ?></th>
                                <th><?= e(t('bill_col_status')) ?></th>
                                <th><?= e(t('bill_col_reference')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $payStatus = (string) $payment['status'];
                                $payTone = $payStatus === 'succeeded' ? 'ok' : ($payStatus === 'failed' ? 'bad' : 'muted');
                                $when = (string) ($payment['paid_at'] ?? $payment['created_at']);
                                ?>
                                <tr>
                                    <td><?= e($when) ?></td>
                                    <td><strong><?= e(money((int) $payment['amount_kobo'])) ?></strong></td>
                                    <td><?= e((string) ($payment['plan_name'] ?? ($plan['name'] ?? t('subscription_none')))) ?></td>
                                    <td><span class="bill-status bill-status-<?= e($payTone) ?>"><?= e(t('subscription_payment_' . $payStatus)) ?></span></td>
                                    <td class="meta"><?= e((string) ($payment['provider_reference'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
