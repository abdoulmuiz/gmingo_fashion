<?php

declare(strict_types=1);

require_once __DIR__ . '/defaults.php';

function account_clean(string $value): string
{
    return trim($value);
}

function business_register(array $input): array
{
    $business = account_clean((string) ($input['business_name'] ?? ''));
    $owner = account_clean((string) ($input['owner_name'] ?? ''));
    $email = strtolower(account_clean((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $phone = phone_normalize((string) ($input['phone'] ?? ''));
    $errors = [];
    if ($business === '' || strlen($business) > 190) {
        $errors['business_name'] = 'required';
    }
    if ($owner === '' || strlen($owner) > 190) {
        $errors['owner_name'] = 'required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'invalid_email';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'invalid_password';
    }
    if ($phone === null) {
        $errors['phone'] = 'invalid_phone';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    if (auth_find_user_by_email($email) !== null) {
        return ['errors' => ['email' => 'email_taken']];
    }

    $tenantId = uuid();
    $userId = uuid();
    $now = date('Y-m-d H:i:s');
    db_begin();
    try {
        auth_login($userId, $tenantId);
        db_insert('fashion_tenants', [
            'id' => $tenantId,
            'name' => $business,
            'phone' => $phone,
            'deposit_percent' => null,
            'capacity_max' => null,
            'payment_bank' => null,
            'payment_account' => null,
            'created_at' => $now,
        ]);
        db_insert('fashion_users', [
            'id' => $userId,
            'name' => $owner,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'owner',
            'lang' => 'en',
            'active' => 1,
            'created_at' => $now,
        ]);
        shop_seed_defaults();
        if (defined('GMINGO_TESTING') && !empty($GLOBALS['gmingo_fail_register'])) {
            throw new RuntimeException('Simulated signup failure.');
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        auth_logout();
        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
            return ['errors' => ['email' => 'email_taken']];
        }
        if ($e instanceof RuntimeException && $e->getMessage() === 'Simulated signup failure.') {
            throw $e;
        }
        throw $e;
    }
    lang_set('en');
    return ['user_id' => $userId, 'tenant_id' => $tenantId];
}

function login_dummy_hash(): string
{
    static $hash = null;
    if ($hash === null) {
        $hash = password_hash('not-used', PASSWORD_DEFAULT);
    }
    return $hash;
}

function login_user(string $email, string $password): ?string
{
    $email = strtolower(trim($email));
    if (auth_too_many_attempts($email)) {
        return 'rate_limited';
    }
    $user = auth_find_user_by_email($email);
    $hash = is_array($user) ? (string) $user['password_hash'] : login_dummy_hash();
    $valid = password_verify($password, $hash);
    if (!is_array($user) || (int) $user['active'] !== 1 || !$valid) {
        auth_register_failure($email);
        return 'login_failed';
    }
    auth_clear_attempts($email);
    auth_login((string) $user['id'], (string) $user['tenant_id']);
    $lang = (string) $user['lang'];
    if (!in_array($lang, ['en', 'ha'], true)) {
        $lang = 'en';
    }
    lang_set($lang);
    return null;
}
