<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/platform.php';
require_once dirname(__DIR__, 2) . '/domain/modules.php';
require_once dirname(__DIR__, 2) . '/domain/saas_gateway.php';
require_once dirname(__DIR__, 2) . '/domain/billing.php';

function platform_money_input(string $raw): int|false
{
    $raw = trim($raw);
    if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
        return false;
    }
    return (int) $raw;
}

/** @return list<string> */
function platform_features_input(string $raw): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function platform_limit_inputs(array $input): array
{
    $limits = [];
    foreach (subscription_limit_keys() as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $raw = trim((string) $input[$key]);
        if ($raw === '') {
            $limits[$key] = null;
            continue;
        }
        if (!preg_match('/^\d+$/', $raw)) {
            $limits[$key] = -1;
            continue;
        }
        $limits[$key] = (int) $raw;
    }
    return $limits;
}

function platform_home(): void
{
    require_platform_admin();
    render('platform/home', [
        'title' => t('platform_home'),
        'stats' => platform_stats(),
    ]);
}

function platform_password_show(array $errors = []): void
{
    require_platform_admin();
    render('platform/password', [
        'title' => t('platform_password_change'),
        'errors' => $errors,
        'must_change' => platform_admin_must_change_password(),
    ]);
}

function platform_password_submit(): void
{
    require_platform_admin();
    csrf_check();
    $result = platform_password_change($_POST);
    if (isset($result['errors'])) {
        platform_password_show($result['errors']);
        return;
    }
    flash_set('success', t('platform_password_changed'));
    redirect('/platform');
}

function platform_plans_index(): void
{
    require_platform_admin();
    render('platform/plans', [
        'title' => t('platform_plans'),
        'plans' => plan_list_all(),
        'modules' => module_keys(),
        'limit_keys' => subscription_limit_keys(),
        'errors' => [],
        'old' => [],
    ]);
}

function platform_plans_create(): void
{
    require_platform_admin();
    csrf_check();
    $monthly = platform_money_input((string) ($_POST['price_monthly_kobo'] ?? ''));
    $annual = platform_money_input((string) ($_POST['price_annual_kobo'] ?? ''));
    $result = plan_create([
        'code' => (string) ($_POST['code'] ?? ''),
        'name' => (string) ($_POST['name'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'price_monthly_kobo' => $monthly === false ? -1 : $monthly,
        'price_annual_kobo' => $annual === false ? -1 : $annual,
        'currency' => (string) ($_POST['currency'] ?? 'NGN'),
        'trial_available' => !empty($_POST['trial_available']),
        'trial_days' => (int) ($_POST['trial_days'] ?? 0),
        'active' => !empty($_POST['active']),
        'is_popular' => !empty($_POST['is_popular']),
        'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        'icon' => (string) ($_POST['icon'] ?? ''),
        'features' => platform_features_input((string) ($_POST['features'] ?? '')),
        'limits' => platform_limit_inputs($_POST['limits'] ?? []),
        'modules' => array_values(array_filter(
            is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [],
            static fn ($m): bool => is_string($m)
        )),
    ]);
    if (isset($result['errors'])) {
        render('platform/plans', [
            'title' => t('platform_plans'),
            'plans' => plan_list_all(),
            'modules' => module_keys(),
            'limit_keys' => subscription_limit_keys(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('platform_plan_created'));
    redirect('/platform/plans');
}

function platform_plan_edit_show(string $planId, array $errors = [], array $old = []): void
{
    require_platform_admin();
    $plan = plan_get($planId);
    if ($plan === null) {
        throw new HttpException(t('not_found'), 404);
    }
    render('platform/plan_edit', [
        'title' => t('platform_plan_edit'),
        'plan' => $plan,
        'modules' => module_keys(),
        'limit_keys' => subscription_limit_keys(),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function platform_plan_edit_submit(string $planId): void
{
    require_platform_admin();
    csrf_check();
    $monthly = platform_money_input((string) ($_POST['price_monthly_kobo'] ?? ''));
    $annual = platform_money_input((string) ($_POST['price_annual_kobo'] ?? ''));
    $result = plan_update($planId, [
        'name' => (string) ($_POST['name'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'price_monthly_kobo' => $monthly === false ? -1 : $monthly,
        'price_annual_kobo' => $annual === false ? -1 : $annual,
        'currency' => (string) ($_POST['currency'] ?? 'NGN'),
        'trial_available' => !empty($_POST['trial_available']),
        'trial_days' => (int) ($_POST['trial_days'] ?? 0),
        'active' => !empty($_POST['active']),
        'is_popular' => !empty($_POST['is_popular']),
        'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        'icon' => (string) ($_POST['icon'] ?? ''),
        'features' => platform_features_input((string) ($_POST['features'] ?? '')),
        'confirm_price_change' => !empty($_POST['confirm_price_change']),
        'limits' => platform_limit_inputs($_POST['limits'] ?? []),
        'modules' => array_values(array_filter(
            is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [],
            static fn ($m): bool => is_string($m)
        )),
        'paystack_plan_code_monthly' => (string) ($_POST['paystack_plan_code_monthly'] ?? ''),
        'paystack_plan_code_annual' => (string) ($_POST['paystack_plan_code_annual'] ?? ''),
    ]);
    if (isset($result['errors'])) {
        platform_plan_edit_show($planId, $result['errors'], $_POST);
        return;
    }
    flash_set('success', t('platform_plan_updated'));
    redirect('/platform/plans/' . $planId);
}

function platform_tenants_index(): void
{
    require_platform_admin();
    $q = trim((string) ($_GET['q'] ?? ''));
    render('platform/tenants', [
        'title' => t('platform_tenants'),
        'tenants' => platform_list_tenants($q !== '' ? $q : null),
        'q' => $q,
    ]);
}

function platform_tenant_show(string $tenantId, array $errors = []): void
{
    require_platform_admin();
    $tenant = platform_tenant_get($tenantId);
    if ($tenant === null) {
        throw new HttpException(t('not_found'), 404);
    }
    render('platform/tenant', [
        'title' => (string) $tenant['name'],
        'tenant' => $tenant,
        'events' => platform_tenant_events($tenantId),
        'plans' => plan_list_active(),
        'errors' => $errors,
    ]);
}

function platform_tenant_action(string $tenantId): void
{
    require_platform_admin();
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    if (strlen($note) > 500) {
        $note = substr($note, 0, 500);
    }
    try {
        if ($action === 'suspend') {
            platform_suspend_tenant($tenantId, $note !== '' ? $note : null);
            flash_set('success', t('platform_tenant_suspended'));
        } elseif ($action === 'reactivate') {
            $planId = (string) ($_POST['plan_id'] ?? '');
            $interval = (string) ($_POST['billing_interval'] ?? 'monthly');
            platform_reactivate_tenant(
                $tenantId,
                $planId !== '' ? $planId : null,
                $interval
            );
            flash_set('success', t('platform_tenant_reactivated'));
        } elseif ($action === 'change_plan') {
            $planId = (string) ($_POST['plan_id'] ?? '');
            if (!is_uuid($planId)) {
                platform_tenant_show($tenantId, ['form' => 'invalid_plan']);
                return;
            }
            platform_change_tenant_plan($tenantId, $planId);
            flash_set('success', t('platform_tenant_plan_changed'));
        } else {
            platform_tenant_show($tenantId, ['form' => 'not_allowed']);
            return;
        }
    } catch (Throwable $e) {
        if ($e instanceof RedirectException || $e instanceof HttpException) {
            throw $e;
        }
        platform_tenant_show($tenantId, ['form' => 'platform_action_failed']);
        return;
    }
    redirect('/platform/tenants/' . $tenantId);
}

function platform_gateway_show(array $errors = []): void
{
    require_platform_admin();
    render('platform/gateway', [
        'title' => t('platform_gateway'),
        'gateway' => saas_gateway_view(),
        'errors' => $errors,
    ]);
}

function platform_gateway_submit(): void
{
    require_platform_admin();
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'test') {
        $result = saas_gateway_test_connection();
        if (($result['ok'] ?? false) === true) {
            flash_set('success', t('platform_gateway_test_ok'));
        } else {
            flash_set('error', t((string) ($result['error'] ?? 'saas_gateway_test_failed')));
        }
        redirect('/platform/gateway');
    }
    $result = saas_gateway_save([
        'enabled' => !empty($_POST['enabled']),
        'environment' => (string) ($_POST['environment'] ?? 'test'),
        'public_key' => (string) ($_POST['public_key'] ?? ''),
        'secret_key' => (string) ($_POST['secret_key'] ?? ''),
        'webhook_secret' => (string) ($_POST['webhook_secret'] ?? ''),
    ]);
    if (isset($result['errors'])) {
        platform_gateway_show($result['errors']);
        return;
    }
    flash_set('success', t('platform_gateway_saved'));
    redirect('/platform/gateway');
}

function platform_payments_index(): void
{
    require_platform_admin();
    $filters = [
        'status' => (string) ($_GET['status'] ?? ''),
        'provider' => (string) ($_GET['provider'] ?? ''),
        'tenant_id' => (string) ($_GET['tenant_id'] ?? ''),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
    ];
    render('platform/payments', [
        'title' => t('platform_payments'),
        'payments' => billing_platform_payments($filters),
        'filters' => $filters,
        'tenants' => platform_list_tenants(),
    ]);
}
