<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/reports.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function reports_index(): void
{
    require_access('/reports');
    render('reports/index', [
        'title' => t('reports'),
        'summary' => reports_summary(),
    ]);
}
