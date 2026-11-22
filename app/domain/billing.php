<?php

declare(strict_types=1);

require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/access.php';
require_once dirname(__DIR__) . '/payments/SaasPaystack.php';

function billing_checkout_present(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'tenant_id' => (string) $row['tenant_id'],
        'subscription_id' => $row['subscription_id'] !== null ? (string) $row['subscription_id'] : null,
        'plan_id' => (string) $row['plan_id'],
        'billing_interval' => (string) $row['billing_interval'],
        'intent' => (string) $row['intent'],
        'amount_kobo' => (int) $row['amount_kobo'],
        'currency' => (string) $row['currency'],
        'provider' => (string) $row['provider'],
        'provider_reference' => (string) $row['provider_reference'],
        'status' => (string) $row['status'],
        'authorization_url' => $row['authorization_url'] !== null ? (string) $row['authorization_url'] : null,
        'created_at' => (string) $row['created_at'],
        'paid_at' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
    ];
}

function billing_owner_email(): string
{
    require_login();
    $rows = db_select('fashion_users', ['id' => (string) current_user_id()], ['limit' => 1]);
    if ($rows === []) {
        return '';
    }
    return strtolower(trim((string) $rows[0]['email']));
}

function billing_checkout_start(string $planId, string $interval): array
{
    require_login();
    if (!access_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    if (!saas_paystack_enabled()) {
        return ['errors' => ['form' => 'saas_paystack_disabled']];
    }
    if (!in_array($interval, subscription_intervals(), true)) {
        return ['errors' => ['billing_interval' => 'invalid_interval']];
    }
    $plan = plan_get($planId);
    if ($plan === null || !$plan['active']) {
        return ['errors' => ['plan_id' => 'invalid_plan']];
    }
    // Authoritative amount from fashion_plans only — never from the browser.
    $amount = plan_amount_kobo($plan, $interval);
    $currency = (string) ($plan['currency'] ?? 'NGN');
    if ($amount <= 0 || $currency !== 'NGN') {
        return ['errors' => ['form' => 'invalid_price']];
    }
    $email = billing_owner_email();
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['errors' => ['form' => 'invalid_email']];
    }
    $sub = subscription_current();
    $intent = ($sub !== null && subscription_is_entitled($sub)
        && (string) $sub['status'] === 'active'
        && (string) $sub['plan_id'] === $planId
        && (string) $sub['billing_interval'] === $interval)
        ? 'renew'
        : 'activate';
    $reference = 'sb' . bin2hex(random_bytes(16));
    $planCode = plan_paystack_code($plan, $interval);
    $init = saas_paystack_initialize($email, $amount, $reference, $planCode);
    if (($init['ok'] ?? false) !== true) {
        return ['errors' => ['form' => (string) ($init['error'] ?? 'save_failed')]];
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $metadata = [
        'plan_code' => $plan['code'],
        'price_version' => (int) $plan['price_version'],
        'paystack_plan_code' => $planCode !== '' ? $planCode : null,
        'recurring' => $planCode !== '',
    ];
    $id = db_insert('fashion_subscription_checkouts', [
        'subscription_id' => $sub['id'] ?? null,
        'plan_id' => $plan['id'],
        'billing_interval' => $interval,
        'intent' => $intent,
        'amount_kobo' => $amount,
        'currency' => $currency,
        'provider' => 'paystack',
        'provider_reference' => $reference,
        'status' => 'pending',
        'authorization_url' => (string) $init['url'],
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
        'created_at' => $now,
        'paid_at' => null,
    ]);
    $rows = db_select('fashion_subscription_checkouts', ['id' => $id], ['limit' => 1]);
    return [
        'checkout' => billing_checkout_present($rows[0]),
        'url' => (string) $init['url'],
    ];
}

function billing_checkout_by_reference(string $reference): ?array
{
    if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
        return null;
    }
    $rows = billing_platform_query(
        'SELECT id, tenant_id, subscription_id, plan_id, billing_interval, intent, amount_kobo, currency,
                provider, provider_reference, status, authorization_url, metadata_json, created_at, paid_at
         FROM fashion_subscription_checkouts
         WHERE provider = :provider AND provider_reference = :reference
         LIMIT 2',
        ['provider' => 'paystack', 'reference' => $reference]
    );
    if (count($rows) !== 1 || !is_array($rows[0])) {
        return null;
    }
    return billing_checkout_present($rows[0]);
}

function billing_mark_paid(string $checkoutId): void
{
    if (!is_uuid($checkoutId)) {
        throw new RuntimeException('Invalid checkout.');
    }
    db_update('fashion_subscription_checkouts', [
        'status' => 'paid',
        'paid_at' => app_now()->format('Y-m-d H:i:s'),
    ], ['id' => $checkoutId]);
}

function billing_fulfill_reference(string $reference, string $source): string
{
    if (!in_array($source, ['webhook', 'verify'], true)) {
        return 'ignored';
    }
    $checkout = billing_checkout_by_reference($reference);
    if ($checkout === null) {
        return 'missing';
    }
    auth_bind_tenant((string) $checkout['tenant_id']);
    try {
        $rows = db_select('fashion_subscription_checkouts', ['id' => $checkout['id']], ['limit' => 1]);
        if ($rows === []) {
            return 'missing';
        }
        $current = billing_checkout_present($rows[0]);
        if ($current['status'] === 'paid') {
            return 'paid';
        }
        if ($current['status'] !== 'pending') {
            return 'ignored';
        }
        $verified = saas_paystack_verify($reference);
        $exact = ($verified['ok'] ?? false) === true
            && (int) ($verified['amount_kobo'] ?? 0) === $current['amount_kobo']
            && (string) ($verified['reference'] ?? '') === $reference
            && (string) ($verified['currency'] ?? '') === 'NGN';
        if (!$exact) {
            if ((string) ($verified['status'] ?? '') === 'success'
                && (string) ($verified['currency'] ?? '') !== ''
                && ((int) ($verified['amount_kobo'] ?? 0) !== $current['amount_kobo']
                    || (string) ($verified['currency'] ?? '') !== 'NGN')) {
                return 'mismatch';
            }
            return (string) ($verified['status'] ?? '') === 'success' ? 'mismatch' : 'waiting';
        }
        db_begin();
        try {
            $locked = db_query(
                'SELECT id, status, plan_id, billing_interval, amount_kobo, provider_reference
                 FROM fashion_subscription_checkouts
                 WHERE tenant_id = :tenant_id AND id = :id
                 LIMIT 1 FOR UPDATE',
                ['id' => $current['id']]
            );
            if ($locked === [] || (string) $locked[0]['status'] === 'paid') {
                db_commit();
                return 'paid';
            }
            if ((string) $locked[0]['status'] !== 'pending') {
                db_commit();
                return 'ignored';
            }
            subscription_fulfill_checkout(
                (string) $locked[0]['plan_id'],
                (string) $locked[0]['billing_interval'],
                (int) $locked[0]['amount_kobo'],
                (string) $locked[0]['provider_reference']
            );
            $customerCode = (string) ($verified['customer_code'] ?? '');
            if ($customerCode !== '') {
                subscription_bind_paystack_codes($customerCode, null);
            }
            billing_mark_paid((string) $locked[0]['id']);
            db_commit();
            return 'paid';
        } catch (Throwable $e) {
            db_rollback();
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                $again = db_select('fashion_subscription_checkouts', ['id' => $current['id']], ['limit' => 1]);
                if ($again !== [] && (string) $again[0]['status'] === 'pending') {
                    billing_mark_paid($current['id']);
                }
                return 'paid';
            }
            throw $e;
        }
    } finally {
        auth_release_tenant();
    }
}

function billing_rate_path(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/ratelimit/saas_paystack';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir . '/' . hash('sha256', client_ip()) . '.json';
}

function billing_rate_limited(): bool
{
    $path = billing_rate_path();
    if (!is_file($path)) {
        return false;
    }
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return false;
    }
    $cut = time() - 60;
    $recent = array_values(array_filter($data, static fn ($t): bool => is_int($t) && $t >= $cut));
    return count($recent) >= 30;
}

function billing_rate_hit(): void
{
    $path = billing_rate_path();
    $raw = is_file($path) ? file_get_contents($path) : '[]';
    $data = is_string($raw) ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $cut = time() - 60;
    $recent = array_values(array_filter($data, static fn ($t): bool => is_int($t) && $t >= $cut));
    $recent[] = time();
    file_put_contents($path, json_encode($recent), LOCK_EX);
}

function billing_webhook_event_key(string $payload, array $parsed): string
{
    $decoded = json_decode($payload, true);
    if (is_array($decoded) && isset($decoded['data']['id']) && is_scalar($decoded['data']['id'])) {
        return (string) ($parsed['event'] ?? 'event') . ':' . (string) $decoded['data']['id'];
    }
    $ref = (string) ($parsed['reference'] ?? '');
    $sub = (string) ($parsed['subscription_code'] ?? '');
    return hash('sha256', ($parsed['event'] ?? '') . '|' . $ref . '|' . $sub . '|' . $payload);
}

function billing_webhook_seen(string $eventKey): bool
{
    $rows = saas_platform_query(
        'SELECT id FROM fashion_saas_webhook_receipts WHERE event_key = :event_key LIMIT 1',
        ['event_key' => substr($eventKey, 0, 190)]
    );
    return $rows !== [];
}

function billing_webhook_mark(string $eventKey, string $eventType, ?string $reference): void
{
    try {
        saas_platform_execute(
            'INSERT INTO fashion_saas_webhook_receipts (
                id, tenant_id, event_key, event_type, provider_reference, payload_hash, processed_at
             ) VALUES (
                :id, :tenant_id, :event_key, :event_type, :provider_reference, :payload_hash, :processed_at
             )',
            [
                'id' => uuid(),
                'tenant_id' => subscription_platform_tenant_id(),
                'event_key' => substr($eventKey, 0, 190),
                'event_type' => substr($eventType, 0, 80),
                'provider_reference' => $reference !== null && $reference !== '' ? substr($reference, 0, 190) : null,
                'payload_hash' => hash('sha256', $eventKey),
                'processed_at' => app_now()->format('Y-m-d H:i:s'),
            ]
        );
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }
    }
}

function billing_subscription_by_paystack_code(string $subscriptionCode): ?array
{
    $subscriptionCode = trim($subscriptionCode);
    if ($subscriptionCode === '' || strlen($subscriptionCode) > 80) {
        return null;
    }
    $rows = billing_platform_query(
        'SELECT id, tenant_id, plan_id, status, billing_interval, started_on, trial_starts_on, trial_ends_on,
                current_period_start, current_period_end, expires_on, cancelled_at, suspended_at,
                paystack_customer_code, paystack_subscription_code, cancel_at_period_end, created_at, updated_at
         FROM fashion_subscriptions
         WHERE paystack_subscription_code = :code
         LIMIT 2',
        ['code' => $subscriptionCode]
    );
    if (count($rows) !== 1 || !is_array($rows[0])) {
        return null;
    }
    return subscription_present($rows[0]);
}

function billing_handle_recurring_event(array $parsed): void
{
    $event = (string) ($parsed['event'] ?? '');
    $subscriptionCode = (string) ($parsed['subscription_code'] ?? '');
    $customerCode = (string) ($parsed['customer_code'] ?? '');
    $reference = (string) ($parsed['reference'] ?? '');

    if ($event === 'subscription.create' && $subscriptionCode !== '') {
        if ($reference !== '' && str_starts_with($reference, 'sb')) {
            $checkout = billing_checkout_by_reference($reference);
            if ($checkout !== null) {
                auth_bind_tenant((string) $checkout['tenant_id']);
                try {
                    subscription_bind_paystack_codes(
                        $customerCode !== '' ? $customerCode : null,
                        $subscriptionCode
                    );
                } finally {
                    auth_release_tenant();
                }
            }
            return;
        }
        $existing = billing_subscription_by_paystack_code($subscriptionCode);
        if ($existing !== null) {
            return;
        }
        return;
    }

    if (in_array($event, ['subscription.disable', 'subscription.not_renew'], true) && $subscriptionCode !== '') {
        $sub = billing_subscription_by_paystack_code($subscriptionCode);
        if ($sub === null) {
            return;
        }
        auth_bind_tenant((string) $sub['tenant_id']);
        try {
            if ($event === 'subscription.not_renew') {
                subscription_mark_cancel_at_period_end(true);
                $fresh = subscription_current() ?? $sub;
                subscription_record_event(
                    $fresh,
                    'interval_changed',
                    (string) $sub['status'],
                    (string) $sub['status'],
                    null,
                    null,
                    null,
                    null,
                    'paystack:subscription.not_renew'
                );
            } elseif (in_array((string) $sub['status'], ['trial', 'active'], true)) {
                $now = app_now()->format('Y-m-d H:i:s');
                $updated = subscription_write([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                ], ['id' => $sub['id']]);
                subscription_record_event(
                    $updated,
                    'cancelled',
                    (string) $sub['status'],
                    'cancelled',
                    null,
                    null,
                    null,
                    null,
                    'paystack:subscription.disable'
                );
            }
        } finally {
            auth_release_tenant();
        }
        return;
    }

    if ($event === 'invoice.payment_failed' && $subscriptionCode !== '') {
        $sub = billing_subscription_by_paystack_code($subscriptionCode);
        if ($sub === null) {
            return;
        }
        auth_bind_tenant((string) $sub['tenant_id']);
        try {
            $amount = (int) ($parsed['amount_kobo'] ?? 0);
            $failRef = $reference !== '' ? $reference : ('fail_' . bin2hex(random_bytes(8)));
            try {
                subscription_record_payment(
                    $sub,
                    max(0, $amount),
                    'paystack',
                    $failRef,
                    'failed'
                );
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }
            subscription_record_event(
                $sub,
                'renewed',
                (string) $sub['status'],
                (string) $sub['status'],
                null,
                null,
                null,
                null,
                'paystack:invoice.payment_failed'
            );
        } finally {
            auth_release_tenant();
        }
        return;
    }

    if ($event === 'invoice.create') {
        // Informational only — access extends only via verified charge.success.
        return;
    }
}

function billing_webhook_receive(string $payload, string $signature): void
{
    try {
        if (billing_rate_limited()) {
            http_response_code(429);
            return;
        }
        billing_rate_hit();
        $parsed = saas_paystack_parse_webhook($payload, $signature);
        if (($parsed['error'] ?? '') === 'bad_signature') {
            http_response_code(400);
            return;
        }
        if (($parsed['ok'] ?? false) !== true) {
            http_response_code(200);
            return;
        }
        $event = (string) ($parsed['event'] ?? '');
        $eventKey = billing_webhook_event_key($payload, $parsed);
        if (billing_webhook_seen($eventKey)) {
            http_response_code(200);
            return;
        }
        if ($event === 'charge.success') {
            billing_fulfill_reference((string) $parsed['reference'], 'webhook');
        } else {
            billing_handle_recurring_event($parsed);
        }
        billing_webhook_mark($eventKey, $event, $parsed['reference'] ?? null);
        http_response_code(200);
    } catch (Throwable $e) {
        http_response_code(400);
    }
}

function billing_return_peek(string $reference): string
{
    if ($reference === '') {
        return 'missing';
    }
    $checkout = billing_checkout_by_reference($reference);
    if ($checkout === null) {
        return 'missing';
    }
    if ($checkout['status'] === 'paid') {
        return 'paid';
    }
    if ($checkout['status'] === 'pending') {
        return 'waiting';
    }
    return 'failed';
}

function billing_verify_reference(string $reference): string
{
    return billing_fulfill_reference($reference, 'verify');
}

/**
 * @return list<array>
 */
function billing_platform_payments(array $filters = []): array
{
    $params = [];
    $where = ['1=1'];
    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && in_array($status, ['pending', 'succeeded', 'failed'], true)) {
        $where[] = 'p.status = :status';
        $params['status'] = $status;
    }
    $provider = trim((string) ($filters['provider'] ?? ''));
    if ($provider !== '' && in_array($provider, ['manual', 'paystack'], true)) {
        $where[] = 'p.provider = :provider';
        $params['provider'] = $provider;
    }
    $tenantId = trim((string) ($filters['tenant_id'] ?? ''));
    if ($tenantId !== '' && is_uuid($tenantId)) {
        $where[] = 'p.tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }
    $from = trim((string) ($filters['from'] ?? ''));
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
        $where[] = 'p.created_at >= :from_dt';
        $params['from_dt'] = $from . ' 00:00:00';
    }
    $to = trim((string) ($filters['to'] ?? ''));
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
        $where[] = 'p.created_at <= :to_dt';
        $params['to_dt'] = $to . ' 23:59:59';
    }
    $sql = 'SELECT p.id, p.tenant_id, p.subscription_id, p.amount_kobo, p.currency, p.provider,
                   p.provider_reference, p.status, p.paid_at, p.created_at, t.name AS business_name
            FROM fashion_subscription_payments p
            JOIN fashion_tenants t ON t.id = p.tenant_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.seq DESC
            LIMIT 100';
    $rows = billing_platform_query($sql, $params);
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = subscription_payment_present($row);
        }
    }
    return $out;
}
