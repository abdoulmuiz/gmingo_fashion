<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/measurements.php';
require_once __DIR__ . '/settings.php';

function measurement_submit(string $customerId): void
{
    require_module('fashion.measurements');
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $garmentId = (string) ($_POST['garment_id'] ?? '');
    $values = $_POST['values'] ?? [];
    if (!is_array($values)) {
        $values = [];
    }
    $others = $_POST['others'] ?? [];
    if (!is_array($others)) {
        $others = [];
    }
    $others = measurement_other_rows($others);
    if (!in_array($action, ['new', 'take', 'update'], true)) {
        flash_set('error', t('required'));
    } else {
        $result = measurement_save($customerId, $garmentId, (string) ($_POST['unit'] ?? ''), $values, $others);
        if (isset($result['error'])) {
            flash_set('error', t((string) $result['error']));
        } else {
            flash_set('success', t('saved'));
        }
    }
    $back = '/customers/' . $customerId;
    if (is_uuid($garmentId)) {
        $back .= '?garment=' . $garmentId;
    }
    redirect($back);
}

function garments_form(): void
{
    require_module('fashion.measurements');
    owner_only();
    $garments = [];
    foreach (garment_rows() as $garment) {
        $garment['measurement_count'] = garment_measurement_count((string) $garment['id']);
        $garments[] = $garment;
    }
    render('garments', [
        'title' => t('garment_settings'),
        'garments' => $garments,
    ]);
}

function garments_editor(string $id): void
{
    require_module('fashion.measurements');
    owner_only();
    if (!is_uuid($id)) {
        http_response_code(404);
        render('simple', ['title' => t('not_found'), 'body' => t('not_found_body')]);
        return;
    }
    $rows = db_select('fashion_garment_types', ['id' => $id]);
    if ($rows === []) {
        http_response_code(404);
        render('simple', ['title' => t('not_found'), 'body' => t('not_found_body')]);
        return;
    }
    $garment = $rows[0];
    $garment['measurement_count'] = garment_measurement_count($id);
    render('garment_fields', [
        'title' => (string) $garment['name'],
        'garment' => $garment,
        'fields' => field_rows($id),
    ]);
}

function garments_submit(): void
{
    require_module('fashion.measurements');
    owner_only();
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    $name = (string) ($_POST['name'] ?? '');
    $garmentId = (string) ($_POST['garment_id'] ?? '');
    $result = [];
    if ($action === 'garment_add') {
        $result = garment_add($name);
    } elseif ($action === 'garment_rename') {
        $result = garment_rename($id, $name);
    } elseif ($action === 'garment_up' || $action === 'garment_down') {
        garment_reorder($id, $action === 'garment_up' ? 'up' : 'down');
    } elseif ($action === 'garment_deactivate') {
        garment_set_active($id, false);
    } elseif ($action === 'garment_activate') {
        garment_set_active($id, true);
    } elseif ($action === 'garment_remove') {
        $result = garment_remove($id);
        $garmentId = '';
    } elseif ($action === 'field_add') {
        $result = field_add($garmentId, $name, (string) ($_POST['unit'] ?? ''));
    } elseif ($action === 'field_save') {
        $result = field_save($id, $name, (string) ($_POST['unit'] ?? ''));
    } elseif ($action === 'field_rename') {
        $result = field_rename($id, $name);
    } elseif ($action === 'field_up' || $action === 'field_down') {
        field_reorder($garmentId, $id, $action === 'field_up' ? 'up' : 'down');
    } elseif ($action === 'field_deactivate') {
        field_set_active($id, false);
    } elseif ($action === 'field_activate') {
        field_set_active($id, true);
    } elseif ($action === 'field_remove') {
        $result = field_remove($id);
    } elseif ($action === 'field_order') {
        $ids = $_POST['ids'] ?? [];
        field_order($garmentId, is_array($ids) ? $ids : []);
    }
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    $back = '/settings/garments';
    if (is_uuid($garmentId) && $action !== 'garment_remove') {
        $back .= '/' . $garmentId;
    }
    redirect($back);
}
