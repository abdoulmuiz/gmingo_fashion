<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/purchasing.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function expenses_index(): void
{
    require_access('/expenses');
    render('expenses/index', [
        'title' => t('expenses'),
        'expenses' => expense_list(),
        'errors' => [],
        'old' => [],
    ]);
}

function expenses_create_submit(): void
{
    require_access('/expenses');
    csrf_check();
    $result = expense_create($_POST);
    if (isset($result['errors'])) {
        render('expenses/index', [
            'title' => t('expenses'),
            'expenses' => expense_list(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('expense_created'));
    redirect('/expenses');
}
