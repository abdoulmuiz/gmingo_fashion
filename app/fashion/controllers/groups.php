<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/groups.php';
require_once dirname(__DIR__, 2) . '/domain/payments.php';

function groups_index(): void
{
    require_module('fashion.orders');
    $page = list_page($_GET['page'] ?? '1');
    $list = group_list($page);
    render('groups/index', [
        'title' => t('groups'),
        'groups' => $list['groups'],
        'page' => $list['page'],
        'has_previous' => $list['has_previous'],
        'has_next' => $list['has_next'],
    ]);
}

function groups_form(array $errors = [], array $old = []): void
{
    require_module('fashion.orders');
    render('groups/form', [
        'title' => t('new_group'),
        'errors' => $errors,
        'old' => $old,
        'group' => null,
        'customers' => group_customer_choices(),
    ]);
}

function groups_save(): void
{
    require_module('fashion.orders');
    csrf_check();
    $input = group_posted();
    $result = group_create($input);
    if (isset($result['error'])) {
        groups_form([$result['error'] => $result['error']], $input);
        return;
    }
    redirect('/groups/' . $result['id']);
}

function groups_show(string $id): void
{
    require_module('fashion.orders');
    $group = group_find($id);
    if ($group === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $page = group_page($id, list_page($_GET['page'] ?? '1'));
    render('groups/show', [
        'title' => $group['name'],
        'group' => $group,
        'members' => $page['members'],
        'row_count' => $page['row_count'],
        'total_kobo' => $page['total_kobo'],
        'paid_kobo' => $page['paid_kobo'],
        'balance_kobo' => $page['balance_kobo'],
        'page' => $page['page'],
        'has_previous' => $page['has_previous'],
        'has_next' => $page['has_next'],
        'payments' => group_payment_rows($id),
        'customers' => group_customer_choices(),
        'is_owner' => deposit_is_owner(),
        'finance' => module_enabled('fashion.finance'),
    ]);
}

function groups_update(string $id): void
{
    require_module('fashion.orders');
    csrf_check();
    $result = group_update($id, group_posted());
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/groups/' . $id);
}

function group_pay_submit(string $id): void
{
    require_module('fashion.orders');
    require_module('fashion.finance');
    csrf_check();
    $result = group_pay($id, (string) ($_POST['amount'] ?? ''), (string) ($_POST['method'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('payment_saved'));
    }
    redirect('/groups/' . $id);
}

function group_reverse_submit(string $groupId, string $paymentId): void
{
    require_module('fashion.orders');
    require_module('fashion.finance');
    csrf_check();
    if (!is_uuid($groupId) || !is_uuid($paymentId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_payments', ['id' => $paymentId, 'group_id' => $groupId], ['limit' => 1]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $result = payment_reverse((string) $rows[0]['order_id'], $paymentId, (string) ($_POST['reason'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('reversed'));
    }
    redirect('/groups/' . $groupId);
}

function order_group_submit(string $orderId): void
{
    require_module('fashion.orders');
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'remove') {
        $result = group_remove_order($orderId);
    } else {
        $result = group_add_order($orderId, (string) ($_POST['group_id'] ?? ''));
    }
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/orders/' . $orderId);
}

function group_posted(): array
{
    return [
        'name' => (string) ($_POST['name'] ?? ''),
        'event_date' => (string) ($_POST['event_date'] ?? ''),
        'pay_mode' => (string) ($_POST['pay_mode'] ?? ''),
        'payer_customer_id' => (string) ($_POST['payer_customer_id'] ?? ''),
    ];
}
