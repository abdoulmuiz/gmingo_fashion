<?php

declare(strict_types=1);

require_once __DIR__ . '/fashion/controllers/auth_pages.php';
require_once __DIR__ . '/fashion/controllers/home.php';
require_once __DIR__ . '/fashion/controllers/customers.php';
require_once __DIR__ . '/fashion/controllers/orders.php';
require_once __DIR__ . '/fashion/controllers/settings.php';
require_once __DIR__ . '/fashion/controllers/stages.php';
require_once __DIR__ . '/fashion/controllers/payments.php';
require_once __DIR__ . '/fashion/controllers/payment_settings.php';
require_once __DIR__ . '/fashion/controllers/paystack.php';
require_once __DIR__ . '/fashion/controllers/measurements.php';

route('GET', '/', function (): void {
    require_login();
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

route('POST', '/lang', function (): void {
    lang_submit();
});

route('GET', '/orders', function (): void {
    require_module('fashion.orders');
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

route('POST', '/orders/{id}/stage', function (array $params): void {
    require_module('fashion.production');
    stage_move_submit((string) $params['id']);
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
    require_module('fashion.finance');
    outstanding_show();
});

route('GET', '/board', function (): void {
    require_module('fashion.production');
    board_show();
});

route('GET', '/customers', function (): void {
    require_module('fashion.orders');
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
    settings_form();
});

route('POST', '/settings', function (): void {
    settings_submit();
});

route('GET', '/settings/payments', function (): void {
    payment_settings_form();
});

route('POST', '/settings/payments', function (): void {
    payment_settings_submit();
});

route('GET', '/settings/stages', function (): void {
    stages_form();
});

route('POST', '/settings/stages', function (): void {
    stages_submit();
});

route('GET', '/settings/garments', function (): void {
    garments_form();
});

route('POST', '/settings/garments', function (): void {
    garments_submit();
});

route('GET', '/more', function (): void {
    require_login();
    render('more', ['title' => t('more')]);
});
