<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/routes.php';

router_dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
