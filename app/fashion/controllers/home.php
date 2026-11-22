<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/dashboard.php';
require_once dirname(__DIR__, 2) . '/domain/subscriptions.php';

function landing_show(): void
{
    render('landing', [
        'title' => t('app_name'),
        'plans' => plan_list_public(),
        'interval' => 'monthly',
    ]);
}

function home_show(): void
{
    require_login();
    $dash = dashboard_home();
    render('home', [
        'title' => t('home'),
        'greeting' => $dash['greeting'],
        'hello' => $dash['hello'],
        'user_name' => $dash['user_name'],
        'user_role' => $dash['user_role'],
        'period_label' => $dash['period_label'],
        'range' => $dash['range'],
        'cards' => $dash['cards'],
        'kpis' => $dash['kpis'],
        'revenue' => $dash['revenue'],
        'categories' => $dash['categories'],
        'order_status' => $dash['order_status'],
        'top_products' => $dash['top_products'],
        'activity' => $dash['activity'],
        'attention' => $dash['attention'],
        'orders_on' => $dash['orders_on'],
        'empty' => $dash['empty'],
        'upgrade_href' => $dash['upgrade_href'],
        'show_upgrade' => !empty($dash['show_upgrade']),
    ]);
}
