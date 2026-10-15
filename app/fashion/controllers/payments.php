<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/payments.php';

function outstanding_show(): void
{
    require_module('fashion.finance');
    $page = payment_outstanding(list_page($_GET['page'] ?? '1'));
    render('outstanding', [
        'title' => t('outstanding'),
        'orders_off' => $page['orders_off'],
        'rows' => $page['rows'],
        'total_kobo' => $page['total_kobo'],
        'page' => $page['page'],
        'has_previous' => $page['has_previous'],
        'has_next' => $page['has_next'],
    ]);
}

function payment_record_submit(string $id): void
{
    require_module('fashion.finance');
    csrf_check();
    $result = payment_record($id, (string) ($_POST['amount'] ?? ''), (string) ($_POST['method'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('payment_saved'));
    }
    redirect('/orders/' . $id);
}

function payment_reverse_submit(string $orderId, string $paymentId): void
{
    require_module('fashion.finance');
    csrf_check();
    $result = payment_reverse($orderId, $paymentId);
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('reversed'));
    }
    redirect('/orders/' . $orderId);
}
