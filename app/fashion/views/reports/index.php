<?php
/** @var array $summary */
$title = t('reports');
$subtitle = t('reports_help');
$back_href = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$reportCards = [
    [
        'label' => t('report_open_orders'),
        'value' => number_format((int) $summary['open_orders']),
        'tone' => 'blue',
        'icon' => 'package',
    ],
    [
        'label' => t('report_orders_outstanding'),
        'value' => money((int) $summary['orders_outstanding_kobo']),
        'tone' => 'orange',
        'icon' => 'wallet',
    ],
    [
        'label' => t('report_sales_total'),
        'value' => money((int) $summary['sales_total_kobo']),
        'tone' => 'green',
        'icon' => 'receipt',
    ],
    [
        'label' => t('report_expenses_total'),
        'value' => money((int) $summary['expenses_total_kobo']),
        'tone' => 'red',
        'icon' => 'chart-column',
    ],
    [
        'label' => t('report_profit_estimate'),
        'value' => money((int) $summary['profit_estimate_kobo']),
        'tone' => ((int) $summary['profit_estimate_kobo'] >= 0) ? 'green' : 'red',
        'icon' => 'chart-column',
    ],
];
?>
<section class="dash-kpis reports-kpis" aria-label="<?= e(t('reports')) ?>">
    <?php foreach ($reportCards as $card): ?>
        <article class="dash-kpi dash-kpi-<?= e((string) $card['tone']) ?>">
            <span class="dash-kpi-top">
                <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon((string) $card['icon'], 'ui-icon') ?></span>
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?= e((string) $card['label']) ?></span>
                    <strong class="dash-kpi-value" title="<?= e((string) $card['value']) ?>"><?= e((string) $card['value']) ?></strong>
                </span>
            </span>
        </article>
    <?php endforeach; ?>
</section>