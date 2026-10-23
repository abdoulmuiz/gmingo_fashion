<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('html_errors', '0');

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/routes.php';

errors_install();
router_dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
