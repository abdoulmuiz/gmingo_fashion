<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/routes.php';

try {
    router_dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
} catch (RedirectException $e) {
    exit;
} catch (HttpException $e) {
    http_response_code($e->status());
    header('Content-Type: text/html; charset=utf-8');
    echo e($e->getMessage());
}
