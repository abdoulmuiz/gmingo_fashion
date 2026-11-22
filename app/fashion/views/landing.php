<?php
/** @var list<array> $plans */
/** @var string $interval */
$billing_enabled = false;
$logged_in = false;
$is_owner = false;
$landing_mode = true;
?>
<section class="lp-hero" aria-labelledby="lp-hero-title">
    <div class="lp-wrap lp-hero-grid">
        <div class="lp-hero-copy">
            <div class="lp-badges">
                <span class="lp-badge lp-badge-lime"><?= e(t('app_name')) ?></span>
                <span class="lp-badge lp-badge-outline"><?= e(t('landing_badge_os')) ?></span>
            </div>
            <h1 id="lp-hero-title"><?= e(t('landing_title')) ?></h1>
            <p class="lp-hero-lead"><?= e(t('landing_help')) ?></p>
            <div class="lp-hero-cta">
                <a class="btn lp-btn-primary" href="<?= e(url('/register')) ?>"><?= e(t('pricing_start_trial')) ?> <span aria-hidden="true">→</span></a>
                <a class="btn btn-quiet lp-btn-ghost" href="#features"><?= e(t('landing_explore_features')) ?></a>
            </div>
            <ul class="lp-trust-metrics">
                <li>
                    <span class="lp-metric-icon" aria-hidden="true">◆</span>
                    <span><?= e(t('landing_metric_businesses')) ?></span>
                </li>
                <li>
                    <span class="lp-metric-icon" aria-hidden="true">●</span>
                    <span><?= e(t('landing_metric_uptime')) ?></span>
                </li>
                <li>
                    <span class="lp-metric-icon" aria-hidden="true">★</span>
                    <span><?= e(t('landing_metric_rating')) ?></span>
                </li>
            </ul>
        </div>
        <div class="lp-hero-visual" aria-hidden="true">
            <div class="lp-hero-art">
                <div class="lp-hero-shape lp-hero-shape-a"></div>
                <div class="lp-hero-shape lp-hero-shape-b"></div>
                <div class="lp-hero-shape lp-hero-shape-c"></div>
                <div class="lp-hero-figures">
                    <div class="lp-figure lp-figure-a">
                        <span class="lp-figure-head"></span>
                        <span class="lp-figure-body"></span>
                    </div>
                    <div class="lp-figure lp-figure-b">
                        <span class="lp-figure-head"></span>
                        <span class="lp-figure-body"></span>
                    </div>
                </div>
                <div class="lp-hero-rack"></div>
            </div>
            <p class="lp-hero-caption"><?= e(t('landing_hero_visual_note')) ?></p>
        </div>
    </div>
</section>

<section class="lp-features" id="features" aria-label="<?= e(t('landing_features_label')) ?>">
    <div class="lp-wrap">
        <div class="lp-feature-row">
            <?php
            $featureItems = [
                ['landing_feat_inventory', '▣'],
                ['landing_feat_sales', '▥'],
                ['landing_feat_pos', '▤'],
                ['landing_feat_store', '⌂'],
                ['landing_feat_subs', '↻'],
                ['landing_feat_customers', '☺'],
                ['landing_feat_support', '☎'],
            ];
            foreach ($featureItems as [$key, $icon]):
            ?>
                <a class="lp-feature-card" href="#showcase">
                    <span class="lp-feature-icon" aria-hidden="true"><?= e($icon) ?></span>
                    <span><?= e(t($key)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-showcase" id="showcase" aria-labelledby="lp-showcase-title">
    <div class="lp-wrap lp-showcase-grid">
        <div class="lp-showcase-copy">
            <p class="lp-accent-line" aria-hidden="true"></p>
            <h2 id="lp-showcase-title"><?= e(t('landing_showcase_title')) ?></h2>
            <ul class="lp-check-list">
                <li><span class="pricing-check" aria-hidden="true">✓</span><?= e(t('landing_showcase_dash')) ?></li>
                <li><span class="pricing-check" aria-hidden="true">✓</span><?= e(t('landing_showcase_inventory')) ?></li>
                <li><span class="pricing-check" aria-hidden="true">✓</span><?= e(t('landing_showcase_store')) ?></li>
                <li><span class="pricing-check" aria-hidden="true">✓</span><?= e(t('landing_showcase_subs')) ?></li>
            </ul>
            <a class="btn lp-btn-primary" href="<?= e(url('/pricing')) ?>"><?= e(t('landing_learn_more')) ?></a>
        </div>
        <div class="lp-dash-mock" role="img" aria-label="<?= e(t('landing_dash_mock_label')) ?>">
            <div class="lp-dash-side">
                <strong><?= e(t('app_product')) ?></strong>
                <span class="is-active"><?= e(t('home')) ?></span>
                <span><?= e(t('products')) ?></span>
                <span><?= e(t('stock')) ?></span>
                <span><?= e(t('sales')) ?></span>
                <span><?= e(t('customers')) ?></span>
            </div>
            <div class="lp-dash-main">
                <p class="lp-dash-note"><?= e(t('landing_dash_mock_note')) ?></p>
                <div class="lp-dash-kpis">
                    <div>
                        <span><?= e(t('landing_mock_revenue')) ?></span>
                        <strong>₦2,450,000</strong>
                        <em>+12.5%</em>
                    </div>
                    <div>
                        <span><?= e(t('landing_mock_orders')) ?></span>
                        <strong>1,248</strong>
                        <em>+8.3%</em>
                    </div>
                    <div>
                        <span><?= e(t('landing_mock_customers')) ?></span>
                        <strong>892</strong>
                        <em>+15.7%</em>
                    </div>
                </div>
                <div class="lp-dash-panels">
                    <div class="lp-dash-chart">
                        <span><?= e(t('landing_mock_sales_overview')) ?></span>
                        <svg viewBox="0 0 240 80" width="100%" height="80" aria-hidden="true">
                            <polyline fill="none" stroke="#2b74e8" stroke-width="3" points="0,55 30,48 60,60 90,35 120,42 150,28 180,38 210,22 240,30"></polyline>
                            <polyline fill="rgba(43,116,232,0.12)" stroke="none" points="0,80 0,55 30,48 60,60 90,35 120,42 150,28 180,38 210,22 240,30 240,80"></polyline>
                        </svg>
                    </div>
                    <div class="lp-dash-top">
                        <span><?= e(t('landing_mock_top_products')) ?></span>
                        <ul>
                            <li><i></i><?= e(t('landing_mock_product_1')) ?></li>
                            <li><i></i><?= e(t('landing_mock_product_2')) ?></li>
                            <li><i></i><?= e(t('landing_mock_product_3')) ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="lp-pricing landing-pricing" id="pricing" aria-labelledby="lp-pricing-title">
    <div class="lp-wrap">
        <div class="landing-pricing-head">
            <div>
                <h2 id="lp-pricing-title"><?= e(t('landing_pricing_title')) ?></h2>
                <p><?= e(t('landing_pricing_help')) ?></p>
            </div>
            <a class="btn lp-btn-primary landing-pricing-all" href="<?= e(url('/pricing')) ?>"><?= e(t('pricing_view_all_plans')) ?> <span aria-hidden="true">→</span></a>
        </div>
        <?php require __DIR__ . '/partials/pricing_cards.php'; ?>
    </div>
</section>

<section class="lp-testimonials" id="about" aria-labelledby="lp-testimonials-title">
    <div class="lp-wrap">
        <div class="landing-pricing-head">
            <div>
                <h2 id="lp-testimonials-title"><?= e(t('landing_testimonials_title')) ?></h2>
                <p><?= e(t('landing_testimonials_help')) ?></p>
            </div>
            <a class="btn btn-quiet" href="<?= e(url('/register')) ?>"><?= e(t('landing_read_reviews')) ?></a>
        </div>
        <div class="lp-testimonial-grid">
            <?php
            $quotes = [
                ['landing_quote_1', 'landing_quote_1_name', 'landing_quote_1_role', 'AB'],
                ['landing_quote_2', 'landing_quote_2_name', 'landing_quote_2_role', 'IM'],
                ['landing_quote_3', 'landing_quote_3_name', 'landing_quote_3_role', 'FL'],
            ];
            foreach ($quotes as [$quoteKey, $nameKey, $roleKey, $initials]):
            ?>
                <article class="lp-testimonial-card">
                    <div class="lp-testimonial-avatar" aria-hidden="true"><?= e($initials) ?></div>
                    <p class="lp-stars" aria-label="<?= e(t('landing_five_stars')) ?>">★★★★★</p>
                    <blockquote><?= e(t($quoteKey)) ?></blockquote>
                    <p class="lp-testimonial-name"><strong><?= e(t($nameKey)) ?></strong><span><?= e(t($roleKey)) ?></span></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-banner" id="contact" aria-labelledby="lp-banner-title">
    <div class="lp-wrap lp-banner-inner">
        <div class="lp-banner-mark" aria-hidden="true">G</div>
        <div>
            <h2 id="lp-banner-title"><?= e(t('landing_banner_title')) ?></h2>
            <p><?= e(t('landing_banner_help')) ?></p>
        </div>
        <a class="btn lp-btn-lime" href="<?= e(url('/register')) ?>"><?= e(t('landing_get_started_now')) ?> <span aria-hidden="true">→</span></a>
    </div>
</section>
