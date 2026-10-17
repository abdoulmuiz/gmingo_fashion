<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/modules.php';
require_once dirname(__DIR__, 2) . '/domain/deposits.php';
require_once dirname(__DIR__, 2) . '/domain/tracking.php';

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
    $deposit = deposit_shop();
    render('settings', [
        'title' => t('settings'),
        'company_type' => (string) ($tenant[0]['company_type'] ?? ''),
        'modules' => db_select('fashion_tenant_modules', [], ['order' => 'module_key ASC']),
        'deposit_percent' => $deposit['percent'],
        'deposit_stage_id' => (string) ($deposit['stage_id'] ?? ''),
        'deposit_stages' => deposit_stage_choices(),
        'payment_channel' => (string) ($tenant[0]['payment_channel'] ?? ''),
        'payment_bank' => (string) ($tenant[0]['payment_bank'] ?? ''),
        'payment_account' => (string) ($tenant[0]['payment_account'] ?? ''),
        'payment_reference_note' => (string) ($tenant[0]['payment_reference_note'] ?? ''),
    ]);
}

function settings_submit(): void
{
    owner_only();
    csrf_check();
    if ((string) ($_POST['action'] ?? '') === 'deposit') {
        $result = deposit_settings_save(
            (string) ($_POST['deposit_percent'] ?? ''),
            (string) ($_POST['deposit_stage_id'] ?? '')
        );
        if (isset($result['error'])) {
            flash_set('error', t((string) $result['error']));
        } else {
            flash_set('success', t('saved'));
        }
        redirect('/settings');
    }
    if ((string) ($_POST['action'] ?? '') === 'payment_details') {
        $result = tracking_payment_save(
            (string) ($_POST['payment_channel'] ?? ''),
            (string) ($_POST['payment_bank'] ?? ''),
            (string) ($_POST['payment_account'] ?? ''),
            (string) ($_POST['payment_reference_note'] ?? '')
        );
        if (isset($result['error'])) {
            flash_set('error', t((string) $result['error']));
        } else {
            flash_set('success', t('saved'));
        }
        redirect('/settings');
    }
    $posted = $_POST['modules'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    module_save($posted);
    flash_set('success', t('saved'));
    redirect('/settings');
}
