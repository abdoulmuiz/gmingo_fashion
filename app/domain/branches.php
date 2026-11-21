<?php

declare(strict_types=1);

require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/subscriptions.php';

function branch_list(bool $activeOnly = true): array
{
    require_permission('branches.manage');
    $sql = 'SELECT id, name, phone, address, active, created_at
            FROM fashion_branches WHERE tenant_id = :tenant_id';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY name ASC';
    $rows = db_query($sql);
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'phone' => (string) $row['phone'],
                'address' => (string) $row['address'],
                'active' => (int) $row['active'] === 1,
            ];
        }
    }
    return $out;
}

function branch_count(): int
{
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_branches WHERE tenant_id = :tenant_id AND active = 1'
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function branch_create(array $input): array
{
    require_permission('branches.manage');
    $name = trim((string) ($input['name'] ?? ''));
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
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
    if (strlen($address) > 255) {
        $errors['address'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    try {
        subscription_assert_within_limit('max_branches', branch_count() + 1);
    } catch (HttpException $e) {
        return ['errors' => ['form' => 'subscription_limit_reached']];
    }
    $id = db_insert('fashion_branches', [
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'active' => 1,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['branch_id' => $id];
}

function branch_deactivate(string $branchId): array
{
    require_permission('branches.manage');
    if (!is_uuid($branchId)) {
        return ['errors' => ['form' => 'not_found']];
    }
    $rows = db_select('fashion_branches', ['id' => $branchId], ['limit' => 1]);
    if ($rows === []) {
        return ['errors' => ['form' => 'not_found']];
    }
    db_update('fashion_branches', ['active' => 0], ['id' => $branchId]);
    return ['ok' => true];
}
