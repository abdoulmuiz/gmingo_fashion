<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/dashboard.php';

function home_show(): void
{
    require_login();
    $dash = dashboard_home();
    render('home', [
        'title' => t('home'),
        'greeting' => $dash['greeting'],
        'cards' => $dash['cards'],
        'attention' => $dash['attention'],
        'orders_on' => $dash['orders_on'],
        'empty' => $dash['empty'],
    ]);
}
