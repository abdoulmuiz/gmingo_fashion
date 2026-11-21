<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/staff.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function staff_index(): void
{
    require_access('/staff');
    render('staff/index', [
        'title' => t('staff'),
        'people' => staff_list(true),
    ]);
}

function staff_create_show(array $errors = [], array $old = []): void
{
    require_access('/staff');
    render('staff/form', [
        'title' => t('staff_add'),
        'mode' => 'create',
        'person' => null,
        'roles' => staff_assignable_roles(),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function staff_create_submit(): void
{
    require_access('/staff');
    csrf_check();
    $result = staff_create([
        'name' => (string) ($_POST['name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
        'role' => (string) ($_POST['role'] ?? 'staff'),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'started_on' => (string) ($_POST['started_on'] ?? ''),
    ]);
    if (isset($result['errors'])) {
        staff_create_show($result['errors'], $_POST);
        return;
    }
    flash_set('success', t('staff_created'));
    redirect('/staff/' . $result['user']['id']);
}

function staff_edit_show(string $userId, array $errors = [], array $old = []): void
{
    require_access('/staff');
    $person = staff_get($userId);
    if ($person === null) {
        throw new HttpException(t('not_found'), 404);
    }
    render('staff/form', [
        'title' => t('staff_edit'),
        'mode' => 'edit',
        'person' => $person,
        'roles' => $person['role'] === 'owner' ? ['owner'] : staff_assignable_roles(),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function staff_edit_submit(string $userId): void
{
    require_access('/staff');
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'deactivate') {
        $result = staff_deactivate($userId);
        if (isset($result['errors'])) {
            staff_edit_show($userId, $result['errors']);
            return;
        }
        flash_set('success', t('staff_deactivated'));
        redirect('/staff/' . $userId);
    }
    if ($action === 'reactivate') {
        $result = staff_reactivate($userId);
        if (isset($result['errors'])) {
            staff_edit_show($userId, $result['errors']);
            return;
        }
        flash_set('success', t('staff_reactivated'));
        redirect('/staff/' . $userId);
    }
    $result = staff_update($userId, [
        'name' => (string) ($_POST['name'] ?? ''),
        'role' => (string) ($_POST['role'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'started_on' => (string) ($_POST['started_on'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
    ]);
    if (isset($result['errors'])) {
        staff_edit_show($userId, $result['errors'], $_POST);
        return;
    }
    flash_set('success', t('staff_updated'));
    redirect('/staff/' . $userId);
}
