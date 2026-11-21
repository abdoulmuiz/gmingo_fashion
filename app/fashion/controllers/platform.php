<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/platform.php';
require_once dirname(__DIR__, 2) . '/domain/modules.php';

function platform_money_input(string $raw): int|false
{
    $raw = trim($raw);
    if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
        return false;
    }
    return (int) $raw;
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
        'trial_available' => !empty($_POST['trial_available']),
        'trial_days' => (int) ($_POST['trial_days'] ?? 0),
        'active' => !empty($_POST['active']),
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
        'trial_available' => !empty($_POST['trial_available']),
        'trial_days' => (int) ($_POST['trial_days'] ?? 0),
        'active' => !empty($_POST['active']),
        'limits' => platform_limit_inputs($_POST['limits'] ?? []),
        'modules' => array_values(array_filter(
            is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [],
            static fn ($m): bool => is_string($m)
        )),
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
