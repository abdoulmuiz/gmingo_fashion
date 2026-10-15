<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/payment_settings.php';

function payment_settings_form(): void
{
    require_module('fashion.finance');
    payment_settings_owner();
    render('payment_settings', [
        'title' => t('payment_settings'),
        'settings' => payment_settings_view(),
        'online_payments' => module_enabled('fashion.online_payments'),
    ]);
}

function payment_settings_submit(): void
{
    require_module('fashion.finance');
    payment_settings_owner();
    csrf_check();
    $result = payment_settings_save(
        (string) ($_POST['mode'] ?? ''),
        (string) ($_POST['public_key'] ?? ''),
        (string) ($_POST['secret_key'] ?? '')
    );
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('payment_mode_saved'));
    }
    redirect('/settings/payments');
}
