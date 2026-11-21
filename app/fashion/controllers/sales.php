<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/sales.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function sales_index(): void
{
    require_access('/sales');
    render('sales/index', [
        'title' => t('sales'),
        'sales' => sale_list(),
        'products' => sale_product_options(),
        'channels' => sale_channels(),
        'errors' => [],
        'old' => [],
    ]);
}

function sales_create_submit(): void
{
    require_access('/sales');
    csrf_check();
    $result = sale_create($_POST);
    if (isset($result['errors'])) {
        render('sales/index', [
            'title' => t('sales'),
            'sales' => sale_list(),
            'products' => sale_product_options(),
            'channels' => sale_channels(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('sale_recorded'));
    redirect('/sales');
}

function sales_return_submit(string $saleId): void
{
    require_access('/sales');
    csrf_check();
    $result = sale_return($saleId);
    if (isset($result['errors'])) {
        flash_set('error', t('not_found'));
        redirect('/sales');
    }
    flash_set('success', t('sale_returned'));
    redirect('/sales');
}
