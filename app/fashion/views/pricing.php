<?php
/** @var list<array> $plans */
/** @var string $interval */
/** @var bool $billing_enabled */
/** @var bool $logged_in */
/** @var bool $is_owner */
$landing_mode = false;
?>
<section class="pricing-hero">
    <p class="eyebrow"><?= e(t('app_kicker')) ?></p>
    <h1><?= e(t('pricing_title')) ?></h1>
    <p><?= e(t('pricing_help')) ?></p>
    <div class="pricing-toggle" role="group" aria-label="<?= e(t('platform_billing_interval')) ?>">
        <a class="pricing-toggle-btn<?= $interval === 'monthly' ? ' is-active' : '' ?>" href="<?= e(url('/pricing?interval=monthly')) ?>"><?= e(t('platform_interval_monthly')) ?></a>
        <a class="pricing-toggle-btn<?= $interval === 'annual' ? ' is-active' : '' ?>" href="<?= e(url('/pricing?interval=annual')) ?>"><?= e(t('platform_interval_annual')) ?></a>
    </div>
</section>

<?php require __DIR__ . '/partials/pricing_cards.php'; ?>
