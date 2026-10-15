<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProvider.php';

final class ManualProvider implements PaymentProvider
{
    public function createPaymentLink(string $orderId, int $amountKobo): array
    {
        return ['ok' => false, 'error' => 'manual_mode'];
    }

    public function verifyPayment(string $reference): array
    {
        return ['ok' => false, 'error' => 'manual_mode'];
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        return ['ok' => false, 'error' => 'manual_mode'];
    }
}
