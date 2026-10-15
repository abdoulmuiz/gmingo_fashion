<?php

declare(strict_types=1);

require_once __DIR__ . '/http/auth_pages.php';

route('GET', '/', function (): void {
    require_login();
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
    require_login();
    render('simple', ['title' => t('orders'), 'body' => t('coming_soon')]);
});

route('GET', '/board', function (): void {
    require_login();
    render('simple', ['title' => t('board'), 'body' => t('coming_soon')]);
});

route('GET', '/customers', function (): void {
    require_login();
    render('simple', ['title' => t('customers'), 'body' => t('coming_soon')]);
});

route('GET', '/more', function (): void {
    require_login();
    render('more', ['title' => t('more')]);
});
