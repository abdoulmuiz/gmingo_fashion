<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/stages.php';
require_once __DIR__ . '/settings.php';

function board_show(): void
{
    require_module('fashion.production');
    $board = stage_board();
    render('board', [
        'title' => t('board'),
        'orders_off' => $board['orders_off'],
        'groups' => $board['groups'],
    ]);
}

function stage_move_submit(string $id): void
{
    require_module('fashion.production');
    csrf_check();
    $result = stage_advance($id);
    if (isset($result['error'])) {
        flash_set('error', stage_result_message($result));
    } else {
        flash_set('success', t('moved'));
    }
    redirect('/orders/' . $id);
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
