<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/work_orders.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function work_orders_index(): void
{
    require_access('/work-orders');
    render('work_orders/index', [
        'title' => t('work_orders'),
        'orders' => work_order_list(),
        'statuses' => work_order_statuses(),
        'errors' => [],
        'old' => [],
    ]);
}

function work_orders_create_submit(): void
{
    require_access('/work-orders');
    csrf_check();
    $result = work_order_create($_POST);
    if (isset($result['errors'])) {
        render('work_orders/index', [
            'title' => t('work_orders'),
            'orders' => work_order_list(),
            'statuses' => work_order_statuses(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('work_order_created'));
    redirect('/work-orders');
}

function work_orders_status_submit(string $workOrderId): void
{
    require_access('/work-orders');
    csrf_check();
    $result = work_order_set_status($workOrderId, (string) ($_POST['status'] ?? ''));
    if (isset($result['errors'])) {
        flash_set('error', t('not_found'));
        redirect('/work-orders');
    }
    flash_set('success', t('work_order_updated'));
    redirect('/work-orders');
}

function work_orders_quality_submit(): void
{
    require_access('/work-orders');
    csrf_check();
    $result = quality_check_record($_POST);
    if (isset($result['errors'])) {
        render('work_orders/index', [
            'title' => t('work_orders'),
            'orders' => work_order_list(),
            'statuses' => work_order_statuses(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('quality_check_saved'));
    redirect('/work-orders');
}
