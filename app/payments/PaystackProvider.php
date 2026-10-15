<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProvider.php';
require_once __DIR__ . '/PaystackClient.php';
require_once __DIR__ . '/PaystackTransport.php';
require_once dirname(__DIR__) . '/domain/payment_settings.php';

final class PaystackProvider implements PaymentProvider
{
    public function createPaymentLink(string $orderId, int $amountKobo): array
    {
        if ($amountKobo <= 0 || !is_uuid($orderId)) {
            return ['ok' => false, 'error' => 'invalid_amount'];
        }
        if (payment_mode() !== 'paystack') {
            return ['ok' => false, 'error' => 'manual_mode'];
        }
        $orders = db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]);
        if ($orders === []) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $customers = db_select('fashion_customers', ['id' => (string) $orders[0]['customer_id']], ['limit' => 1]);
        $email = trim((string) ($customers[0]['email'] ?? ''));
        if ($email === '') {
            return ['ok' => false, 'error' => 'required'];
        }
        $callback = payment_callback_url();
        if ($callback === '') {
            return ['ok' => false, 'error' => 'payment_domain_missing'];
        }
        $reference = 'ps' . bin2hex(random_bytes(16));
        $client = $this->openClient();
        if ($client === null) {
            return ['ok' => false, 'error' => 'invalid_paystack_key'];
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

    public function verifyPayment(string $reference): array
    {
        if (preg_match('/^[A-Za-z0-9=._-]{8,100}$/', $reference) !== 1) {
            return ['ok' => false, 'error' => 'not_found', 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => ''];
        }
        $client = $this->openClient();
        if ($client === null) {
            return ['ok' => false, 'error' => 'invalid_paystack_key', 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => ''];
        }
        try {
            $response = $client->call('GET', '/transaction/verify/' . rawurlencode($reference), null);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'save_failed', 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => ''];
        }
        $decoded = json_decode($response['body'], true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'mismatch', 'status' => '', 'currency' => '', 'amount_kobo' => 0, 'reference' => ''];
        }
        $status = (string) ($data['status'] ?? '');
        $currency = (string) ($data['currency'] ?? '');
        $amount = self::wholeKobo($data['amount'] ?? null);
        $returned = (string) ($data['reference'] ?? '');
        $ok = ($response['status'] ?? 0) === 200
            && ($decoded['status'] ?? false) === true
            && $status === 'success'
            && $currency === 'NGN'
            && $returned === $reference
            && $amount > 0;
        return [
            'ok' => $ok,
            'status' => $status,
            'currency' => $currency,
            'amount_kobo' => $amount,
            'reference' => $returned,
            'error' => $ok ? '' : 'mismatch',
        ];
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        $client = $this->openClient();
        if ($client === null || !$client->signatureMatches($payload, $signature)) {
            return ['ok' => false, 'error' => 'bad_signature'];
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || (string) ($decoded['event'] ?? '') !== 'charge.success') {
            return ['ok' => false, 'error' => 'ignored'];
        }
        $data = $decoded['data'] ?? null;
        $reference = is_array($data) ? (string) ($data['reference'] ?? '') : '';
        if ($reference === '') {
            return ['ok' => false, 'error' => 'ignored'];
        }
        return ['ok' => true, 'reference' => $reference];
    }

    private function openClient(): ?PaystackClient
    {
        $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
        $encrypted = (string) ($rows[0]['paystack_secret_key_encrypted'] ?? '');
        if ($encrypted === '') {
            return null;
        }
        try {
            return PaystackClient::open($encrypted);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function wholeKobo(mixed $amount): int
    {
        if (is_int($amount) && $amount > 0) {
            return $amount;
        }
        if (is_string($amount) && preg_match('/^\d+$/', $amount) === 1) {
            return (int) $amount;
        }
        return 0;
    }
}
