<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/paystack.php';

function payment_request_submit(string $id): void
{
    require_module('fashion.finance');
    require_module('fashion.online_payments');
    csrf_check();
    $result = payment_request_create($id, (string) ($_POST['amount'] ?? ''), (string) ($_POST['email'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('payment_link'));
    }
    redirect('/orders/' . $id);
}

function paystack_webhook_submit(string $token): void
{
    $raw = file_get_contents('php://input');
    $signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
    paystack_webhook_receive($token, is_string($raw) ? $raw : '', $signature);
}

function paystack_return_show(): void
{
    http_response_code(200);
    $reference = trim((string) ($_GET['reference'] ?? ''));
    $status = paystack_return_status($reference);
    if ($status === 'paid') {
        $message = t('payment_received');
    } elseif ($status === 'pending') {
        $message = t('payment_confirming');
    } else {
        $message = t('payment_failed');
    }
    render('pay_return', [
        'title' => $message,
        'message' => $message,
    ]);
}
