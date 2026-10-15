<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/orders.php';
require_once dirname(__DIR__, 2) . '/domain/stages.php';
require_once dirname(__DIR__, 2) . '/domain/payments.php';
require_once dirname(__DIR__, 2) . '/domain/paystack.php';

function orders_index(): void
{
    require_module('fashion.orders');
    $filter = (string) ($_GET['tab'] ?? 'all');
    if (!in_array($filter, order_tabs(), true)) {
        $filter = 'all';
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    $page = list_page($_GET['page'] ?? '1');
    $total = order_count($filter, $query);
    render('orders/index', [
        'title' => t('orders'),
        'filter' => $filter,
        'query' => $query,
        'orders' => order_list($filter, $query, $page),
        'page' => $page,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $total,
        'previous_path' => order_page_path($filter, $query, $page - 1),
        'next_path' => order_page_path($filter, $query, $page + 1),
    ]);
}

function orders_form(array $errors = [], array $old = []): void
{
    require_module('fashion.orders');
    if (!isset($old['customer_id']) && isset($_GET['customer']) && is_uuid((string) $_GET['customer'])) {
        $old['customer_id'] = (string) $_GET['customer'];
    }
    render('orders/form', [
        'title' => t('new_order'),
        'errors' => $errors,
        'old' => $old,
        'customers' => customer_search(''),
        'garments' => order_garments(),
    ]);
}

function orders_save(): void
{
    require_module('fashion.orders');
    csrf_check();
    $items = $_POST['items'] ?? [];
    $input = [
        'customer_id' => (string) ($_POST['customer_id'] ?? ''),
        'due_date' => (string) ($_POST['due_date'] ?? ''),
        'urgent' => (string) ($_POST['urgent'] ?? ''),
        'items' => is_array($items) ? $items : [],
    ];
    $result = order_create($input);
    if (isset($result['errors'])) {
        orders_form($result['errors'], $input);
        return;
    }
    redirect('/orders/' . $result['id']);
}

function orders_show(string $id): void
{
    require_module('fashion.orders');
    $order = order_find($id);
    if ($order === null) {
        http_response_code(404);
        render('simple', ['title' => t('not_found'), 'body' => t('not_found_body')]);
        return;
    }
    $finance = module_enabled('fashion.finance');
    $links = $finance && payment_links_enabled();
    render('orders/show', [
        'title' => $order['order_number'],
        'order' => $order,
        'next_stage' => module_enabled('fashion.production') ? stage_next($id) : null,
        'payments' => $finance ? payment_history($id) : [],
        'can_request_payment' => $links && (int) $order['balance_kobo'] > 0,
        'payment_requests' => $links ? payment_requests_open($id) : [],
        'email_note' => $links && payment_customer_needs_email_note((string) $order['customer_id']),
        'request_amount' => $links ? payment_amount_input((int) $order['balance_kobo']) : '',
    ]);
}
