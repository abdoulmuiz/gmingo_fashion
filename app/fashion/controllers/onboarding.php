<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/onboarding.php';

function onboarding_require_owner(): void
{
    require_login();
    if (!onboarding_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
}

function onboarding_show(): void
{
    onboarding_require_owner();
    $state = onboarding_state();
    if (!$state['incomplete']) {
        redirect('/');
    }
    render('onboarding/wizard', [
        'title' => t('onboarding_title'),
        'state' => $state,
        'errors' => [],
        'old' => [],
    ]);
}

function onboarding_submit(): void
{
    onboarding_require_owner();
    csrf_check();
    $state = onboarding_state();
    if (!$state['incomplete']) {
        redirect('/');
    }
    $step = (string) ($state['step'] ?? 'business');
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'skip_all') {
        onboarding_skip();
        flash_set('success', t('onboarding_skipped'));
        redirect('/');
    }
    $result = ['ok' => true];
    if ($step === 'business') {
        $result = onboarding_save_business($_POST);
    } elseif ($step === 'contact') {
        $result = onboarding_save_contact($_POST);
    } elseif ($step === 'branding') {
        $result = onboarding_save_branding($_POST, $_FILES['logo'] ?? null);
    } elseif ($step === 'preferences') {
        $result = onboarding_save_preferences($_POST);
    } elseif ($step === 'staff') {
        $result = onboarding_save_staff($_POST);
    } elseif ($step === 'inventory') {
        $result = onboarding_save_inventory($_POST);
    } elseif ($step === 'subscription') {
        $result = onboarding_confirm_subscription();
    }
    if (isset($result['errors'])) {
        $state = onboarding_state();
        render('onboarding/wizard', [
            'title' => t('onboarding_title'),
            'state' => $state,
            'errors' => $result['errors'],
            'old' => $_POST,
        ]);
        return;
    }
    $state = onboarding_state();
    if (!$state['incomplete']) {
        flash_set('success', t('onboarding_done'));
        redirect('/');
    }
    redirect('/onboarding');
}

function onboarding_logo_show(): void
{
    onboarding_require_owner();
    $full = onboarding_logo_absolute();
    if ($full === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $mime = $types[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($full));
    header('Cache-Control: private, max-age=3600');
    readfile($full);
    exit;
}

function post_login_path(): string
{
    require_once dirname(__DIR__, 2) . '/domain/platform.php';
    if (is_platform_admin()) {
        return '/platform';
    }
    require_once dirname(__DIR__, 2) . '/domain/onboarding.php';
    if (onboarding_is_owner() && onboarding_incomplete()) {
        return '/onboarding';
    }
    return '/';
}
