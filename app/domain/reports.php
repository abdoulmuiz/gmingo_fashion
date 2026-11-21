<?php

declare(strict_types=1);

require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/modules.php';

function reports_summary(): array
{
    require_permission('reports.view');
    $ordersOutstanding = 0;
    $salesTotal = 0;
    $expenseTotal = 0;
    $openOrders = 0;
    if (module_enabled('fashion.finance') || module_enabled('fashion.orders')) {
        $paid = db_query(
            'SELECT COALESCE(SUM(CASE WHEN reverses_payment_id IS NULL THEN amount_kobo ELSE -amount_kobo END), 0) AS paid
             FROM fashion_payments WHERE tenant_id = :tenant_id'
        );
        $totals = db_query(
            'SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) AS total
             FROM fashion_order_items i
             INNER JOIN fashion_orders o ON o.id = i.order_id AND o.tenant_id = :tenant_id
             WHERE i.tenant_id = :tenant_id'
        );
        $ordersOutstanding = max(0, (int) ($totals[0]['total'] ?? 0) - (int) ($paid[0]['paid'] ?? 0));
        $open = db_query(
            'SELECT COUNT(*) AS total FROM fashion_orders WHERE tenant_id = :tenant_id'
        );
        $openOrders = (int) ($open[0]['total'] ?? 0);
    }
    if (module_enabled('fashion.sales')) {
        $sales = db_query(
            'SELECT COALESCE(SUM(total_kobo), 0) AS total
             FROM fashion_sales
             WHERE tenant_id = :tenant_id AND status = \'completed\''
        );
        $salesTotal = (int) ($sales[0]['total'] ?? 0);
    }
    if (module_enabled('fashion.finance')) {
        $expenses = db_query(
            'SELECT COALESCE(SUM(amount_kobo), 0) AS total
             FROM fashion_expenses WHERE tenant_id = :tenant_id'
        );
        $expenseTotal = (int) ($expenses[0]['total'] ?? 0);
    }
    return [
        'open_orders' => $openOrders,
        'orders_outstanding_kobo' => $ordersOutstanding,
        'sales_total_kobo' => $salesTotal,
        'expenses_total_kobo' => $expenseTotal,
        'profit_estimate_kobo' => $salesTotal - $expenseTotal,
    ];
}
