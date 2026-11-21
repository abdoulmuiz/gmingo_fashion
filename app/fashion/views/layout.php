<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/access.php';
require_once dirname(__DIR__, 2) . '/domain/platform.php';

/** @var string $title */
/** @var string $view_file */
$current = request_path();
$isPlatformAdmin = current_user_id() !== null && is_platform_admin();
$showNav = !in_array($current, ['/login', '/register', '/onboarding', '/pay/return'], true)
    && !str_starts_with($current, '/onboarding/')
    && !str_starts_with($current, '/shop/');
$navItems = $showNav
    ? ($isPlatformAdmin
        ? [
            ['/platform', 'platform_home'],
            ['/platform/tenants', 'platform_tenants'],
            ['/platform/plans', 'platform_plans'],
        ]
        : menu_links('nav'))
    : [];
$brandHref = $isPlatformAdmin ? '/platform' : '/';
$isAuthGuest = in_array($current, ['/login', '/register'], true);
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(t('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/assets/app.css')) ?>">
    <?php
    $tailwindCss = dirname(__DIR__, 3) . '/public/assets/tailwind.css';
    if (is_file($tailwindCss)):
    ?>
    <link rel="stylesheet" href="<?= e(url('/assets/tailwind.css')) ?>?v=<?= e((string) filemtime($tailwindCss)) ?>">
    <?php endif; ?>
</head>
<body class="<?= $showNav ? 'has-nav' : 'is-guest' ?>">
    <div class="shell">
        <header class="site-header">
            <div class="utility">
                <div class="utility-inner">
                    <span class="utility-brand"><?= e(t('app_kicker')) ?></span>
                    <span class="utility-note"><?= e($isPlatformAdmin ? t('platform_home_help') : t('home_body')) ?></span>
                </div>
            </div>
            <div class="mast">
                <div class="mast-inner">
                    <a class="brand" href="<?= e(url($brandHref)) ?>">
                        <img
                            class="brand-logo"
                            src="<?= e(url('/assets/gmingo-logo.jpg')) ?>"
                            alt="<?= e(t('company_name')) ?>"
                            width="180"
                            height="48"
                        >
                        <span class="brand-product"><?= e(t('app_product')) ?></span>
                    </a>
                    <?php if (!$showNav): ?>
                        <div class="mast-actions">
                            <a class="btn btn-quiet btn-small<?= $current === '/login' ? ' is-current' : '' ?>" href="<?= e(url('/login')) ?>"><?= e(t('login')) ?></a>
                            <a class="btn btn-small<?= $current === '/register' ? ' is-current' : '' ?>" href="<?= e(url('/register')) ?>"><?= e(t('register')) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <div class="frame">
            <?php if ($showNav): ?>
                <nav class="bottom-nav" aria-label="<?= e(t('main_menu')) ?>">
                    <?php foreach ($navItems as [$path, $label]): ?>
                        <a class="<?= e(nav_active($path)) ?>" href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="column">
                <main class="app<?= $isAuthGuest ? ' app-guest' : '' ?>">
                    <?php $flashSuccess = flash_get('success'); ?>
                    <?php $flashError = flash_get('error'); ?>
                    <?php if ($flashSuccess !== null): ?>
                        <p class="flash" role="status"><?= e($flashSuccess) ?></p>
                    <?php endif; ?>
                    <?php if ($flashError !== null): ?>
                        <p class="flash flash-error" role="alert"><?= e($flashError) ?></p>
                    <?php endif; ?>
                    <?php require $view_file; ?>
                </main>
                <footer class="site-footer">
                    <div class="footer-inner">
                        <div class="footer-brand">
                            <img
                                class="footer-logo"
                                src="<?= e(url('/assets/gmingo-logo.jpg')) ?>"
                                alt="<?= e(t('company_name')) ?>"
                                width="140"
                                height="38"
                            >
                            <strong><?= e(t('app_name')) ?></strong>
                            <p><?= e(t('home_title')) ?></p>
                        </div>
                        <?php if ($navItems !== []): ?>
                            <nav class="footer-nav" aria-label="<?= e(t('main_menu')) ?>">
                                <?php foreach ($navItems as [$path, $label]): ?>
                                    <a href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a>
                                <?php endforeach; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</body>
</html>
