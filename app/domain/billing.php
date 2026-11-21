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
    $amount = $interval === 'annual' ? $plan['price_annual_kobo'] : $plan['price_monthly_kobo'];
    if ($amount <= 0) {
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
    $init = saas_paystack_initialize($email, $amount, $reference);
    if (($init['ok'] ?? false) !== true) {
        return ['errors' => ['form' => (string) ($init['error'] ?? 'save_failed')]];
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $id = db_insert('fashion_subscription_checkouts', [
        'subscription_id' => $sub['id'] ?? null,
        'plan_id' => $plan['id'],
        'billing_interval' => $interval,
        'intent' => $intent,
        'amount_kobo' => $amount,
        'currency' => 'NGN',
        'provider' => 'paystack',
        'provider_reference' => $reference,
        'status' => 'pending',
        'authorization_url' => (string) $init['url'],
        'metadata_json' => null,
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
    if (!in_array($source, ['webhook', 'return'], true)) {
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
        billing_fulfill_reference((string) $parsed['reference'], 'webhook');
        http_response_code(200);
    } catch (Throwable $e) {
        http_response_code(400);
    }
}

function billing_return_status(string $reference): string
{
    if ($reference === '') {
        return 'missing';
    }
    return billing_fulfill_reference($reference, 'return');
}
