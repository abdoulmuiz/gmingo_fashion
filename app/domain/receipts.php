<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/tracking.php';
require_once __DIR__ . '/paper.php';

function receipt_write_guard(): void
{
    if (tracking_subscription_expired()) {
        http_response_code(402);
        throw new HttpException(t('subscription_expired'), 402);
    }
}

function receipt_allocate_number(): string
{
    $rows = db_query(
        'SELECT id, prefix, last_number FROM fashion_receipt_counters WHERE tenant_id = :tenant_id FOR UPDATE'
    );
    if ($rows === []) {
        throw new RuntimeException('Missing receipt counter.');
    }
    $prefix = strtoupper(trim((string) $rows[0]['prefix']));
    if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
        throw new RuntimeException('Invalid receipt prefix.');
    }
    $next = (int) $rows[0]['last_number'] + 1;
    db_execute(
        'UPDATE fashion_receipt_counters SET last_number = :next WHERE tenant_id = :tenant_id AND id = :id',
        ['next' => $next, 'id' => (string) $rows[0]['id']]
    );
    return $prefix . (string) $next;
}

function receipt_method_code(array $payment): string
{
    $provider = (string) ($payment['provider'] ?? 'manual');
    if ($provider === 'paystack') {
        return 'paystack';
    }
    $method = (string) ($payment['method'] ?? '');
    return in_array($method, ['cash', 'transfer'], true) ? $method : 'cash';
}

function receipt_method_label(string $method): string
{
    if ($method === 'paystack') {
        return t('mode_paystack');
    }
    if ($method === 'transfer') {
        return t('transfer');
    }
    return t('cash');
}

/**
 * Create the receipt for a payment that was just inserted.
 * Caller must already be inside a transaction.
 *
 * @return array{id: string, receipt_number: string, amount_kobo: int, balance_after_kobo: int}
 */
function receipt_issue(string $paymentId): array
{
    receipt_write_guard();
    if (!is_uuid($paymentId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $existing = db_select('fashion_receipts', ['payment_id' => $paymentId], ['limit' => 1]);
    if ($existing !== []) {
        return [
            'id' => (string) $existing[0]['id'],
            'receipt_number' => (string) $existing[0]['receipt_number'],
            'amount_kobo' => (int) $existing[0]['amount_kobo'],
            'balance_after_kobo' => (int) $existing[0]['balance_after_kobo'],
        ];
    }
    $payments = db_select('fashion_payments', ['id' => $paymentId], ['limit' => 1]);
    if ($payments === [] || (string) $payments[0]['kind'] !== 'payment') {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $payment = $payments[0];
    $orderId = (string) $payment['order_id'];
    $locked = db_query(
        'SELECT id FROM fashion_orders WHERE tenant_id = :tenant_id AND id = :id LIMIT 1 FOR UPDATE',
        ['id' => $orderId]
    );
    if ($locked === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $number = receipt_allocate_number();
    $totals = db_query(
        'SELECT COALESCE(SUM(quantity * price_kobo), 0) AS total_kobo
         FROM fashion_order_items
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $paidRows = db_query(
        'SELECT COALESCE(SUM(amount_kobo), 0) AS paid_kobo
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $amount = (int) $payment['amount_kobo'];
    $balanceAfter = (int) ($totals[0]['total_kobo'] ?? 0) - (int) ($paidRows[0]['paid_kobo'] ?? 0);
    $id = db_insert('fashion_receipts', [
        'payment_id' => $paymentId,
        'order_id' => $orderId,
        'receipt_number' => $number,
        'amount_kobo' => $amount,
        'method' => receipt_method_code($payment),
        'balance_after_kobo' => $balanceAfter,
        'created_at' => (string) $payment['created_at'],
    ]);
    return [
        'id' => $id,
        'receipt_number' => $number,
        'amount_kobo' => $amount,
        'balance_after_kobo' => $balanceAfter,
    ];
}

function receipt_void_payment(string $paymentId): void
{
    receipt_write_guard();
    if (!is_uuid($paymentId)) {
        return;
    }
    $rows = db_select('fashion_receipts', ['payment_id' => $paymentId], ['limit' => 1]);
    if ($rows === []) {
        return;
    }
    $receiptId = (string) $rows[0]['id'];
    if (db_select('fashion_receipt_voids', ['receipt_id' => $receiptId], ['limit' => 1]) !== []) {
        return;
    }
    db_insert('fashion_receipt_voids', [
        'receipt_id' => $receiptId,
        'payment_id' => $paymentId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function receipt_load(string $receiptId): ?array
{
    if (!is_uuid($receiptId)) {
        return null;
    }
    $rows = db_query(
        'SELECT r.id, r.receipt_number, r.amount_kobo, r.method, r.balance_after_kobo, r.order_id, r.created_at,
                o.order_number, c.name AS customer_name, t.name AS shop_name, t.phone AS shop_phone
         FROM fashion_receipts r
         INNER JOIN fashion_orders o ON o.id = r.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         INNER JOIN fashion_tenants t ON t.tenant_id = :tenant_id
         WHERE r.tenant_id = :tenant_id AND r.id = :id
         LIMIT 1',
        ['id' => $receiptId]
    );
    if ($rows === []) {
        return null;
    }
    $row = $rows[0];
    $voided = db_select('fashion_receipt_voids', ['receipt_id' => $receiptId], ['limit' => 1]) !== [];
    return [
        'id' => (string) $row['id'],
        'receipt_number' => (string) $row['receipt_number'],
        'amount_kobo' => (int) $row['amount_kobo'],
        'method' => (string) $row['method'],
        'method_label' => receipt_method_label((string) $row['method']),
        'balance_after_kobo' => (int) $row['balance_after_kobo'],
        'order_id' => (string) $row['order_id'],
        'order_number' => (string) $row['order_number'],
        'customer_name' => (string) $row['customer_name'],
        'shop_name' => (string) $row['shop_name'],
        'shop_phone' => (string) $row['shop_phone'],
        'when' => message_format_datetime((string) $row['created_at'], current_lang()),
        'voided' => $voided,
        'whatsapp' => message_latest_share((string) $row['order_id'], 'receipt_share', 'receipt:' . $receiptId . ':%'),
    ];
}

function receipt_prepare_share(string $receiptId): string
{
    $receipt = receipt_load($receiptId);
    if ($receipt === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return message_prepare_event((string) $receipt['order_id'], 'receipt_share', 'receipt:' . $receiptId . ':' . uuid(), [
        'amount_kobo' => (int) $receipt['amount_kobo'],
        'receipt_no' => (string) $receipt['receipt_number'],
        'balance_kobo' => (int) $receipt['balance_after_kobo'],
        'void_note' => $receipt['voided'] ? t('void_mark') . ' ' : '',
    ]);
}

/**
 * @return array<string, array{href: string, share_action: string, whatsapp: ?string, png: string, pdf: string, number: string}>
 */
function receipt_buttons(string $orderId): array
{
    if (!is_uuid($orderId)) {
        return [];
    }
    $rows = db_query(
        'SELECT id, payment_id, receipt_number FROM fashion_receipts WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $buttons = [];
    foreach ($rows as $row) {
        $id = (string) $row['id'];
        $buttons[(string) $row['payment_id']] = [
            'href' => url('/receipts/' . $id),
            'share_action' => url('/receipts/' . $id . '/share'),
            'whatsapp' => message_latest_share($orderId, 'receipt_share', 'receipt:' . $id . ':%'),
            'png' => url('/receipts/' . $id . '.png'),
            'pdf' => url('/receipts/' . $id . '.pdf'),
            'number' => (string) $row['receipt_number'],
        ];
    }
    return $buttons;
}

function receipt_document_for_payment(string $orderId, string $paymentId): ?array
{
    if (!is_uuid($orderId) || !is_uuid($paymentId)) {
        return null;
    }
    $existing = db_query(
        'SELECT id FROM fashion_receipts
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND payment_id = :payment_id
         LIMIT 1',
        ['order_id' => $orderId, 'payment_id' => $paymentId]
    );
    if ($existing !== []) {
        $loaded = receipt_load((string) $existing[0]['id']);
        return $loaded === null ? null : paper_receipt_document($loaded);
    }
    $payments = db_query(
        'SELECT p.amount_kobo, p.method, p.provider, p.created_at, p.seq, o.order_number,
                t.name AS shop_name, t.phone AS shop_phone
         FROM fashion_payments p
         INNER JOIN fashion_orders o ON o.id = p.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_tenants t ON t.tenant_id = :tenant_id
         WHERE p.tenant_id = :tenant_id AND p.order_id = :order_id AND p.id = :id AND p.kind = :kind
         LIMIT 1',
        ['order_id' => $orderId, 'id' => $paymentId, 'kind' => 'payment']
    );
    if ($payments === []) {
        return null;
    }
    $payment = $payments[0];
    $totals = db_query(
        'SELECT COALESCE(SUM(quantity * price_kobo), 0) AS total_kobo
         FROM fashion_order_items
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $paid = db_query(
        'SELECT COALESCE(SUM(amount_kobo), 0) AS paid_kobo
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND seq <= :seq',
        ['order_id' => $orderId, 'seq' => (int) $payment['seq']]
    );
    $reversed = db_query(
        'SELECT id FROM fashion_payments
         WHERE tenant_id = :tenant_id AND reverses_payment_id = :id
         LIMIT 1',
        ['id' => $paymentId]
    );
    return paper_receipt_document([
        'shop_name' => (string) $payment['shop_name'],
        'shop_phone' => (string) $payment['shop_phone'],
        'receipt_number' => (string) $payment['order_number'],
        'show_number' => false,
        'order_number' => (string) $payment['order_number'],
        'when' => message_format_datetime((string) $payment['created_at'], current_lang()),
        'amount_kobo' => (int) $payment['amount_kobo'],
        'method_label' => receipt_method_label(receipt_method_code($payment)),
        'balance_after_kobo' => (int) ($totals[0]['total_kobo'] ?? 0) - (int) ($paid[0]['paid_kobo'] ?? 0),
        'voided' => $reversed !== [],
    ]);
}

function invoice_whatsapp_href(string $orderId): ?string
{
    if (!is_uuid($orderId)) {
        return null;
    }
    return message_latest_share($orderId, 'invoice_share', 'invoice:' . $orderId . ':%');
}

/**
 * @return array<string, mixed>|null
 */
function invoice_load(string $orderId): ?array
{
    if (!is_uuid($orderId)) {
        return null;
    }
    $orders = db_query(
        'SELECT o.id, o.order_number, o.created_at, c.name AS customer_name, t.name AS shop_name, t.phone AS shop_phone
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         INNER JOIN fashion_tenants t ON t.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id AND o.id = :id
         LIMIT 1',
        ['id' => $orderId]
    );
    if ($orders === []) {
        return null;
    }
    $order = $orders[0];
    $items = db_query(
        'SELECT g.name AS name, i.quantity AS quantity, i.price_kobo AS price_kobo
         FROM fashion_order_items i
         INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
         WHERE i.tenant_id = :tenant_id AND i.order_id = :order_id
         ORDER BY g.name ASC',
        ['order_id' => $orderId]
    );
    $lines = [];
    $total = 0;
    foreach ($items as $item) {
        $quantity = (int) $item['quantity'];
        $line = $quantity * (int) $item['price_kobo'];
        $total += $line;
        $lines[] = [
            'description' => trim((string) $item['name']) . ' x ' . (string) $quantity,
            'amount_kobo' => $line,
        ];
    }
    $paidRows = db_query(
        'SELECT COALESCE(SUM(amount_kobo), 0) AS paid_kobo
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $paid = (int) ($paidRows[0]['paid_kobo'] ?? 0);
    $deposit = deposit_due_kobo($total, (int) deposit_shop()['percent']);
    return [
        'order_id' => (string) $order['id'],
        'order_number' => (string) $order['order_number'],
        'customer_name' => (string) $order['customer_name'],
        'shop_name' => (string) $order['shop_name'],
        'shop_phone' => (string) $order['shop_phone'],
        'when' => message_format_datetime((string) $order['created_at'], current_lang()),
        'lines' => $lines,
        'total_kobo' => $total,
        'deposit_kobo' => $deposit,
        'paid_kobo' => $paid,
        'balance_kobo' => $total - $paid,
        'whatsapp' => invoice_whatsapp_href($orderId),
    ];
}

function invoice_prepare_share(string $orderId): string
{
    $invoice = invoice_load($orderId);
    if ($invoice === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return message_prepare_event($orderId, 'invoice_share', 'invoice:' . $orderId . ':' . uuid(), [
        'total_kobo' => (int) $invoice['total_kobo'],
        'paid_kobo' => (int) $invoice['paid_kobo'],
        'balance_kobo' => (int) $invoice['balance_kobo'],
        'deposit_due_kobo' => (int) $invoice['deposit_kobo'],
    ]);
}
