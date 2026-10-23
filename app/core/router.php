<?php

declare(strict_types=1);

function route(string $method, string $pattern, callable $handler): void
{
    $GLOBALS['gmingo_routes'][] = [strtoupper($method), $pattern, $handler];
}

function route_defined(string $method, string $pattern): bool
{
    $method = strtoupper($method);
    foreach ($GLOBALS['gmingo_routes'] ?? [] as $route) {
        if ($route[0] === $method && $route[1] === $pattern) {
            return true;
        }
    }
    return false;
}

function router_dispatch(string $method, string $path): void
{
    $method = strtoupper($method);
    $path = $path === '' ? '/' : $path;
    $accepted = [$method];
    if ($method === 'HEAD') {
        $accepted[] = 'GET';
    }
    $routes = $GLOBALS['gmingo_routes'] ?? [];
    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if (!in_array($routeMethod, $accepted, true)) {
            continue;
        }
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (!preg_match($regex, $path, $matches)) {
            continue;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        if ($method === 'POST' && !str_starts_with($pattern, '/webhooks/')) {
            csrf_check();
        }
        $handler($params);
        return;
    }
    require_login();
    http_response_code(404);
    render('simple', [
        'title' => t('not_found'),
        'body' => t('not_found_body'),
    ]);
}
