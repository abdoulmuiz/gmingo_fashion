<?php

declare(strict_types=1);

interface PaystackTransport
{
    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, array $headers, string $body): array;
}

function paystack_transport(): PaystackTransport
{
    $current = $GLOBALS['gmingo_paystack_transport'] ?? null;
    if ($current instanceof PaystackTransport) {
        return $current;
    }
    return new CurlPaystackTransport();
}

function paystack_transport_use(PaystackTransport $transport): void
{
    $GLOBALS['gmingo_paystack_transport'] = $transport;
}

final class CurlPaystackTransport implements PaystackTransport
{
    public function send(string $method, string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return ['status' => 0, 'body' => ''];
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!is_string($raw)) {
            return ['status' => $status, 'body' => ''];
        }
        return ['status' => $status, 'body' => $raw];
    }
}
