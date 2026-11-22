<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/stages.php';
require_once __DIR__ . '/settings.php';

function board_show(): void
{
    require_module('fashion.production');
    $filter = stage_board_normalize_filter((string) ($_GET['filter'] ?? 'all'));
    $q = trim((string) ($_GET['q'] ?? ''));
    $board = stage_board(['filter' => $filter, 'q' => $q]);
    render('board', [
        'title' => t('board_title'),
        'orders_off' => $board['orders_off'],
        'groups' => $board['groups'],
        'summary' => $board['summary'],
        'filter' => $board['filter'],
        'query' => $board['q'],
        'total_cards' => $board['total_cards'],
        'can_move' => module_enabled('fashion.production'),
        'csrf' => csrf_token(),
        'stage_move_base' => url('/orders'),
        'board_path' => stage_board_path($filter, $q),
    ]);
}

function stage_move_submit(string $id): void
{
    require_module('fashion.production');
    csrf_check();
    $result = stage_advance($id);
    $wantsJson = (string) ($_POST['ajax'] ?? '') === '1'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $returnTo = stage_move_return_path((string) ($_POST['return_to'] ?? ''));
    if (isset($result['error'])) {
        $message = stage_result_message($result);
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'error' => (string) ($result['error'] ?? 'error'),
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        flash_set('error', $message);
    } else {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'stage_id' => (string) ($result['stage_id'] ?? ''),
                'code' => (string) ($result['code'] ?? ''),
                'message' => t('moved'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        flash_set('success', t('moved'));
    }
    redirect($returnTo !== null ? $returnTo : '/orders/' . $id);
}

function stage_move_return_path(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || str_contains($raw, '://') || str_contains($raw, '//') || str_contains($raw, "\n") || str_contains($raw, "\r")) {
        return null;
    }
    if (preg_match('#^/board(?:\?[A-Za-z0-9_&=%\+\.\-]*)?$#', $raw) === 1) {
        return $raw;
    }
    if (preg_match('#^/orders/[0-9a-fA-F-]{36}$#', $raw) === 1) {
        return $raw;
    }
    return null;
}

function deposit_override_submit(string $id): void
{
    require_module('fashion.production');
    csrf_check();
    $result = deposit_override($id, (string) ($_POST['reason'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', stage_result_message($result));
    } else {
        flash_set('success', t('deposit_override_saved'));
    }
    redirect('/orders/' . $id);
}

function stage_result_message(array $result): string
{
    if (($result['error'] ?? '') === 'deposit_short') {
        return t('deposit_short', [
            'due' => money((int) $result['due_kobo']),
            'paid' => money((int) $result['paid_kobo']),
            'needed' => money((int) $result['needed_kobo']),
        ]);
    }
    return t((string) $result['error']);
}

function stages_form(): void
{
    require_module('fashion.production');
    owner_only();
    render('stages', [
        'title' => t('stages_settings'),
        'stages' => stage_definitions(),
    ]);
}

function stages_submit(): void
{
    require_module('fashion.production');
    owner_only();
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    $name = (string) ($_POST['name'] ?? '');
    $result = [];
    if ($action === 'add') {
        $result = stage_add($name);
    } elseif ($action === 'rename') {
        $result = stage_rename($id, $name);
    } elseif ($action === 'up' || $action === 'down') {
        stage_reorder($id, $action);
    } elseif ($action === 'deactivate') {
        stage_set_active($id, false);
    } elseif ($action === 'activate') {
        stage_set_active($id, true);
    } elseif ($action === 'roles') {
        $posted = $_POST['roles'] ?? [];
        $result = stage_save_roles(is_array($posted) ? $posted : []);
    }
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/settings/stages');
}
