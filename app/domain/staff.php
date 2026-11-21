<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/accounts.php';

function staff_shop_roles(): array
{
    return ['owner', 'manager', 'staff', 'tailor'];
}

function staff_assignable_roles(): array
{
    return ['manager', 'staff', 'tailor'];
}

function staff_permission_keys(): array
{
    return [
        'settings.manage',
        'staff.manage',
        'finance.view',
        'finance.record',
        'finance.reverse',
        'orders.manage',
        'production.board',
        'customers.manage',
        'inventory.manage',
        'catalogue.manage',
        'sales.record',
        'purchasing.manage',
        'reports.view',
        'branches.manage',
        'messages.send',
        'billing.manage',
    ];
}

function staff_role_permissions(string $role): array
{
    $all = staff_permission_keys();
    return match ($role) {
        'owner' => $all,
        'manager' => array_values(array_filter(
            $all,
            static fn (string $key): bool => $key !== 'billing.manage'
        )),
        'staff' => [
            'finance.view',
            'finance.record',
            'orders.manage',
            'production.board',
            'customers.manage',
            'inventory.manage',
            'catalogue.manage',
            'sales.record',
            'messages.send',
        ],
        'tailor' => [
            'orders.manage',
            'production.board',
        ],
        default => [],
    };
}

function staff_present(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
        'phone' => (string) ($row['phone'] ?? ''),
        'started_on' => $row['started_on'] !== null ? (string) $row['started_on'] : null,
        'lang' => (string) ($row['lang'] ?? 'en'),
        'active' => (int) ($row['active'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function staff_current_user(): ?array
{
    $userId = current_user_id();
    if ($userId === null) {
        return null;
    }
    $rows = db_select('fashion_users', ['id' => $userId], ['limit' => 1]);
    return $rows !== [] ? staff_present($rows[0]) : null;
}

function staff_current_role(): string
{
    $user = staff_current_user();
    return $user['role'] ?? '';
}

function user_can(string $permission): bool
{
    if (!in_array($permission, staff_permission_keys(), true)) {
        return false;
    }
    $role = staff_current_role();
    if ($role === 'platform_admin') {
        return false;
    }
    return in_array($permission, staff_role_permissions($role), true);
}

function require_permission(string $permission): void
{
    require_login();
    if (!user_can($permission)) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
}

function staff_owner_count(): int
{
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_users
         WHERE tenant_id = :tenant_id AND role = :role AND active = 1',
        ['role' => 'owner']
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function staff_log(string $action, string $targetUserId, int $enabled): void
{
    $actor = current_user_id();
    if ($actor === null || !is_uuid($targetUserId)) {
        return;
    }
    if (!in_array($action, ['staff_created', 'staff_updated', 'staff_deactivated', 'staff_reactivated'], true)) {
        return;
    }
    db_insert('fashion_activity_log', [
        'user_id' => $actor,
        'action' => $action,
        'module_key' => 'fashion.staff',
        'enabled' => $enabled,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

function staff_find(string $userId): ?array
{
    if (!is_uuid($userId)) {
        return null;
    }
    $rows = db_select('fashion_users', ['id' => $userId], ['limit' => 1]);
    if ($rows === []) {
        return null;
    }
    $user = staff_present($rows[0]);
    if ($user['role'] === 'platform_admin') {
        return null;
    }
    return $user;
}

function staff_list(bool $includeInactive = true): array
{
    require_permission('staff.manage');
    $sql = 'SELECT id, name, email, role, phone, started_on, lang, active, created_at
            FROM fashion_users
            WHERE tenant_id = :tenant_id AND role <> :platform';
    $params = ['platform' => 'platform_admin'];
    if (!$includeInactive) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY active DESC, role ASC, name ASC';
    $rows = db_query($sql, $params);
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = staff_present($row);
        }
    }
    return $out;
}

function staff_get(string $userId): ?array
{
    require_permission('staff.manage');
    return staff_find($userId);
}

function staff_create(array $input, bool $enforceManagePermission = true): array
{
    if ($enforceManagePermission) {
        require_permission('staff.manage');
    } else {
        require_login();
        if (staff_current_role() !== 'owner') {
            return ['errors' => ['form' => 'not_allowed']];
        }
    }
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $role = (string) ($input['role'] ?? 'staff');
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $startedOn = trim((string) ($input['started_on'] ?? ''));
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'invalid_email';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'invalid_password';
    }
    if (!in_array($role, staff_assignable_roles(), true)) {
        $errors['role'] = 'invalid_role';
    }
    $phone = '';
    if ($phoneRaw !== '') {
        $normalized = phone_normalize($phoneRaw);
        if ($normalized === null) {
            $errors['phone'] = 'invalid_phone';
        } else {
            $phone = $normalized;
        }
    }
    $started = null;
    if ($startedOn !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $startedOn, app_timezone());
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $startedOn) {
            $errors['started_on'] = 'invalid_date';
        } else {
            $started = $startedOn;
        }
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    if (auth_email_taken($email)) {
        return ['errors' => ['email' => 'email_taken']];
    }
    try {
        subscription_assert_within_limit('max_staff', subscription_staff_count() + 1);
    } catch (HttpException $e) {
        return ['errors' => ['email' => 'subscription_limit_reached']];
    }
    $id = db_insert('fashion_users', [
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'phone' => $phone,
        'started_on' => $started,
        'lang' => 'en',
        'active' => 1,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    staff_log('staff_created', $id, 1);
    return ['user' => staff_find($id)];
}

function staff_update(string $userId, array $input): array
{
    require_permission('staff.manage');
    $user = staff_get($userId);
    if ($user === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    $name = trim((string) ($input['name'] ?? $user['name']));
    $role = (string) ($input['role'] ?? $user['role']);
    $phoneRaw = trim((string) ($input['phone'] ?? $user['phone']));
    $startedOn = trim((string) ($input['started_on'] ?? ($user['started_on'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if ($user['role'] === 'owner') {
        $role = 'owner';
    } elseif (!in_array($role, staff_assignable_roles(), true)) {
        $errors['role'] = 'invalid_role';
    }
    if ($user['role'] === 'owner' && $role !== 'owner') {
        $errors['role'] = 'cannot_demote_owner';
    }
    $phone = '';
    if ($phoneRaw !== '') {
        $normalized = phone_normalize($phoneRaw);
        if ($normalized === null) {
            $errors['phone'] = 'invalid_phone';
        } else {
            $phone = $normalized;
        }
    }
    $started = null;
    if ($startedOn !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $startedOn, app_timezone());
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $startedOn) {
            $errors['started_on'] = 'invalid_date';
        } else {
            $started = $startedOn;
        }
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors['password'] = 'invalid_password';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $data = [
        'name' => $name,
        'role' => $role,
        'phone' => $phone,
        'started_on' => $started,
    ];
    if ($password !== '') {
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    db_update('fashion_users', $data, ['id' => $userId]);
    staff_log('staff_updated', $userId, $user['active'] ? 1 : 0);
    return ['user' => staff_find($userId)];
}

function staff_deactivate(string $userId): array
{
    require_permission('staff.manage');
    $user = staff_get($userId);
    if ($user === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    if ((string) current_user_id() === $userId) {
        return ['errors' => ['form' => 'cannot_deactivate_self']];
    }
    if ($user['role'] === 'owner' && staff_owner_count() <= 1) {
        return ['errors' => ['form' => 'cannot_deactivate_last_owner']];
    }
    if (!$user['active']) {
        return ['user' => $user];
    }
    db_update('fashion_users', ['active' => 0], ['id' => $userId]);
    staff_log('staff_deactivated', $userId, 0);
    return ['user' => staff_find($userId)];
}

function staff_reactivate(string $userId): array
{
    require_permission('staff.manage');
    $user = staff_get($userId);
    if ($user === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    if ($user['active']) {
        return ['user' => $user];
    }
    try {
        subscription_assert_within_limit('max_staff', subscription_staff_count() + 1);
    } catch (HttpException $e) {
        return ['errors' => ['form' => 'subscription_limit_reached']];
    }
    db_update('fashion_users', ['active' => 1], ['id' => $userId]);
    staff_log('staff_reactivated', $userId, 1);
    return ['user' => staff_find($userId)];
}
