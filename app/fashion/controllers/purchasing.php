<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/purchasing.php';
require_once dirname(__DIR__, 2) . '/domain/stock.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function purchasing_index(): void
{
    require_access('/purchasing');
    render('purchasing/index', [
        'title' => t('purchasing'),
        'suppliers' => supplier_list(false),
        'orders' => purchase_order_list(),
        'stock_items' => stock_list('', '', 1)['items'],
        'errors' => [],
        'old' => [],
    ]);
}

function purchasing_supplier_submit(): void
{
    require_access('/purchasing');
    csrf_check();
    $result = supplier_create($_POST);
    if (isset($result['errors'])) {
        render('purchasing/index', [
            'title' => t('purchasing'),
            'suppliers' => supplier_list(false),
            'orders' => purchase_order_list(),
            'stock_items' => stock_list('', '', 1)['items'],
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('supplier_created'));
    redirect('/purchasing');
}

function purchasing_order_submit(): void
{
    require_access('/purchasing');
    csrf_check();
    $result = purchase_order_create($_POST);
    if (isset($result['errors'])) {
        render('purchasing/index', [
            'title' => t('purchasing'),
            'suppliers' => supplier_list(false),
            'orders' => purchase_order_list(),
            'stock_items' => stock_list('', '', 1)['items'],
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('purchase_order_created'));
    redirect('/purchasing');
}

function purchasing_receive_submit(string $poId): void
{
    require_access('/purchasing');
    csrf_check();
    $result = purchase_order_receive($poId);
    if (isset($result['errors'])) {
        flash_set('error', t('not_found'));
        redirect('/purchasing');
    }
    flash_set('success', t('purchase_order_received'));
    redirect('/purchasing');
}
