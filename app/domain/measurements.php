<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function garment_rows(): array
{
    require_module('fashion.measurements');
    return db_select('fashion_garment_types', [], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
}

function measurement_active_fields(string $garmentId): array
{
    require_module('fashion.measurements');
    $rows = [];
    $fields = db_select('fashion_measurement_fields', ['garment_type_id' => $garmentId], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
    foreach ($fields as $field) {
        if ((int) $field['active'] === 1) {
            $rows[] = $field;
        }
    }
    return $rows;
}

function measurement_other_rows(array $others): array
{
    if (isset($others['label']) && is_array($others['label'])) {
        $rows = [];
        $values = is_array($others['value'] ?? null) ? $others['value'] : [];
        foreach ($others['label'] as $index => $label) {
            $rows[] = [
                'label' => $label,
                'value' => $values[$index] ?? '',
            ];
        }
        return $rows;
    }
    return $others;
}

function measurement_save(string $customerId, string $garmentId, string $unit, array $values, array $others = []): array
{
    require_module('fashion.measurements');
    if (!is_uuid($customerId) || db_select('fashion_customers', ['id' => $customerId]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    if (!is_uuid($garmentId) || db_select('fashion_garment_types', ['id' => $garmentId, 'active' => 1]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    if (!in_array($unit, ['inches', 'cm'], true)) {
        return ['error' => 'required'];
    }
    $allowed = [];
    foreach (measurement_active_fields($garmentId) as $field) {
        $allowed[(string) $field['field_key']] = true;
    }
    $stored = [];
    foreach ($values as $key => $value) {
        $key = (string) $key;
        if (!isset($allowed[$key]) || !is_scalar($value)) {
            continue;
        }
        $clean = trim((string) $value);
        if ($clean === '') {
            continue;
        }
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $clean)) {
            return ['error' => 'invalid_measurement'];
        }
        $stored[$key] = $clean;
    }
    foreach ($others as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $amount = trim((string) ($row['value'] ?? ''));
        if ($label === '' || $amount === '') {
            continue;
        }
        if (strlen($label) > 40 || preg_match('/[\r\n:]/', $label) === 1) {
            return ['error' => 'required'];
        }
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $amount)) {
            return ['error' => 'invalid_measurement'];
        }
        $stored['other:' . $label] = $amount;
    }
    if ($stored === []) {
        return ['error' => 'invalid_measurement'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $id = db_insert('fashion_measurements', [
        'customer_id' => $customerId,
        'garment_type_id' => $garmentId,
        'unit' => $unit,
        'values_json' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function measurement_profile(string $customerId, string $garmentId, string $fromId = ''): array
{
    require_module('fashion.measurements');
    $garments = [];
    foreach (garment_rows() as $garment) {
        if ((int) $garment['active'] === 1) {
            $garments[] = $garment;
        }
    }
    $selected = null;
    foreach ($garments as $garment) {
        if ((string) $garment['id'] === $garmentId) {
            $selected = $garment;
            break;
        }
    }
    if ($selected === null && $garments !== []) {
        $selected = $garments[0];
    }
    $versions = measurement_versions($customerId);
    $latest = [];
    $history = [];
    $seen = [];
    foreach ($versions as $version) {
        if (!isset($seen[$version['garment_id']])) {
            $seen[$version['garment_id']] = true;
            $latest[] = $version;
        } else {
            $history[] = $version;
        }
    }
    $prefill = ['unit' => 'inches', 'values' => []];
    $formOpen = false;
    if ($fromId !== '' && is_uuid($fromId)) {
        foreach ($versions as $version) {
            if ($version['id'] !== $fromId) {
                continue;
            }
            foreach ($garments as $garment) {
                if ((string) $garment['id'] === $version['garment_id']) {
                    $selected = $garment;
                    break;
                }
            }
            $prefill = ['unit' => $version['unit'], 'values' => $version['values']];
            $formOpen = true;
            break;
        }
    }
    if (!$formOpen && $selected !== null) {
        foreach ($latest as $version) {
            if ($version['garment_id'] === (string) $selected['id']) {
                $prefill = ['unit' => $version['unit'], 'values' => $version['values']];
                break;
            }
        }
    }
    $choices = [];
    foreach ($garments as $garment) {
        $choices[] = [
            'id' => (string) $garment['id'],
            'name' => (string) $garment['name'],
            'placeholder' => (int) $garment['placeholder'] === 1,
            'fields' => measurement_active_fields((string) $garment['id']),
        ];
    }
    return [
        'garments' => $choices,
        'garment_id' => $selected === null ? '' : (string) $selected['id'],
        'placeholder' => $selected !== null && (int) $selected['placeholder'] === 1,
        'fields' => $selected === null ? [] : measurement_active_fields((string) $selected['id']),
        'prefill' => $prefill,
        'form_open' => $formOpen,
        'latest' => $latest,
        'history' => $history,
    ];
}

function measurement_versions(string $customerId): array
{
    require_module('fashion.measurements');
    $names = [];
    foreach (db_select('fashion_garment_types', [], ['limit' => 100]) as $garment) {
        $names[(string) $garment['id']] = (string) $garment['name'];
    }
    $labels = [];
    $order = [];
    foreach (db_select('fashion_measurement_fields', [], ['order' => 'sort_order ASC', 'limit' => 500]) as $field) {
        $garmentId = (string) $field['garment_type_id'];
        $labels[$garmentId][(string) $field['field_key']] = (string) $field['name'];
        $order[$garmentId][(string) $field['field_key']] = (int) $field['sort_order'];
    }
    $people = [];
    foreach (db_select('fashion_users', [], ['limit' => 100]) as $user) {
        $people[(string) $user['id']] = (string) $user['name'];
    }
    $versions = [];
    $rows = db_select('fashion_measurements', ['customer_id' => $customerId], ['order' => 'seq DESC', 'limit' => 100]);
    foreach ($rows as $row) {
        $garmentId = (string) $row['garment_type_id'];
        $decoded = json_decode((string) $row['values_json'], true);
        $values = [];
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (is_string($key) && (is_string($value) || is_int($value))) {
                    $values[$key] = (string) $value;
                }
            }
        }
        $lines = [];
        foreach ($values as $key => $value) {
            $name = $labels[$garmentId][$key] ?? $key;
            if (str_starts_with($key, 'other:')) {
                $name = substr($key, 6);
            }
            $lines[] = [
                'key' => $key,
                'name' => $name,
                'value' => $value,
                'sort' => $order[$garmentId][$key] ?? 1000,
            ];
        }
        usort($lines, static function (array $left, array $right): int {
            return $left['sort'] <=> $right['sort'];
        });
        $versions[] = [
            'id' => (string) $row['id'],
            'garment_id' => $garmentId,
            'garment_name' => $names[$garmentId] ?? '',
            'unit' => (string) $row['unit'],
            'created_at' => (string) $row['created_at'],
            'taken_by' => $people[(string) $row['created_by']] ?? '',
            'values' => $values,
            'lines' => $lines,
        ];
    }
    return $versions;
}

function garment_rename(string $id, string $name): array
{
    require_module('fashion.measurements');
    $name = trim($name);
    if (!is_uuid($id) || $name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    db_update('fashion_garment_types', ['name' => $name], ['id' => $id]);
    return [];
}

function garment_reorder(string $id, string $direction): void
{
    measurement_reorder('fashion_garment_types', garment_rows(), $id, $direction);
}

function garment_add(string $name): array
{
    require_module('fashion.measurements');
    $name = trim($name);
    if ($name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    $sort = 1;
    foreach (garment_rows() as $garment) {
        $sort = max($sort, (int) $garment['sort_order'] + 1);
    }
    db_insert('fashion_garment_types', [
        'code' => measurement_unique_code('fashion_garment_types', 'code', measurement_slug($name), []),
        'name' => $name,
        'sort_order' => $sort,
        'active' => 1,
        'placeholder' => 0,
        'lead_days' => null,
    ]);
    return [];
}

function garment_set_active(string $id, bool $active): void
{
    require_module('fashion.measurements');
    if (!is_uuid($id)) {
        return;
    }
    db_update('fashion_garment_types', ['active' => $active ? 1 : 0], ['id' => $id]);
}

function field_rows(string $garmentId): array
{
    require_module('fashion.measurements');
    return db_select('fashion_measurement_fields', ['garment_type_id' => $garmentId], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
}

function field_rename(string $id, string $name): array
{
    require_module('fashion.measurements');
    $name = trim($name);
    if (!is_uuid($id) || $name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    db_update('fashion_measurement_fields', ['name' => $name], ['id' => $id]);
    return [];
}

function field_reorder(string $garmentId, string $id, string $direction): void
{
    if (!is_uuid($garmentId)) {
        return;
    }
    measurement_reorder('fashion_measurement_fields', field_rows($garmentId), $id, $direction);
}

function field_add(string $garmentId, string $name, string $unit = ''): array
{
    require_module('fashion.measurements');
    $name = trim($name);
    $unit = trim($unit);
    if (!is_uuid($garmentId) || db_select('fashion_garment_types', ['id' => $garmentId]) === [] || $name === '' || strlen($name) > 80 || strlen($unit) > 20) {
        return ['error' => 'required'];
    }
    $sort = 1;
    foreach (field_rows($garmentId) as $field) {
        $sort = max($sort, (int) $field['sort_order'] + 1);
    }
    db_insert('fashion_measurement_fields', [
        'garment_type_id' => $garmentId,
        'field_key' => measurement_unique_code('fashion_measurement_fields', 'field_key', measurement_slug($name), ['garment_type_id' => $garmentId]),
        'name' => $name,
        'unit' => $unit,
        'sort_order' => $sort,
        'active' => 1,
        'placeholder' => 0,
    ]);
    return [];
}

function field_save(string $id, string $name, string $unit): array
{
    require_module('fashion.measurements');
    $name = trim($name);
    $unit = trim($unit);
    if (!is_uuid($id) || $name === '' || strlen($name) > 80 || strlen($unit) > 20) {
        return ['error' => 'required'];
    }
    if (db_select('fashion_measurement_fields', ['id' => $id]) === []) {
        return ['error' => 'not_found'];
    }
    db_update('fashion_measurement_fields', ['name' => $name, 'unit' => $unit], ['id' => $id]);
    return [];
}

function garment_measurement_count(string $id): int
{
    require_module('fashion.measurements');
    if (!is_uuid($id)) {
        return 0;
    }
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_measurements WHERE tenant_id = :tenant_id AND garment_type_id = :garment_id',
        ['garment_id' => $id]
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function garment_remove(string $id): array
{
    require_module('fashion.measurements');
    if (!is_uuid($id) || db_select('fashion_garment_types', ['id' => $id]) === []) {
        return ['error' => 'not_found'];
    }
    if (garment_measurement_count($id) > 0) {
        garment_set_active($id, false);
        return ['archived' => true];
    }
    db_begin();
    try {
        foreach (field_rows($id) as $field) {
            db_delete('fashion_measurement_fields', ['id' => (string) $field['id']]);
        }
        db_delete('fashion_garment_types', ['id' => $id]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['deleted' => true];
}

function field_has_saved_values(string $garmentId, string $key): bool
{
    $rows = db_query(
        'SELECT values_json FROM fashion_measurements WHERE tenant_id = :tenant_id AND garment_type_id = :garment_id',
        ['garment_id' => $garmentId]
    );
    foreach ($rows as $row) {
        $decoded = json_decode((string) $row['values_json'], true);
        if (is_array($decoded) && array_key_exists($key, $decoded)) {
            return true;
        }
    }
    return false;
}

function field_remove(string $id): array
{
    require_module('fashion.measurements');
    if (!is_uuid($id)) {
        return ['error' => 'not_found'];
    }
    $rows = db_select('fashion_measurement_fields', ['id' => $id]);
    if ($rows === []) {
        return ['error' => 'not_found'];
    }
    $field = $rows[0];
    if (field_has_saved_values((string) $field['garment_type_id'], (string) $field['field_key'])) {
        field_set_active($id, false);
        return ['archived' => true];
    }
    db_delete('fashion_measurement_fields', ['id' => $id]);
    return ['deleted' => true];
}

function field_order(string $garmentId, array $ids): void
{
    require_module('fashion.measurements');
    if (!is_uuid($garmentId)) {
        return;
    }
    $rows = field_rows($garmentId);
    $byId = [];
    foreach ($rows as $row) {
        $byId[(string) $row['id']] = $row;
    }
    $ordered = [];
    foreach ($ids as $id) {
        $id = (string) $id;
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
            unset($byId[$id]);
        }
    }
    foreach ($byId as $row) {
        $ordered[] = $row;
    }
    db_begin();
    try {
        $sort = 1;
        foreach ($ordered as $row) {
            db_update('fashion_measurement_fields', ['sort_order' => $sort], ['id' => (string) $row['id']]);
            $sort++;
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}

function field_set_active(string $id, bool $active): void
{
    require_module('fashion.measurements');
    if (!is_uuid($id)) {
        return;
    }
    db_update('fashion_measurement_fields', ['active' => $active ? 1 : 0], ['id' => $id]);
}

function measurement_reorder(string $table, array $rows, string $id, string $direction): void
{
    require_module('fashion.measurements');
    if (!is_uuid($id) || !in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $index = null;
    foreach ($rows as $i => $row) {
        if ((string) $row['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $neighbor = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($rows[$neighbor])) {
        return;
    }
    $here = $rows[$index];
    $there = $rows[$neighbor];
    db_begin();
    try {
        db_update($table, ['sort_order' => (int) $there['sort_order']], ['id' => (string) $here['id']]);
        db_update($table, ['sort_order' => (int) $here['sort_order']], ['id' => (string) $there['id']]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}

function measurement_slug(string $name): string
{
    $code = strtolower($name);
    $code = preg_replace('/[^a-z0-9]+/', '_', $code);
    $code = is_string($code) ? trim($code, '_') : '';
    if ($code === '') {
        $code = 'item';
    }
    return substr($code, 0, 30);
}

function measurement_unique_code(string $table, string $column, string $base, array $where): string
{
    $code = $base;
    $number = 2;
    while (db_select($table, $where + [$column => $code]) !== []) {
        $code = substr($base, 0, 28) . '_' . (string) $number;
        $number++;
        if ($number > 50) {
            $code = 'item_' . substr(uuid(), 0, 8);
            break;
        }
    }
    return $code;
}
