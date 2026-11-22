<?php
/** @var list<array> $plans */
/** @var string $interval */
/** @var bool $billing_enabled */
/** @var bool $logged_in */
/** @var bool $is_owner */
/** @var bool $landing_mode */
$previewCount = 4;
$landingMode = !empty($landing_mode);
?>
<?php if ($plans === []): ?>
    <section class="card">
        <p class="meta"><?= e(t('pricing_empty')) ?></p>
    </section>
<?php else: ?>
    <section class="pricing-grid" aria-label="<?= e(t('pricing_title')) ?>">
        <?php foreach ($plans as $plan): ?>
            <?php
            $popular = !empty($plan['is_popular']);
            $amount = plan_amount_kobo($plan, $interval);
            $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];
            $shown = array_slice($features, 0, $previewCount);
            $rest = array_slice($features, $previewCount);
            $code = (string) ($plan['code'] ?? '');
            $isEnterprise = $code === 'enterprise';
            if ($landingMode) {
                $ctaLabel = $isEnterprise ? t('pricing_contact_sales') : t('pricing_start_trial');
                $chooseHref = $isEnterprise ? url('/pricing') : url('/register');
            } else {
                $ctaLabel = t('pricing_choose_plan');
                $chooseHref = $logged_in
                    ? url('/account/subscription')
                    : url('/register');
            }
            ?>
            <article class="pricing-card<?= $popular ? ' pricing-card-featured' : '' ?>">
                <?php if ($popular): ?>
                    <p class="pricing-badge"><?= e(t('pricing_most_popular')) ?></p>
                <?php endif; ?>
                <div class="pricing-icon" aria-hidden="true"><?= e(strtoupper(substr((string) ($plan['icon'] ?: $plan['code']), 0, 1))) ?></div>
                <h2><?= e((string) $plan['name']) ?></h2>
                <p class="pricing-desc"><?= e((string) $plan['description']) ?></p>
                <p class="pricing-price">
                    <span class="pricing-amount"><?= e(money($amount)) ?></span>
                    <span class="pricing-period">/ <?= e(t('platform_interval_' . $interval)) ?></span>
                </p>
                <p class="pricing-currency meta"><?= e((string) ($plan['currency'] ?? 'NGN')) ?></p>
                <ul class="pricing-features">
                    <?php foreach ($shown as $feature): ?>
                        <li><span class="pricing-check" aria-hidden="true">✓</span><?= e((string) $feature) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest !== []): ?>
                    <details class="pricing-more">
                        <summary><?= e(t('pricing_view_all_features')) ?></summary>
                        <ul class="pricing-features">
                            <?php foreach ($rest as $feature): ?>
                                <li><span class="pricing-check" aria-hidden="true">✓</span><?= e((string) $feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <?php if (!$landingMode && $logged_in && $is_owner && $billing_enabled): ?>
                    <form method="post" action="<?= e(url('/account/subscription')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="plan_id" value="<?= e((string) $plan['id']) ?>">
                        <input type="hidden" name="billing_interval" value="<?= e($interval) ?>">
                        <button class="btn<?= $popular ? '' : ' btn-quiet' ?>" type="submit"><?= e($ctaLabel) ?></button>
                    </form>
                <?php else: ?>
                    <a class="btn<?= $popular ? '' : ' btn-quiet' ?>" href="<?= e($chooseHref) ?>"><?= e($ctaLabel) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
