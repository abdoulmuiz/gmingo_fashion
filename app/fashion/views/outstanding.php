<?php
$title = t('outstanding');
$back_href = null;
$subtitle = null;
$actions_html = '';
require __DIR__ . '/partials/ui_page_header.php';
?>
<?php if ($orders_off): ?>
    <?php
    $title = t('outstanding');
    $help = t('payments_need_orders');
    $icon = 'wallet';
    $cta_href = null;
    $cta_label = null;
    require __DIR__ . '/partials/ui_empty_state.php';
    ?>
<?php else: ?>
    <section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
        <article class="dash-kpi dash-kpi-orange">
            <span class="dash-kpi-top">
                <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('wallet', 'ui-icon') ?></span>
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?= e(t('outstanding')) ?></span>
                    <strong class="dash-kpi-value"><?= e(money((int) $total_kobo)) ?></strong>
                </span>
            </span>
        </article>
        <article class="dash-kpi dash-kpi-blue">
            <span class="dash-kpi-top">
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?= e(t('order_count')) ?></span>
                    <strong class="dash-kpi-value"><?= e((string) count($rows)) ?></strong>
                </span>
            </span>
        </article>
    </section>

    <section class="card list-page-shell" style="margin-top:1rem">
        <?php if ($rows === []): ?>
            <?php
            $title = t('no_outstanding');
            $help = null;
            $icon = 'circle-check';
            $cta_href = '/orders';
            $cta_label = t('orders');
            require __DIR__ . '/partials/ui_empty_state.php';
            ?>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th><?= e(t('orders')) ?></th>
                            <th><?= e(t('customer')) ?></th>
                            <th><?= e(t('due_date')) ?></th>
                            <th class="is-num"><?= e(t('balance')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td data-label="<?= e(t('orders')) ?>">
                                    <a href="<?= e(url('/orders/' . $row['id'])) ?>"><strong><?= e($row['order_number']) ?></strong></a>
                                </td>
                                <td data-label="<?= e(t('customer')) ?>"><?= e(order_display_customer_name((string) $row['customer_name'])) ?></td>
                                <td data-label="<?= e(t('due_date')) ?>"><?= e($row['due_date']) ?></td>
                                <td class="is-num" data-label="<?= e(t('balance')) ?>"><?= e(money((int) $row['balance_kobo'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($has_previous || $has_next): ?>
                <nav class="pager" aria-label="<?= e(t('outstanding')) ?>">
                    <?php if ($has_previous): ?>
                        <a href="<?= e(url('/outstanding?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
                    <?php endif; ?>
                    <?php if ($has_next): ?>
                        <a href="<?= e(url('/outstanding?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
