<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $view_file */
$current = request_path();
$showNav = !in_array($current, ['/login', '/register'], true);
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(t('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= e(url('/')) ?>"><?= e(t('app_name')) ?></a>
    </header>
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
    <?php if ($showNav): ?>
        <nav class="bottom-nav" aria-label="<?= e(t('main_menu')) ?>">
            <?php
            $nav = [
                ['/', 'home', null],
                ['/orders', 'orders', 'fashion.orders'],
                ['/board', 'board', 'fashion.production'],
                ['/customers', 'customers', 'fashion.orders'],
                ['/more', 'more', null],
            ];
            foreach ($nav as [$path, $label, $module]) {
                if ($module !== null && !module_enabled($module)) {
                    continue;
                }
                ?>
            <a class="<?= e(nav_active($path)) ?>" href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a>
                <?php
            }
            ?>
        </nav>
    <?php endif; ?>
</body>
</html>
