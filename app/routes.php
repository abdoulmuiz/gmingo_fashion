<?php

declare(strict_types=1);

route('GET', '/', function (): void {
    render('home', ['title' => t('home')]);
});

route('GET', '/orders', function (): void {
    render('simple', ['title' => t('orders'), 'body' => t('coming_soon')]);
});

route('GET', '/board', function (): void {
    render('simple', ['title' => t('board'), 'body' => t('coming_soon')]);
});

route('GET', '/customers', function (): void {
    render('simple', ['title' => t('customers'), 'body' => t('coming_soon')]);
});

route('GET', '/more', function (): void {
    render('simple', ['title' => t('more'), 'body' => t('coming_soon')]);
});
