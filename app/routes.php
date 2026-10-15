<?php

declare(strict_types=1);

require_once __DIR__ . '/fashion/controllers/auth_pages.php';
require_once __DIR__ . '/fashion/controllers/customers.php';
require_once __DIR__ . '/fashion/controllers/settings.php';

route('GET', '/', function (): void {
    require_login();
    http_response_code(200);
    render('home', ['title' => t('home')]);
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
    render('simple', ['title' => t('orders'), 'body' => t('coming_soon')]);
});

route('GET', '/board', function (): void {
    require_module('fashion.production');
    render('simple', ['title' => t('board'), 'body' => t('coming_soon')]);
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

route('GET', '/settings', function (): void {
    settings_form();
});

route('POST', '/settings', function (): void {
    settings_submit();
});

route('GET', '/more', function (): void {
    require_login();
    render('more', ['title' => t('more')]);
});
