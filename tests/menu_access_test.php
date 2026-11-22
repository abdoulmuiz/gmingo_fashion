<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run menu access tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();
// Keep wall-clock / prior suite gmingo_now from expiring the fresh trial mid-run.
$GLOBALS['gmingo_now'] = '2026-11-22 12:00:00';

$shop = business_register([
    'business_name' => 'Menu Access',
    'owner_name' => 'Amina Bello',
    'email' => 'menu-access@example.com',
    'password' => 'password',
    'phone' => '08039990001',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'menu access shop is created');

function menu_visit(string $path): array
{
    http_response_code(200);
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = url($path);
    $level = ob_get_level();
    ob_start();
    try {
        router_dispatch('GET', $path);
        $body = (string) ob_get_clean();
        $status = http_response_code();
        return [
            'kind' => 'page',
            'status' => is_int($status) ? $status : 200,
            'body' => $body,
        ];
    } catch (RedirectException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'redirect', 'status' => 302, 'body' => ''];
    } catch (HttpException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => $e->status(), 'body' => ''];
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => 500, 'body' => $e->getMessage()];
    }
}

function menu_href_present(string $html, string $path): bool
{
    return str_contains($html, 'href="' . url($path) . '"');
}

$modules = module_keys();
$roles = ['owner', 'staff'];
$expected = count($roles) * (1 << count($modules));
$seen = 0;
$problems = [];
$userId = (string) $shop['user_id'];

for ($mask = 0; $mask < (1 << count($modules)); $mask++) {
    $enabled = [];
    foreach ($modules as $index => $key) {
        if (($mask & (1 << $index)) !== 0) {
            $enabled[] = $key;
        }
    }
    db_update('fashion_users', ['role' => 'owner'], ['id' => $userId]);
    module_save($enabled);
    foreach ($roles as $role) {
        db_update('fashion_users', ['role' => $role], ['id' => $userId]);
        $seen++;
        $html = '';
        $opened = [];
        foreach (['/', '/more'] as $path) {
            $opened[$path] = menu_visit($path);
            $html .= $opened[$path]['body'];
        }
        if (menu_href_present($html, '/settings')) {
            $opened['/settings'] = menu_visit('/settings');
            $html .= $opened['/settings']['body'];
        }
        foreach (menu_catalog() as $item) {
            $path = $item['path'];
            $visible = menu_href_present($html, $path);
            if ($visible !== can_access($path)) {
                $problems[] = $role . ' ' . implode(',', $enabled) . ' ' . $path . ' link and can_access disagree';
            }
            $result = $opened[$path] ?? menu_visit($path);
            if ($visible) {
                $ok = $result['kind'] === 'redirect' || ($result['kind'] === 'page' && $result['status'] === 200);
                if (!$ok) {
                    $detail = $result['body'] !== '' ? ' ' . $result['body'] : '';
                    $problems[] = $role . ' ' . implode(',', $enabled) . ' visible ' . $path . ' returned ' . (string) $result['status'] . $detail;
                }
            } else {
                $denial = access_denial($path);
                $okBlocked = $result['status'] === 403
                    || ($denial === 'subscription' && $result['kind'] === 'redirect');
                if (!$okBlocked) {
                    $problems[] = $role . ' ' . implode(',', $enabled) . ' hidden ' . $path . ' returned ' . (string) $result['status'];
                }
            }
            if (count($problems) >= 8) {
                break 3;
            }
        }
    }
}

check($problems === [], 'every visible menu link returns 200 or a redirect and every hidden menu link returns 403 (' . implode('; ', $problems) . ')');
check_eq($seen, $expected, 'every role and module combination was checked');
