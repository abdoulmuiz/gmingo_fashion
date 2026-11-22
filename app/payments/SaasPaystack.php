<?php

declare(strict_types=1);

require_once __DIR__ . '/PaystackClient.php';
require_once __DIR__ . '/PaystackTransport.php';
require_once dirname(__DIR__) . '/domain/saas_gateway.php';

/**
 * Platform SaaS Paystack (Gmingo subscription billing).
 * Secrets come from fashion_saas_gateway_settings (preferred) or config fallback.
 * Never uses shop fashion_payment_settings.
 */
function saas_paystack_enabled(): bool
{
    return saas_gateway_checkout_enabled();
}

function saas_paystack_client(): ?PaystackClient
{
    $secret = saas_gateway_secret_plain();
    if ($secret === null) {
        return null;
    }
    try {
        return PaystackClient::open(PaystackClient::seal($secret));
    } catch (Throwable $e) {
        return null;
    }
}

function saas_paystack_callback_url(): string
{
    return saas_paystack_callback_url_raw();
}

/**
 * @return array{ok: bool, url?: string, reference?: string, error?: string}
 */
function saas_paystack_initialize(string $email, int $amountKobo, string $reference, string $planCode = ''): array
{
    if ($amountKobo <= 0 || $email === '') {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }
    if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
        return ['ok' => false, 'error' => 'invalid_reference'];
    }
    $callback = saas_paystack_callback_url();
    if ($callback === '') {
        return ['ok' => false, 'error' => 'payment_domain_missing'];
    }
    $client = saas_paystack_client();
    if ($client === null) {
        return ['ok' => false, 'error' => 'saas_paystack_disabled'];
    }
    $payload = [
        'email' => $email,
        'amount' => (string) $amountKobo,
        'currency' => 'NGN',
        'reference' => $reference,
        'callback_url' => $callback,
    ];
    $planCode = trim($planCode);
    if ($planCode !== '' && preg_match('/^[A-Za-z0-9_-]{3,80}$/', $planCode) === 1) {
        $payload['plan'] = $planCode;
    }
    try {
        $response = $client->call('POST', '/transaction/initialize', $payload);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'save_failed'];
    }
    $decoded = json_decode($response['body'], true);
    $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
    $url = is_array($data) ? (string) ($data['authorization_url'] ?? '') : '';
    $returned = is_array($data) ? (string) ($data['reference'] ?? '') : '';
    $accepted = ($response['status'] ?? 0) === 200
        && is_array($decoded)
        && ($decoded['status'] ?? false) === true
        && $returned === $reference
        && preg_match('#^https://#', $url) === 1
        && strlen($url) <= 500;
    if (!$accepted) {
        return ['ok' => false, 'error' => 'save_failed'];
    }
    return ['ok' => true, 'url' => $url, 'reference' => $reference];
}

/**
 * @return array{ok: bool, status: string, currency: string, amount_kobo: int, reference: string, error: string, customer_code?: string, authorization_code?: string}
 */
function saas_paystack_verify(string $reference): array
{
    $empty = ['ok' => false, 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => '', 'error' => 'not_found'];
    if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
        return $empty;
    }
    $client = saas_paystack_client();
    if ($client === null) {
        return ['ok' => false, 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => '', 'error' => 'saas_paystack_disabled'];
    }
    try {
        $response = $client->call('GET', '/transaction/verify/' . rawurlencode($reference), null);
    } catch (Throwable $e) {
        return ['ok' => false, 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => '', 'error' => 'save_failed'];
    }
    $decoded = json_decode($response['body'], true);
    $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
    if (!is_array($data)) {
        return ['ok' => false, 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => '', 'error' => 'mismatch'];
    }
    $status = (string) ($data['status'] ?? '');
    $currency = (string) ($data['currency'] ?? '');
    $amount = $data['amount'] ?? null;
    $amountKobo = 0;
    if (is_int($amount) && $amount > 0) {
        $amountKobo = $amount;
    } elseif (is_string($amount) && preg_match('/^\d+$/', $amount) === 1) {
        $amountKobo = (int) $amount;
    }
    $returned = (string) ($data['reference'] ?? '');
    $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
    $auth = is_array($data['authorization'] ?? null) ? $data['authorization'] : [];
    $ok = ($response['status'] ?? 0) === 200
        && ($decoded['status'] ?? false) === true
        && $status === 'success'
        && $currency === 'NGN'
        && $returned === $reference
        && $amountKobo > 0;
    return [
        'ok' => $ok,
        'status' => $status,
        'currency' => $currency,
        'amount_kobo' => $amountKobo,
        'reference' => $returned,
        'error' => $ok ? '' : 'mismatch',
        'customer_code' => (string) ($customer['customer_code'] ?? ''),
        'authorization_code' => (string) ($auth['authorization_code'] ?? ''),
    ];
}

function saas_paystack_signature_ok(string $payload, string $signature): bool
{
    $secret = saas_gateway_webhook_secret_plain();
    if ($secret === null) {
        return false;
    }
    $signature = strtolower(trim($signature));
    if (preg_match('/^[a-f0-9]{128}$/', $signature) !== 1) {
        return false;
    }
    return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
}

/**
 * @return array{ok: bool, event?: string, reference?: string, subscription_code?: string, customer_code?: string, amount_kobo?: int, currency?: string, error?: string}
 */
function saas_paystack_parse_webhook(string $payload, string $signature): array
{
    if (!saas_paystack_signature_ok($payload, $signature)) {
        return ['ok' => false, 'error' => 'bad_signature'];
    }
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'ignored'];
    }
    $event = (string) ($decoded['event'] ?? '');
    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $allowed = [
        'charge.success',
        'subscription.create',
        'invoice.create',
        'invoice.payment_failed',
        'subscription.not_renew',
        'subscription.disable',
    ];
    if (!in_array($event, $allowed, true)) {
        return ['ok' => false, 'error' => 'ignored'];
    }
    $reference = (string) ($data['reference'] ?? '');
    if ($reference === '' && isset($data['transaction']) && is_array($data['transaction'])) {
        $reference = (string) ($data['transaction']['reference'] ?? '');
    }
    $subscriptionCode = (string) ($data['subscription_code'] ?? '');
    if ($subscriptionCode === '' && isset($data['subscription']) && is_array($data['subscription'])) {
        $subscriptionCode = (string) ($data['subscription']['subscription_code'] ?? '');
    }
    $customerCode = '';
    if (isset($data['customer']) && is_array($data['customer'])) {
        $customerCode = (string) ($data['customer']['customer_code'] ?? '');
    }
    $amount = $data['amount'] ?? null;
    $amountKobo = 0;
    if (is_int($amount) && $amount > 0) {
        $amountKobo = $amount;
    } elseif (is_string($amount) && preg_match('/^\d+$/', $amount) === 1) {
        $amountKobo = (int) $amount;
    }
    $currency = (string) ($data['currency'] ?? 'NGN');
    if ($event === 'charge.success') {
        if ($reference === '' || !str_starts_with($reference, 'sb')) {
            return ['ok' => false, 'error' => 'ignored'];
        }
    }
    return [
        'ok' => true,
        'event' => $event,
        'reference' => $reference,
        'subscription_code' => $subscriptionCode,
        'customer_code' => $customerCode,
        'amount_kobo' => $amountKobo,
        'currency' => $currency,
    ];
}
