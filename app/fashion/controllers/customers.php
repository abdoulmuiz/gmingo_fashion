<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/customers.php';
require_once dirname(__DIR__, 2) . '/domain/measurements.php';

function customers_index(): void
{
    require_module('fashion.orders');
    $tab = customer_normalize_tab((string) ($_GET['tab'] ?? 'all'));
    $query = trim((string) ($_GET['q'] ?? ''));
    $sort = customer_normalize_sort((string) ($_GET['sort'] ?? 'name'));
    $options = ['tab' => $tab, 'q' => $query, 'sort' => $sort];
    $page = list_page($_GET['page'] ?? '1');
    $pageSize = customer_list_page_size();
    $total = customer_list_count($options);
    $totalPages = max(1, (int) ceil(max(0, $total) / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $fromRow = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
    $toRow = min($total, $page * $pageSize);
    $summary = customer_summary_stats();
    render('customers/index', [
        'title' => t('customers'),
        'tab' => $tab,
        'query' => $query,
        'sort' => $sort,
        'customers' => customer_list($options, $page),
        'summary' => $summary,
        'tab_counts' => customer_tab_counts($query),
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => $totalPages,
        'from_row' => $fromRow,
        'to_row' => $toRow,
        'has_previous' => $page > 1,
        'has_next' => $page * $pageSize < $total,
        'previous_path' => customer_list_path($tab, $query, $sort, $page - 1),
        'next_path' => customer_list_path($tab, $query, $sort, $page + 1),
        'page_path' => static function (int $p) use ($tab, $query, $sort): string {
            return customer_list_path($tab, $query, $sort, $p);
        },
        'shop_has_customers' => $summary['total'] > 0,
        'has_active_filters' => $tab !== 'all' || $query !== '' || $sort !== 'name',
        'can_new_order' => module_enabled('fashion.orders'),
    ]);
}

function customers_form(array $errors = [], array $old = []): void
{
    require_module('fashion.orders');
    render('customers/form', [
        'title' => t('new_customer'),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function customers_save(): void
{
    require_module('fashion.orders');
    csrf_check();
    $input = [
        'name' => (string) ($_POST['name'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'gender' => (string) ($_POST['gender'] ?? ''),
        'customer_type' => (string) ($_POST['customer_type'] ?? ''),
        'lang' => (string) ($_POST['lang'] ?? ''),
    ];
    $result = customer_create($input);
    if (isset($result['errors'])) {
        customers_form($result['errors'], $input);
        return;
    }
    redirect('/customers/' . $result['id']);
}

function customers_show(string $id): void
{
    require_module('fashion.orders');
    $profile = customer_profile($id);
    if ($profile === null) {
        http_response_code(404);
        render('simple', ['title' => t('not_found'), 'body' => t('not_found_body')]);
        return;
    }
    render('customers/show', [
        'title' => $profile['name'],
        'customer' => $profile,
        'measure' => module_enabled('fashion.measurements') ? measurement_profile($id, (string) ($_GET['garment'] ?? ''), (string) ($_GET['from'] ?? '')) : null,
    ]);
}
