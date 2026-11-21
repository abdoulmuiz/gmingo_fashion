<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/branches.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function branches_index(): void
{
    require_access('/branches');
    render('branches/index', [
        'title' => t('branches'),
        'branches' => branch_list(false),
        'errors' => [],
        'old' => [],
    ]);
}

function branches_create_submit(): void
{
    require_access('/branches');
    csrf_check();
    $result = branch_create($_POST);
    if (isset($result['errors'])) {
        render('branches/index', [
            'title' => t('branches'),
            'branches' => branch_list(false),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('branch_created'));
    redirect('/branches');
}

function branches_deactivate_submit(string $branchId): void
{
    require_access('/branches');
    csrf_check();
    $result = branch_deactivate($branchId);
    if (isset($result['errors'])) {
        flash_set('error', t('not_found'));
        redirect('/branches');
    }
    flash_set('success', t('branch_deactivated'));
    redirect('/branches');
}
