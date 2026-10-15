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
        <?php require $view_file; ?>
    </main>
    <?php if ($showNav): ?>
        <nav class="bottom-nav" aria-label="<?= e(t('main_menu')) ?>">
            <a class="<?= e(nav_active('/')) ?>" href="<?= e(url('/')) ?>"><?= e(t('home')) ?></a>
            <a class="<?= e(nav_active('/orders')) ?>" href="<?= e(url('/orders')) ?>"><?= e(t('orders')) ?></a>
            <a class="<?= e(nav_active('/board')) ?>" href="<?= e(url('/board')) ?>"><?= e(t('board')) ?></a>
            <a class="<?= e(nav_active('/customers')) ?>" href="<?= e(url('/customers')) ?>"><?= e(t('customers')) ?></a>
            <a class="<?= e(nav_active('/more')) ?>" href="<?= e(url('/more')) ?>"><?= e(t('more')) ?></a>
        </nav>
    <?php endif; ?>
</body>
</html>
