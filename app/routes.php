<?php

declare(strict_types=1);

require_once __DIR__ . '/domain/access.php';
require_once __DIR__ . '/fashion/controllers/auth_pages.php';
require_once __DIR__ . '/fashion/controllers/home.php';
require_once __DIR__ . '/fashion/controllers/customers.php';
require_once __DIR__ . '/fashion/controllers/orders.php';
require_once __DIR__ . '/fashion/controllers/settings.php';
require_once __DIR__ . '/fashion/controllers/stages.php';
require_once __DIR__ . '/fashion/controllers/payments.php';
require_once __DIR__ . '/fashion/controllers/receipts.php';
require_once __DIR__ . '/fashion/controllers/payment_settings.php';
require_once __DIR__ . '/fashion/controllers/paystack.php';
require_once __DIR__ . '/fashion/controllers/measurements.php';
require_once __DIR__ . '/fashion/controllers/groups.php';
require_once __DIR__ . '/fashion/controllers/stock.php';
require_once __DIR__ . '/fashion/controllers/fittings.php';
require_once __DIR__ . '/fashion/controllers/messages.php';
require_once __DIR__ . '/fashion/controllers/tracking.php';

route('GET', '/', function (): void {
    require_access('/');
    http_response_code(200);
    home_show();
});

route('GET', '/login', function (): void {
    login_form();
});

route('POST', '/login', function (): void {
    login_submit();
});

route('GET', '/register', function (): void {
    register_form();
});

route('POST', '/register', function (): void {
    register_submit();
});

route('POST', '/logout', function (): void {
    logout_submit();
});

route('POST', '/language', function (): void {
    lang_submit();
});

route('GET', '/track/{token}/invoice.png', function (array $params): void {
    tracking_download((string) $params['token'], 'invoice');
});

route('GET', '/track/{token}/invoice.pdf', function (array $params): void {
    tracking_download((string) $params['token'], 'invoice');
});

route('GET', '/track/{token}/receipt/{id}.png', function (array $params): void {
    tracking_download((string) $params['token'], 'receipt', (string) $params['id']);
});

route('GET', '/track/{token}/receipt/{id}.pdf', function (array $params): void {
    tracking_download((string) $params['token'], 'receipt', (string) $params['id']);
});

route('GET', '/track/{token}', function (array $params): void {
    tracking_show((string) $params['token']);
});

route('POST', '/track/{token}/language', function (array $params): void {
    tracking_language_submit((string) $params['token']);
});

route('GET', '/groups', function (): void {
    require_access('/groups');
    groups_index();
});

route('GET', '/groups/new', function (): void {
    require_module('fashion.orders');
    groups_form();
});

route('POST', '/groups', function (): void {
    require_module('fashion.orders');
    groups_save();
});

route('GET', '/groups/{id}', function (array $params): void {
    require_module('fashion.orders');
    groups_show((string) $params['id']);
});

route('POST', '/groups/{id}', function (array $params): void {
    require_module('fashion.orders');
    groups_update((string) $params['id']);
});

route('POST', '/groups/{id}/pay', function (array $params): void {
    require_module('fashion.orders');
    group_pay_submit((string) $params['id']);
});

route('POST', '/groups/{id}/payments/{payment}/reverse', function (array $params): void {
    require_module('fashion.orders');
    group_reverse_submit((string) $params['id'], (string) $params['payment']);
});

route('GET', '/stock', function (): void {
    require_access('/stock');
    stock_index();
});

route('GET', '/stock/new', function (): void {
    require_module('fashion.inventory');
    stock_form();
});

route('POST', '/stock', function (): void {
    require_module('fashion.inventory');
    stock_save();
});

route('GET', '/stock/{id}', function (array $params): void {
    require_module('fashion.inventory');
    stock_show((string) $params['id']);
});

route('POST', '/stock/{id}/move', function (array $params): void {
    require_module('fashion.inventory');
    stock_move_submit((string) $params['id']);
});

route('GET', '/calendar', function (): void {
    require_access('/calendar');
    calendar_show();
});

route('GET', '/calendar/{date}', function (array $params): void {
    require_module('fashion.orders');
    calendar_day_show((string) $params['date']);
});

route('POST', '/orders/{id}/fittings', function (array $params): void {
    require_module('fashion.orders');
    fitting_schedule_submit((string) $params['id']);
});

route('POST', '/orders/{id}/fittings/{fitting}/status', function (array $params): void {
    require_module('fashion.orders');
    fitting_status_submit((string) $params['id'], (string) $params['fitting']);
});

route('GET', '/orders', function (): void {
    require_access('/orders');
    orders_index();
});

route('GET', '/orders/new', function (): void {
    require_module('fashion.orders');
    orders_form();
});

route('POST', '/orders', function (): void {
    require_module('fashion.orders');
    orders_save();
});

route('GET', '/orders/{id}', function (array $params): void {
    require_module('fashion.orders');
    orders_show((string) $params['id']);
});

route('GET', '/orders/{id}/fabric', function (array $params): void {
    require_module('fashion.orders');
    fabric_history_show((string) $params['id']);
});

route('POST', '/orders/{id}/fabric', function (array $params): void {
    require_module('fashion.orders');
    fabric_submit((string) $params['id']);
});

route('POST', '/orders/{id}/group', function (array $params): void {
    require_module('fashion.orders');
    order_group_submit((string) $params['id']);
});

route('POST', '/orders/{id}/stage', function (array $params): void {
    require_module('fashion.production');
    stage_move_submit((string) $params['id']);
});

route('POST', '/orders/{id}/deposit-override', function (array $params): void {
    require_module('fashion.production');
    deposit_override_submit((string) $params['id']);
});

route('GET', '/orders/{id}/invoice.png', function (array $params): void {
    require_module('fashion.finance');
    invoice_download((string) $params['id'], 'png');
});

route('GET', '/orders/{id}/invoice.pdf', function (array $params): void {
    require_module('fashion.finance');
    invoice_download((string) $params['id'], 'pdf');
});

route('GET', '/orders/{id}/invoice', function (array $params): void {
    require_module('fashion.finance');
    invoice_show((string) $params['id']);
});

route('POST', '/orders/{id}/invoice/share', function (array $params): void {
    require_module('fashion.finance');
    invoice_share_submit((string) $params['id']);
});

route('GET', '/receipts/{id}.png', function (array $params): void {
    require_module('fashion.finance');
    receipt_download((string) $params['id'], 'png');
});

route('GET', '/receipts/{id}.pdf', function (array $params): void {
    require_module('fashion.finance');
    receipt_download((string) $params['id'], 'pdf');
});

route('GET', '/receipts/{id}', function (array $params): void {
    require_module('fashion.finance');
    receipt_show((string) $params['id']);
});

route('POST', '/receipts/{id}/share', function (array $params): void {
    require_module('fashion.finance');
    receipt_share_submit((string) $params['id']);
});

route('POST', '/orders/{id}/payments', function (array $params): void {
    require_module('fashion.finance');
    payment_record_submit((string) $params['id']);
});

route('POST', '/orders/{id}/payments/{payment}/reverse', function (array $params): void {
    require_module('fashion.finance');
    payment_reverse_submit((string) $params['id'], (string) $params['payment']);
});

route('POST', '/orders/{id}/payment-request', function (array $params): void {
    require_module('fashion.finance');
    require_module('fashion.online_payments');
    payment_request_submit((string) $params['id']);
});

route('POST', '/webhooks/paystack/{token}', function (array $params): void {
    paystack_webhook_submit((string) $params['token']);
});

route('GET', '/pay/return', function (): void {
    paystack_return_show();
});

route('GET', '/outstanding', function (): void {
    require_access('/outstanding');
    outstanding_show();
});

route('GET', '/board', function (): void {
    require_access('/board');
    board_show();
});

route('GET', '/customers', function (): void {
    require_access('/customers');
    customers_index();
});

route('GET', '/customers/new', function (): void {
    require_module('fashion.orders');
    customers_form();
});

route('POST', '/customers', function (): void {
    require_module('fashion.orders');
    customers_save();
});

route('GET', '/customers/{id}', function (array $params): void {
    require_module('fashion.orders');
    customers_show((string) $params['id']);
});

route('POST', '/customers/{id}/measurements', function (array $params): void {
    require_module('fashion.measurements');
    measurement_submit((string) $params['id']);
});

route('GET', '/settings', function (): void {
    require_access('/settings');
    settings_form();
});

route('POST', '/settings', function (): void {
    settings_submit();
});

route('GET', '/settings/payments', function (): void {
    require_access('/settings/payments');
    payment_settings_form();
});

route('POST', '/settings/payments', function (): void {
    payment_settings_submit();
});

route('GET', '/settings/stages', function (): void {
    require_access('/settings/stages');
    stages_form();
});

route('POST', '/settings/stages', function (): void {
    stages_submit();
});

route('GET', '/settings/garments', function (): void {
    require_access('/settings/garments');
    garments_form();
});

route('GET', '/settings/garments/{id}', function (array $params): void {
    garments_editor((string) $params['id']);
});

route('POST', '/settings/garments', function (): void {
    garments_submit();
});

route('GET', '/messages', function (): void {
    require_access('/messages');
    messages_index();
});

route('GET', '/messages/templates', function (): void {
    require_module('fashion.communication');
    messages_templates_form();
});

route('POST', '/messages/templates', function (): void {
    require_module('fashion.communication');
    messages_templates_submit();
});

route('GET', '/messages/{id}', function (array $params): void {
    require_module('fashion.communication');
    messages_show((string) $params['id']);
});

route('POST', '/messages/{id}/sent', function (array $params): void {
    require_module('fashion.communication');
    message_sent_submit((string) $params['id']);
});

route('POST', '/orders/{id}/messages/link', function (array $params): void {
    require_module('fashion.communication');
    message_link_submit((string) $params['id']);
});

route('POST', '/orders/{id}/messages/ready', function (array $params): void {
    require_module('fashion.communication');
    message_ready_submit((string) $params['id']);
});

route('POST', '/orders/{id}/messages/late', function (array $params): void {
    require_module('fashion.communication');
    message_late_submit((string) $params['id']);
});

route('POST', '/orders/{id}/tracking', function (array $params): void {
    require_module('fashion.communication');
    tracking_regenerate_submit((string) $params['id']);
});

route('GET', '/more', function (): void {
    require_access('/more');
    render('more', ['title' => t('more')]);
});
