<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/orders.php';
require_once dirname(__DIR__, 2) . '/domain/messages.php';
require_once dirname(__DIR__, 2) . '/domain/fittings.php';
require_once dirname(__DIR__, 2) . '/domain/groups.php';
require_once dirname(__DIR__, 2) . '/domain/fabric.php';
require_once dirname(__DIR__, 2) . '/domain/stages.php';
require_once dirname(__DIR__, 2) . '/domain/deposits.php';
require_once dirname(__DIR__, 2) . '/domain/payments.php';
require_once dirname(__DIR__, 2) . '/domain/receipts.php';
require_once dirname(__DIR__, 2) . '/domain/tracking.php';
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

function fabric_submit(string $id): void
{
    require_module('fashion.orders');
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'switch') {
        $result = fabric_set_supplied($id, (string) ($_POST['supplied'] ?? '') === '1');
    } else {
        $result = fabric_save_yards(
            $id,
            (string) ($_POST['received'] ?? ''),
            (string) ($_POST['used'] ?? ''),
            (string) ($_POST['note'] ?? '')
        );
    }
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/orders/' . $id);
}

function fabric_history_show(string $id): void
{
    require_module('fashion.orders');
    $order = order_find($id);
    if ($order === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    render('orders/fabric_history', [
        'title' => t('fabric_history'),
        'order' => $order,
        'rows' => fabric_log($id),
    ]);
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
    $nextStage = module_enabled('fashion.production') ? stage_next($id) : null;
    $depositBlock = null;
    if ($nextStage !== null) {
        $depositBlock = deposit_move_block($id, (string) $nextStage['id']);
    }
    render('orders/show', [
        'title' => $order['order_number'],
        'order' => $order,
        'next_stage' => $nextStage,
        'deposit' => deposit_status($id),
        'deposit_block' => $depositBlock,
        'is_owner' => deposit_is_owner(),
        'payments' => $finance ? payment_history($id) : [],
        'receipt_buttons' => $finance ? receipt_buttons($id) : [],
        'invoice_whatsapp' => $finance ? invoice_whatsapp_href($id) : null,
        'can_request_payment' => $links && (int) $order['balance_kobo'] > 0,
        'payment_requests' => $links ? payment_requests_open($id) : [],
        'email_note' => $links && payment_customer_needs_email_note((string) $order['customer_id']),
        'request_amount' => $links ? payment_amount_input((int) $order['balance_kobo']) : '',
        'fabric_has_log' => fabric_has_log($id),
        'order_group' => group_membership($id),
        'group_choices' => group_choices(),
        'fittings' => fitting_list($id),
        'tracking_href' => module_enabled('fashion.communication') ? url('/track/' . (string) tracking_issue($id)) : null,
        'message_link' => module_enabled('fashion.communication') ? message_link_state($id) : null,
        'messages_on' => module_enabled('fashion.communication'),
        'message_problem' => module_enabled('fashion.communication') ? message_customer_problem((string) $order['customer_id']) : null,
        'message_queue' => module_enabled('fashion.communication') ? message_to_send_list(1, $id) : [],
    ]);
}
