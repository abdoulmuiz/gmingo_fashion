<?php

declare(strict_types=1);

require_once __DIR__ . '/payment_settings.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/payments.php';
require_once dirname(__DIR__) . '/payments/PaystackProvider.php';

function payment_amount_input(int $kobo): string
{
    if ($kobo < 0) {
        $kobo = 0;
    }
    return intdiv($kobo, 100) . '.' . str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
}

function payment_customer_needs_email_note(string $customerId): bool
{
    if (!is_uuid($customerId)) {
        return false;
    }
    $rows = db_select('fashion_customers', ['id' => $customerId], ['limit' => 1]);
    if ($rows === []) {
        return false;
    }
    $email = trim((string) ($rows[0]['email'] ?? ''));
    return $email === '' || payment_email_is_placeholder($email);
}

/**
 * @return list<array{amount_kobo: int, url: string, reference: string, placeholder: bool}>
 */
function payment_requests_open(string $orderId): array
{
    if (!is_uuid($orderId)) {
        return [];
    }
    $rows = db_select(
        'fashion_payment_requests',
        ['order_id' => $orderId, 'status' => 'pending'],
        ['order' => 'created_at DESC', 'limit' => 20]
    );
    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'amount_kobo' => (int) $row['amount_kobo'],
            'url' => (string) $row['authorization_url'],
            'reference' => (string) $row['reference'],
            'placeholder' => (int) $row['used_placeholder'] === 1,
        ];
    }
    return $list;
}

/**
 * @return array{email?: string, placeholder?: bool, error?: string}
 */
function payment_customer_email(string $customerId, string $typed): array
{
    $rows = db_select('fashion_customers', ['id' => $customerId], ['limit' => 1]);
    if ($rows === []) {
        return ['error' => 'not_found'];
    }
    $typed = trim($typed);
    if ($typed !== '') {
        if (strlen($typed) > 190 || filter_var($typed, FILTER_VALIDATE_EMAIL) === false) {
            return ['error' => 'invalid_email'];
        }
        db_update('fashion_customers', ['email' => $typed], ['id' => $customerId]);
        return ['email' => $typed, 'placeholder' => false];
    }
    $saved = trim((string) ($rows[0]['email'] ?? ''));
    if ($saved !== '') {
        return ['email' => $saved, 'placeholder' => payment_email_is_placeholder($saved)];
    }
    $host = payment_placeholder_host();
    if ($host === '' || preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
        return ['error' => 'payment_domain_missing'];
    }
    $email = 'pay-' . bin2hex(random_bytes(8)) . '@' . $host;
    db_update('fashion_customers', ['email' => $email], ['id' => $customerId]);
    return ['email' => $email, 'placeholder' => true];
}

/**
 * @return array{id?: string, reference?: string, url?: string, amount_kobo?: int, placeholder?: bool, error?: string}
 */
function payment_request_create(string $orderId, string $amountRaw, string $typedEmail): array
{
    if (!payment_links_enabled()) {
        return ['error' => 'manual_mode'];
    }
    if (!is_uuid($orderId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $order = order_find($orderId);
    if ($order === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $balance = (int) $order['balance_kobo'];
    if ($balance <= 0) {
        return ['error' => 'invalid_amount'];
    }
    $raw = trim($amountRaw);
    $kobo = $raw === '' ? $balance : kobo_from_input($raw);
    if ($kobo === null || $kobo <= 0) {
        return ['error' => 'invalid_amount'];
    }
    if ($kobo > $balance) {
        return ['error' => 'amount_above_balance'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $email = payment_customer_email((string) $order['customer_id'], $typedEmail);
    if (isset($email['error'])) {
        return ['error' => (string) $email['error']];
    }
    $created = (new PaystackProvider())->createPaymentLink($orderId, $kobo);
    if (($created['ok'] ?? false) !== true) {
        return ['error' => (string) ($created['error'] ?? 'save_failed')];
    }
    $reference = (string) ($created['reference'] ?? '');
    $url = (string) ($created['url'] ?? '');
    if ($reference === '' || $url === '') {
        return ['error' => 'save_failed'];
    }
    $id = db_insert('fashion_payment_requests', [
        'order_id' => $orderId,
        'amount_kobo' => $kobo,
        'reference' => $reference,
        'authorization_url' => $url,
        'status' => 'pending',
        'needs_review' => 0,
        'used_placeholder' => !empty($email['placeholder']) ? 1 : 0,
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return [
        'id' => $id,
        'reference' => $reference,
        'url' => $url,
        'amount_kobo' => $kobo,
        'placeholder' => !empty($email['placeholder']),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function paystack_shop_by_token(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{16,64}$/', $token) !== 1) {
        return null;
    }
    $rows = paystack_platform_query(
        'SELECT tenant_id, webhook_token, updated_by FROM fashion_payment_settings WHERE webhook_token = :token LIMIT 2',
        ['token' => $token]
    );
    if (count($rows) !== 1) {
        return null;
    }
    $row = $rows[0];
    if (!is_uuid((string) ($row['tenant_id'] ?? ''))) {
        return null;
    }
    return $row;
}

/**
 * @return array<string, mixed>|null
 */
function paystack_request_by_reference(string $reference): ?array
{
    if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
        return null;
    }
    $rows = paystack_platform_query(
        'SELECT tenant_id, reference, status FROM fashion_payment_requests WHERE reference = :reference LIMIT 2',
        ['reference' => $reference]
    );
    if (count($rows) !== 1) {
        return null;
    }
    if (!is_uuid((string) ($rows[0]['tenant_id'] ?? ''))) {
        return null;
    }
    return $rows[0];
}

function paystack_note(string $code): void
{
    if (!in_array($code, ['unknown_token', 'bad_signature', 'mismatch', 'unknown_reference', 'rejected'], true)) {
        return;
    }
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        return;
    }
    file_put_contents($dir . '/paystack.log', gmdate('c') . ' ' . $code . "\n", FILE_APPEND | LOCK_EX);
}

function paystack_actor(): string
{
    $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
    $id = (string) ($rows[0]['updated_by'] ?? '');
    if (is_uuid($id)) {
        return $id;
    }
    $users = db_select('fashion_users', [], ['limit' => 1]);
    return (string) ($users[0]['id'] ?? '');
}

function paystack_log(string $action, string $userId): void
{
    if (!in_array($action, ['paystack_bad_signature', 'paystack_mismatch', 'paystack_unknown'], true)) {
        return;
    }
    if (!is_uuid($userId)) {
        return;
    }
    db_insert('fashion_activity_log', [
        'user_id' => $userId,
        'action' => $action,
        'module_key' => 'fashion.online_payments',
        'enabled' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function paystack_rate_path(string $token): string
{
    $dir = dirname(__DIR__, 2) . '/storage/ratelimit/paystack';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir . '/' . hash('sha256', $token . '|' . client_ip()) . '.json';
}

/**
 * @return list<int>
 */
function paystack_rate_recent(string $token): array
{
    $path = paystack_rate_path($token);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }
    $cutoff = time() - 300;
    $recent = [];
    foreach ($decoded as $stamp) {
        if (is_int($stamp) && $stamp >= $cutoff) {
            $recent[] = $stamp;
        }
    }
    return $recent;
}

function paystack_rate_limited(string $token): bool
{
    return count(paystack_rate_recent($token)) >= 30;
}

function paystack_rate_hit(string $token): void
{
    $recent = paystack_rate_recent($token);
    $recent[] = time();
    file_put_contents(paystack_rate_path($token), json_encode($recent), LOCK_EX);
}

/**
 * @param array<string, mixed> $request
 */
function paystack_append_payment(array $request, string $source): string
{
    receipt_write_guard();
    $reference = (string) $request['reference'];
    try {
        db_begin();
        $locked = db_query(
            'SELECT id, status, amount_kobo, order_id, created_by FROM fashion_payment_requests WHERE tenant_id = :tenant_id AND reference = :reference LIMIT 1 FOR UPDATE',
            ['reference' => $reference]
        );
        if ($locked === []) {
            db_commit();
            return 'ignored';
        }
        if ((string) $locked[0]['status'] !== 'pending') {
            db_commit();
            return (string) $locked[0]['status'] === 'paid' ? 'paid' : 'ignored';
        }
        $paymentId = db_insert('fashion_payments', [
            'order_id' => (string) $locked[0]['order_id'],
            'amount_kobo' => (int) $locked[0]['amount_kobo'],
            'method' => 'transfer',
            'reverses_payment_id' => null,
            'kind' => 'payment',
            'user_id' => (string) $locked[0]['created_by'],
            'created_at' => date('Y-m-d H:i:s'),
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'source' => $source,
        ]);
        receipt_issue($paymentId);
        db_update('fashion_payment_requests', [
            'status' => 'paid',
            'needs_review' => 0,
        ], ['id' => (string) $locked[0]['id']]);
        db_commit();
        return 'paid';
    } catch (PDOException $e) {
        db_rollback();
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return 'paid';
        }
        paystack_note('rejected');
        return 'error';
    }
}

function paystack_record_verified(string $reference, string $source, bool $claimedPaid): string
{
    if (!in_array($source, ['webhook', 'return'], true)) {
        return 'ignored';
    }
    $rows = db_select('fashion_payment_requests', ['reference' => $reference], ['limit' => 1]);
    if ($rows === []) {
        paystack_note('unknown_reference');
        paystack_log('paystack_unknown', paystack_actor());
        return 'missing';
    }
    $request = $rows[0];
    if ((string) $request['status'] === 'paid') {
        return 'paid';
    }
    if ((string) $request['status'] !== 'pending') {
        return 'ignored';
    }
    $verified = (new PaystackProvider())->verifyPayment($reference);
    $exact = ($verified['ok'] ?? false) === true
        && (int) ($verified['amount_kobo'] ?? 0) === (int) $request['amount_kobo']
        && (string) ($verified['reference'] ?? '') === $reference
        && (string) ($verified['currency'] ?? '') === 'NGN';
    if ($exact) {
        return paystack_append_payment($request, $source);
    }
    $status = (string) ($verified['status'] ?? '');
    if ($status === 'success' || $claimedPaid) {
        db_update('fashion_payment_requests', ['needs_review' => 1], ['id' => (string) $request['id']]);
        paystack_note('mismatch');
        paystack_log('paystack_mismatch', (string) $request['created_by']);
        return 'mismatch';
    }
    return 'waiting';
}

function paystack_webhook_receive(string $token, string $payload, string $signature): void
{
    try {
        if (paystack_rate_limited($token)) {
            http_response_code(429);
            return;
        }
        paystack_rate_hit($token);
        $shop = paystack_shop_by_token($token);
        if ($shop === null) {
            paystack_note('unknown_token');
            http_response_code(404);
            return;
        }
        auth_bind_tenant((string) $shop['tenant_id']);
        $parsed = (new PaystackProvider())->parseWebhook($payload, $signature);
        if (($parsed['error'] ?? '') === 'bad_signature') {
            paystack_note('bad_signature');
            paystack_log('paystack_bad_signature', paystack_actor());
            http_response_code(400);
            return;
        }
        if (($parsed['ok'] ?? false) !== true) {
            http_response_code(200);
            return;
        }
        paystack_record_verified((string) $parsed['reference'], 'webhook', true);
        http_response_code(200);
    } catch (Throwable $e) {
        paystack_note('rejected');
        http_response_code(400);
    } finally {
        auth_release_tenant();
    }
}

function paystack_return_status(string $reference): string
{
    if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
        return 'failed';
    }
    $found = paystack_request_by_reference($reference);
    if ($found === null) {
        return 'failed';
    }
    auth_bind_tenant((string) $found['tenant_id']);
    try {
        $rows = db_select('fashion_payment_requests', ['reference' => $reference], ['limit' => 1]);
        if ($rows !== [] && (string) $rows[0]['status'] === 'pending') {
            paystack_record_verified($reference, 'return', false);
            $rows = db_select('fashion_payment_requests', ['reference' => $reference], ['limit' => 1]);
        }
        $saved = (string) ($rows[0]['status'] ?? 'failed');
        if ($saved === 'paid') {
            return 'paid';
        }
        if ($saved === 'pending') {
            return 'pending';
        }
        return 'failed';
    } finally {
        auth_release_tenant();
    }
}
