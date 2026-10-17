<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $view_file */
$current = request_path();
$showNav = !in_array($current, ['/login', '/register', '/pay/return'], true);
$navItems = [];
if ($showNav) {
    $nav = [
        ['/', 'home', null],
        ['/orders', 'orders', 'fashion.orders'],
        ['/board', 'board', 'fashion.production'],
        ['/customers', 'customers', 'fashion.orders'],
        ['/outstanding', 'balances', 'fashion.finance'],
        ['/more', 'more', null],
    ];
    foreach ($nav as [$path, $label, $module]) {
        if ($module !== null && !module_enabled($module)) {
            continue;
        }
        $navItems[] = [$path, $label];
    }
}
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(t('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/assets/app.css')) ?>">
</head>
<body class="<?= $showNav ? 'has-nav' : 'is-guest' ?>">
    <div class="shell">
        <header class="site-header">
            <div class="utility">
                <div class="utility-inner">
                    <span><?= e(t('app_kicker')) ?></span>
                    <span class="utility-note"><?= e(t('home_body')) ?></span>
                </div>
            </div>
            <div class="mast">
                <div class="mast-inner">
                    <a class="brand" href="<?= e(url('/')) ?>">
                        <span class="brand-mark" aria-hidden="true">G</span>
                        <span><?= e(t('app_name')) ?></span>
                    </a>
                    <?php if (!$showNav): ?>
                        <div class="mast-actions">
                            <a class="btn btn-quiet btn-small" href="<?= e(url('/login')) ?>"><?= e(t('login')) ?></a>
                            <a class="btn btn-small" href="<?= e(url('/register')) ?>"><?= e(t('register')) ?></a>
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
                <main class="app">
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
