<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

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
    return ['id' => $id, 'amount_kobo' => $kobo];
}

function payment_reverse(string $orderId, string $paymentId): array
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
    $payment = $rows[0];
    if ((string) $payment['kind'] !== 'payment') {
        return ['error' => 'not_allowed'];
    }
    if (db_select('fashion_payments', ['reverses_payment_id' => $paymentId]) !== []) {
        return ['error' => 'already_reversed'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $amount = (int) $payment['amount_kobo'];
    $provider = (string) ($payment['provider'] ?? 'manual');
    if (!in_array($provider, ['manual', 'paystack'], true)) {
        $provider = 'manual';
    }
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
    ]);
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
        ];
    }
    return $history;
}

function payment_outstanding(): array
{
    require_module('fashion.finance');
    if (!module_enabled('fashion.orders')) {
        return ['orders_off' => true, 'rows' => [], 'total_kobo' => 0];
    }
    $rows = db_query(
        'SELECT o.id, o.order_number, o.due_date, c.name AS customer_name,
            (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i
             WHERE i.order_id = o.id AND i.tenant_id = :tenant_id) AS total_kobo,
            (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p
             WHERE p.order_id = o.id AND p.tenant_id = :tenant_id) AS paid_kobo
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id
           AND (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i
                WHERE i.order_id = o.id AND i.tenant_id = :tenant_id)
             > (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p
                WHERE p.order_id = o.id AND p.tenant_id = :tenant_id)
         ORDER BY o.due_date ASC, o.order_number ASC
         LIMIT 300'
    );
    $list = [];
    $total = 0;
    foreach ($rows as $row) {
        $balance = (int) $row['total_kobo'] - (int) $row['paid_kobo'];
        $total += $balance;
        $list[] = [
            'id' => (string) $row['id'],
            'order_number' => (string) $row['order_number'],
            'customer_name' => (string) $row['customer_name'],
            'due_date' => (string) $row['due_date'],
            'balance_kobo' => $balance,
        ];
    }
    return ['orders_off' => false, 'rows' => $list, 'total_kobo' => $total];
}
