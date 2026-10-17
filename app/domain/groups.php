<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/receipts.php';

function group_modes(): array
{
    return ['each_pays', 'one_pays'];
}

function group_mode(string $mode): string
{
    if ($mode === 'each') {
        return 'each_pays';
    }
    return in_array($mode, group_modes(), true) ? $mode : 'each_pays';
}

function group_require_order(string $orderId): array
{
    require_module('fashion.orders');
    if (!is_uuid($orderId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $rows[0];
}

function group_require(string $groupId): array
{
    require_module('fashion.orders');
    $group = group_find($groupId);
    if ($group === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $group;
}

function group_find(string $groupId): ?array
{
    require_module('fashion.orders');
    if (!is_uuid($groupId)) {
        return null;
    }
    $rows = db_query(
        'SELECT g.id, g.name, g.pay_mode, g.event_date, g.payer_customer_id, g.created_at, c.name AS payer_name
         FROM fashion_order_groups g
         LEFT JOIN fashion_customers c ON c.id = g.payer_customer_id AND c.tenant_id = :tenant_id
         WHERE g.tenant_id = :tenant_id AND g.id = :group_id
         LIMIT 1',
        ['group_id' => $groupId]
    );
    if ($rows === []) {
        return null;
    }
    $row = $rows[0];
    $payerId = $row['payer_customer_id'];
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'pay_mode' => group_mode((string) $row['pay_mode']),
        'event_date' => is_string($row['event_date']) && $row['event_date'] !== '' ? (string) $row['event_date'] : null,
        'payer_customer_id' => is_string($payerId) && $payerId !== '' ? $payerId : null,
        'payer_name' => (string) ($row['payer_name'] ?? ''),
        'created_at' => (string) $row['created_at'],
    ];
}

function group_input(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $mode = (string) ($input['pay_mode'] ?? '');
    $dateRaw = trim((string) ($input['event_date'] ?? ''));
    $payerId = trim((string) ($input['payer_customer_id'] ?? ''));
    if ($name === '' || strlen($name) > 190) {
        return ['error' => 'required'];
    }
    if (!in_array($mode, group_modes(), true)) {
        return ['error' => 'group_mode_invalid'];
    }
    $eventDate = null;
    if ($dateRaw !== '') {
        $parsed = DateTime::createFromFormat('!Y-m-d', $dateRaw);
        if ($parsed === false || $parsed->format('Y-m-d') !== $dateRaw) {
            return ['error' => 'event_date_invalid'];
        }
        $eventDate = $dateRaw;
    }
    if ($mode === 'one_pays') {
        if (!is_uuid($payerId) || db_select('fashion_customers', ['id' => $payerId], ['limit' => 1]) === []) {
            return ['error' => 'group_payer_required'];
        }
    } else {
        $payerId = null;
    }
    return [
        'name' => $name,
        'pay_mode' => $mode,
        'event_date' => $eventDate,
        'payer_customer_id' => $mode === 'one_pays' ? $payerId : null,
    ];
}

function group_create(array $input): array
{
    require_module('fashion.orders');
    $clean = group_input($input);
    if (isset($clean['error'])) {
        return $clean;
    }
    $id = db_insert('fashion_order_groups', [
        'name' => $clean['name'],
        'pay_mode' => $clean['pay_mode'],
        'event_date' => $clean['event_date'],
        'payer_customer_id' => $clean['payer_customer_id'],
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function group_update(string $groupId, array $input): array
{
    require_module('fashion.orders');
    $group = group_require($groupId);
    $clean = group_input($input);
    if (isset($clean['error'])) {
        return $clean;
    }
    if ($clean['pay_mode'] !== $group['pay_mode'] && group_has_payments($groupId)) {
        return ['error' => 'group_mode_locked'];
    }
    db_update('fashion_order_groups', [
        'name' => $clean['name'],
        'pay_mode' => $clean['pay_mode'],
        'event_date' => $clean['event_date'],
        'payer_customer_id' => $clean['payer_customer_id'],
    ], ['id' => $groupId]);
    return [];
}

function group_has_payments(string $groupId): bool
{
    $rows = db_query(
        'SELECT p.id
         FROM fashion_payments p
         INNER JOIN fashion_orders o ON o.id = p.order_id AND o.tenant_id = :tenant_id
         WHERE p.tenant_id = :tenant_id AND o.group_id = :group_id
         LIMIT 1',
        ['group_id' => $groupId]
    );
    if ($rows !== []) {
        return true;
    }
    $shared = db_query(
        'SELECT id FROM fashion_payments WHERE tenant_id = :tenant_id AND group_id = :group_id LIMIT 1',
        ['group_id' => $groupId]
    );
    return $shared !== [];
}

function group_choices(): array
{
    require_module('fashion.orders');
    $rows = db_query(
        'SELECT id, name FROM fashion_order_groups WHERE tenant_id = :tenant_id ORDER BY name ASC'
    );
    $choices = [];
    foreach ($rows as $row) {
        $choices[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
        ];
    }
    return $choices;
}

function group_customer_choices(): array
{
    require_module('fashion.orders');
    $rows = db_query(
        'SELECT id, name FROM fashion_customers WHERE tenant_id = :tenant_id ORDER BY name ASC'
    );
    $choices = [];
    foreach ($rows as $row) {
        $choices[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
        ];
    }
    return $choices;
}

function group_membership(string $orderId): ?array
{
    require_module('fashion.orders');
    if (!is_uuid($orderId)) {
        return null;
    }
    $rows = db_query(
        'SELECT g.id, g.name, g.pay_mode
         FROM fashion_orders o
         INNER JOIN fashion_order_groups g ON g.id = o.group_id AND g.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id AND o.id = :order_id
         LIMIT 1',
        ['order_id' => $orderId]
    );
    if ($rows === []) {
        return null;
    }
    return [
        'id' => (string) $rows[0]['id'],
        'name' => (string) $rows[0]['name'],
        'pay_mode' => group_mode((string) $rows[0]['pay_mode']),
    ];
}

function group_add_order(string $orderId, string $groupId): array
{
    require_module('fashion.orders');
    $order = group_require_order($orderId);
    if (!is_uuid($groupId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $group = group_require($groupId);
    $current = $order['group_id'] ?? null;
    if (!is_string($current) || $current === '') {
        $current = null;
    }
    if ($current !== null && $current !== $group['id']) {
        return ['error' => 'order_already_in_group'];
    }
    if ($current === $group['id']) {
        return [];
    }
    db_update('fashion_orders', ['group_id' => $group['id']], ['id' => $orderId]);
    return [];
}

function group_remove_order(string $orderId): array
{
    require_module('fashion.orders');
    group_require_order($orderId);
    db_update('fashion_orders', ['group_id' => null], ['id' => $orderId]);
    return [];
}

function group_money(string $groupId): array
{
    require_module('fashion.orders');
    $totals = db_query(
        'SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) AS total_kobo
         FROM fashion_order_items i
         INNER JOIN fashion_orders o ON o.id = i.order_id AND o.tenant_id = :tenant_id
         WHERE i.tenant_id = :tenant_id AND o.group_id = :group_id',
        ['group_id' => $groupId]
    );
    $paid = db_query(
        'SELECT COALESCE(SUM(p.amount_kobo), 0) AS paid_kobo
         FROM fashion_payments p
         INNER JOIN fashion_orders o ON o.id = p.order_id AND o.tenant_id = :tenant_id
         WHERE p.tenant_id = :tenant_id AND o.group_id = :group_id',
        ['group_id' => $groupId]
    );
    $total = (int) ($totals[0]['total_kobo'] ?? 0);
    $paidKobo = (int) ($paid[0]['paid_kobo'] ?? 0);
    return [
        'total_kobo' => $total,
        'paid_kobo' => $paidKobo,
        'balance_kobo' => $total - $paidKobo,
    ];
}

function group_page(string $groupId, int $page = 1): array
{
    require_module('fashion.orders');
    $page = list_page($page);
    $countRows = db_query(
        'SELECT COUNT(*) AS row_count FROM fashion_orders WHERE tenant_id = :tenant_id AND group_id = :group_id',
        ['group_id' => $groupId]
    );
    $rowCount = (int) ($countRows[0]['row_count'] ?? 0);
    $money = group_money($groupId);
    $rows = db_query(
        'SELECT o.id, o.order_number, c.name AS customer_name,
            (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id) AS total_kobo,
            (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p WHERE p.order_id = o.id AND p.tenant_id = :tenant_id) AS paid_kobo
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id AND o.group_id = :group_id
         ORDER BY o.created_at ASC, o.order_number ASC
         LIMIT :row_limit OFFSET :row_offset',
        [
            'group_id' => $groupId,
            'row_limit' => list_page_size(),
            'row_offset' => ($page - 1) * list_page_size(),
        ]
    );
    $members = [];
    foreach ($rows as $row) {
        $total = (int) $row['total_kobo'];
        $paidKobo = (int) $row['paid_kobo'];
        $members[] = [
            'id' => (string) $row['id'],
            'order_number' => (string) $row['order_number'],
            'customer_name' => (string) $row['customer_name'],
            'total_kobo' => $total,
            'paid_kobo' => $paidKobo,
            'balance_kobo' => $total - $paidKobo,
        ];
    }
    return [
        'members' => $members,
        'row_count' => $rowCount,
        'total_kobo' => $money['total_kobo'],
        'paid_kobo' => $money['paid_kobo'],
        'balance_kobo' => $money['balance_kobo'],
        'page' => $page,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $rowCount,
    ];
}

function group_list(int $page = 1): array
{
    require_module('fashion.orders');
    $page = list_page($page);
    $countRows = db_query('SELECT COUNT(*) AS row_count FROM fashion_order_groups WHERE tenant_id = :tenant_id');
    $rowCount = (int) ($countRows[0]['row_count'] ?? 0);
    $rows = db_query(
        'SELECT id, name, pay_mode, event_date
         FROM fashion_order_groups
         WHERE tenant_id = :tenant_id
         ORDER BY name ASC
         LIMIT :row_limit OFFSET :row_offset',
        [
            'row_limit' => list_page_size(),
            'row_offset' => ($page - 1) * list_page_size(),
        ]
    );
    $groups = [];
    foreach ($rows as $row) {
        $groups[] = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'pay_mode' => group_mode((string) $row['pay_mode']),
            'event_date' => is_string($row['event_date']) && $row['event_date'] !== '' ? (string) $row['event_date'] : null,
        ];
    }
    return [
        'groups' => $groups,
        'page' => $page,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $rowCount,
    ];
}

function group_payment_rows(string $groupId): array
{
    require_module('fashion.orders');
    $rows = db_query(
        'SELECT group_payment_id, MIN(id) AS payment_id, MIN(order_id) AS order_id,
                SUM(amount_kobo) AS amount_kobo, MIN(created_at) AS created_at, MIN(method) AS method
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND group_id = :group_id AND kind = :kind AND group_payment_id IS NOT NULL
         GROUP BY group_payment_id
         ORDER BY MIN(seq) ASC',
        ['group_id' => $groupId, 'kind' => 'payment']
    );
    $reversedRows = db_query(
        'SELECT reverses_payment_id
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND group_id = :group_id AND kind = :kind',
        ['group_id' => $groupId, 'kind' => 'reversal']
    );
    $reversed = [];
    foreach ($reversedRows as $row) {
        if (is_string($row['reverses_payment_id']) && $row['reverses_payment_id'] !== '') {
            $reversed[$row['reverses_payment_id']] = true;
        }
    }
    $payments = [];
    foreach ($rows as $row) {
        $paymentId = (string) $row['payment_id'];
        $payments[] = [
            'group_payment_id' => (string) $row['group_payment_id'],
            'payment_id' => $paymentId,
            'order_id' => (string) $row['order_id'],
            'amount_kobo' => (int) $row['amount_kobo'],
            'created_at' => (string) $row['created_at'],
            'method' => (string) $row['method'],
            'reversed' => isset($reversed[$paymentId]),
        ];
    }
    return $payments;
}

function group_pay(string $groupId, string $amount, string $method): array
{
    require_module('fashion.orders');
    require_module('fashion.finance');
    $group = group_require($groupId);
    if ($group['pay_mode'] !== 'one_pays') {
        return ['error' => 'group_each_pays_note'];
    }
    if ($group['payer_customer_id'] === null) {
        return ['error' => 'group_payer_required'];
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
    $groupPaymentId = uuid();
    $now = app_now()->format('Y-m-d H:i:s');
    db_begin();
    try {
        $orders = db_query(
            'SELECT o.id,
                (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id) AS total_kobo,
                (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p WHERE p.order_id = o.id AND p.tenant_id = :tenant_id) AS paid_kobo
             FROM fashion_orders o
             WHERE o.tenant_id = :tenant_id AND o.group_id = :group_id
             ORDER BY o.created_at ASC, o.order_number ASC',
            ['group_id' => $groupId]
        );
        $remaining = $kobo;
        $splits = [];
        foreach ($orders as $order) {
            $balance = (int) $order['total_kobo'] - (int) $order['paid_kobo'];
            if ($balance <= 0 || $remaining <= 0) {
                continue;
            }
            $apply = $balance < $remaining ? $balance : $remaining;
            $splits[] = [
                'order_id' => (string) $order['id'],
                'amount_kobo' => $apply,
            ];
            $remaining -= $apply;
        }
        if ($remaining > 0) {
            db_rollback();
            return ['error' => 'group_amount_above'];
        }
        $inserted = 0;
        foreach ($splits as $split) {
            $paymentId = db_insert('fashion_payments', [
                'order_id' => $split['order_id'],
                'amount_kobo' => $split['amount_kobo'],
                'method' => $method,
                'reverses_payment_id' => null,
                'kind' => 'payment',
                'user_id' => $userId,
                'created_at' => $now,
                'provider' => 'manual',
                'provider_reference' => null,
                'source' => 'staff',
                'reason' => null,
                'group_id' => $groupId,
                'group_payment_id' => $groupPaymentId,
                'payer_customer_id' => $group['payer_customer_id'],
            ]);
            receipt_issue($paymentId);
            $inserted++;
            if (defined('GMINGO_TESTING') && !empty($GLOBALS['gmingo_fail_group_pay']) && $inserted === 1) {
                throw new RuntimeException('Simulated group payment failure.');
            }
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return [
        'group_payment_id' => $groupPaymentId,
        'amount_kobo' => $kobo,
        'rows' => count($splits),
    ];
}

function group_payment_reverse(string $groupPaymentId, string $reason): array
{
    require_module('fashion.orders');
    require_module('fashion.finance');
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('group_reverse_owner'), 403);
    }
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        return ['error' => 'reversal_reason_required'];
    }
    if (!is_uuid($groupPaymentId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    receipt_write_guard();
    db_begin();
    try {
        $rows = db_query(
            'SELECT id, order_id, amount_kobo, method, provider, payer_customer_id, group_id
             FROM fashion_payments
             WHERE tenant_id = :tenant_id AND group_payment_id = :group_payment_id AND kind = :kind
             ORDER BY seq ASC',
            ['group_payment_id' => $groupPaymentId, 'kind' => 'payment']
        );
        if ($rows === []) {
            db_rollback();
            http_response_code(404);
            throw new HttpException(t('not_found'), 404);
        }
        foreach ($rows as $row) {
            $existing = db_select('fashion_payments', ['reverses_payment_id' => (string) $row['id']], ['limit' => 1]);
            if ($existing !== []) {
                db_rollback();
                return ['error' => 'already_reversed'];
            }
        }
        $now = app_now()->format('Y-m-d H:i:s');
        $firstId = null;
        $sum = 0;
        $written = 0;
        foreach ($rows as $row) {
            $amount = (int) $row['amount_kobo'];
            $provider = (string) ($row['provider'] ?? 'manual');
            if (!in_array($provider, ['manual', 'paystack'], true)) {
                $provider = 'manual';
            }
            $payerId = $row['payer_customer_id'];
            $storedGroupId = $row['group_id'];
            $id = db_insert('fashion_payments', [
                'order_id' => (string) $row['order_id'],
                'amount_kobo' => -$amount,
                'method' => (string) $row['method'],
                'reverses_payment_id' => (string) $row['id'],
                'kind' => 'reversal',
                'user_id' => $userId,
                'created_at' => $now,
                'provider' => $provider,
                'provider_reference' => null,
                'source' => 'staff',
                'reason' => $reason,
                'group_id' => is_string($storedGroupId) && $storedGroupId !== '' ? $storedGroupId : null,
                'group_payment_id' => $groupPaymentId,
                'payer_customer_id' => is_string($payerId) && $payerId !== '' ? $payerId : null,
            ]);
            receipt_void_payment((string) $row['id']);
            $written++;
            if ($firstId === null) {
                $firstId = $id;
            }
            $sum += $amount;
            if (defined('GMINGO_TESTING') && !empty($GLOBALS['gmingo_fail_group_reverse']) && $written === 1) {
                throw new RuntimeException('Simulated group reversal failure.');
            }
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['id' => $firstId, 'amount_kobo' => -$sum];
}
