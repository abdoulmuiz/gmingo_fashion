<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/orders.php';

function orders_index(): void
{
    require_module('fashion.orders');
    $filter = (string) ($_GET['tab'] ?? 'all');
    if (!in_array($filter, order_tabs(), true)) {
        $filter = 'all';
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    render('orders/index', [
        'title' => t('orders'),
        'filter' => $filter,
        'query' => $query,
        'orders' => order_list($filter, $query),
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
    render('orders/show', [
        'title' => $order['order_number'],
        'order' => $order,
    ]);
}
