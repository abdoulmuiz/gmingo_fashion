<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/receipts.php';

function receipt_show(string $id): void
{
    require_module('fashion.finance');
    $receipt = receipt_load($id);
    if ($receipt === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    render('receipts/show', [
        'title' => t('receipt') . ' ' . $receipt['receipt_number'],
        'receipt' => $receipt,
        'messages_on' => module_enabled('fashion.communication'),
    ]);
}

function receipt_share_submit(string $id): void
{
    require_module('fashion.finance');
    csrf_check();
    receipt_prepare_share($id);
    redirect('/receipts/' . $id);
}

function invoice_show(string $id): void
{
    require_module('fashion.finance');
    $invoice = invoice_load($id);
    if ($invoice === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    render('invoices/show', [
        'title' => t('invoice') . ' ' . $invoice['order_number'],
        'invoice' => $invoice,
        'messages_on' => module_enabled('fashion.communication'),
    ]);
}

function invoice_share_submit(string $id): void
{
    require_module('fashion.finance');
    csrf_check();
    invoice_prepare_share($id);
    redirect('/orders/' . $id . '/invoice');
}

function invoice_download(string $id, string $ext): void
{
    require_module('fashion.finance');
    if (paper_path_extension('/orders/' . $id . '/invoice.') !== $ext) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $invoice = invoice_load($id);
    if ($invoice === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    paper_send(paper_invoice_document($invoice), $ext);
}

function receipt_download(string $id, string $ext): void
{
    require_module('fashion.finance');
    if (paper_path_extension('/receipts/' . $id . '.') !== $ext) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $receipt = receipt_load($id);
    if ($receipt === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    paper_send(paper_receipt_document($receipt), $ext);
}

function paper_path_extension(string $prefix): ?string
{
    $path = request_path();
    if ($path === $prefix . 'png') {
        return 'png';
    }
    if ($path === $prefix . 'pdf') {
        return 'pdf';
    }
    return null;
}
