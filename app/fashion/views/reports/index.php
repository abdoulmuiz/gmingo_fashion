<?php
/** @var array $summary */
?>
<section class="card">
    <h1><?= e(t('reports')) ?></h1>
    <p class="meta"><?= e(t('reports_help')) ?></p>
    <div class="report-grid">
        <p><strong><?= e(t('report_open_orders')) ?></strong> <?= e((string) $summary['open_orders']) ?></p>
        <p><strong><?= e(t('report_orders_outstanding')) ?></strong> <?= e((string) $summary['orders_outstanding_kobo']) ?> kobo</p>
        <p><strong><?= e(t('report_sales_total')) ?></strong> <?= e((string) $summary['sales_total_kobo']) ?> kobo</p>
        <p><strong><?= e(t('report_expenses_total')) ?></strong> <?= e((string) $summary['expenses_total_kobo']) ?> kobo</p>
        <p><strong><?= e(t('report_profit_estimate')) ?></strong> <?= e((string) $summary['profit_estimate_kobo']) ?> kobo</p>
    </div>
</section>
