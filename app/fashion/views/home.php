<?php
/** @var string $greeting */
/** @var string $hello */
/** @var string $user_name */
/** @var array $range */
/** @var string $period_label */
/** @var list<array> $cards */
/** @var list<array> $kpis */
/** @var array $revenue */
/** @var array $categories */
/** @var list<array> $order_status */
/** @var list<array> $top_products */
/** @var list<array> $activity */
/** @var list<array> $attention */
/** @var bool $orders_on */
/** @var bool $empty */
/** @var string $upgrade_href */
/** @var bool $show_upgrade */

$range = is_array($range ?? null) ? $range : ['preset' => 'last_30', 'start' => '', 'end' => '', 'label' => $period_label, 'chart_key' => '30d'];
$show_upgrade = !empty($show_upgrade);
$points = is_array($revenue['points'] ?? null) ? $revenue['points'] : [];
$dataMaxKobo = (int) ($revenue['max_kobo'] ?? 1);
$yTicks = dashboard_chart_y_ticks($dataMaxKobo, 4);
$chartMax = max(1, (int) end($yTicks));
$chartW = 640;
$chartH = 260;
$padL = 56;
$padR = 16;
$padT = 16;
$padB = 36;
$chart = dashboard_chart_geometry($points, $chartMax, $chartW, $chartH, $padL, $padR, $padT, $padB);
$plotH = $chartH - $padT - $padB;
$plotW = $chartW - $padL - $padR;
$catItems = is_array($categories['items'] ?? null) ? $categories['items'] : [];
$catGradient = dashboard_donut_gradient($catItems);
$statusTotal = 0;
foreach ($order_status as $st) {
    $statusTotal += (int) ($st['count'] ?? 0);
}
$statusForDonut = [];
foreach ($order_status as $st) {
    $count = (int) ($st['count'] ?? 0);
    if ($count <= 0) {
        continue;
    }
    $statusForDonut[] = [
        'label' => (string) $st['label'],
        'percent' => $statusTotal > 0 ? (int) round(($count / $statusTotal) * 100) : 0,
        'count' => $count,
    ];
}
$statusGradient = dashboard_donut_gradient($statusForDonut);
$toneColor = [
    'blue' => 'var(--accent-blue)',
    'purple' => 'var(--accent-purple)',
    'green' => 'var(--accent-green)',
    'orange' => 'var(--accent-orange)',
];
$mannequin = url('/assets/img/mannequin.jpeg');
$promoModel = url('/assets/img/promo-model.jpeg');
$imgFallback = "this.onerror=null;this.src='data:image/svg+xml,'+encodeURIComponent('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"64\" height=\"64\"></svg>');this.classList.add('img-missing');";
$changePct = $revenue['change_pct'] ?? null;
$xLabels = [];
if ($points !== []) {
    $nPts = count($points);
    $maxLabels = min(7, $nPts);
    if ($maxLabels === 1) {
        $indexes = [0];
    } else {
        $indexes = [];
        for ($li = 0; $li < $maxLabels; $li++) {
            $indexes[] = (int) round(($li / ($maxLabels - 1)) * ($nPts - 1));
        }
        $indexes = array_values(array_unique($indexes));
    }
    foreach ($indexes as $idx) {
        $day = (string) ($points[$idx]['day'] ?? '');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        $xLabels[$idx] = $dt ? $dt->format('M j') : $day;
    }
}
$preset = (string) ($range['preset'] ?? 'last_30');
?>
<section class="dash" aria-label="<?= e(t('home')) ?>">
    <div class="dash-head">
        <div class="dash-hello card">
            <div class="dash-hello-icon">
                <img
                    src="<?= e($mannequin) ?>"
                    alt="<?= e(t('app_name')) ?>"
                    width="64"
                    height="64"
                    onerror="<?= e($imgFallback) ?>"
                >
            </div>
            <div>
                <h1><?= e($greeting) ?></h1>
                <p><?= e(t('dash_subtitle')) ?></p>
                <p class="sr-only"><?= e(t('home_title')) ?></p>
            </div>
        </div>
        <details class="dash-range card">
            <summary>
                <?= ui_icon('calendar', 'ui-icon') ?>
                <span><?= e((string) ($range['label'] ?? $period_label)) ?></span>
            </summary>
            <div class="dash-range-menu">
                <a href="<?= e(url('/?range=today')) ?>" class="<?= $preset === 'today' ? 'is-active' : '' ?>"><?= e(t('dash_range_today')) ?></a>
                <a href="<?= e(url('/?range=last_7')) ?>" class="<?= $preset === 'last_7' ? 'is-active' : '' ?>"><?= e(t('dash_range_7')) ?></a>
                <a href="<?= e(url('/?range=this_month')) ?>" class="<?= $preset === 'this_month' ? 'is-active' : '' ?>"><?= e(t('dash_range_month')) ?></a>
                <a href="<?= e(url('/?range=last_30')) ?>" class="<?= $preset === 'last_30' ? 'is-active' : '' ?>"><?= e(t('dash_range_30')) ?></a>
                <form class="dash-range-custom" method="get" action="<?= e(url('/')) ?>">
                    <input type="hidden" name="range" value="custom">
                    <label><?= e(t('dash_range_from')) ?>
                        <input type="date" name="from" value="<?= e((string) ($range['start'] ?? '')) ?>" required>
                    </label>
                    <label><?= e(t('dash_range_to')) ?>
                        <input type="date" name="to" value="<?= e((string) ($range['end'] ?? '')) ?>" required>
                    </label>
                    <button class="btn btn-small" type="submit"><?= e(t('dash_range_apply')) ?></button>
                </form>
            </div>
        </details>
    </div>

    <?php if ($empty): ?>
        <section class="card">
            <p><?= e(t('switch_on_module')) ?></p>
            <?php if (can_access('/settings')): ?>
                <p><a href="<?= e(url('/settings')) ?>"><?= e(t('settings')) ?></a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($kpis !== []): ?>
        <section class="dash-kpis" aria-label="<?= e(t('home')) ?>">
            <?php foreach ($kpis as $kpi): ?>
                <?php
                $tone = (string) ($kpi['tone'] ?? 'blue');
                $color = $toneColor[$tone] ?? 'var(--accent-blue)';
                $spark = dashboard_sparkline(is_array($kpi['points'] ?? null) ? $kpi['points'] : [], $color);
                $change = $kpi['change_pct'] ?? null;
                ?>
                <a class="dash-kpi dash-kpi-<?= e($tone) ?>" href="<?= e(url((string) ($kpi['href'] ?: '/'))) ?>">
                    <span class="dash-kpi-top">
                        <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon((string) ($kpi['icon'] ?? 'chart-column'), 'ui-icon') ?></span>
                        <span class="dash-kpi-body">
                            <span class="dash-kpi-label"><?= e((string) $kpi['label']) ?></span>
                            <strong
                                class="dash-kpi-value"
                                title="<?= e((string) $kpi['value']) ?>"
                            ><?= e((string) ($kpi['value_compact'] ?? $kpi['value'])) ?></strong>
                            <?php if ($change !== null): ?>
                                <span class="dash-kpi-change <?= $change >= 0 ? 'is-up' : 'is-down' ?>">
                                    <span class="dash-kpi-pct"><?= $change >= 0 ? '↑' : '↓' ?><?= e(($change >= 0 ? '+' : '') . number_format((float) $change, 1, '.', '')) ?>%</span>
                                    <span class="dash-kpi-vs"><?= e(t('dash_vs_prev')) ?></span>
                                </span>
                            <?php elseif (!empty($kpi['note'])): ?>
                                <span class="dash-kpi-note<?= !empty($kpi['note_danger']) ? ' is-danger' : '' ?>"><?= e((string) $kpi['note']) ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <?php if ($spark !== ''): ?>
                        <span class="dash-kpi-spark-wrap"><?= $spark ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($cards !== []): ?>
        <section class="stats dash-legacy-stats" aria-label="<?= e(t('home')) ?>">
            <?php foreach ($cards as $card): ?>
                <div>
                    <span><?php if (($card['href'] ?? '') !== ''): ?><a href="<?= e(url((string) $card['href'])) ?>"><?= e($card['label']) ?></a><?php else: ?><?= e($card['label']) ?><?php endif; ?></span>
                    <strong><?= e($card['value']) ?></strong>
                    <?php if ($card['note'] !== ''): ?>
                        <span><?= e($card['note']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="dash-mid">
        <article class="card dash-panel dash-revenue">
            <div class="dash-panel-head">
                <div>
                    <h2><?= e(t('dash_revenue_overview')) ?></h2>
                    <p class="dash-revenue-total">
                        <strong><?= e(money((int) ($revenue['period_total_kobo'] ?? 0))) ?></strong>
                        <?php if ($changePct !== null): ?>
                            <span class="dash-kpi-change <?= $changePct >= 0 ? 'is-up' : 'is-down' ?>">
                                <?= $changePct >= 0 ? '↑' : '↓' ?>
                                <?= e(($changePct >= 0 ? '+' : '') . number_format((float) $changePct, 1, '.', '')) ?>%
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="dash-chart-tabs" role="group" aria-label="<?= e(t('dash_revenue_overview')) ?>">
                    <?php
                    $tabs = [
                        '7d' => ['last_7', '7D'],
                        '30d' => ['last_30', '30D'],
                        '3m' => ['last_90', '3M'],
                        '1y' => ['last_365', '1Y'],
                    ];
                    foreach ($tabs as $key => [$presetKey, $label]):
                    ?>
                        <a class="dash-chart-tab<?= ($range['chart_key'] ?? '') === $key ? ' is-active' : '' ?>" href="<?= e(url('/?range=' . $presetKey)) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dash-chart-wrap" data-dash-chart>
                <svg class="dash-chart" viewBox="0 0 <?= e((string) $chartW) ?> <?= e((string) $chartH) ?>" preserveAspectRatio="none" role="img" aria-label="<?= e(t('dash_revenue_overview')) ?>">
                    <defs>
                        <linearGradient id="dashRevFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1A56F0" stop-opacity="0.28"/>
                            <stop offset="100%" stop-color="#1A56F0" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <?php foreach ($yTicks as $tick): ?>
                        <?php
                        // Ascending scale: 0 at bottom, chartMax at top.
                        $y = $padT + $plotH - (((int) $tick / $chartMax) * $plotH);
                        ?>
                        <line x1="<?= e((string) $padL) ?>" y1="<?= e((string) round($y, 1)) ?>" x2="<?= e((string) ($chartW - $padR)) ?>" y2="<?= e((string) round($y, 1)) ?>" stroke="var(--border-soft)" stroke-width="1"></line>
                        <text x="<?= e((string) ($padL - 8)) ?>" y="<?= e((string) round($y + 4, 1)) ?>" text-anchor="end" class="dash-chart-axis"><?= e(money_compact((int) $tick)) ?></text>
                    <?php endforeach; ?>
                    <?php if ($chart['polyline'] !== '' && (int) ($revenue['period_total_kobo'] ?? 0) > 0): ?>
                        <polygon fill="url(#dashRevFill)" points="<?= e($chart['area']) ?>"></polygon>
                        <polyline fill="none" stroke="#1A56F0" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="<?= e($chart['polyline']) ?>"></polyline>
                        <?php foreach ($chart['dots'] as $dot): ?>
                            <circle
                                class="dash-chart-dot"
                                cx="<?= e((string) $dot['x']) ?>"
                                cy="<?= e((string) $dot['y']) ?>"
                                r="4"
                                fill="#fff"
                                stroke="#1A56F0"
                                stroke-width="2"
                                data-day="<?= e((string) $dot['day']) ?>"
                                data-amount="<?= e(money((int) $dot['amount'])) ?>"
                            ></circle>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <text x="<?= e((string) ($padL + $plotW / 2)) ?>" y="<?= e((string) ($padT + $plotH / 2)) ?>" text-anchor="middle" class="dash-chart-empty"><?= e(t('dash_revenue_empty')) ?></text>
                    <?php endif; ?>
                    <?php foreach ($xLabels as $idx => $label): ?>
                        <?php
                        $n = count($points);
                        $x = $padL + ($n <= 1 ? $plotW / 2 : ($idx / max(1, $n - 1)) * $plotW);
                        ?>
                        <text x="<?= e((string) round($x, 1)) ?>" y="<?= e((string) ($chartH - 10)) ?>" text-anchor="middle" class="dash-chart-axis"><?= e($label) ?></text>
                    <?php endforeach; ?>
                </svg>
                <div class="dash-chart-tooltip" data-dash-tooltip hidden></div>
            </div>
        </article>

        <article class="card dash-panel dash-categories">
            <div class="dash-panel-head">
                <h2><?= e(t('dash_sales_by_category')) ?></h2>
                <?php if (can_access('/reports')): ?>
                    <a href="<?= e(url('/reports')) ?>"><?= e(t('dash_view_report')) ?></a>
                <?php endif; ?>
            </div>
            <?php if ($catItems === []): ?>
                <p class="meta"><?= e(t('dash_chart_empty')) ?></p>
            <?php else: ?>
                <div class="dash-donut-wrap">
                    <div class="dash-donut" style="background: <?= e($catGradient) ?>">
                        <div class="dash-donut-hole">
                            <strong><?= e((string) ((int) ($catItems[0]['percent'] ?? 0))) ?>%</strong>
                            <span><?= e((string) ($catItems[0]['label'] ?? '')) ?></span>
                        </div>
                    </div>
                    <ul class="dash-legend">
                        <?php
                        $legendTones = ['#1A56F0', '#8B3FE0', '#12A150', '#F59E0B', '#F21A3C'];
                        foreach ($catItems as $ci => $item):
                        ?>
                            <li>
                                <span><i class="dash-legend-swatch" style="background:<?= e($legendTones[$ci % count($legendTones)]) ?>"></i><?= e((string) $item['label']) ?></span>
                                <strong><?= e((string) ((int) $item['percent'])) ?>%</strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </article>

        <article class="card dash-panel dash-activity">
            <div class="dash-panel-head">
                <h2><?= e(t('dash_recent_activity')) ?></h2>
                <?php if ($orders_on && can_access('/orders')): ?>
                    <a href="<?= e(url('/orders')) ?>"><?= e(t('dash_view_all')) ?></a>
                <?php endif; ?>
            </div>
            <?php if ($activity === []): ?>
                <p class="meta"><?= e(t('nothing_urgent')) ?></p>
            <?php else: ?>
                <ul class="dash-activity-list">
                    <?php foreach ($activity as $item): ?>
                        <li>
                            <a href="<?= e(url((string) $item['href'])) ?>">
                                <span class="dash-activity-icon dash-tone-<?= e((string) $item['tone']) ?>" aria-hidden="true">
                                    <?= ui_icon((string) ($item['icon'] ?? 'package'), 'ui-icon') ?>
                                </span>
                                <span class="dash-activity-text">
                                    <strong><?= e((string) $item['title']) ?><?php if ((string) $item['meta'] !== ''): ?> · <?= e((string) $item['meta']) ?><?php endif; ?></strong>
                                    <span class="dash-activity-when"><?= e((string) $item['when']) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <section class="dash-bottom">
        <article class="card dash-panel">
            <div class="dash-panel-head">
                <h2><?= e(t('dash_top_products')) ?></h2>
            </div>
            <?php if ($top_products === []): ?>
                <p class="meta"><?= e(t('dash_chart_empty')) ?></p>
            <?php else: ?>
                <ol class="dash-top-list">
                    <?php foreach ($top_products as $i => $product): ?>
                        <li>
                            <span class="dash-rank"><?= e((string) ($i + 1)) ?></span>
                            <span class="dash-top-thumb" aria-hidden="true"><?= e(strtoupper(substr((string) $product['label'], 0, 1))) ?></span>
                            <span class="dash-top-name"><?= e((string) $product['label']) ?></span>
                            <strong><?= e(money((int) $product['amount_kobo'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <article class="card dash-panel">
            <div class="dash-panel-head">
                <h2><?= e(t('dash_order_status')) ?></h2>
            </div>
            <?php if ($statusForDonut === []): ?>
                <p class="meta"><?= e(t('dash_chart_empty')) ?></p>
            <?php else: ?>
                <div class="dash-donut-wrap">
                    <div class="dash-donut" style="background: <?= e($statusGradient) ?>">
                        <div class="dash-donut-hole">
                            <strong><?= e((string) $statusTotal) ?></strong>
                            <span><?= e(t('orders')) ?></span>
                        </div>
                    </div>
                    <ul class="dash-legend">
                        <?php
                        $statusToneHex = [
                            'orange' => '#F59E0B',
                            'blue' => '#1A56F0',
                            'green' => '#12A150',
                            'red' => '#F21A3C',
                        ];
                        foreach ($order_status as $st):
                            if ((int) ($st['count'] ?? 0) <= 0) {
                                continue;
                            }
                            $sw = $statusToneHex[(string) ($st['tone'] ?? '')] ?? '#1A56F0';
                        ?>
                            <li>
                                <span><i class="dash-legend-swatch" style="background:<?= e($sw) ?>"></i><?= e((string) $st['label']) ?></span>
                                <strong><?= e((string) ((int) $st['count'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </article>

        <?php if ($show_upgrade): ?>
            <article class="dash-promo">
                <div class="dash-promo-copy">
                    <h2><?= e(t('dash_promo_title')) ?></h2>
                    <p><?= e(t('dash_promo_help')) ?></p>
                    <a class="btn" href="<?= e(url($upgrade_href)) ?>"><?= e(t('dash_promo_cta')) ?> →</a>
                </div>
                <img
                    class="dash-promo-img"
                    src="<?= e($promoModel) ?>"
                    alt="<?= e(t('dash_promo_title')) ?>"
                    width="320"
                    height="200"
                    loading="lazy"
                    onerror="<?= e($imgFallback) ?>"
                >
            </article>
        <?php endif; ?>
    </section>

    <?php if ($orders_on): ?>
        <section class="card dash-attention">
            <h2><?= e(t('needs_attention')) ?></h2>
            <?php if ($attention === []): ?>
                <p><?= e(t('nothing_urgent')) ?></p>
            <?php endif; ?>
            <?php foreach ($attention as $order): ?>
                <a class="line" href="<?= e(url('/orders/' . $order['id'])) ?>">
                    <strong><?= e($order['order_number']) ?></strong>
                    <span><?= e($order['customer_name']) ?></span>
                    <span><?= e($order['due_date']) ?></span>
                </a>
                <?php if ($order['urgent'] || $order['overdue']): ?>
                    <p class="meta">
                        <?php if ($order['overdue']): ?><?= e(t('overdue')) ?><?php endif; ?>
                        <?php if ($order['urgent'] && $order['overdue']): ?> · <?php endif; ?>
                        <?php if ($order['urgent']): ?><?= e(t('urgent')) ?><?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>
<script>
(function () {
    var wrap = document.querySelector('[data-dash-chart]');
    if (!wrap) return;
    var tip = wrap.querySelector('[data-dash-tooltip]');
    var svg = wrap.querySelector('.dash-chart');
    wrap.querySelectorAll('.dash-chart-dot').forEach(function (dot) {
        dot.addEventListener('mouseenter', function () {
            if (!tip || !svg) return;
            tip.hidden = false;
            tip.textContent = (dot.getAttribute('data-day') || '') + ' · ' + (dot.getAttribute('data-amount') || '');
            var rect = svg.getBoundingClientRect();
            var wrapRect = wrap.getBoundingClientRect();
            var cx = parseFloat(dot.getAttribute('cx') || '0');
            var cy = parseFloat(dot.getAttribute('cy') || '0');
            tip.style.left = ((cx / <?= (int) $chartW ?>) * rect.width + (rect.left - wrapRect.left)) + 'px';
            tip.style.top = ((cy / <?= (int) $chartH ?>) * rect.height + (rect.top - wrapRect.top)) + 'px';
        });
        dot.addEventListener('mouseleave', function () {
            if (tip) tip.hidden = true;
        });
    });
})();
</script>
