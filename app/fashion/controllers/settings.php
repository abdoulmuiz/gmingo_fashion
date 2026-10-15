<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/modules.php';

function owner_only(): void
{
    require_login();
    $rows = db_select('fashion_users', ['id' => (string) current_user_id()]);
    if ($rows === [] || (string) $rows[0]['role'] !== 'owner') {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
}

function settings_form(): void
{
    owner_only();
    $tenant = db_select('fashion_tenants', ['id' => (string) current_tenant_id()]);
    render('settings', [
        'title' => t('settings'),
        'company_type' => (string) ($tenant[0]['company_type'] ?? ''),
        'modules' => db_select('fashion_tenant_modules', [], ['order' => 'module_key ASC']),
    ]);
}

function settings_submit(): void
{
    owner_only();
    csrf_check();
    $posted = $_POST['modules'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    module_save($posted);
    flash_set('success', t('saved'));
    redirect('/settings');
}
