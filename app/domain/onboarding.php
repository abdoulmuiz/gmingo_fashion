<?php

declare(strict_types=1);

require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/staff.php';

function onboarding_steps(): array
{
    return ['business', 'contact', 'branding', 'preferences', 'staff', 'inventory', 'subscription'];
}

function onboarding_currencies(): array
{
    return ['NGN', 'USD', 'GBP', 'EUR'];
}

function onboarding_tenant(): array
{
    require_login();
    $rows = db_select('fashion_tenants', ['id' => (string) current_tenant_id()], ['limit' => 1]);
    if ($rows === []) {
        throw new RuntimeException('Missing business.');
    }
    return $rows[0];
}

function onboarding_is_owner(): bool
{
    $userId = current_user_id();
    if ($userId === null) {
        return false;
    }
    $rows = db_select('fashion_users', ['id' => $userId], ['limit' => 1]);
    return $rows !== [] && (string) $rows[0]['role'] === 'owner';
}

function onboarding_incomplete(?array $tenant = null): bool
{
    if ($tenant === null) {
        if (current_tenant_id() === null) {
            return false;
        }
        $tenant = onboarding_tenant();
    }
    $step = (string) ($tenant['onboarding_step'] ?? 'complete');
    return $step !== 'complete';
}

function onboarding_begin(): void
{
    require_login();
    if (!onboarding_is_owner()) {
        return;
    }
    db_update('fashion_tenants', [
        'onboarding_step' => 'business',
        'onboarding_completed_at' => null,
    ], ['id' => (string) current_tenant_id()]);
}

function onboarding_current_step(?array $tenant = null): string
{
    if ($tenant === null) {
        $tenant = onboarding_tenant();
    }
    $step = (string) ($tenant['onboarding_step'] ?? 'complete');
    if ($step === 'complete') {
        return 'complete';
    }
    if (!in_array($step, onboarding_steps(), true)) {
        return 'business';
    }
    if ($step === 'inventory' && !module_enabled('fashion.inventory')) {
        return 'subscription';
    }
    return $step;
}

function onboarding_next_step(string $step): string
{
    $steps = onboarding_steps();
    $index = array_search($step, $steps, true);
    if ($index === false) {
        return 'complete';
    }
    for ($i = $index + 1; $i < count($steps); $i++) {
        $candidate = $steps[$i];
        if ($candidate === 'inventory' && !module_enabled('fashion.inventory')) {
            continue;
        }
        return $candidate;
    }
    return 'complete';
}

function onboarding_advance(string $fromStep): void
{
    $next = onboarding_next_step($fromStep);
    if ($next === 'complete') {
        onboarding_finish();
        return;
    }
    db_update('fashion_tenants', [
        'onboarding_step' => $next,
    ], ['id' => (string) current_tenant_id()]);
}

function onboarding_finish(): void
{
    require_login();
    db_update('fashion_tenants', [
        'onboarding_step' => 'complete',
        'onboarding_completed_at' => app_now()->format('Y-m-d H:i:s'),
    ], ['id' => (string) current_tenant_id()]);
}

function onboarding_skip(): void
{
    require_login();
    if (!onboarding_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    onboarding_finish();
}

function onboarding_state(): array
{
    require_login();
    $tenant = onboarding_tenant();
    $step = onboarding_current_step($tenant);
    $plan = subscription_plan();
    $sub = subscription_current();
    return [
        'tenant' => $tenant,
        'step' => $step,
        'steps' => onboarding_steps(),
        'incomplete' => onboarding_incomplete($tenant),
        'currencies' => onboarding_currencies(),
        'company_types' => company_types(),
        'inventory_on' => module_enabled('fashion.inventory'),
        'subscription' => $sub,
        'plan' => $plan,
        'logo_url' => onboarding_logo_url($tenant),
    ];
}

function onboarding_logo_url(array $tenant): string
{
    $path = $tenant['logo_path'] ?? null;
    if (!is_string($path) || $path === '') {
        return '';
    }
    return url('/onboarding/logo');
}

function onboarding_save_business(array $input): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $name = trim((string) ($input['business_name'] ?? ''));
    $type = (string) ($input['company_type'] ?? '');
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['business_name'] = 'required';
    }
    if (!in_array($type, company_types(), true)) {
        $errors['company_type'] = 'invalid_company_type';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $tenant = onboarding_tenant();
    $previous = (string) ($tenant['company_type'] ?? '');
    db_update('fashion_tenants', [
        'name' => $name,
        'company_type' => $type,
    ], ['id' => (string) $tenant['id']]);
    if ($previous !== $type) {
        module_save(module_defaults($type));
    }
    onboarding_advance('business');
    return ['ok' => true];
}

function onboarding_save_contact(array $input): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $phone = phone_normalize((string) ($input['phone'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $errors = [];
    if ($phone === null) {
        $errors['phone'] = 'invalid_phone';
    }
    if (strlen($address) > 255) {
        $errors['address'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    db_update('fashion_tenants', [
        'phone' => $phone,
        'address' => $address,
    ], ['id' => (string) current_tenant_id()]);
    onboarding_advance('contact');
    return ['ok' => true];
}

function onboarding_colour_ok(string $value): bool
{
    return $value === '' || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
}

function onboarding_logo_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir;
}

function onboarding_save_branding(array $input, ?array $file): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $primary = trim((string) ($input['brand_primary'] ?? ''));
    $accent = trim((string) ($input['brand_accent'] ?? ''));
    $errors = [];
    if (!onboarding_colour_ok($primary) || !onboarding_colour_ok($accent)) {
        $errors['brand'] = 'invalid_brand_colour';
    }
    $logoPath = null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $saved = onboarding_store_logo($file);
        if (isset($saved['error'])) {
            $errors['logo'] = (string) $saved['error'];
        } else {
            $logoPath = (string) $saved['path'];
        }
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $data = [
        'brand_primary' => $primary,
        'brand_accent' => $accent,
    ];
    if ($logoPath !== null) {
        $data['logo_path'] = $logoPath;
    }
    db_update('fashion_tenants', $data, ['id' => (string) current_tenant_id()]);
    onboarding_advance('branding');
    return ['ok' => true];
}

function onboarding_store_logo(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['error' => 'invalid_logo'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1 || $size > 1024 * 1024) {
        return ['error' => 'invalid_logo'];
    }
    $info = @getimagesize($tmp);
    if (!is_array($info) || !isset($info[2])) {
        return ['error' => 'invalid_logo'];
    }
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    $type = (int) $info[2];
    if (!isset($map[$type])) {
        return ['error' => 'invalid_logo'];
    }
    $tenantId = (string) current_tenant_id();
    $ext = $map[$type];
    $relative = $tenantId . '.' . $ext;
    $target = onboarding_logo_dir() . '/' . $relative;
    foreach (glob(onboarding_logo_dir() . '/' . $tenantId . '.*') ?: [] as $old) {
        if (is_file($old)) {
            unlink($old);
        }
    }
    if (!move_uploaded_file($tmp, $target)) {
        return ['error' => 'invalid_logo'];
    }
    chmod($target, 0640);
    return ['path' => $relative];
}

function onboarding_logo_absolute(?array $tenant = null): ?string
{
    if ($tenant === null) {
        $tenant = onboarding_tenant();
    }
    $path = $tenant['logo_path'] ?? null;
    if (!is_string($path) || $path === '' || str_contains($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
        return null;
    }
    $full = onboarding_logo_dir() . '/' . $path;
    if (!is_file($full)) {
        return null;
    }
    return $full;
}

function onboarding_save_preferences(array $input): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $currency = strtoupper(trim((string) ($input['currency'] ?? 'NGN')));
    $lang = strtolower(trim((string) ($input['lang'] ?? 'en')));
    $errors = [];
    if (!in_array($currency, onboarding_currencies(), true)) {
        $errors['currency'] = 'invalid_currency';
    }
    if (!in_array($lang, ['en', 'ha'], true)) {
        $errors['lang'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    db_update('fashion_tenants', [
        'currency' => $currency,
    ], ['id' => (string) current_tenant_id()]);
    $userId = current_user_id();
    if ($userId !== null) {
        db_update('fashion_users', ['lang' => $lang], ['id' => $userId]);
        lang_set($lang);
    }
    onboarding_advance('preferences');
    return ['ok' => true];
}

function onboarding_save_staff(array $input): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $skip = !empty($input['skip']);
    if ($skip) {
        onboarding_advance('staff');
        return ['ok' => true];
    }
    $name = trim((string) ($input['staff_name'] ?? ''));
    $email = strtolower(trim((string) ($input['staff_email'] ?? '')));
    $password = (string) ($input['staff_password'] ?? '');
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['staff_name'] = 'required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['staff_email'] = 'invalid_email';
    }
    if (strlen($password) < 8) {
        $errors['staff_password'] = 'invalid_password';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $created = staff_create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => 'staff',
        'phone' => '',
        'started_on' => app_today(),
    ], false);
    if (isset($created['errors'])) {
        $mapped = [];
        foreach ($created['errors'] as $key => $code) {
            $mapped[$key === 'email' ? 'staff_email' : ($key === 'name' ? 'staff_name' : ($key === 'password' ? 'staff_password' : $key))] = $code;
        }
        return ['errors' => $mapped];
    }
    onboarding_advance('staff');
    return ['ok' => true];
}

function onboarding_save_inventory(array $input): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    if (!module_enabled('fashion.inventory')) {
        onboarding_advance('inventory');
        return ['ok' => true];
    }
    if (!empty($input['skip'])) {
        onboarding_advance('inventory');
        return ['ok' => true];
    }
    $result = stock_create([
        'name' => (string) ($input['name'] ?? ''),
        'category' => (string) ($input['category'] ?? 'fabric'),
        'unit' => (string) ($input['unit'] ?? 'yards'),
        'colour' => (string) ($input['colour'] ?? ''),
        'minimum' => (string) ($input['minimum'] ?? ''),
    ]);
    if (isset($result['error'])) {
        return ['errors' => ['name' => (string) $result['error']]];
    }
    $qty = trim((string) ($input['opening_qty'] ?? ''));
    if ($qty !== '' && isset($result['id'])) {
        $adjust = stock_adjust((string) $result['id'], [
            'amount' => $qty,
            'reason' => 'opening',
            'note' => 'onboarding',
        ]);
        if (isset($adjust['error'])) {
            return ['errors' => ['opening_qty' => (string) $adjust['error']]];
        }
    }
    onboarding_advance('inventory');
    return ['ok' => true];
}

function onboarding_confirm_subscription(): array
{
    require_login();
    if (!onboarding_is_owner()) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $sub = subscription_current();
    if ($sub === null) {
        subscription_start_trial();
    }
    onboarding_finish();
    return ['ok' => true];
}
