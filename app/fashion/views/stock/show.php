<?php
/** @var array $item */
/** @var list<array> $rows */
/** @var int $page */
/** @var bool $has_previous */
/** @var bool $has_next */
$qty = (int) $item['quantity_hundredths'];
$min = $item['minimum_hundredths'] !== null ? (int) $item['minimum_hundredths'] : null;
$isLow = $min !== null && $qty <= $min;
$title = $item['name'];
$back_href = '/stock';
$back_label = t('stock');
$subtitle = e(t('stock_cat_' . $item['category']))
    . ' · ' . e(t('stock_unit_' . $item['unit']))
    . ($item['colour'] !== null ? ' · ' . e($item['colour']) : '');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-<?= $isLow ? 'orange' : 'blue' ?>">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('package', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('stock_quantity')) ?></span>
                <strong class="dash-kpi-value"><?= e(stock_format($qty)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-purple">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('stock_minimum')) ?></span>
                <strong class="dash-kpi-value"><?= e($item['minimum_hundredths'] === null ? t('stock_minimum_none') : stock_format($min)) ?></strong>
            </span>
        </span>
    </article>
</section>

<div class="detail-layout" style="margin-top:1rem">
    <div class="detail-main">
        <section class="card">
            <h2><?= e(t('stock')) ?></h2>
            <?php
            $item_id = $item['id'];
            $back = '/stock/' . $item['id'];
            require __DIR__ . '/move_forms.php';
            ?>
        </section>

        <section class="card">
            <h2><?= e(t('stock_history')) ?></h2>
            <?php if ($rows === []): ?>
                <p><?= e(t('no_movements')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="is-num"><?= e(t('stock_custom_amount')) ?></th>
                                <th><?= e(t('stock_reason')) ?></th>
                                <th><?= e(t('staff')) ?></th>
                                <th><?= e(t('date')) ?></th>
                                <th><?= e(t('note')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="is-num" data-label="<?= e(t('stock_custom_amount')) ?>">
                                        <strong><?= e(stock_format((int) $row['change_hundredths'])) ?></strong>
                                    </td>
                                    <td data-label="<?= e(t('stock_reason')) ?>">
                                        <span class="orders-badge is-blue"><?= e(t('stock_reason_' . $row['reason'])) ?></span>
                                    </td>
                                    <td data-label="<?= e(t('staff')) ?>"><?= e($row['user_name']) ?></td>
                                    <td data-label="<?= e(t('date')) ?>"><?= e(date('j M Y H:i', strtotime((string) $row['created_at']))) ?></td>
                                    <td data-label="<?= e(t('note')) ?>">
                                        <?php if ($row['order_id'] !== null && $row['order_number'] !== ''): ?>
                                            <a href="<?= e(url('/orders/' . $row['order_id'])) ?>"><?= e($row['order_number']) ?></a>
                                        <?php endif; ?>
                                        <?php if ($row['note'] !== ''): ?>
                                            <?= e($row['note']) ?>
                                        <?php endif; ?>
                                        <?php if ($row['order_id'] === null && $row['note'] === ''): ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($has_previous || $has_next): ?>
                    <nav class="pager" aria-label="<?= e(t('stock_history')) ?>">
                        <?php if ($has_previous): ?>
                            <a class="btn btn-small btn-quiet" href="<?= e(url('/stock/' . $item['id'] . '?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
                        <?php endif; ?>
                        <?php if ($has_next): ?>
                            <a class="btn btn-small btn-quiet" href="<?= e(url('/stock/' . $item['id'] . '?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <aside class="detail-side"></aside>
</div>