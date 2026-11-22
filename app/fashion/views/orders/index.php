<?php
/** @var string $filter */
/** @var string $query */
/** @var string $sort */
/** @var string $date_from */
/** @var string $date_to */
/** @var list<array> $orders */
/** @var array{total:int,overdue:int,due_today:int,unpaid_balance_kobo:int} $summary */
/** @var array<string,int> $tab_counts */
/** @var int $page */
/** @var int $page_size */
/** @var int $total */
/** @var int $total_pages */
/** @var int $from_row */
/** @var int $to_row */
/** @var bool $has_previous */
/** @var bool $has_next */
/** @var string $previous_path */
/** @var string $next_path */
/** @var callable(int):string $page_path */
/** @var bool $can_calendar */
/** @var bool $can_finance */
/** @var bool $has_active_filters */

$tabLabel = static function (string $tab): string {
    return $tab === 'unpaid' ? t('tab_unpaid') : t($tab);
};
$sortLabels = [
    'newest' => t('orders_sort_newest'),
    'oldest' => t('orders_sort_oldest'),
    'due' => t('orders_sort_due'),
    'amount' => t('orders_sort_amount'),
];
$summaryCards = [
    [
        'key' => 'all',
        'label' => t('orders_summary_total'),
        'value' => number_format((int) $summary['total']),
        'tone' => 'blue',
        'icon' => 'package',
        'href' => order_page_path('all', $query, 1, ['sort' => $sort, 'from' => $date_from, 'to' => $date_to]),
    ],
    [
        'key' => 'overdue',
        'label' => t('overdue'),
        'value' => number_format((int) $summary['overdue']),
        'tone' => 'red',
        'icon' => 'clock',
        'href' => order_page_path('overdue', $query, 1, ['sort' => $sort, 'from' => $date_from, 'to' => $date_to]),
    ],
    [
        'key' => 'due_today',
        'label' => t('due_today'),
        'value' => number_format((int) $summary['due_today']),
        'tone' => 'orange',
        'icon' => 'calendar',
        'href' => order_page_path('due_today', $query, 1, ['sort' => $sort, 'from' => $date_from, 'to' => $date_to]),
    ],
    [
        'key' => 'unpaid',
        'label' => t('orders_summary_unpaid'),
        'value' => money((int) $summary['unpaid_balance_kobo']),
        'tone' => 'blue',
        'icon' => 'wallet',
        'href' => order_page_path('unpaid', $query, 1, ['sort' => $sort, 'from' => $date_from, 'to' => $date_to]),
    ],
];
$pageWindow = [];
if ($total_pages <= 7) {
    for ($p = 1; $p <= $total_pages; $p++) {
        $pageWindow[] = $p;
    }
} else {
    $pageWindow = [1];
    $start = max(2, $page - 1);
    $end = min($total_pages - 1, $page + 1);
    if ($start > 2) {
        $pageWindow[] = 0;
    }
    for ($p = $start; $p <= $end; $p++) {
        $pageWindow[] = $p;
    }
    if ($end < $total_pages - 1) {
        $pageWindow[] = 0;
    }
    $pageWindow[] = $total_pages;
}
?>
<section class="orders-page">
    <header class="orders-header">
        <div class="orders-header-copy">
            <h1><?= e(t('orders')) ?></h1>
            <p><?= e(t('orders_subtitle')) ?></p>
        </div>
        <div class="orders-header-actions">
            <?php if ($can_calendar): ?>
                <a class="btn btn-quiet orders-btn-nowrap" href="<?= e(url('/calendar')) ?>">
                    <?= ui_icon('calendar', 'ui-icon') ?>
                    <span><?= e(t('calendar')) ?></span>
                </a>
            <?php endif; ?>
            <a class="btn orders-btn-nowrap" href="<?= e(url('/orders/new')) ?>">
                <?= ui_icon('plus', 'ui-icon') ?>
                <span><?= e(t('new_order')) ?></span>
            </a>
        </div>
    </header>

    <section class="dash-kpis orders-summary" aria-label="<?= e(t('orders')) ?>">
        <?php foreach ($summaryCards as $card): ?>
            <a class="dash-kpi dash-kpi-<?= e((string) $card['tone']) ?><?= $filter === $card['key'] ? ' is-active' : '' ?>" href="<?= e(url((string) $card['href'])) ?>">
                <span class="dash-kpi-top">
                    <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon((string) $card['icon'], 'ui-icon') ?></span>
                    <span class="dash-kpi-body">
                        <span class="dash-kpi-label"><?= e((string) $card['label']) ?></span>
                        <strong class="dash-kpi-value"><?= e((string) $card['value']) ?></strong>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="orders-toolbar card">
        <nav class="orders-chips" aria-label="<?= e(t('orders')) ?>">
            <?php foreach (order_tabs() as $tab): ?>
                <?php
                $count = (int) ($tab_counts[$tab] ?? 0);
                $href = order_page_path($tab, $query, 1, ['sort' => $sort, 'from' => $date_from, 'to' => $date_to]);
                ?>
                <a class="orders-chip<?= $filter === $tab ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
                    <span><?= e($tabLabel($tab)) ?></span>
                    <span class="orders-chip-count"><?= e(number_format($count)) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="orders-filters" method="get" action="<?= e(url('/orders')) ?>" data-orders-filter>
            <input type="hidden" name="tab" value="<?= e($filter) ?>">
            <label class="orders-search">
                <span class="sr-only"><?= e(t('search')) ?></span>
                <?= ui_icon('search', 'ui-icon orders-search-icon') ?>
                <input
                    type="search"
                    name="q"
                    value="<?= e($query) ?>"
                    placeholder="<?= e(t('orders_search_placeholder')) ?>"
                    autocomplete="off"
                    data-orders-search
                >
            </label>
            <label class="orders-sort">
                <span class="sr-only"><?= e(t('orders_sort')) ?></span>
                <select name="sort" data-orders-sort>
                    <?php foreach ($sortLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="orders-date">
                <span class="sr-only"><?= e(t('orders_date_from')) ?></span>
                <input type="date" name="from" value="<?= e($date_from) ?>" title="<?= e(t('orders_date_from')) ?>">
            </label>
            <label class="orders-date">
                <span class="sr-only"><?= e(t('orders_date_to')) ?></span>
                <input type="date" name="to" value="<?= e($date_to) ?>" title="<?= e(t('orders_date_to')) ?>">
            </label>
            <noscript>
                <button class="btn btn-small" type="submit"><?= e(t('search')) ?></button>
            </noscript>
        </form>
    </section>

    <?php if ($orders === []): ?>
        <section class="orders-empty card">
            <span class="orders-empty-icon" aria-hidden="true"><?= ui_icon('package', 'ui-icon') ?></span>
            <h2><?= e(t('orders_empty_title')) ?></h2>
            <p><?= e($has_active_filters ? t('orders_empty_filtered') : t('no_orders')) ?></p>
            <?php if ($has_active_filters): ?>
                <a class="btn" href="<?= e(url('/orders')) ?>"><?= e(t('orders_clear_filters')) ?></a>
            <?php else: ?>
                <a class="btn" href="<?= e(url('/orders/new')) ?>"><?= e(t('new_order')) ?></a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="orders-table-card card">
            <div class="orders-table-scroll">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('orders_col_number')) ?></th>
                            <th scope="col"><?= e(t('customer')) ?></th>
                            <th scope="col"><?= e(t('orders_col_items')) ?></th>
                            <th scope="col"><?= e(t('due_date')) ?></th>
                            <th scope="col"><?= e(t('stage')) ?></th>
                            <th scope="col"><?= e(t('orders_col_payment')) ?></th>
                            <th scope="col" class="is-num"><?= e(t('total')) ?></th>
                            <th scope="col" class="is-num"><?= e(t('balance')) ?></th>
                            <th scope="col"><span class="sr-only"><?= e(t('orders_col_actions')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $itemsLabel = t('orders_items_count', ['n' => (string) max(0, (int) $order['item_count'])]);
                            $dueClass = 'orders-due';
                            if ($order['due_tone'] === 'overdue') {
                                $dueClass .= ' is-overdue';
                            } elseif ($order['due_tone'] === 'today') {
                                $dueClass .= ' is-today';
                            }
                            ?>
                            <tr class="orders-row">
                                <td data-label="<?= e(t('orders_col_number')) ?>">
                                    <div class="orders-cell-order">
                                        <a class="orders-number" href="<?= e(url('/orders/' . $order['id'])) ?>"><?= e($order['order_number']) ?></a>
                                        <?php if ($order['urgent']): ?>
                                            <span class="orders-pill is-urgent"><?= e(t('urgent')) ?></span>
                                        <?php endif; ?>
                                        <span class="orders-badge is-<?= e((string) $order['status_tone']) ?> orders-badge-mobile"><?= e((string) $order['status_label']) ?></span>
                                    </div>
                                </td>
                                <td data-label="<?= e(t('customer')) ?>">
                                    <div class="orders-customer">
                                        <strong><?= e((string) $order['customer_display_name']) ?></strong>
                                        <?php if ((string) $order['customer_phone'] !== ''): ?>
                                            <span><?= e((string) $order['customer_phone']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="<?= e(t('orders_col_items')) ?>"><?= e($itemsLabel) ?></td>
                                <td data-label="<?= e(t('due_date')) ?>">
                                    <span class="<?= e($dueClass) ?>">
                                        <?= e((string) $order['due_date']) ?>
                                        <?php if ($order['due_tone'] === 'overdue'): ?>
                                            <em><?= e(t('overdue')) ?></em>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td data-label="<?= e(t('stage')) ?>" class="orders-status-cell">
                                    <span class="orders-badge is-<?= e((string) $order['status_tone']) ?>"><?= e((string) $order['status_label']) ?></span>
                                </td>
                                <td data-label="<?= e(t('orders_col_payment')) ?>">
                                    <span class="orders-badge is-<?= e((string) $order['payment_tone']) ?>"><?= e((string) $order['payment_label']) ?></span>
                                </td>
                                <td data-label="<?= e(t('total')) ?>" class="is-num"><?= e(money((int) $order['total_kobo'])) ?></td>
                                <td data-label="<?= e(t('balance')) ?>" class="is-num">
                                    <?php if ((int) $order['balance_kobo'] > 0): ?>
                                        <span class="orders-balance is-owed"><?= e(money((int) $order['balance_kobo'])) ?></span>
                                    <?php else: ?>
                                        <span class="orders-balance is-zero">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?= e(t('orders_col_actions')) ?>" class="orders-actions-cell">
                                    <div class="orders-actions">
                                        <a class="btn btn-small btn-quiet" href="<?= e(url('/orders/' . $order['id'])) ?>"><?= e(t('view')) ?></a>
                                        <details class="orders-menu">
                                            <summary aria-label="<?= e(t('orders_col_actions')) ?>">
                                                <?= ui_icon('ellipsis-vertical', 'ui-icon') ?>
                                            </summary>
                                            <div class="orders-menu-panel">
                                                <a href="<?= e(url('/orders/' . $order['id'])) ?>"><?= e(t('view')) ?></a>
                                                <?php if ($can_finance): ?>
                                                    <a href="<?= e(url('/orders/' . $order['id'] . '#record-payment')) ?>"><?= e(t('record_payment')) ?></a>
                                                    <a href="<?= e(url('/orders/' . $order['id'] . '/invoice')) ?>"><?= e(t('orders_print_invoice')) ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <footer class="orders-pager">
                <p><?= e(t('orders_showing', [
                    'from' => (string) $from_row,
                    'to' => (string) $to_row,
                    'total' => number_format($total),
                ])) ?></p>
                <nav class="orders-pager-nav" aria-label="<?= e(t('orders')) ?>">
                    <?php if ($has_previous): ?>
                        <a class="btn btn-small btn-quiet" href="<?= e(url($previous_path)) ?>"><?= e(t('previous')) ?></a>
                    <?php else: ?>
                        <span class="btn btn-small btn-quiet is-disabled" aria-disabled="true"><?= e(t('previous')) ?></span>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $p): ?>
                        <?php if ($p === 0): ?>
                            <span class="orders-pager-ellipsis">…</span>
                        <?php elseif ($p === $page): ?>
                            <span class="orders-pager-page is-current" aria-current="page"><?= e((string) $p) ?></span>
                        <?php else: ?>
                            <a class="orders-pager-page" href="<?= e(url($page_path($p))) ?>"><?= e((string) $p) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($has_next): ?>
                        <a class="btn btn-small btn-quiet" href="<?= e(url($next_path)) ?>"><?= e(t('next')) ?></a>
                    <?php else: ?>
                        <span class="btn btn-small btn-quiet is-disabled" aria-disabled="true"><?= e(t('next')) ?></span>
                    <?php endif; ?>
                </nav>
            </footer>
        </section>
    <?php endif; ?>
</section>
<script>
(function () {
    var form = document.querySelector('[data-orders-filter]');
    if (!form) return;
    var search = form.querySelector('[data-orders-search]');
    var sort = form.querySelector('[data-orders-sort]');
    function submitNow() {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
    // Full-page GET (not AJAX): search submits on Enter only.
    if (search) {
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitNow();
            }
        });
    }
    if (sort) {
        sort.addEventListener('change', submitNow);
    }
    form.querySelectorAll('input[type="date"]').forEach(function (input) {
        input.addEventListener('change', submitNow);
    });
})();
</script>
