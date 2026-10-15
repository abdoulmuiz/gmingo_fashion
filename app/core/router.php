<?php

declare(strict_types=1);

function route(string $method, string $pattern, callable $handler): void
{
    $GLOBALS['gmingo_routes'][] = [strtoupper($method), $pattern, $handler];
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
        $handler($params);
        return;
    }
    http_response_code(404);
    render('simple', [
        'title' => t('not_found'),
        'body' => t('not_found_body'),
    ]);
}
