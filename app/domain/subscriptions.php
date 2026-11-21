<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function subscription_statuses(): array
{
    return ['trial', 'active', 'expired', 'suspended', 'cancelled'];
}

function subscription_intervals(): array
{
    return ['monthly', 'annual'];
}

function subscription_limit_keys(): array
{
    return ['max_staff', 'max_branches', 'max_products', 'max_storage_mb', 'max_orders_month'];
}

function subscription_default_plan_code(): string
{
    return 'starter';
}

function plan_decode_json(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function plan_present(array $row): array
{
    $limits = plan_decode_json((string) ($row['limits_json'] ?? '{}'));
    $modules = plan_decode_json((string) ($row['modules_json'] ?? '[]'));
    $moduleList = [];
    foreach ($modules as $module) {
        if (is_string($module) && in_array($module, module_keys(), true)) {
            $moduleList[] = $module;
        }
    }
    $cleanLimits = [];
    foreach (subscription_limit_keys() as $key) {
        if (!array_key_exists($key, $limits)) {
            continue;
        }
        $value = $limits[$key];
        if ($value === null) {
            $cleanLimits[$key] = null;
            continue;
        }
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            $cleanLimits[$key] = (int) $value;
        }
    }
    return [
        'id' => (string) $row['id'],
        'code' => (string) $row['code'],
        'name' => (string) $row['name'],
        'description' => (string) ($row['description'] ?? ''),
        'price_monthly_kobo' => (int) $row['price_monthly_kobo'],
        'price_annual_kobo' => (int) $row['price_annual_kobo'],
        'trial_available' => (int) ($row['trial_available'] ?? 0) === 1,
        'trial_days' => (int) ($row['trial_days'] ?? 0),
        'active' => (int) ($row['active'] ?? 0) === 1,
        'limits' => $cleanLimits,
        'modules' => $moduleList,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function plan_get(string $planId): ?array
{
    if (!is_uuid($planId)) {
        return null;
    }
    $rows = subscription_platform_query(
        'SELECT id, tenant_id, code, name, description, price_monthly_kobo, price_annual_kobo,
                trial_available, trial_days, active, limits_json, modules_json, created_at, updated_at
         FROM fashion_plans
         WHERE id = :id
         LIMIT 1',
        ['id' => $planId]
    );
    $row = $rows[0] ?? null;
    return is_array($row) ? plan_present($row) : null;
}

function plan_by_code(string $code): ?array
{
    $code = strtolower(trim($code));
    if ($code === '' || strlen($code) > 40) {
        return null;
    }
    $rows = subscription_platform_query(
        'SELECT id, tenant_id, code, name, description, price_monthly_kobo, price_annual_kobo,
                trial_available, trial_days, active, limits_json, modules_json, created_at, updated_at
         FROM fashion_plans
         WHERE code = :code
         LIMIT 1',
        ['code' => $code]
    );
    $row = $rows[0] ?? null;
    return is_array($row) ? plan_present($row) : null;
}

function plan_list_active(): array
{
    $rows = subscription_platform_query(
        'SELECT id, tenant_id, code, name, description, price_monthly_kobo, price_annual_kobo,
                trial_available, trial_days, active, limits_json, modules_json, created_at, updated_at
         FROM fashion_plans
         WHERE active = 1
         ORDER BY price_monthly_kobo ASC, name ASC'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = plan_present($row);
        }
    }
    return $out;
}

function plan_list_all(): array
{
    $rows = subscription_platform_query(
        'SELECT id, tenant_id, code, name, description, price_monthly_kobo, price_annual_kobo,
                trial_available, trial_days, active, limits_json, modules_json, created_at, updated_at
         FROM fashion_plans
         ORDER BY active DESC, price_monthly_kobo ASC, name ASC'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = plan_present($row);
        }
    }
    return $out;
}

function plan_update(string $planId, array $input): array
{
    $plan = plan_get($planId);
    if ($plan === null) {
        return ['errors' => ['form' => 'plan_not_found']];
    }
    $name = trim((string) ($input['name'] ?? $plan['name']));
    $description = trim((string) ($input['description'] ?? $plan['description']));
    $monthly = $input['price_monthly_kobo'] ?? $plan['price_monthly_kobo'];
    $annual = $input['price_annual_kobo'] ?? $plan['price_annual_kobo'];
    $trialAvailable = array_key_exists('trial_available', $input)
        ? !empty($input['trial_available'])
        : $plan['trial_available'];
    $trialDays = array_key_exists('trial_days', $input)
        ? (int) $input['trial_days']
        : $plan['trial_days'];
    $active = array_key_exists('active', $input) ? !empty($input['active']) : $plan['active'];
    $limits = is_array($input['limits'] ?? null) ? $input['limits'] : $plan['limits'];
    $modules = is_array($input['modules'] ?? null) ? $input['modules'] : $plan['modules'];
    $errors = [];
    if ($name === '' || strlen($name) > 120) {
        $errors['name'] = 'invalid_plan_name';
    }
    if (strlen($description) > 500) {
        $errors['description'] = 'invalid_plan_description';
    }
    if (!is_int($monthly) || $monthly < 0) {
        $errors['price_monthly_kobo'] = 'invalid_price';
    }
    if (!is_int($annual) || $annual < 0) {
        $errors['price_annual_kobo'] = 'invalid_price';
    }
    if ($trialAvailable && $trialDays < 1) {
        $errors['trial_days'] = 'invalid_trial_days';
    }
    if (!$trialAvailable) {
        $trialDays = 0;
    }
    $cleanLimits = [];
    foreach (subscription_limit_keys() as $key) {
        if (!array_key_exists($key, $limits)) {
            continue;
        }
        $value = $limits[$key];
        if ($value === null) {
            $cleanLimits[$key] = null;
            continue;
        }
        if (!is_int($value) || $value < 0) {
            $errors['limits'] = 'invalid_limits';
            break;
        }
        $cleanLimits[$key] = $value;
    }
    $cleanModules = [];
    foreach ($modules as $module) {
        if (!is_string($module) || !in_array($module, module_keys(), true)) {
            $errors['modules'] = 'invalid_modules';
            break;
        }
        $cleanModules[] = $module;
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $now = app_now()->format('Y-m-d H:i:s');
    subscription_platform_execute(
        'UPDATE fashion_plans
         SET name = :name,
             description = :description,
             price_monthly_kobo = :price_monthly_kobo,
             price_annual_kobo = :price_annual_kobo,
             trial_available = :trial_available,
             trial_days = :trial_days,
             active = :active,
             limits_json = :limits_json,
             modules_json = :modules_json,
             updated_at = :updated_at
         WHERE id = :id AND tenant_id = :tenant_id',
        [
            'id' => $planId,
            'tenant_id' => subscription_platform_tenant_id(),
            'name' => $name,
            'description' => $description,
            'price_monthly_kobo' => $monthly,
            'price_annual_kobo' => $annual,
            'trial_available' => $trialAvailable ? 1 : 0,
            'trial_days' => $trialDays,
            'active' => $active ? 1 : 0,
            'limits_json' => json_encode($cleanLimits, JSON_THROW_ON_ERROR),
            'modules_json' => json_encode(array_values(array_unique($cleanModules)), JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ]
    );
    $updated = plan_get($planId);
    if ($updated === null) {
        throw new RuntimeException('Plan was not updated.');
    }
    return ['plan' => $updated];
}

function plan_create(array $input): array
{
    $code = strtolower(trim((string) ($input['code'] ?? '')));
    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $monthly = $input['price_monthly_kobo'] ?? null;
    $annual = $input['price_annual_kobo'] ?? null;
    $trialAvailable = !empty($input['trial_available']);
    $trialDays = (int) ($input['trial_days'] ?? 0);
    $active = !array_key_exists('active', $input) || !empty($input['active']);
    $limits = is_array($input['limits'] ?? null) ? $input['limits'] : [];
    $modules = is_array($input['modules'] ?? null) ? $input['modules'] : [];
    $errors = [];
    if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code)) {
        $errors['code'] = 'invalid_plan_code';
    }
    if ($name === '' || strlen($name) > 120) {
        $errors['name'] = 'invalid_plan_name';
    }
    if (strlen($description) > 500) {
        $errors['description'] = 'invalid_plan_description';
    }
    if (!is_int($monthly) || $monthly < 0) {
        $errors['price_monthly_kobo'] = 'invalid_price';
    }
    if (!is_int($annual) || $annual < 0) {
        $errors['price_annual_kobo'] = 'invalid_price';
    }
    if ($trialAvailable && $trialDays < 1) {
        $errors['trial_days'] = 'invalid_trial_days';
    }
    if (!$trialAvailable) {
        $trialDays = 0;
    }
    $cleanLimits = [];
    foreach (subscription_limit_keys() as $key) {
        if (!array_key_exists($key, $limits)) {
            continue;
        }
        $value = $limits[$key];
        if ($value === null) {
            $cleanLimits[$key] = null;
            continue;
        }
        if (!is_int($value) || $value < 0) {
            $errors['limits'] = 'invalid_limits';
            break;
        }
        $cleanLimits[$key] = $value;
    }
    $cleanModules = [];
    foreach ($modules as $module) {
        if (!is_string($module) || !in_array($module, module_keys(), true)) {
            $errors['modules'] = 'invalid_modules';
            break;
        }
        $cleanModules[] = $module;
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    if (plan_by_code($code) !== null) {
        return ['errors' => ['code' => 'plan_code_taken']];
    }
    $id = uuid();
    $now = app_now()->format('Y-m-d H:i:s');
    $platformTenant = subscription_platform_tenant_id();
    subscription_platform_execute(
        'INSERT INTO fashion_plans (
            id, tenant_id, code, name, description, price_monthly_kobo, price_annual_kobo,
            trial_available, trial_days, active, limits_json, modules_json, created_at, updated_at
         ) VALUES (
            :id, :tenant_id, :code, :name, :description, :price_monthly_kobo, :price_annual_kobo,
            :trial_available, :trial_days, :active, :limits_json, :modules_json, :created_at, :updated_at
         )',
        [
            'id' => $id,
            'tenant_id' => $platformTenant,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'price_monthly_kobo' => $monthly,
            'price_annual_kobo' => $annual,
            'trial_available' => $trialAvailable ? 1 : 0,
            'trial_days' => $trialDays,
            'active' => $active ? 1 : 0,
            'limits_json' => json_encode($cleanLimits, JSON_THROW_ON_ERROR),
            'modules_json' => json_encode(array_values(array_unique($cleanModules)), JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $plan = plan_get($id);
    if ($plan === null) {
        throw new RuntimeException('Plan was not saved.');
    }
    return ['plan' => $plan];
}

function subscription_present(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'tenant_id' => (string) $row['tenant_id'],
        'plan_id' => (string) $row['plan_id'],
        'status' => (string) $row['status'],
        'billing_interval' => (string) $row['billing_interval'],
        'started_on' => (string) $row['started_on'],
        'trial_starts_on' => $row['trial_starts_on'] !== null ? (string) $row['trial_starts_on'] : null,
        'trial_ends_on' => $row['trial_ends_on'] !== null ? (string) $row['trial_ends_on'] : null,
        'current_period_start' => $row['current_period_start'] !== null ? (string) $row['current_period_start'] : null,
        'current_period_end' => $row['current_period_end'] !== null ? (string) $row['current_period_end'] : null,
        'expires_on' => $row['expires_on'] !== null ? (string) $row['expires_on'] : null,
        'cancelled_at' => $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
        'suspended_at' => $row['suspended_at'] !== null ? (string) $row['suspended_at'] : null,
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function subscription_current(): ?array
{
    if (current_tenant_id() === null) {
        require_login();
    }
    $rows = db_select('fashion_subscriptions', [], ['limit' => 1]);
    $row = $rows[0] ?? null;
    return is_array($row) ? subscription_present($row) : null;
}

function subscription_require(): array
{
    $current = subscription_current();
    if ($current === null) {
        throw new RuntimeException('No subscription for this business.');
    }
    return $current;
}

function subscription_period_end(string $start, string $interval): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $start, app_timezone());
    if ($date === false) {
        throw new RuntimeException('Invalid period start.');
    }
    if ($interval === 'annual') {
        return $date->modify('+1 year')->modify('-1 day')->format('Y-m-d');
    }
    if ($interval === 'monthly') {
        return $date->modify('+1 month')->modify('-1 day')->format('Y-m-d');
    }
    throw new RuntimeException('Invalid billing interval.');
}

function subscription_access_end_date(array $sub): ?string
{
    $status = (string) $sub['status'];
    if ($status === 'trial') {
        return $sub['trial_ends_on'] ?? $sub['expires_on'];
    }
    if ($status === 'active' || $status === 'cancelled') {
        return $sub['expires_on'] ?? $sub['current_period_end'];
    }
    return null;
}

function subscription_is_entitled(?array $sub = null): bool
{
    if ($sub === null) {
        $sub = subscription_current();
    }
    if ($sub === null) {
        return false;
    }
    $status = (string) $sub['status'];
    if ($status === 'suspended' || $status === 'expired') {
        return false;
    }
    if (!in_array($status, ['trial', 'active', 'cancelled'], true)) {
        return false;
    }
    $ends = subscription_access_end_date($sub);
    if ($ends === null || $ends === '') {
        return false;
    }
    return app_today() <= $ends;
}

function subscription_compat_grace_days(): int
{
    if (function_exists('tracking_grace_days')) {
        return tracking_grace_days();
    }
    return 7;
}

function subscription_sync_ends_on(array $sub): void
{
    $tenantId = (string) $sub['tenant_id'];
    if ($tenantId !== (string) current_tenant_id()) {
        throw new RuntimeException('Subscription tenant mismatch.');
    }
    $status = (string) $sub['status'];
    if ($status === 'suspended' || $status === 'expired') {
        $ends = app_now()->modify('-' . (string) (subscription_compat_grace_days() + 1) . ' days')->format('Y-m-d');
    } else {
        $ends = subscription_access_end_date($sub);
    }
    db_update('fashion_tenants', [
        'subscription_ends_on' => $ends,
    ], ['id' => $tenantId]);
}

function subscription_record_event(
    array $sub,
    string $eventType,
    ?string $fromStatus,
    ?string $toStatus,
    ?string $fromPlanId = null,
    ?string $toPlanId = null,
    ?string $fromInterval = null,
    ?string $toInterval = null,
    ?string $note = null
): void {
    db_insert('fashion_subscription_events', [
        'subscription_id' => $sub['id'],
        'event_type' => $eventType,
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'from_plan_id' => $fromPlanId,
        'to_plan_id' => $toPlanId,
        'from_interval' => $fromInterval,
        'to_interval' => $toInterval,
        'actor_user_id' => current_user_id(),
        'note' => $note,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

function subscription_write(array $data, array $where): array
{
    $data['updated_at'] = app_now()->format('Y-m-d H:i:s');
    db_update('fashion_subscriptions', $data, $where);
    $rows = db_select('fashion_subscriptions', $where, ['limit' => 1]);
    if ($rows === []) {
        throw new RuntimeException('Subscription was not saved.');
    }
    $present = subscription_present($rows[0]);
    subscription_sync_ends_on($present);
    return $present;
}

function subscription_start_trial(?string $planCode = null): array
{
    require_login();
    $existing = subscription_current();
    if ($existing !== null) {
        throw new RuntimeException('This business already has a subscription.');
    }
    $plan = plan_by_code($planCode ?? subscription_default_plan_code());
    if ($plan === null || !$plan['active']) {
        throw new RuntimeException('Trial plan is not available.');
    }
    if (!$plan['trial_available'] || $plan['trial_days'] < 1) {
        throw new RuntimeException('This plan has no trial.');
    }
    $today = app_today();
    $trialEnd = app_now()->modify('+' . (string) $plan['trial_days'] . ' days')->format('Y-m-d');
    $now = app_now()->format('Y-m-d H:i:s');
    $id = db_insert('fashion_subscriptions', [
        'plan_id' => $plan['id'],
        'status' => 'trial',
        'billing_interval' => 'monthly',
        'started_on' => $today,
        'trial_starts_on' => $today,
        'trial_ends_on' => $trialEnd,
        'current_period_start' => $today,
        'current_period_end' => $trialEnd,
        'expires_on' => $trialEnd,
        'cancelled_at' => null,
        'suspended_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $rows = db_select('fashion_subscriptions', ['id' => $id], ['limit' => 1]);
    $sub = subscription_present($rows[0]);
    subscription_record_event($sub, 'trial_started', null, 'trial', null, $plan['id'], null, 'monthly', null);
    subscription_sync_ends_on($sub);
    return $sub;
}

function subscription_activate(string $planId, string $interval, ?int $amountKobo = null, string $provider = 'manual', ?string $reference = null): array
{
    if (current_tenant_id() === null) {
        require_login();
    }
    if (!in_array($interval, subscription_intervals(), true)) {
        throw new RuntimeException('Invalid billing interval.');
    }
    $plan = plan_get($planId);
    if ($plan === null || !$plan['active']) {
        throw new RuntimeException('Plan is not available.');
    }
    $price = $interval === 'annual' ? $plan['price_annual_kobo'] : $plan['price_monthly_kobo'];
    if ($amountKobo === null) {
        $amountKobo = $price;
    }
    if ($amountKobo < 0 || $amountKobo !== $price) {
        throw new RuntimeException('Payment amount does not match the plan.');
    }
    $today = app_today();
    $periodEnd = subscription_period_end($today, $interval);
    $now = app_now()->format('Y-m-d H:i:s');
    $current = subscription_current();
    if ($current === null) {
        $id = db_insert('fashion_subscriptions', [
            'plan_id' => $plan['id'],
            'status' => 'active',
            'billing_interval' => $interval,
            'started_on' => $today,
            'trial_starts_on' => null,
            'trial_ends_on' => null,
            'current_period_start' => $today,
            'current_period_end' => $periodEnd,
            'expires_on' => $periodEnd,
            'cancelled_at' => null,
            'suspended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rows = db_select('fashion_subscriptions', ['id' => $id], ['limit' => 1]);
        $sub = subscription_present($rows[0]);
        subscription_record_event($sub, 'activated', null, 'active', null, $plan['id'], null, $interval, null);
    } else {
        $fromStatus = $current['status'];
        $sub = subscription_write([
            'plan_id' => $plan['id'],
            'status' => 'active',
            'billing_interval' => $interval,
            'current_period_start' => $today,
            'current_period_end' => $periodEnd,
            'expires_on' => $periodEnd,
            'cancelled_at' => null,
            'suspended_at' => null,
        ], ['id' => $current['id']]);
        subscription_record_event(
            $sub,
            'activated',
            $fromStatus,
            'active',
            $current['plan_id'],
            $plan['id'],
            $current['billing_interval'],
            $interval,
            null
        );
    }
    subscription_record_payment($sub, $amountKobo, $provider, $reference, 'succeeded');
    return $sub;
}

function subscription_renew(
    ?int $amountKobo = null,
    string $provider = 'manual',
    ?string $reference = null,
    ?string $intervalOverride = null
): array {
    if (current_tenant_id() === null) {
        require_login();
    }
    $current = subscription_require();
    if (!in_array($current['status'], ['active', 'expired', 'cancelled'], true)) {
        throw new RuntimeException('Only active, expired, or cancelled subscriptions can renew.');
    }
    $plan = plan_get($current['plan_id']);
    if ($plan === null || !$plan['active']) {
        throw new RuntimeException('Plan is not available.');
    }
    $interval = $intervalOverride ?? $current['billing_interval'];
    if (!in_array($interval, subscription_intervals(), true)) {
        throw new RuntimeException('Invalid billing interval.');
    }
    $price = $interval === 'annual' ? $plan['price_annual_kobo'] : $plan['price_monthly_kobo'];
    if ($amountKobo === null) {
        $amountKobo = $price;
    }
    if ($amountKobo !== $price) {
        throw new RuntimeException('Payment amount does not match the plan.');
    }
    $today = app_today();
    $start = $today;
    if ($current['status'] === 'active' && is_string($current['current_period_end']) && $current['current_period_end'] >= $today) {
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $current['current_period_end'], app_timezone());
        if ($endDate instanceof DateTimeImmutable) {
            $start = $endDate->modify('+1 day')->format('Y-m-d');
        }
    }
    $periodEnd = subscription_period_end($start, $interval);
    $fromStatus = $current['status'];
    $sub = subscription_write([
        'status' => 'active',
        'billing_interval' => $interval,
        'current_period_start' => $start,
        'current_period_end' => $periodEnd,
        'expires_on' => $periodEnd,
        'cancelled_at' => null,
        'suspended_at' => null,
    ], ['id' => $current['id']]);
    subscription_record_event($sub, 'renewed', $fromStatus, 'active', null, null, null, null, null);
    subscription_record_payment($sub, $amountKobo, $provider, $reference, 'succeeded');
    return $sub;
}

/**
 * Apply a verified SaaS checkout (tenant must already be bound; login optional).
 */
function subscription_fulfill_checkout(string $planId, string $interval, int $amountKobo, string $reference): array
{
    if (current_tenant_id() === null) {
        throw new RuntimeException('Missing session.');
    }
    $current = subscription_current();
    $useActivate = $current === null
        || !subscription_is_entitled($current)
        || in_array((string) $current['status'], ['suspended', 'expired', 'cancelled'], true)
        || (string) $current['plan_id'] !== $planId
        || (string) $current['billing_interval'] !== $interval;
    if ($useActivate) {
        return subscription_activate($planId, $interval, $amountKobo, 'paystack', $reference);
    }
    return subscription_renew($amountKobo, 'paystack', $reference, $interval);
}

function subscription_change_plan(string $planId): array
{
    require_login();
    $current = subscription_require();
    if (!subscription_is_entitled($current) && $current['status'] !== 'suspended') {
        throw new RuntimeException('Subscription cannot change plan in this state.');
    }
    if ($current['status'] === 'cancelled' || $current['status'] === 'expired') {
        throw new RuntimeException('Renew before changing plan.');
    }
    $plan = plan_get($planId);
    if ($plan === null || !$plan['active']) {
        throw new RuntimeException('Plan is not available.');
    }
    if ($plan['id'] === $current['plan_id']) {
        return $current;
    }
    $sub = subscription_write([
        'plan_id' => $plan['id'],
    ], ['id' => $current['id']]);
    subscription_record_event(
        $sub,
        'plan_changed',
        $current['status'],
        $current['status'],
        $current['plan_id'],
        $plan['id'],
        null,
        null,
        null
    );
    return $sub;
}

function subscription_change_interval(string $interval): array
{
    require_login();
    if (!in_array($interval, subscription_intervals(), true)) {
        throw new RuntimeException('Invalid billing interval.');
    }
    $current = subscription_require();
    if ($current['status'] !== 'active' && $current['status'] !== 'trial') {
        throw new RuntimeException('Billing interval can change only on trial or active subscriptions.');
    }
    if ($current['billing_interval'] === $interval) {
        return $current;
    }
    $sub = subscription_write([
        'billing_interval' => $interval,
    ], ['id' => $current['id']]);
    subscription_record_event(
        $sub,
        'interval_changed',
        $current['status'],
        $current['status'],
        null,
        null,
        $current['billing_interval'],
        $interval,
        null
    );
    return $sub;
}

function subscription_cancel(?string $note = null): array
{
    require_login();
    $current = subscription_require();
    if (!in_array($current['status'], ['trial', 'active'], true)) {
        throw new RuntimeException('Only trial or active subscriptions can be cancelled.');
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $sub = subscription_write([
        'status' => 'cancelled',
        'cancelled_at' => $now,
    ], ['id' => $current['id']]);
    subscription_record_event($sub, 'cancelled', $current['status'], 'cancelled', null, null, null, null, $note);
    return $sub;
}

function subscription_suspend(?string $note = null): array
{
    require_login();
    $current = subscription_require();
    if (!in_array($current['status'], ['trial', 'active', 'cancelled'], true)) {
        throw new RuntimeException('Subscription cannot be suspended in this state.');
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $sub = subscription_write([
        'status' => 'suspended',
        'suspended_at' => $now,
    ], ['id' => $current['id']]);
    subscription_record_event($sub, 'suspended', $current['status'], 'suspended', null, null, null, null, $note);
    return $sub;
}

function subscription_expire(): array
{
    require_login();
    $current = subscription_require();
    if ($current['status'] === 'expired') {
        return $current;
    }
    if ($current['status'] === 'suspended') {
        throw new RuntimeException('Suspended subscriptions are not expired through the period clock.');
    }
    $ends = subscription_access_end_date($current);
    if ($ends === null || app_today() <= $ends) {
        throw new RuntimeException('Subscription period has not ended.');
    }
    $sub = subscription_write([
        'status' => 'expired',
    ], ['id' => $current['id']]);
    subscription_record_event($sub, 'expired', $current['status'], 'expired', null, null, null, null, null);
    return $sub;
}

function subscription_record_payment(
    array $sub,
    int $amountKobo,
    string $provider,
    ?string $reference,
    string $status
): array {
    if ($amountKobo < 0) {
        throw new RuntimeException('Invalid subscription payment amount.');
    }
    if (!in_array($provider, ['manual', 'paystack'], true)) {
        throw new RuntimeException('Invalid subscription payment provider.');
    }
    if (!in_array($status, ['pending', 'succeeded', 'failed'], true)) {
        throw new RuntimeException('Invalid subscription payment status.');
    }
    if ($sub['tenant_id'] !== (string) current_tenant_id()) {
        throw new RuntimeException('Subscription payment tenant mismatch.');
    }
    $id = db_insert('fashion_subscription_payments', [
        'subscription_id' => $sub['id'],
        'amount_kobo' => $amountKobo,
        'currency' => 'NGN',
        'provider' => $provider,
        'provider_reference' => $reference,
        'status' => $status,
        'paid_at' => $status === 'succeeded' ? app_now()->format('Y-m-d H:i:s') : null,
        'metadata_json' => null,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    $rows = db_select('fashion_subscription_payments', ['id' => $id], ['limit' => 1]);
    return $rows[0];
}

function subscription_plan(?array $sub = null): ?array
{
    if ($sub === null) {
        $sub = subscription_current();
    }
    if ($sub === null) {
        return null;
    }
    return plan_get((string) $sub['plan_id']);
}

function subscription_allows_module(string $moduleKey, ?array $sub = null): bool
{
    if (!in_array($moduleKey, module_keys(), true)) {
        return false;
    }
    if ($sub === null) {
        $sub = subscription_current();
    }
    if ($sub === null || !subscription_is_entitled($sub)) {
        return false;
    }
    $plan = subscription_plan($sub);
    if ($plan === null) {
        return false;
    }
    return in_array($moduleKey, $plan['modules'], true);
}

function subscription_limit(string $limitKey, ?array $sub = null): ?int
{
    if (!in_array($limitKey, subscription_limit_keys(), true)) {
        return 0;
    }
    if ($sub === null) {
        $sub = subscription_current();
    }
    if ($sub === null || !subscription_is_entitled($sub)) {
        return 0;
    }
    $plan = subscription_plan($sub);
    if ($plan === null) {
        return 0;
    }
    if (!array_key_exists($limitKey, $plan['limits'])) {
        return null;
    }
    $value = $plan['limits'][$limitKey];
    return $value === null ? null : (int) $value;
}

function subscription_within_limit(string $limitKey, int $usage, ?array $sub = null): bool
{
    if ($usage < 0) {
        return false;
    }
    $limit = subscription_limit($limitKey, $sub);
    if ($limit === null) {
        return subscription_is_entitled($sub ?? subscription_current());
    }
    return $usage <= $limit;
}

function subscription_allows(string $feature, ?array $sub = null): bool
{
    if (str_starts_with($feature, 'module:')) {
        return subscription_allows_module(substr($feature, 7), $sub);
    }
    if (str_starts_with($feature, 'limit:')) {
        $limitKey = substr($feature, 6);
        $limit = subscription_limit($limitKey, $sub);
        return $limit === null || $limit > 0;
    }
    if ($feature === 'access') {
        return subscription_is_entitled($sub);
    }
    return false;
}

function subscription_account_paths(): array
{
    return [
        '/account',
        '/account/subscription',
        '/account/billing/return',
        '/onboarding',
        '/onboarding/logo',
        '/platform',
    ];
}

function subscription_path_open(?string $path = null): bool
{
    $path = $path ?? request_path();
    if (in_array($path, subscription_account_paths(), true)) {
        return true;
    }
    if (str_starts_with($path, '/onboarding/')) {
        return true;
    }
    if (str_starts_with($path, '/account/billing/')) {
        return true;
    }
    return str_starts_with($path, '/platform/');
}

function subscription_refresh_status(?array $sub = null): ?array
{
    if ($sub === null) {
        $sub = subscription_current();
    }
    if ($sub === null) {
        return null;
    }
    if (!in_array((string) $sub['status'], ['trial', 'active', 'cancelled'], true)) {
        return $sub;
    }
    $ends = subscription_access_end_date($sub);
    if ($ends === null || $ends === '' || app_today() <= $ends) {
        return $sub;
    }
    return subscription_expire();
}

function subscription_has_app_access(?array $sub = null): bool
{
    $sub = subscription_refresh_status($sub);
    return subscription_is_entitled($sub);
}

function require_subscription_entitled(): void
{
    require_login();
    if (subscription_path_open()) {
        return;
    }
    if (subscription_has_app_access()) {
        return;
    }
    redirect('/account/subscription');
}

function subscription_staff_count(): int
{
    require_login();
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_users WHERE tenant_id = :tenant_id AND active = 1'
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function subscription_assert_within_limit(string $limitKey, int $usage): void
{
    if (subscription_within_limit($limitKey, $usage)) {
        return;
    }
    http_response_code(403);
    throw new HttpException(t('subscription_limit_reached'), 403);
}
