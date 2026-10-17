<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/receipts.php';

function payment_record(string $orderId, string $amount, string $method): array
{
    require_module('fashion.finance');
    if (!is_uuid($orderId) || db_select('fashion_orders', ['id' => $orderId]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $kobo = kobo_from_input($amount);
    if ($kobo === null || $kobo <= 0) {
        return ['error' => 'invalid_amount'];
    }
    if (!in_array($method, ['cash', 'transfer'], true)) {
        return ['error' => 'required'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    receipt_write_guard();
    $ownTransaction = !db()->inTransaction();
    if ($ownTransaction) {
        db_begin();
    }
    try {
        $id = db_insert('fashion_payments', [
            'order_id' => $orderId,
            'amount_kobo' => $kobo,
            'method' => $method,
            'reverses_payment_id' => null,
            'kind' => 'payment',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'provider' => 'manual',
            'provider_reference' => null,
            'source' => 'staff',
        ]);
        $receipt = receipt_issue($id);
        if ($ownTransaction) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction) {
            db_rollback();
        }
        throw $e;
    }
    return [
        'id' => $id,
        'amount_kobo' => $kobo,
        'receipt_id' => $receipt['id'],
        'receipt_number' => $receipt['receipt_number'],
    ];
}

function payment_reverse(string $orderId, string $paymentId, string $reason): array
{
    require_module('fashion.finance');
    if (!is_uuid($orderId) || !is_uuid($paymentId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_payments', ['id' => $paymentId, 'order_id' => $orderId]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        return ['error' => 'reversal_reason_required'];
    }
    $payment = $rows[0];
    if ((string) $payment['kind'] !== 'payment') {
        return ['error' => 'not_allowed'];
    }
    $groupPaymentId = $payment['group_payment_id'] ?? null;
    if (is_string($groupPaymentId) && $groupPaymentId !== '') {
        return group_payment_reverse($groupPaymentId, $reason);
    }
    if (db_select('fashion_payments', ['reverses_payment_id' => $paymentId]) !== []) {
        return ['error' => 'already_reversed'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    receipt_write_guard();
    $amount = (int) $payment['amount_kobo'];
    $provider = (string) ($payment['provider'] ?? 'manual');
    if (!in_array($provider, ['manual', 'paystack'], true)) {
        $provider = 'manual';
    }
    $ownTransaction = !db()->inTransaction();
    if ($ownTransaction) {
        db_begin();
    }
    try {
        $id = db_insert('fashion_payments', [
            'order_id' => $orderId,
            'amount_kobo' => -$amount,
            'method' => (string) $payment['method'],
            'reverses_payment_id' => $paymentId,
            'kind' => 'reversal',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'provider' => $provider,
            'provider_reference' => null,
            'source' => 'staff',
            'reason' => $reason,
        ]);
        receipt_void_payment($paymentId);
        if ($ownTransaction) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction) {
            db_rollback();
        }
        throw $e;
    }
    return ['id' => $id, 'amount_kobo' => -$amount];
}

function payment_history(string $orderId): array
{
    require_module('fashion.finance');
    $rows = db_select('fashion_payments', ['order_id' => $orderId], ['order' => 'seq ASC', 'limit' => 100]);
    $reversed = [];
    foreach ($rows as $row) {
        if (is_string($row['reverses_payment_id']) && $row['reverses_payment_id'] !== '') {
            $reversed[$row['reverses_payment_id']] = true;
        }
    }
    $history = [];
    foreach ($rows as $row) {
        $history[] = [
            'id' => (string) $row['id'],
            'amount_kobo' => (int) $row['amount_kobo'],
            'method' => (string) $row['method'],
            'kind' => (string) $row['kind'],
            'created_at' => (string) $row['created_at'],
            'reversed' => isset($reversed[(string) $row['id']]),
            'provider' => (string) ($row['provider'] ?? 'manual'),
            'provider_reference' => (string) ($row['provider_reference'] ?? ''),
            'reason' => (string) ($row['reason'] ?? ''),
        ];
    }
    return $history;
}

function payment_outstanding(int $page = 1): array
{
    require_module('fashion.finance');
    $page = list_page($page);
    if (!module_enabled('fashion.orders')) {
        return [
            'orders_off' => true,
            'rows' => [],
            'total_kobo' => 0,
            'page' => $page,
            'has_previous' => false,
            'has_next' => false,
        ];
    }
    [$totalSql, $paidSql] = order_money_sql();
    $where = ' FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id
           AND ' . $totalSql . ' > ' . $paidSql;
    $summary = db_query(
        'SELECT COUNT(*) AS row_count, COALESCE(SUM(' . $totalSql . ' - ' . $paidSql . '), 0) AS total_kobo' . $where
    );
    $rowCount = (int) ($summary[0]['row_count'] ?? 0);
    $total = (int) ($summary[0]['total_kobo'] ?? 0);
    $rows = db_query(
        'SELECT o.id, o.order_number, o.due_date, c.name AS customer_name, '
            . $totalSql . ' AS total_kobo, ' . $paidSql . ' AS paid_kobo'
            . $where
            . ' ORDER BY o.due_date ASC, o.order_number ASC LIMIT :row_limit OFFSET :row_offset',
        [
            'row_limit' => list_page_size(),
            'row_offset' => ($page - 1) * list_page_size(),
        ]
    );
    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id' => (string) $row['id'],
            'order_number' => (string) $row['order_number'],
            'customer_name' => (string) $row['customer_name'],
            'due_date' => (string) $row['due_date'],
            'balance_kobo' => (int) $row['total_kobo'] - (int) $row['paid_kobo'],
        ];
    }
    return [
        'orders_off' => false,
        'rows' => $list,
        'total_kobo' => $total,
        'page' => $page,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $rowCount,
    ];
}
