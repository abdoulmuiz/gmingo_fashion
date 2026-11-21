<?php

declare(strict_types=1);

require_once __DIR__ . '/subscriptions.php';

function platform_admin_email(): string
{
    $config = app_config();
    $email = $config['platform_admin_email'] ?? '';
    if (!is_string($email) || $email === '') {
        return '';
    }
    $email = strtolower(trim($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function platform_admin_password_is_secure(string $password): bool
{
    if (strlen($password) < 12) {
        return false;
    }
    $lower = strtolower($password);
    $blocked = ['password', 'password123', 'changeme', 'admin', 'admin123', 'gmingo', 'platform'];
    if (in_array($lower, $blocked, true)) {
        return false;
    }
    if ($password === 'password') {
        return false;
    }
    return true;
}

function platform_admin_password_configured(): ?string
{
    $config = app_config();
    $password = $config['platform_admin_password'] ?? null;
    if (!is_string($password) || $password === '') {
        return null;
    }
    if (!platform_admin_password_is_secure($password)) {
        return null;
    }
    return $password;
}

/** @deprecated Use platform_admin_password_configured(); kept for tests that override config. */
function platform_admin_password(): string
{
    $password = platform_admin_password_configured();
    if ($password === null) {
        throw new RuntimeException('Platform admin password is not securely configured.');
    }
    return $password;
}

function is_platform_admin(): bool
{
    $userId = current_user_id();
    $tenantId = current_tenant_id();
    if ($userId === null || $tenantId === null) {
        return false;
    }
    if ($tenantId !== subscription_platform_tenant_id()) {
        return false;
    }
    $rows = db_select('fashion_users', ['id' => $userId], ['limit' => 1]);
    return $rows !== [] && (string) $rows[0]['role'] === 'platform_admin';
}

function platform_admin_must_change_password(): bool
{
    if (!is_platform_admin()) {
        return false;
    }
    $rows = db_select('fashion_users', ['id' => (string) current_user_id()], ['limit' => 1]);
    return $rows !== [] && (int) ($rows[0]['password_must_change'] ?? 0) === 1;
}

function require_platform_admin(bool $allowPasswordChange = false): void
{
    require_login();
    if (!is_platform_admin()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    $path = request_path();
    if (platform_admin_must_change_password()
        && !$allowPasswordChange
        && $path !== '/platform/password'
        && $path !== '/logout') {
        redirect('/platform/password');
    }
}

function platform_bootstrap(): array
{
    $email = platform_admin_email();
    $password = platform_admin_password_configured();
    if ($email === '' || $password === null) {
        throw new RuntimeException('Platform admin credentials are not securely configured.');
    }
    $tenantId = subscription_platform_tenant_id();
    $userId = platform_admin_user_id();
    $now = app_now()->format('Y-m-d H:i:s');

    $tenantRows = admin_platform_query(
        'SELECT id FROM fashion_tenants WHERE id = :id LIMIT 1',
        ['id' => $tenantId]
    );
    if ($tenantRows === []) {
        auth_login($userId, $tenantId);
        try {
            db_insert('fashion_tenants', [
                'id' => $tenantId,
                'name' => 'Gmingo Platform',
                'phone' => '',
                'deposit_percent' => 0,
                'capacity_max' => null,
                'payment_bank' => null,
                'payment_account' => null,
                'company_type' => 'platform',
                'onboarding_step' => 'complete',
                'onboarding_completed_at' => $now,
                'currency' => 'NGN',
                'address' => '',
                'logo_path' => null,
                'brand_primary' => '',
                'brand_accent' => '',
                'created_at' => $now,
            ]);
        } finally {
            auth_logout();
        }
    }

    $userRows = admin_platform_query(
        'SELECT id, email, active FROM fashion_users WHERE id = :id OR email = :email LIMIT 1',
        ['id' => $userId, 'email' => $email]
    );
    if ($userRows === []) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        admin_platform_execute(
            'INSERT INTO fashion_users (
                id, tenant_id, name, email, password_hash, role, lang, active, created_at, password_must_change
             ) VALUES (
                :id, :tenant_id, :name, :email, :password_hash, :role, :lang, :active, :created_at, :password_must_change
             )',
            [
                'id' => $userId,
                'tenant_id' => $tenantId,
                'name' => 'Gmingo Platform Admin',
                'email' => $email,
                'password_hash' => $hash,
                'role' => 'platform_admin',
                'lang' => 'en',
                'active' => 1,
                'created_at' => $now,
                'password_must_change' => 1,
            ]
        );
        return ['created' => true, 'user_id' => $userId, 'tenant_id' => $tenantId, 'email' => $email];
    }

    $existing = $userRows[0];
    admin_platform_execute(
        'UPDATE fashion_users
         SET tenant_id = :tenant_id,
             name = :name,
             email = :email,
             role = :role,
             active = 1
         WHERE id = :id',
        [
            'id' => (string) $existing['id'],
            'tenant_id' => $tenantId,
            'name' => 'Gmingo Platform Admin',
            'email' => $email,
            'role' => 'platform_admin',
        ]
    );
    return ['created' => false, 'user_id' => (string) $existing['id'], 'tenant_id' => $tenantId, 'email' => $email];
}

function platform_password_change(array $input): array
{
    require_platform_admin(true);
    $current = (string) ($input['current_password'] ?? '');
    $next = (string) ($input['new_password'] ?? '');
    $confirm = (string) ($input['confirm_password'] ?? '');
    $errors = [];
    $rows = db_select('fashion_users', ['id' => (string) current_user_id()], ['limit' => 1]);
    if ($rows === []) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    if (!password_verify($current, (string) $rows[0]['password_hash'])) {
        $errors['current_password'] = 'login_failed';
    }
    if (!platform_admin_password_is_secure($next)) {
        $errors['new_password'] = 'platform_password_weak';
    }
    if ($next !== $confirm) {
        $errors['confirm_password'] = 'platform_password_mismatch';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    db_update('fashion_users', [
        'password_hash' => password_hash($next, PASSWORD_DEFAULT),
        'password_must_change' => 0,
    ], ['id' => (string) current_user_id()]);
    return ['ok' => true];
}

function platform_tenant_present(array $row, ?array $sub = null, ?array $plan = null): array
{
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'phone' => (string) ($row['phone'] ?? ''),
        'company_type' => (string) ($row['company_type'] ?? ''),
        'currency' => (string) ($row['currency'] ?? 'NGN'),
        'subscription_ends_on' => $row['subscription_ends_on'] !== null ? (string) $row['subscription_ends_on'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'subscription' => $sub,
        'plan' => $plan,
    ];
}

function platform_list_tenants(?string $q = null): array
{
    require_platform_admin();
    $platformId = subscription_platform_tenant_id();
    $params = ['platform_id' => $platformId];
    $where = 't.id <> :platform_id AND t.company_type <> \'platform\'';
    if ($q !== null && trim($q) !== '') {
        $params['q'] = '%' . trim($q) . '%';
        $where .= ' AND (t.name LIKE :q OR t.phone LIKE :q)';
    }
    $rows = admin_platform_query(
        'SELECT t.id, t.name, t.phone, t.company_type, t.currency, t.subscription_ends_on, t.created_at,
                s.id AS subscription_id, s.plan_id, s.status, s.billing_interval, s.expires_on,
                s.trial_ends_on, s.current_period_end, s.suspended_at, s.cancelled_at,
                p.code AS plan_code, p.name AS plan_name
         FROM fashion_tenants t
         LEFT JOIN fashion_subscriptions s ON s.tenant_id = t.id
         LEFT JOIN fashion_plans p ON p.id = s.plan_id
         WHERE ' . $where . '
         ORDER BY t.created_at DESC
         LIMIT 200',
        $params
    );
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sub = null;
        $plan = null;
        if (!empty($row['subscription_id'])) {
            $sub = [
                'id' => (string) $row['subscription_id'],
                'plan_id' => (string) $row['plan_id'],
                'status' => (string) $row['status'],
                'billing_interval' => (string) $row['billing_interval'],
                'expires_on' => $row['expires_on'] !== null ? (string) $row['expires_on'] : null,
                'trial_ends_on' => $row['trial_ends_on'] !== null ? (string) $row['trial_ends_on'] : null,
                'current_period_end' => $row['current_period_end'] !== null ? (string) $row['current_period_end'] : null,
                'suspended_at' => $row['suspended_at'] !== null ? (string) $row['suspended_at'] : null,
                'cancelled_at' => $row['cancelled_at'] !== null ? (string) $row['cancelled_at'] : null,
            ];
            if (!empty($row['plan_code'])) {
                $plan = [
                    'id' => (string) $row['plan_id'],
                    'code' => (string) $row['plan_code'],
                    'name' => (string) $row['plan_name'],
                ];
            }
        }
        $out[] = platform_tenant_present($row, $sub, $plan);
    }
    return $out;
}

function platform_tenant_get(string $tenantId): ?array
{
    require_platform_admin();
    if (!is_uuid($tenantId) || $tenantId === subscription_platform_tenant_id()) {
        return null;
    }
    $rows = admin_platform_query(
        'SELECT id, name, phone, company_type, currency, subscription_ends_on, created_at
         FROM fashion_tenants
         WHERE id = :id AND company_type <> \'platform\'
         LIMIT 1',
        ['id' => $tenantId]
    );
    $row = $rows[0] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $subRows = admin_platform_query(
        'SELECT id, tenant_id, plan_id, status, billing_interval, started_on, trial_starts_on, trial_ends_on,
                current_period_start, current_period_end, expires_on, cancelled_at, suspended_at, created_at, updated_at
         FROM fashion_subscriptions
         WHERE tenant_id = :tenant_id
         LIMIT 1',
        ['tenant_id' => $tenantId]
    );
    $sub = isset($subRows[0]) && is_array($subRows[0]) ? subscription_present($subRows[0]) : null;
    $plan = $sub !== null ? plan_get((string) $sub['plan_id']) : null;
    return platform_tenant_present($row, $sub, $plan);
}

function platform_tenant_events(string $tenantId, int $limit = 20): array
{
    require_platform_admin();
    if (!is_uuid($tenantId)) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $rows = admin_platform_query(
        'SELECT id, subscription_id, event_type, from_status, to_status, note, created_at
         FROM fashion_subscription_events
         WHERE tenant_id = :tenant_id
         ORDER BY created_at DESC
         LIMIT ' . (string) $limit,
        ['tenant_id' => $tenantId]
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'subscription_id' => (string) $row['subscription_id'],
                'event_type' => (string) $row['event_type'],
                'from_status' => $row['from_status'] !== null ? (string) $row['from_status'] : null,
                'to_status' => $row['to_status'] !== null ? (string) $row['to_status'] : null,
                'note' => $row['note'] !== null ? (string) $row['note'] : null,
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    return $out;
}

function platform_with_tenant(string $tenantId, callable $fn): mixed
{
    require_platform_admin();
    if (!is_uuid($tenantId) || $tenantId === subscription_platform_tenant_id()) {
        throw new RuntimeException('Invalid shop.');
    }
    $shop = platform_tenant_get($tenantId);
    if ($shop === null) {
        throw new RuntimeException('Shop not found.');
    }
    auth_bind_tenant($tenantId);
    try {
        return $fn();
    } finally {
        auth_release_tenant();
    }
}

function platform_suspend_tenant(string $tenantId, ?string $note = null): array
{
    return platform_with_tenant($tenantId, static function () use ($note): array {
        $sub = subscription_current();
        if ($sub === null) {
            throw new RuntimeException('Shop has no subscription.');
        }
        if ((string) $sub['status'] === 'suspended') {
            return $sub;
        }
        return subscription_suspend($note);
    });
}

function platform_reactivate_tenant(string $tenantId, ?string $planId = null, string $interval = 'monthly'): array
{
    return platform_with_tenant($tenantId, static function () use ($planId, $interval): array {
        $sub = subscription_current();
        if ($sub === null) {
            $plan = $planId !== null ? plan_get($planId) : plan_by_code(subscription_default_plan_code());
            if ($plan === null) {
                throw new RuntimeException('Plan is not available.');
            }
            return subscription_activate((string) $plan['id'], $interval);
        }
        $usePlanId = $planId ?? (string) $sub['plan_id'];
        $useInterval = in_array($interval, subscription_intervals(), true)
            ? $interval
            : (string) $sub['billing_interval'];
        return subscription_activate($usePlanId, $useInterval);
    });
}

function platform_change_tenant_plan(string $tenantId, string $planId): array
{
    return platform_with_tenant($tenantId, static function () use ($planId): array {
        return subscription_change_plan($planId);
    });
}

function platform_stats(): array
{
    require_platform_admin();
    $platformId = subscription_platform_tenant_id();
    $tenants = admin_platform_query(
        'SELECT COUNT(*) AS total FROM fashion_tenants WHERE id <> :platform_id AND company_type <> \'platform\'',
        ['platform_id' => $platformId]
    );
    $subs = admin_platform_query(
        'SELECT status, COUNT(*) AS total
         FROM fashion_subscriptions
         WHERE tenant_id <> :platform_id
         GROUP BY status',
        ['platform_id' => $platformId]
    );
    $plans = admin_platform_query(
        'SELECT COUNT(*) AS total FROM fashion_plans WHERE active = 1',
        []
    );
    $byStatus = [];
    foreach ($subs as $row) {
        if (is_array($row)) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }
    }
    return [
        'tenants' => (int) ($tenants[0]['total'] ?? 0),
        'active_plans' => (int) ($plans[0]['total'] ?? 0),
        'subscriptions_by_status' => $byStatus,
    ];
}
