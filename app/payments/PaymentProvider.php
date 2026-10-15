<?php

declare(strict_types=1);

interface PaymentProvider
{
    /**
     * @return array{ok: bool, url?: string, reference?: string, error?: string}
     */
    public function createPaymentLink(string $orderId, int $amountKobo): array;

    /**
     * @return array{ok: bool, reference?: string, amount_kobo?: int, error?: string}
     */
    public function verifyPayment(string $reference): array;

    /**
     * @return array{ok: bool, reference?: string, amount_kobo?: int, error?: string}
     */
    public function parseWebhook(string $payload, string $signature): array;
}
