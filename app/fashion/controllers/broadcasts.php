<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/broadcasts.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function broadcasts_index(): void
{
    require_access('/broadcasts');
    render('broadcasts/index', [
        'title' => t('broadcasts'),
        'broadcasts' => broadcast_list(),
        'errors' => [],
        'old' => [],
    ]);
}

function broadcasts_create_submit(): void
{
    require_access('/broadcasts');
    csrf_check();
    $result = broadcast_create($_POST);
    if (isset($result['errors'])) {
        render('broadcasts/index', [
            'title' => t('broadcasts'),
            'broadcasts' => broadcast_list(),
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    flash_set('success', t('broadcast_created'));
    redirect('/broadcasts');
}
