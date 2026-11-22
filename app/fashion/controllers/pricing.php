<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/subscriptions.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';
require_once dirname(__DIR__, 2) . '/payments/SaasPaystack.php';

function pricing_show(): void
{
    $interval = (string) ($_GET['interval'] ?? 'monthly');
    if (!in_array($interval, subscription_intervals(), true)) {
        $interval = 'monthly';
    }
    render('pricing', [
        'title' => t('pricing_title'),
        'plans' => plan_list_public(),
        'interval' => $interval,
        'billing_enabled' => saas_paystack_enabled(),
        'logged_in' => current_user_id() !== null,
        'is_owner' => current_user_id() !== null && user_can('billing.manage'),
    ]);
}
