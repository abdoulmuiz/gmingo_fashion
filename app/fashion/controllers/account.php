<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/subscriptions.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';
require_once dirname(__DIR__, 2) . '/domain/billing.php';
require_once dirname(__DIR__, 2) . '/payments/SaasPaystack.php';

function account_subscription_show(array $errors = []): void
{
    require_login();
    $sub = subscription_refresh_status();
    $plan = $sub !== null ? subscription_plan($sub) : null;
    render('account/subscription', [
        'title' => t('subscription_account'),
        'subscription' => $sub,
        'plan' => $plan,
        'plans' => plan_list_active(),
        'entitled' => subscription_is_entitled($sub),
        'is_owner' => user_can('billing.manage'),
        'billing_enabled' => saas_paystack_enabled(),
        'errors' => $errors,
    ]);
}

function account_subscription_submit(): void
{
    require_login();
    csrf_check();
    if (!user_can('billing.manage')) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'checkout') {
            $planId = (string) ($_POST['plan_id'] ?? '');
            $interval = (string) ($_POST['billing_interval'] ?? 'monthly');
            $result = billing_checkout_start($planId, $interval);
            if (isset($result['errors'])) {
                account_subscription_show($result['errors']);
                return;
            }
            redirect_external((string) $result['url']);
            return;
        }
        if ($action === 'renew') {
            if (saas_paystack_enabled()) {
                account_subscription_show(['form' => 'use_checkout']);
                return;
            }
            $sub = subscription_current();
            if ($sub === null) {
                subscription_start_trial();
            } else {
                $plan = plan_get((string) $sub['plan_id']);
                if ($plan === null) {
                    throw new RuntimeException('Plan is not available.');
                }
                if (in_array((string) $sub['status'], ['expired', 'cancelled', 'suspended'], true)
                    || !subscription_is_entitled($sub)) {
                    subscription_activate((string) $plan['id'], (string) $sub['billing_interval']);
                } else {
                    subscription_renew();
                }
            }
            flash_set('success', t('subscription_renewed'));
            redirect('/');
        }
    } catch (Throwable $e) {
        if ($e instanceof RedirectException) {
            throw $e;
        }
        account_subscription_show(['form' => 'subscription_renew_failed']);
        return;
    }
    account_subscription_show(['form' => 'subscription_renew_failed']);
}

function account_billing_return_show(): void
{
    require_login();
    $reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
    $status = billing_return_peek($reference);
    render('account/billing_return', [
        'title' => t('subscription_billing_return'),
        'reference' => $reference,
        'status' => $status,
    ]);
}

function account_billing_verify_submit(): void
{
    require_login();
    csrf_check();
    if (!user_can('billing.manage')) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    $reference = trim((string) ($_POST['reference'] ?? ''));
    $status = billing_verify_reference($reference);
    if ($status === 'paid') {
        flash_set('success', t('subscription_payment_received'));
        redirect('/');
    }
    if ($status === 'waiting') {
        flash_set('error', t('subscription_payment_waiting'));
        redirect('/account/billing/return?reference=' . rawurlencode($reference));
    }
    flash_set('error', t('subscription_payment_failed'));
    redirect('/account/subscription');
}

function account_billing_webhook_submit(): void
{
    $raw = file_get_contents('php://input');
    $signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
    billing_webhook_receive(is_string($raw) ? $raw : '', $signature);
}
