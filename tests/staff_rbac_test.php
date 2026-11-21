<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/staff.php';
require_once dirname(__DIR__) . '/app/domain/access.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run staff RBAC tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

function module_keys_enabled(): array
{
    $on = [];
    foreach (db_select('fashion_tenant_modules') as $row) {
        if ((int) $row['enabled'] === 1) {
            $on[] = (string) $row['module_key'];
        }
    }
    return $on;
}

function staff_visit(string $method, string $path, array $post = []): array
{
    http_response_code(200);
    $_GET = [];
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = url($path);
    $level = ob_get_level();
    ob_start();
    try {
        router_dispatch($method, $path);
        $body = (string) ob_get_clean();
        $status = http_response_code();
        return [
            'kind' => 'page',
            'status' => is_int($status) ? $status : 200,
            'body' => $body,
            'location' => '',
        ];
    } catch (RedirectException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'redirect', 'status' => 302, 'body' => '', 'location' => $e->path()];
    } catch (HttpException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => $e->status(), 'body' => $e->getMessage(), 'location' => ''];
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => 500, 'body' => $e->getMessage(), 'location' => ''];
    }
}

$shop = business_register([
    'business_name' => 'RBAC Shop',
    'owner_name' => 'Owner RBAC',
    'email' => 'rbac-owner@example.com',
    'password' => 'password',
    'phone' => '08035550001',
    'company_type' => 'factory',
]);
check(isset($shop['user_id']), 'rbac shop registers');
module_save(array_merge(module_keys_enabled(), ['fashion.staff']));

check(user_can('staff.manage') === true, 'owner can manage staff');
check(user_can('billing.manage') === true, 'owner can manage billing');
check(can_access('/staff') === true, 'owner can access staff page');
check(can_access('/settings') === true, 'owner can access settings');

$created = staff_create([
    'name' => 'Manager Mai',
    'email' => 'rbac-manager@example.com',
    'password' => 'password1',
    'role' => 'manager',
    'phone' => '08035550002',
    'started_on' => '2026-01-15',
]);
check(isset($created['user']['id']), 'owner can create a manager');
$managerId = (string) $created['user']['id'];
check_eq($created['user']['role'], 'manager', 'created role is manager');
check_eq($created['user']['phone'], phone_normalize('08035550002'), 'staff phone is stored');

$tailor = staff_create([
    'name' => 'Tailor Tunde',
    'email' => 'rbac-tailor@example.com',
    'password' => 'password1',
    'role' => 'tailor',
    'phone' => '',
    'started_on' => '',
]);
check(isset($tailor['user']['id']), 'owner can create a tailor');
$tailorId = (string) $tailor['user']['id'];

$listPage = staff_visit('GET', '/staff');
check($listPage['kind'] === 'page' && $listPage['status'] === 200, 'owner opens staff list');
check(str_contains($listPage['body'], 'Manager Mai'), 'staff list shows manager');

auth_logout();
check(login_user('rbac-manager@example.com', 'password1') === null, 'manager signs in');
check(user_can('staff.manage') === true, 'manager can manage staff');
check(user_can('settings.manage') === true, 'manager can manage settings');
check(user_can('billing.manage') === false, 'manager cannot manage billing');
check(can_access('/settings') === true, 'manager can open settings');
check(can_access('/account/subscription') === true, 'manager can view subscription page');
$billingPost = staff_visit('POST', '/account/subscription', [
    'csrf' => csrf_token(),
    'action' => 'renew',
]);
check($billingPost['kind'] === 'error' && $billingPost['status'] === 403, 'manager cannot renew subscription');

auth_logout();
check(login_user('rbac-tailor@example.com', 'password1') === null, 'tailor signs in');
check(user_can('production.board') === true, 'tailor can use production board');
check(user_can('finance.view') === false, 'tailor cannot view finance');
check(can_access('/board') === true, 'tailor can open board');
check(can_access('/outstanding') === false, 'tailor cannot open balances');
check(can_access('/staff') === false, 'tailor cannot open staff');
check(can_access('/settings') === false, 'tailor cannot open settings');
$staffDenied = staff_visit('GET', '/staff');
check($staffDenied['kind'] === 'error' && $staffDenied['status'] === 403, 'tailor staff page returns 403');

auth_logout();
check(login_user('rbac-owner@example.com', 'password') === null, 'owner signs in again');
$deactivated = staff_deactivate($tailorId);
check(($deactivated['user']['active'] ?? true) === false, 'owner can deactivate tailor');
$logs = db_select('fashion_activity_log', ['action' => 'staff_deactivated'], ['limit' => 5]);
check($logs !== [], 'deactivation is activity-logged');

auth_logout();
check(login_user('rbac-tailor@example.com', 'password1') === 'login_failed', 'deactivated tailor cannot sign in');

auth_logout();
check(login_user('rbac-owner@example.com', 'password') === null, 'owner returns');
$self = staff_deactivate((string) $shop['user_id']);
check(isset($self['errors']['form']), 'owner cannot deactivate self');
check_eq($self['errors']['form'] ?? '', 'cannot_deactivate_self', 'self-deactivate error key');

$restored = staff_reactivate($tailorId);
check(($restored['user']['active'] ?? false) === true, 'owner can reactivate tailor');

$promoted = staff_update($managerId, [
    'name' => 'Manager Mai',
    'role' => 'staff',
    'phone' => '08035550002',
    'started_on' => '2026-01-15',
    'password' => '',
]);
check_eq($promoted['user']['role'] ?? '', 'staff', 'manager can be changed to staff');

check(in_array('settings.manage', staff_role_permissions('manager'), true), 'manager permissions include settings');
check(!in_array('billing.manage', staff_role_permissions('manager'), true), 'manager permissions exclude billing');
check(staff_role_permissions('tailor') === ['orders.manage', 'production.board'], 'tailor permissions are limited');
