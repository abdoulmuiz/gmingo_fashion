<?php

declare(strict_types=1);

require_once __DIR__ . '/PaystackTransport.php';

final class PaystackClient
{
    private function __construct(private string $secretKey)
    {
    }

    public static function seal(string $secret): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', self::appKey(), OPENSSL_RAW_DATA, $iv, $tag, '');
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Could not encrypt the Paystack secret.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function open(string $encrypted): self
    {
        $raw = base64_decode($encrypted, true);
        if (!is_string($raw) || strlen($raw) < 29) {
            throw new RuntimeException('Could not read the Paystack secret.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $secret = openssl_decrypt($cipher, 'aes-256-gcm', self::appKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('Could not read the Paystack secret.');
        }
        return new self($secret);
    }

    public function matches(string $candidate): bool
    {
        return hash_equals($this->secretKey, $candidate);
    }

    public function signatureMatches(string $payload, string $signature): bool
    {
        $signature = strtolower(trim($signature));
        if (preg_match('/^[a-f0-9]{128}$/', $signature) !== 1) {
            return false;
        }
        return hash_equals(hash_hmac('sha512', $payload, $this->secretKey), $signature);
    }

    /**
     * @param array<string, string>|null $json
     * @return array{status: int, body: string}
     */
    public function call(string $method, string $path, ?array $json): array
    {
        $base = rtrim((string) (app_config()['paystack_base_url'] ?? ''), '/');
        if (preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?$#', $base) !== 1) {
            throw new RuntimeException('Paystack base URL is not configured.');
        }
        $body = '';
        if ($json !== null) {
            $encoded = json_encode($json, JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                throw new RuntimeException('Could not encode the Paystack request.');
            }
            $body = $encoded;
        }
        return paystack_transport()->send($method, $base . $path, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ], $body);
    }

    private static function appKey(): string
    {
        $key = (string) (app_config()['app_key'] ?? '');
        if (strlen($key) < 32) {
            throw new RuntimeException('Set app_key in config.php before saving a Paystack secret.');
        }
        return hash('sha256', $key, true);
    }
}
