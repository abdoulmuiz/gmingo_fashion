<?php

declare(strict_types=1);

require_once __DIR__ . '/PaystackClient.php';
require_once __DIR__ . '/PaystackTransport.php';

/**
 * Platform SaaS Paystack (Gmingo subscription billing).
 * Uses config keys — never shop fashion_payment_settings.
 */
function saas_paystack_enabled(): bool
{
    $secret = trim((string) (app_config()['saas_paystack_secret_key'] ?? ''));
    return $secret !== '' && strlen((string) (app_config()['app_key'] ?? '')) >= 32;
}

function saas_paystack_client(): ?PaystackClient
{
    if (!saas_paystack_enabled()) {
        return null;
    }
    $secret = trim((string) (app_config()['saas_paystack_secret_key'] ?? ''));
    try {
        // Seal+open so we reuse the same client path as shop Paystack.
        return PaystackClient::open(PaystackClient::seal($secret));
    } catch (Throwable $e) {
        return null;
    }
}

function saas_paystack_callback_url(): string
{
    $origin = '';
    $raw = trim((string) (app_config()['public_base_url'] ?? ''));
    if ($raw !== '') {
        $parts = parse_url($raw);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . (int) $parts['port'];
            }
        }
    }
    if ($origin === '') {
        $placeholder = trim((string) (app_config()['payment_placeholder_domain'] ?? ''));
        if ($placeholder !== '') {
            if (preg_match('#^https?://#i', $placeholder) === 1) {
                $parts = parse_url($placeholder);
                if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                    $origin = $parts['scheme'] . '://' . $parts['host'];
                    if (isset($parts['port'])) {
                        $origin .= ':' . (int) $parts['port'];
                    }
                }
            } else {
                $origin = 'https://' . $placeholder;
            }
        }
    }
    if ($origin === '') {
        return '';
    }
    return $origin . url('/account/billing/return');
}

/**
 * @return array{ok: bool, url?: string, reference?: string, error?: string}
 */
function saas_paystack_initialize(string $email, int $amountKobo, string $reference): array
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
    try {
        $response = $client->call('POST', '/transaction/initialize', [
            'email' => $email,
            'amount' => (string) $amountKobo,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callback,
        ]);
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
 * @return array{ok: bool, status: string, currency: string, amount_kobo: int, reference: string, error: string}
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
    ];
}

/**
 * @return array{ok: bool, reference?: string, error?: string}
 */
function saas_paystack_parse_webhook(string $payload, string $signature): array
{
    $client = saas_paystack_client();
    if ($client === null || !$client->signatureMatches($payload, $signature)) {
        return ['ok' => false, 'error' => 'bad_signature'];
    }
    $decoded = json_decode($payload, true);
    if (!is_array($decoded) || (string) ($decoded['event'] ?? '') !== 'charge.success') {
        return ['ok' => false, 'error' => 'ignored'];
    }
    $data = $decoded['data'] ?? null;
    $reference = is_array($data) ? (string) ($data['reference'] ?? '') : '';
    if ($reference === '' || !str_starts_with($reference, 'sb')) {
        return ['ok' => false, 'error' => 'ignored'];
    }
    return ['ok' => true, 'reference' => $reference];
}
