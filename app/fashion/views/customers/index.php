<?php
/** @var string $tab */
/** @var string $query */
/** @var string $sort */
/** @var list<array> $customers */
/** @var array{total:int,new_month:int,vip:int,outstanding_kobo:int} $summary */
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
/** @var bool $shop_has_customers */
/** @var bool $has_active_filters */
/** @var bool $can_new_order */

$tabLabels = [
    'all' => t('all'),
    'vip' => t('vip'),
    'regular' => t('regular'),
    'balance' => t('customers_tab_balance'),
    'new_month' => t('customers_tab_new_month'),
    'inactive' => t('customers_tab_inactive'),
];
$sortLabels = [
    'name' => t('customers_sort_name'),
    'newest' => t('customers_sort_newest'),
    'orders' => t('customers_sort_orders'),
    'spent' => t('customers_sort_spent'),
    'balance' => t('customers_sort_balance'),
];
$summaryCards = [
    [
        'key' => 'all',
        'label' => t('customers_summary_total'),
        'value' => number_format((int) $summary['total']),
        'tone' => 'blue',
        'icon' => 'users',
        'href' => customer_list_path('all', $query, $sort, 1),
    ],
    [
        'key' => 'new_month',
        'label' => t('customers_summary_new_month'),
        'value' => number_format((int) $summary['new_month']),
        'tone' => 'green',
        'icon' => 'user-plus',
        'href' => customer_list_path('new_month', $query, $sort, 1),
    ],
    [
        'key' => 'vip',
        'label' => t('customers_summary_vip'),
        'value' => number_format((int) $summary['vip']),
        'tone' => 'gold',
        'icon' => 'crown',
        'href' => customer_list_path('vip', $query, $sort, 1),
    ],
    [
        'key' => 'balance',
        'label' => t('customers_summary_outstanding'),
        'value' => money((int) $summary['outstanding_kobo']),
        'tone' => 'red',
        'icon' => 'wallet',
        'href' => customer_list_path('balance', $query, $sort, 1),
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
<section class="customers-page">
    <header class="customers-header">
        <div class="customers-header-copy">
            <h1><?= e(t('customers')) ?></h1>
            <p><?= e(t('customers_subtitle')) ?></p>
        </div>
        <div class="customers-header-actions">
            <a class="btn customers-btn-nowrap" href="<?= e(url('/customers/new')) ?>">
                <?= ui_icon('plus', 'ui-icon') ?>
                <span><?= e(t('new_customer')) ?></span>
            </a>
        </div>
    </header>

    <section class="dash-kpis customers-summary" aria-label="<?= e(t('customers')) ?>">
        <?php foreach ($summaryCards as $card): ?>
            <a class="dash-kpi dash-kpi-<?= e((string) $card['tone']) ?><?= $tab === $card['key'] ? ' is-active' : '' ?>" href="<?= e(url((string) $card['href'])) ?>">
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

    <section class="customers-toolbar card">
        <nav class="customers-chips" aria-label="<?= e(t('customers')) ?>">
            <?php foreach (customer_list_tabs() as $chip): ?>
                <?php
                $count = (int) ($tab_counts[$chip] ?? 0);
                $href = customer_list_path($chip, $query, $sort, 1);
                ?>
                <a class="customers-chip<?= $tab === $chip ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
                    <span><?= e($tabLabels[$chip] ?? $chip) ?></span>
                    <span class="customers-chip-count"><?= e(number_format($count)) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="customers-filters" method="get" action="<?= e(url('/customers')) ?>">
            <?php if ($tab !== 'all'): ?>
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <?php endif; ?>
            <label class="customers-search">
                <span class="sr-only"><?= e(t('search')) ?></span>
                <?= ui_icon('search', 'ui-icon customers-search-icon') ?>
                <input
                    type="search"
                    name="q"
                    value="<?= e($query) ?>"
                    placeholder="<?= e(t('customers_search_placeholder')) ?>"
                    autocomplete="off"
                >
            </label>
            <label class="customers-sort">
                <span class="sr-only"><?= e(t('customers_sort')) ?></span>
                <select name="sort" onchange="this.form.submit()">
                    <?php foreach ($sortLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="sr-only" type="submit"><?= e(t('search')) ?></button>
        </form>
    </section>

    <?php if ($customers === []): ?>
        <section class="customers-empty card">
            <span class="customers-empty-icon" aria-hidden="true"><?= ui_icon('users', 'ui-icon') ?></span>
            <?php if (!$shop_has_customers): ?>
                <h2><?= e(t('customers_empty_first_title')) ?></h2>
                <p><?= e(t('customers_empty_first_help')) ?></p>
                <a class="btn" href="<?= e(url('/customers/new')) ?>"><?= e(t('new_customer')) ?></a>
            <?php else: ?>
                <h2><?= e(t('customers_empty_filtered_title')) ?></h2>
                <p><?= e(t('customers_empty_filtered_help')) ?></p>
                <?php if ($has_active_filters): ?>
                    <a class="btn" href="<?= e(url('/customers')) ?>"><?= e(t('orders_clear_filters')) ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="customers-table-card card">
            <div class="customers-table-scroll">
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('customer')) ?></th>
                            <th scope="col"><?= e(t('phone')) ?></th>
                            <th scope="col" class="is-num"><?= e(t('order_count')) ?></th>
                            <th scope="col" class="is-num"><?= e(t('total_spent')) ?></th>
                            <th scope="col" class="is-num"><?= e(t('balance')) ?></th>
                            <th scope="col"><?= e(t('customers_col_last_order')) ?></th>
                            <th scope="col"><span class="sr-only"><?= e(t('orders_col_actions')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr class="customers-row">
                                <td data-label="<?= e(t('customer')) ?>">
                                    <div class="customers-identity">
                                        <span class="customers-avatar" aria-hidden="true"><?= e((string) $customer['initials']) ?></span>
                                        <span class="customers-identity-text">
                                            <strong><?= e((string) $customer['display_name']) ?></strong>
                                            <span class="customers-type-badge is-<?= e((string) $customer['customer_type']) ?>">
                                                <?= e(t((string) $customer['customer_type'] === 'vip' ? 'vip' : 'regular')) ?>
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="<?= e(t('phone')) ?>"><?= e((string) $customer['phone']) ?></td>
                                <td data-label="<?= e(t('order_count')) ?>" class="is-num"><?= e(number_format((int) $customer['order_count'])) ?></td>
                                <td data-label="<?= e(t('total_spent')) ?>" class="is-num"><?= e(money((int) $customer['spent_kobo'])) ?></td>
                                <td data-label="<?= e(t('balance')) ?>" class="is-num">
                                    <?php if ((int) $customer['balance_kobo'] > 0): ?>
                                        <span class="customers-balance is-owed"><?= e(money((int) $customer['balance_kobo'])) ?></span>
                                    <?php else: ?>
                                        <span class="customers-balance is-zero">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?= e(t('customers_col_last_order')) ?>">
                                    <span
                                        class="customers-last-order"
                                        <?php if ((string) $customer['last_order_title'] !== ''): ?>
                                            title="<?= e((string) $customer['last_order_title']) ?>"
                                        <?php endif; ?>
                                    ><?= e((string) $customer['last_order_label']) ?></span>
                                </td>
                                <td data-label="<?= e(t('orders_col_actions')) ?>" class="customers-actions-cell">
                                    <div class="customers-actions">
                                        <a class="btn btn-small btn-quiet" href="<?= e(url('/customers/' . $customer['id'])) ?>"><?= e(t('view')) ?></a>
                                        <?php if ($customer['tel_href'] !== null): ?>
                                            <a class="customers-icon-btn" href="<?= e((string) $customer['tel_href']) ?>" aria-label="<?= e(t('call')) ?>" title="<?= e(t('call')) ?>">
                                                <?= ui_icon('phone', 'ui-icon') ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($customer['whatsapp_href'] !== null): ?>
                                            <a class="customers-icon-btn is-whatsapp" href="<?= e((string) $customer['whatsapp_href']) ?>" aria-label="<?= e(t('whatsapp')) ?>" title="<?= e(t('whatsapp')) ?>" target="_blank" rel="noopener noreferrer">
                                                <?= ui_icon('message-circle', 'ui-icon') ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($can_new_order): ?>
                                            <a class="btn btn-small" href="<?= e(url('/orders/new?customer=' . $customer['id'])) ?>"><?= e(t('new_order')) ?></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <footer class="customers-pager">
                <p><?= e(t('customers_showing', [
                    'from' => (string) $from_row,
                    'to' => (string) $to_row,
                    'total' => number_format($total),
                ])) ?></p>
                <nav class="customers-pager-nav" aria-label="<?= e(t('customers')) ?>">
                    <?php if ($has_previous): ?>
                        <a class="btn btn-small btn-quiet" href="<?= e(url($previous_path)) ?>"><?= e(t('previous')) ?></a>
                    <?php else: ?>
                        <span class="btn btn-small btn-quiet is-disabled" aria-disabled="true"><?= e(t('previous')) ?></span>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $p): ?>
                        <?php if ($p === 0): ?>
                            <span class="customers-pager-ellipsis">…</span>
                        <?php elseif ($p === $page): ?>
                            <span class="customers-pager-page is-current" aria-current="page"><?= e((string) $p) ?></span>
                        <?php else: ?>
                            <a class="customers-pager-page" href="<?= e(url($page_path($p))) ?>"><?= e((string) $p) ?></a>
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
