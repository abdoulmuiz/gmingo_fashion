<?php

declare(strict_types=1);

/**
 * Walk the running site with curl. Creates only shops named "ZZ Regression …".
 * Does not change application code. Prints PASS or FAIL for each check.
 */

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "FAIL  config  config/config.php is missing\n");
    exit(1);
}
$config = require $configFile;
if (!is_array($config)) {
    fwrite(STDERR, "FAIL  config  config/config.php is unreadable\n");
    exit(1);
}
$base = rtrim((string) ($config['base_path'] ?? ''), '/');
if ($base === '' || $base[0] !== '/') {
    fwrite(STDERR, "FAIL  config  base_path is missing\n");
    exit(1);
}
$english = require $root . '/lang/en.php';
$hausa = require $root . '/lang/ha.php';
if (!is_array($english) || !is_array($hausa)) {
    fwrite(STDERR, "FAIL  lang  language files are unreadable\n");
    exit(1);
}

$runner = new RegressionRun($config, $base, $english, $hausa);
$failed = $runner->run();
exit($failed > 0 ? 1 : 0);

final class RegressionRun
{
    /** @var list<array{page: string, name: string, pass: bool, expected: string, found: string}> */
    private array $rows = [];

    private string $origin = '';

    private string $jar = '';

    private string $jarB = '';

    private string $activeJar = '';

    private bool $signedIn = false;

    private string $suffix = '';

    private string $business = '';

    private string $email = '';

    private string $password = 'password1';

    private string $customerName = '';

    private string $customerPhone = '';

    private string $customerId = '';

    private string $orderId = '';

    private string $orderNumber = '';

    private string $today = '';

    /** @var list<string> */
    private array $stagesSeen = [];

    private bool $reversed = false;

    private int $balanceKobo = 1500000;

    public function __construct(
        private array $config,
        private string $base,
        private array $english,
        private array $hausa,
    ) {
    }

    public function run(): int
    {
        $this->suffix = bin2hex(random_bytes(4));
        $this->business = 'ZZ Regression ' . $this->suffix;
        $this->email = 'zz-regression-' . $this->suffix . '@example.test';
        $this->customerName = 'ZZ Customer ' . $this->suffix;
        $this->customerPhone = '0801' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $zone = new DateTimeZone((string) ($this->config['timezone'] ?? 'Africa/Lagos'));
        $this->today = (new DateTimeImmutable('now', $zone))->format('Y-m-d');
        $dir = sys_get_temp_dir() . '/gmingo-regression-' . $this->suffix;
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            $this->add('setup', 'temp directory', false, 'a writable temp directory', 'mkdir failed');
            $this->printTable();
            return 1;
        }
        $this->jar = $dir . '/a.txt';
        $this->jarB = $dir . '/b.txt';
        $this->activeJar = $this->jar;
        $this->origin = $this->probe();
        if ($this->origin === '') {
            $this->add($this->base . '/login', 'Apache is reachable', false, 'HTTP 200 from /login', 'no working Apache origin');
            $this->printTable();
            return 1;
        }
        $this->publicChecks();
        $this->authChecks();
        $this->customerChecks();
        $this->orderChecks();
        $this->stageChecks();
        $this->paymentChecks();
        $this->measurementChecks();
        $this->settingsChecks();
        $this->dashboardChecks();
        $this->isolationChecks();
        $this->moduleChecks();
        return $this->printTable();
    }

    private function probe(): string
    {
        foreach (['http://127.0.0.1', 'http://localhost'] as $origin) {
            $response = $this->request('GET', '/login', null, $origin);
            if ($response['code'] === 200 && str_contains($response['body'], 'name="csrf"') && str_contains($response['body'], 'name="password"')) {
                return $origin;
            }
        }
        return '';
    }

    private function publicChecks(): void
    {
        $home = $this->request('GET', '/');
        $homeOk = in_array($home['code'], [200, 301, 302, 303], true);
        $this->add($this->url('/'), 'home loads', $homeOk, '200 or a redirect', 'status ' . $home['code']);
        $login = $this->request('GET', '/login');
        $this->add($this->url('/login'), 'login loads', $login['code'] === 200, '200', 'status ' . $login['code']);
        $this->fields($this->url('/login'), 'English login form', $login['body'], ['email', 'password', 'login'], ['name="email"', 'name="password"', 'name="lang"']);
        $register = $this->request('GET', '/register');
        $this->add($this->url('/register'), 'register loads', $register['code'] === 200, '200', 'status ' . $register['code']);
        $this->fields(
            $this->url('/register'),
            'English register form',
            $register['body'],
            ['business_name', 'owner_name', 'email', 'password', 'shop_phone', 'company_type', 'register'],
            ['name="business_name"', 'name="owner_name"', 'name="email"', 'name="password"', 'name="phone"', 'name="company_type"']
        );
        foreach (['/config/config.php', '/lang/en.php', '/.git/config', '/tools/', '/docs/'] as $path) {
            $blocked = $this->request('GET', $path);
            $ok = in_array($blocked['code'], [403, 404], true);
            $this->add($this->url($path), 'private path is blocked', $ok, '403 or 404', 'status ' . $blocked['code']);
        }
        $switched = $this->switchLanguage('ha', '/login');
        $hausaLogin = $this->request('GET', '/login');
        $this->add(
            $this->url('/login'),
            'language switch shows Hausa',
            $switched && $hausaLogin['code'] === 200 && str_contains($hausaLogin['body'], (string) $this->hausa['password']) && str_contains($hausaLogin['body'], 'lang="ha"'),
            'Hausa password label ' . $this->hausa['password'],
            $switched ? $this->snippet($hausaLogin['body']) : 'the language form did not redirect'
        );
        $this->fields(
            $this->url('/register'),
            'Hausa register form',
            $this->request('GET', '/register')['body'],
            ['business_name', 'owner_name', 'email', 'password', 'shop_phone', 'register'],
            ['name="business_name"', 'name="password"', 'name="phone"'],
            'ha'
        );
        $this->switchLanguage('en', '/login');
    }

    private function authChecks(): void
    {
        $page = $this->request('GET', '/register');
        $token = $this->csrf($page['body']);
        $signed = $this->submit('/register', [
            'csrf' => $token,
            'company_type' => 'tailor',
            'business_name' => $this->business,
            'owner_name' => 'Regression Owner',
            'email' => $this->email,
            'password' => $this->password,
            'phone' => '08011112222',
        ]);
        $landed = $signed['code'] === 200 && str_contains($signed['body'], $this->business) === false
            ? str_contains($signed['body'], 'Regression Owner') || str_contains($signed['final'], '/')
            : false;
        $ok = $signed['redirect'] && ($signed['code'] === 200) && (str_contains($signed['body'], 'Regression Owner') || str_contains($signed['body'], (string) $this->english['home_title']));
        $this->add($this->url('/register'), 'sign-up creates the shop', $ok, 'redirect to the dashboard', $this->snippet($signed['body']));
        $this->signedIn = $ok;
        if (!$this->signedIn) {
            return;
        }
        $home = $this->request('GET', '/');
        $this->add($this->url('/'), 'dashboard opens after sign-up', $home['code'] === 200, '200', 'status ' . $home['code']);

        $out = $this->submit('/logout', ['csrf' => $this->csrf($this->request('GET', '/more')['body'])]);
        $loggedOut = $out['redirect'] && str_contains($out['location'], '/login');
        $this->add($this->url('/logout'), 'logout returns to login', $loggedOut, 'redirect to /login', $out['location'] !== '' ? $out['location'] : 'status ' . $out['first']);
        $this->signedIn = false;

        $protected = $this->request('GET', '/customers');
        $toLogin = in_array($protected['code'], [301, 302, 303], true) && str_contains($protected['location'], '/login');
        $this->add($this->url('/customers'), 'logged-out protected page redirects to login', $toLogin, 'redirect to /login', 'status ' . $protected['code'] . ' ' . $protected['location']);

        $bad = $this->submit('/login', [
            'csrf' => $this->csrf($this->request('GET', '/login')['body']),
            'email' => $this->email,
            'password' => 'wrong-password',
        ]);
        $this->add(
            $this->url('/login'),
            'wrong password stays on login',
            $bad['code'] === 200 && str_contains($bad['body'], (string) $this->english['login_failed']),
            $this->english['login_failed'],
            $this->snippet($bad['body'])
        );
        $this->switchLanguage('ha', '/login');
        $badHa = $this->submit('/login', [
            'csrf' => $this->csrf($this->request('GET', '/login')['body']),
            'email' => $this->email,
            'password' => 'wrong-password',
        ]);
        $this->add(
            $this->url('/login'),
            'wrong password is explained in Hausa',
            $badHa['code'] === 200 && str_contains($badHa['body'], (string) $this->hausa['login_failed']),
            $this->hausa['login_failed'],
            $this->snippet($badHa['body'])
        );
        $this->switchLanguage('en', '/login');
        $in = $this->submit('/login', [
            'csrf' => $this->csrf($this->request('GET', '/login')['body']),
            'email' => $this->email,
            'password' => $this->password,
        ]);
        $this->signedIn = $in['redirect'] && $in['code'] === 200 && str_contains($in['body'], 'Regression Owner');
        $this->add($this->url('/login'), 'login opens the dashboard', $this->signedIn, 'redirect to the dashboard', $this->snippet($in['body']));
    }

    private function customerChecks(): void
    {
        if (!$this->gate('customers')) {
            return;
        }
        $form = $this->request('GET', '/customers/new');
        $this->fields(
            $this->url('/customers/new'),
            'English new customer form',
            $form['body'],
            ['name', 'phone', 'gender', 'female', 'male', 'type', 'regular', 'vip', 'save'],
            ['name="name"', 'name="phone"', 'name="gender"', 'name="customer_type"']
        );
        $this->switchLanguage('ha');
        $this->fields(
            $this->url('/customers/new'),
            'Hausa new customer form',
            $this->request('GET', '/customers/new')['body'],
            ['name', 'phone', 'gender', 'save'],
            ['name="name"', 'name="phone"'],
            'ha'
        );
        $this->switchLanguage('en');
        $saved = $this->submit('/customers', [
            'csrf' => $this->csrf($form['body']),
            'name' => $this->customerName,
            'phone' => $this->customerPhone,
            'gender' => 'female',
            'customer_type' => 'regular',
        ]);
        $this->customerId = $this->idFrom($saved['location'], '#/customers/([0-9a-f-]{36})#i');
        $created = $this->customerId !== '' && str_contains($saved['body'], $this->customerName);
        $this->add($this->url('/customers'), 'add customer', $created, 'redirect to the new profile', $saved['location'] !== '' ? $saved['location'] : $this->snippet($saved['body']));
        $found = $this->request('GET', '/customers?q=' . rawurlencode($this->customerName));
        $this->add(
            $this->url('/customers?q=' . rawurlencode($this->customerName)),
            'search finds the customer',
            $found['code'] === 200 && str_contains($found['body'], $this->customerName) && str_contains($found['body'], 'name="q"'),
            $this->customerName . ' and a search field',
            $this->snippet($found['body'])
        );
        $this->profileChecks($saved['body'] !== '' ? $saved['body'] : $this->request('GET', '/customers/' . $this->customerId)['body'], '₦0.00', '₦0.00', '0');
    }

    private function profileChecks(string $html, string $spent, string $owed, string $orders): void
    {
        $page = $this->url('/customers/' . $this->customerId);
        $labels = [
            $this->text('order_count'),
            $this->text('total_spent'),
            $this->text('amount_owed'),
            $this->text('call'),
            $this->text('whatsapp'),
            $this->text('new_order'),
        ];
        $missing = [];
        foreach ($labels as $label) {
            if (!str_contains($html, $label)) {
                $missing[] = $label;
            }
        }
        $numbers = str_contains($html, '>' . $orders . '</strong>') && str_contains($html, $spent) && str_contains($html, $owed);
        $links = str_contains($html, 'tel:+234') && str_contains($html, 'https://wa.me/234') && str_contains($html, '/orders/new?customer=');
        $this->add($page, 'profile shows orders, spent, owed, Call, WhatsApp and New order', $missing === [] && $numbers && $links, implode(', ', $labels) . " with {$orders}, {$spent}, {$owed}", $missing === [] ? $this->snippet($html) : 'missing ' . implode(', ', $missing));
    }

    private function orderChecks(): void
    {
        if (!$this->gate('orders') || $this->customerId === '') {
            return;
        }
        $form = $this->request('GET', '/orders/new');
        $this->fields(
            $this->url('/orders/new'),
            'English new order form',
            $form['body'],
            ['customer', 'due_date', 'urgent', 'add_items', 'garment', 'quantity', 'price', 'fabric', 'notes', 'save'],
            ['name="customer_id"', 'name="due_date"', 'name="urgent"', 'name="items[0][garment_type_id]"', 'name="items[1][quantity]"', 'name="items[1][price]"']
        );
        $garments = $this->garmentIds($form['body']);
        $this->add($this->url('/orders/new'), 'order form lists two garments', count($garments) >= 2, 'at least two garment choices', (string) count($garments) . ' garments');
        if (count($garments) < 2) {
            return;
        }
        $saved = $this->submit('/orders', [
            'csrf' => $this->csrf($form['body']),
            'customer_id' => $this->customerId,
            'due_date' => $this->today,
            'urgent' => '1',
            'items[0][garment_type_id]' => $garments[0],
            'items[0][quantity]' => '1',
            'items[0][price]' => '10000',
            'items[0][fabric]' => 'cotton',
            'items[0][notes]' => 'first piece',
            'items[1][garment_type_id]' => $garments[1],
            'items[1][quantity]' => '2',
            'items[1][price]' => '2500',
            'items[1][fabric]' => '',
            'items[1][notes]' => '',
        ]);
        $this->orderId = $this->idFrom($saved['location'], '#/orders/([0-9a-f-]{36})#i');
        $body = $saved['body'];
        if ($this->orderId !== '' && preg_match('/<h1>([^<]+)<\/h1>/', $body, $number)) {
            $this->orderNumber = trim(html_entity_decode($number[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $totalOk = str_contains($body, '>₦15,000.00</strong>') && str_contains($body, '>₦10,000.00</span>') && str_contains($body, '>₦5,000.00</span>');
        $urgentOk = $this->orderId !== '' && str_contains($body, (string) $this->english['urgent']);
        $this->add($this->url('/orders/' . $this->orderId), 'order saves two items and the total', $this->orderId !== '' && $totalOk, '₦15,000.00 total, ₦10,000.00 and ₦5,000.00 lines', $this->snippet($body));
        $this->add($this->url('/orders/' . $this->orderId), 'order shows the urgent flag', $urgentOk, $this->english['urgent'], $this->snippet($body));
        $this->switchLanguage('ha');
        $hausaOrder = $this->request('GET', '/orders/' . $this->orderId);
        $this->fields($this->url('/orders/' . $this->orderId), 'Hausa order page', $hausaOrder['body'], ['total', 'urgent', 'due_date'], [], 'ha');
        $this->switchLanguage('en');
        $expect = [
            'all' => true,
            'active' => true,
            'overdue' => false,
            'due_today' => true,
            'urgent' => true,
            'ready' => false,
            'delivered' => false,
            'unpaid' => true,
        ];
        $index = $this->request('GET', '/orders');
        $tabLabels = [];
        foreach (array_keys($expect) as $tab) {
            $key = $tab === 'unpaid' ? 'tab_unpaid' : $tab;
            $tabLabels[] = (string) $this->english[$key];
        }
        $missingTabs = array_values(array_filter($tabLabels, static fn (string $label): bool => !str_contains($index['body'], $label)));
        $this->add($this->url('/orders'), 'English filter tabs are on the list', $missingTabs === [], implode(', ', $tabLabels), $missingTabs === [] ? 'all tabs present' : 'missing ' . implode(', ', $missingTabs));
        $this->switchLanguage('ha');
        $hausaIndex = $this->request('GET', '/orders');
        $hausaLabels = [];
        foreach (array_keys($expect) as $tab) {
            $key = $tab === 'unpaid' ? 'tab_unpaid' : $tab;
            $hausaLabels[] = (string) $this->hausa[$key];
        }
        $missingHa = array_values(array_filter($hausaLabels, static fn (string $label): bool => !str_contains($hausaIndex['body'], $label)));
        $this->add($this->url('/orders'), 'Hausa filter tabs are on the list', $missingHa === [], implode(', ', $hausaLabels), $missingHa === [] ? 'all tabs present' : 'missing ' . implode(', ', $missingHa));
        $this->switchLanguage('en');
        foreach ($expect as $tab => $present) {
            $listed = $this->request('GET', '/orders?tab=' . $tab);
            $has = $this->orderNumber !== '' && str_contains($listed['body'], $this->orderNumber);
            $this->add($this->url('/orders?tab=' . $tab), 'tab ' . $tab . ' matches the order', $has === $present, $present ? $this->orderNumber : 'order absent', $has ? $this->orderNumber : 'order absent');
        }
        $search = $this->request('GET', '/orders?q=' . rawurlencode($this->customerName));
        $this->add(
            $this->url('/orders?q=' . rawurlencode($this->customerName)),
            'order search finds the customer',
            $search['code'] === 200 && $this->orderNumber !== '' && str_contains($search['body'], $this->orderNumber) && str_contains($search['body'], 'name="q"'),
            $this->orderNumber,
            $this->snippet($search['body'])
        );
        $profile = $this->request('GET', '/customers/' . $this->customerId);
        $this->profileChecks($profile['body'], '₦0.00', '₦15,000.00', '1');
    }

    private function stageChecks(): void
    {
        if (!$this->gate('stages')) {
            return;
        }
        if ($this->orderId === '') {
            $this->add($this->url('/orders'), 'stage checks', false, 'a saved order', 'the order was not created');
            return;
        }
        $board = $this->request('GET', '/board');
        $this->add($this->url('/board'), 'board shows the order before it moves', $board['code'] === 200 && $this->orderNumber !== '' && str_contains($board['body'], $this->orderNumber), $this->orderNumber, $this->snippet($board['body']));
        $this->switchLanguage('ha');
        $this->fields($this->url('/board'), 'Hausa board', $this->request('GET', '/board')['body'], ['board'], [], 'ha');
        $this->switchLanguage('en');
        $expected = ['New', 'Cutting', 'Sewing', 'Embroidery', 'Finishing', 'Fitting', 'Ready', 'Delivered'];
        for ($step = 0; $step < 8; $step++) {
            $page = $this->request('GET', '/orders/' . $this->orderId);
            if (preg_match('/' . preg_quote((string) $this->english['stage'], '/') . ':\s*([^<]+)/', $page['body'], $stage)) {
                $this->stagesSeen[] = trim(html_entity_decode($stage[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            if (!str_contains($page['body'], (string) $this->english['move_next'])) {
                $this->add($this->url('/orders/' . $this->orderId), 'last stage has no further move', str_contains($page['body'], (string) $this->english['no_next_stage']), $this->english['no_next_stage'], $this->snippet($page['body']));
                break;
            }
            $moved = $this->submit('/orders/' . $this->orderId . '/stage', ['csrf' => $this->csrf($page['body'])]);
            $shown = '';
            if (preg_match('/' . preg_quote((string) $this->english['stage'], '/') . ':\s*([^<]+)/', $moved['body'], $after)) {
                $shown = trim(html_entity_decode($after[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $want = $expected[$step + 1] ?? '';
            $this->add($this->url('/orders/' . $this->orderId), 'move to ' . $want, $shown === $want, $want, $shown === '' ? $this->snippet($moved['body']) : $shown);
        }
        $seen = implode(', ', $this->stagesSeen);
        $this->add($this->url('/orders/' . $this->orderId), 'order passed through every stage', $this->stagesSeen === $expected, implode(', ', $expected), $seen);
        $after = $this->request('GET', '/board');
        $this->add($this->url('/board'), 'board still shows the order', $this->orderNumber !== '' && str_contains($after['body'], $this->orderNumber) && str_contains($after['body'], 'Delivered'), $this->orderNumber . ' under Delivered', $this->snippet($after['body']));

        $settings = $this->request('GET', '/settings/stages');
        $this->fields(
            $this->url('/settings/stages'),
            'English stage settings',
            $settings['body'],
            ['stages_settings', 'add_stage', 'move_up', 'move_down', 'deactivate', 'stage_roles', 'role_open', 'role_ready', 'role_finished', 'save'],
            ['name="action" value="rename"', 'name="action" value="add"', 'name="action" value="up"', 'name="action" value="down"', 'name="action" value="roles"']
        );
        $ids = $this->hiddenIds($settings['body']);
        $first = $ids[0] ?? '';
        if ($first !== '') {
            $renamed = $this->submit('/settings/stages', [
                'csrf' => $this->csrf($settings['body']),
                'action' => 'rename',
                'id' => $first,
                'name' => 'ZZ New',
            ]);
            $this->add($this->url('/settings/stages'), 'rename a stage', str_contains($renamed['body'], 'ZZ New'), 'ZZ New', $this->snippet($renamed['body']));
            $reordered = $this->submit('/settings/stages', [
                'csrf' => $this->csrf($renamed['body']),
                'action' => 'down',
                'id' => $first,
            ]);
            $this->add($this->url('/settings/stages'), 'reorder a stage', $reordered['code'] === 200 && str_contains($reordered['body'], 'ZZ New'), 'ZZ New still listed', $this->snippet($reordered['body']));
        }
        $added = $this->submit('/settings/stages', [
            'csrf' => $this->csrf($this->request('GET', '/settings/stages')['body']),
            'action' => 'add',
            'name' => 'ZZ Press',
        ]);
        $this->add($this->url('/settings/stages'), 'add a stage', str_contains($added['body'], 'ZZ Press'), 'ZZ Press', $this->snippet($added['body']));
        $newId = $this->idNear($added['body'], 'ZZ Press');
        $off = $newId === '' ? $added : $this->submit('/settings/stages', [
            'csrf' => $this->csrf($added['body']),
            'action' => 'deactivate',
            'id' => $newId,
        ]);
        $this->add(
            $this->url('/settings/stages'),
            'deactivate a stage',
            $newId !== '' && str_contains($off['body'], (string) $this->english['activate']),
            $this->english['activate'],
            $newId === '' ? 'could not find the new stage id' : $this->snippet($off['body'])
        );
        $this->switchLanguage('ha');
        $this->fields($this->url('/settings/stages'), 'Hausa stage settings', $this->request('GET', '/settings/stages')['body'], ['add_stage', 'move_up', 'move_down', 'stage_roles'], ['name="action" value="add"'], 'ha');
        $this->switchLanguage('en');
    }

    private function paymentChecks(): void
    {
        if (!$this->gate('payments')) {
            return;
        }
        if ($this->orderId === '') {
            $this->add($this->url('/orders'), 'payment checks', false, 'a saved order', 'the order was not created');
            return;
        }
        $page = $this->request('GET', '/orders/' . $this->orderId);
        $this->fields(
            $this->url('/orders/' . $this->orderId),
            'English payment form',
            $page['body'],
            ['record_payment', 'amount', 'method', 'cash', 'transfer', 'payment_history', 'balance', 'amount_paid'],
            ['name="amount"', 'name="method"']
        );
        $paid = $this->submit('/orders/' . $this->orderId . '/payments', [
            'csrf' => $this->csrf($page['body']),
            'amount' => '5000',
            'method' => 'cash',
        ]);
        $depositOk = str_contains($paid['body'], '>₦5,000.00</strong>') && str_contains($paid['body'], '>₦10,000.00</strong>') && str_contains($paid['body'], (string) $this->english['payment_history']);
        $this->add($this->url('/orders/' . $this->orderId), 'deposit updates paid amount, balance and history', $depositOk, '₦5,000.00 paid and ₦10,000.00 balance', $this->snippet($paid['body']));
        $this->balanceKobo = $depositOk ? 1000000 : 1500000;
        $this->switchLanguage('ha');
        $hausaPaid = $this->request('GET', '/orders/' . $this->orderId);
        $this->fields($this->url('/orders/' . $this->orderId), 'Hausa payment form', $hausaPaid['body'], ['record_payment', 'payment_history', 'balance', 'reverse'], ['name="amount"'], 'ha');
        $this->switchLanguage('en');
        $paid = $this->request('GET', '/orders/' . $this->orderId);
        $reason = $this->reverseHasReason($paid['body']);
        $this->add(
            $this->url('/orders/' . $this->orderId),
            'reversal asks for a reason',
            $reason,
            'a reason field on the reverse form',
            $reason ? 'reason field present' : 'the reverse form has no reason field'
        );
        $paymentId = $this->idFrom($paid['body'], '#/payments/([0-9a-f-]{36})/reverse#');
        if ($paymentId !== '') {
            $fields = ['csrf' => $this->csrf($paid['body']), 'reason' => 'Customer paid twice'];
            $reversed = $this->submit('/orders/' . $this->orderId . '/payments/' . $paymentId . '/reverse', $fields);
            $this->reversed = str_contains($reversed['body'], (string) $this->english['reversed']);
            $this->add(
                $this->url('/orders/' . $this->orderId . '/payments/' . $paymentId . '/reverse'),
                'reversal with a reason is recorded',
                $this->reversed && $reason && str_contains($reversed['body'], 'Customer paid twice'),
                'history shows the reason Customer paid twice',
                $this->snippet($reversed['body'])
            );
            if ($this->reversed) {
                $this->balanceKobo = 1500000;
            }
            $clean = $reversed['body'];
        } else {
            $clean = $paid['body'];
            $this->add($this->url('/orders/' . $this->orderId), 'reversal with a reason is recorded', false, 'a reverse action', 'no reverse form');
        }
        $edit = preg_match('#<a[^>]+href="[^"]*/(?:edit|delete)#i', $clean) === 1
            || preg_match('#>(?:Edit|Delete)</a>#', $clean) === 1
            || preg_match('#>(?:Edit|Delete)</button>#', $clean) === 1;
        $this->add($this->url('/orders/' . $this->orderId), 'payment page has no edit or delete link', !$edit, 'no edit or delete link', $edit ? 'an edit or delete link is present' : 'none');
    }

    private function measurementChecks(): void
    {
        if (!$this->gate('measurements') || $this->customerId === '') {
            return;
        }
        $page = $this->request('GET', '/customers/' . $this->customerId);
        $this->fields(
            $this->url('/customers/' . $this->customerId),
            'English measurement section',
            $page['body'],
            ['measurements', 'new_measurement', 'measure_keeps_old', 'add_other', 'clear_all', 'unit', 'inches', 'cm'],
            ['name="values[neck]"', 'name="unit"', 'name="action" value="new"']
        );
        $garmentId = $this->idFrom($page['body'], '#name="garment_id" value="([0-9a-f-]{36})"#');
        $first = $this->submit('/customers/' . $this->customerId . '/measurements', [
            'csrf' => $this->csrf($page['body']),
            'garment_id' => $garmentId,
            'unit' => 'inches',
            'action' => 'take',
            'values[neck]' => '16',
        ]);
        $second = $this->submit('/customers/' . $this->customerId . '/measurements', [
            'csrf' => $this->csrf($first['body']),
            'garment_id' => $garmentId,
            'unit' => 'cm',
            'action' => 'update',
            'values[neck]' => '41.5',
        ]);
        $both = str_contains($second['body'], '41.5') && str_contains($second['body'], '16')
            && str_contains($second['body'], (string) $this->english['cm'])
            && str_contains($second['body'], (string) $this->english['inches'])
            && str_contains($second['body'], (string) $this->english['view_old_measurements']);
        $this->add(
            $this->url('/customers/' . $this->customerId),
            'two measurement versions keep their units',
            $garmentId !== '' && $both,
            'latest 41.5 centimetres and earlier 16 inches under ' . $this->english['history'],
            $this->snippet($second['body'])
        );
        $this->switchLanguage('ha');
        $this->fields(
            $this->url('/customers/' . $this->customerId),
            'Hausa measurement section',
            $this->request('GET', '/customers/' . $this->customerId)['body'],
            ['measurements', 'new_measurement', 'view_old_measurements', 'unit'],
            ['name="unit"', 'value="new"'],
            'ha'
        );
        $this->switchLanguage('en');
    }

    private function settingsChecks(): void
    {
        if (!$this->gate('settings')) {
            return;
        }
        $garments = $this->request('GET', '/settings/garments');
        $this->fields(
            $this->url('/settings/garments'),
            'English garment settings',
            $garments['body'],
            ['garment_settings', 'add_garment', 'move_up', 'move_down', 'placeholder_note'],
            ['name="action" value="garment_add"', 'class="garment-card"']
        );
        $kaftan = $this->idNear($garments['body'], 'Kaftan');
        $addedGarment = $this->submit('/settings/garments', [
            'csrf' => $this->csrf($garments['body']),
            'action' => 'garment_add',
            'name' => 'ZZ Wrap',
        ]);
        $this->add($this->url('/settings/garments'), 'add a garment type', str_contains($addedGarment['body'], 'ZZ Wrap'), 'ZZ Wrap', $this->snippet($addedGarment['body']));
        $field = $kaftan === '' ? $addedGarment : $this->submit('/settings/garments', [
            'csrf' => $this->csrf($addedGarment['body']),
            'action' => 'field_add',
            'garment_id' => $kaftan,
            'name' => 'ZZ cuff',
        ]);
        $this->add($this->url('/settings/garments'), 'add a measurement field', $kaftan !== '' && str_contains($field['body'], 'ZZ cuff'), 'ZZ cuff', $kaftan === '' ? 'Kaftan id was not on the page' : $this->snippet($field['body']));
        $this->switchLanguage('ha');
        $this->fields($this->url('/settings/garments'), 'Hausa garment settings', $this->request('GET', '/settings/garments')['body'], ['garment_settings', 'add_garment', 'move_up'], ['value="garment_add"', 'class="garment-card"'], 'ha');
        $this->switchLanguage('en');

        $settings = $this->request('GET', '/settings');
        $this->fields(
            $this->url('/settings'),
            'English module switches',
            $settings['body'],
            ['modules', 'modules_help', 'company_type', 'save', 'module_fashion_orders', 'module_fashion_production', 'module_fashion_finance', 'module_fashion_measurements'],
            ['name="modules[]"', 'value="fashion.orders"', 'value="fashion.production"', 'value="fashion.finance"', 'value="fashion.measurements"']
        );
        $this->switchLanguage('ha');
        $this->fields($this->url('/settings'), 'Hausa module switches', $this->request('GET', '/settings')['body'], ['modules', 'module_fashion_orders', 'module_fashion_production'], ['name="modules[]"'], 'ha');
        $this->switchLanguage('en');
    }

    private function dashboardChecks(): void
    {
        if (!$this->gate('dashboard')) {
            return;
        }
        $finished = $this->stagesSeen !== [] && end($this->stagesSeen) === 'Delivered';
        $expected = [
            (string) $this->english['card_active'] => $finished ? '0' : '1',
            (string) $this->english['card_week'] => $finished ? '0' : '1',
            (string) $this->english['card_overdue'] => '0',
            (string) $this->english['card_ready'] => '0',
            (string) $this->english['card_money'] => $this->naira($this->balanceKobo),
        ];
        $home = $this->request('GET', '/');
        $cards = $this->cards($home['body']);
        $wrong = [];
        foreach ($expected as $label => $value) {
            if (($cards[$label] ?? '') !== $value) {
                $wrong[] = $label . ' expected ' . $value . ' found ' . ($cards[$label] ?? 'missing');
            }
        }
        $this->add($this->url('/'), 'dashboard cards match the shop data', $home['code'] === 200 && $wrong === [], implode('; ', $expected), $wrong === [] ? implode('; ', array_map(static fn (string $k, string $v): string => $k . '=' . $v, array_keys($cards), $cards)) : implode('; ', $wrong));
        $this->switchLanguage('ha');
        $hausaHome = $this->request('GET', '/');
        $hausaCards = $this->cards($hausaHome['body']);
        $hausaExpected = (string) $this->hausa['card_active'];
        $this->add(
            $this->url('/'),
            'Hausa dashboard shows the same active count',
            ($hausaCards[$hausaExpected] ?? '') === $expected[(string) $this->english['card_active']],
            $hausaExpected . ' ' . $expected[(string) $this->english['card_active']],
            ($hausaCards[$hausaExpected] ?? 'missing')
        );
        $this->switchLanguage('en');
    }

    private function isolationChecks(): void
    {
        if (!$this->gate('isolation') || $this->customerId === '' || $this->orderId === '') {
            return;
        }
        $this->activeJar = $this->jarB;
        $this->signedIn = false;
        $register = $this->request('GET', '/register');
        $otherEmail = 'zz-other-' . $this->suffix . '@example.test';
        $signed = $this->submit('/register', [
            'csrf' => $this->csrf($register['body']),
            'company_type' => 'tailor',
            'business_name' => 'ZZ Regression other ' . $this->suffix,
            'owner_name' => 'Other Owner',
            'email' => $otherEmail,
            'password' => $this->password,
            'phone' => '08012223333',
        ]);
        $ready = $signed['code'] === 200 && str_contains($signed['body'], 'Other Owner');
        $this->add($this->url('/register'), 'second business signs up', $ready, 'dashboard for the second shop', $this->snippet($signed['body']));
        if (!$ready) {
            $this->activeJar = $this->jar;
            $this->signedIn = true;
            return;
        }
        $targets = [
            '/customers/' . $this->customerId => 'customer',
            '/orders/' . $this->orderId => 'order',
        ];
        foreach ($targets as $path => $label) {
            $response = $this->request('GET', $path);
            $ok = in_array($response['code'], [403, 404], true);
            $this->add($this->url($path), 'second business cannot open the first ' . $label, $ok, '403 or 404', 'status ' . $response['code']);
        }
        $changedCustomer = $this->tweak($this->customerId);
        $changedOrder = $this->tweak($this->orderId);
        foreach (['/customers/' . $changedCustomer => 'customer id', '/orders/' . $changedOrder => 'order id'] as $path => $label) {
            $response = $this->request('GET', $path);
            $ok = in_array($response['code'], [403, 404], true);
            $this->add($this->url($path), 'changed ' . $label . ' stays closed', $ok, '403 or 404', 'status ' . $response['code']);
        }
        $token = $this->csrf($this->request('GET', '/more')['body']);
        $pay = $this->request('POST', '/orders/' . $this->orderId . '/payments', [
            'csrf' => $token,
            'amount' => '1',
            'method' => 'cash',
        ]);
        $this->add($this->url('/orders/' . $this->orderId . '/payments'), 'second business cannot record the first payment', in_array($pay['code'], [403, 404], true), '403 or 404', 'status ' . $pay['code']);
        $measure = $this->request('POST', '/customers/' . $this->customerId . '/measurements', [
            'csrf' => $token,
            'garment_id' => '00000000-0000-4000-8000-000000000001',
            'unit' => 'cm',
            'action' => 'take',
            'values[neck]' => '10',
        ]);
        $this->add($this->url('/customers/' . $this->customerId . '/measurements'), 'second business cannot save the first measurement', in_array($measure['code'], [403, 404], true), '403 or 404', 'status ' . $measure['code']);
        $this->activeJar = $this->jar;
        $this->signedIn = true;
    }

    private function moduleChecks(): void
    {
        if (!$this->gate('modules')) {
            return;
        }
        $settings = $this->request('GET', '/settings');
        $modules = $this->checkedModules($settings['body']);
        $posted = array_values(array_filter($modules, static fn (string $key): bool => $key !== 'fashion.production'));
        $fields = ['csrf' => $this->csrf($settings['body'])];
        foreach ($posted as $index => $key) {
            $fields['modules[' . $index . ']'] = $key;
        }
        $saved = $this->submit('/settings', $fields);
        $nav = $this->request('GET', '/');
        $linkGone = !str_contains($nav['body'], 'href="' . $this->base . '/board"');
        $closed = $this->request('GET', '/board');
        $denied = $closed['code'] === 403 && str_contains($closed['body'], (string) $this->english['module_disabled']);
        $this->add($this->url('/board'), 'production off returns 403', $denied, '403 ' . $this->english['module_disabled'], 'status ' . $closed['code'] . ' ' . $this->snippet($closed['body']));
        $this->add($this->url('/'), 'production link leaves the navigation', $linkGone, 'no board link', $linkGone ? 'board link absent' : 'board link still present');
        $this->switchLanguage('ha');
        $hausaClosed = $this->request('GET', '/board');
        $this->add($this->url('/board'), 'production off is explained in Hausa', $hausaClosed['code'] === 403 && str_contains($hausaClosed['body'], (string) $this->hausa['module_disabled']), $this->hausa['module_disabled'], $this->snippet($hausaClosed['body']));
        $stages = $this->request('GET', '/settings/stages');
        $this->add($this->url('/settings/stages'), 'stage settings stay closed', $stages['code'] === 403, '403', 'status ' . $stages['code']);
    }

    private function gate(string $name): bool
    {
        if ($this->signedIn && $this->origin !== '') {
            return true;
        }
        $this->add($name, $name . ' checks', false, 'a signed-in shop', 'sign-up did not finish');
        return false;
    }

    /**
     * @param array<string, string> $fields
     * @return array{code: int, first: int, body: string, location: string, redirect: bool, final: string}
     */
    private function submit(string $path, array $fields): array
    {
        $sent = $this->request('POST', $path, $fields);
        $location = $sent['location'];
        if (!in_array($sent['code'], [301, 302, 303], true) || $location === '') {
            return ['code' => $sent['code'], 'first' => $sent['code'], 'body' => $sent['body'], 'location' => $location, 'redirect' => false, 'final' => $path];
        }
        $next = $this->request('GET', $this->toPath($location));
        return ['code' => $next['code'], 'first' => $sent['code'], 'body' => $next['body'], 'location' => $location, 'redirect' => true, 'final' => $this->toPath($location)];
    }

    private function switchLanguage(string $lang, string $back = '/more'): bool
    {
        $source = $this->signedIn ? '/more' : '/login';
        $page = $this->request('GET', $source);
        $token = $this->csrf($page['body']);
        if ($token === '') {
            return false;
        }
        $sent = $this->submit('/language', [
            'csrf' => $token,
            'lang' => $lang,
            'back' => $back,
        ]);
        return $sent['redirect'];
    }

    /**
     * @param list<string> $keys
     * @param list<string> $snippets
     */
    private function fields(string $page, string $name, string $html, array $keys, array $snippets, string $lang = 'en'): void
    {
        $catalog = $lang === 'ha' ? $this->hausa : $this->english;
        $missing = [];
        foreach ($keys as $key) {
            $text = (string) ($catalog[$key] ?? $key);
            if (!str_contains($html, $text)) {
                $missing[] = $text;
            }
        }
        foreach ($snippets as $snippet) {
            if (!str_contains($html, $snippet)) {
                $missing[] = $snippet;
            }
        }
        $this->add($page, $name, $missing === [], implode(', ', array_merge($keys, $snippets)), $missing === [] ? 'present' : 'missing ' . implode(', ', $missing));
    }

    /**
     * @param array<string, string>|null $fields
     * @return array{code: int, body: string, location: string}
     */
    private function request(string $method, string $path, ?array $fields = null, string $origin = ''): array
    {
        $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://') ? $path : ($origin !== '' ? $origin : $this->origin) . $this->base . $path;
        $headerFile = tempnam(sys_get_temp_dir(), 'gmingo-hdr');
        $bodyFile = tempnam(sys_get_temp_dir(), 'gmingo-body');
        if ($headerFile === false || $bodyFile === false) {
            return ['code' => 0, 'body' => '', 'location' => ''];
        }
        $command = [
            'curl', '-sS', '--connect-timeout', '5', '--max-time', '20',
            '-c', $this->activeJar, '-b', $this->activeJar,
            '-D', $headerFile, '-o', $bodyFile, '-w', '%{http_code}',
            '-X', $method, $url,
        ];
        if ($fields !== null) {
            foreach ($fields as $key => $value) {
                $command[] = '--data-urlencode';
                $command[] = $key . '=' . $value;
            }
        }
        $code = '0';
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            $code = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        $headers = (string) file_get_contents($headerFile);
        $body = (string) file_get_contents($bodyFile);
        @unlink($headerFile);
        @unlink($bodyFile);
        $location = '';
        if (preg_match('/^Location:\s*(\S+)/mi', $headers, $match)) {
            $location = trim($match[1]);
        }
        return ['code' => (int) trim((string) $code), 'body' => $body, 'location' => $location];
    }

    private function csrf(string $html): string
    {
        if (preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $match)) {
            return $match[1];
        }
        return '';
    }

    private function idFrom(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $match)) {
            return $match[1];
        }
        return '';
    }

    /** @return list<string> */
    private function garmentIds(string $html): array
    {
        if (!preg_match('#name="items\[0\]\[garment_type_id\]".*?</select>#s', $html, $select)) {
            return [];
        }
        preg_match_all('#<option value="([0-9a-f-]{36})"#i', $select[0], $match);
        return $match[1] ?? [];
    }

    /** @return list<string> */
    private function hiddenIds(string $html): array
    {
        preg_match_all('#name="id" value="([0-9a-f-]{36})"#i', $html, $match);
        $ids = [];
        foreach ($match[1] ?? [] as $id) {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function idNear(string $html, string $label): string
    {
        $position = strpos($html, $label);
        if ($position === false) {
            return '';
        }
        $chunk = substr($html, max(0, $position - 800), 800);
        if (preg_match('#/settings/garments/([0-9a-f-]{36})#', $chunk, $found) === 1) {
            return (string) $found[1];
        }
        if (preg_match_all('#name="id" value="([0-9a-f-]{36})"#i', $chunk, $match) && $match[1] !== []) {
            return (string) end($match[1]);
        }
        return '';
    }

    private function reverseHasReason(string $html): bool
    {
        if (!preg_match('#<form method="post" action="[^"]+/reverse"[^>]*>(.*?)</form>#s', $html, $form)) {
            return false;
        }
        return str_contains($form[1], 'name="reason"');
    }

    /** @return list<string> */
    private function checkedModules(string $html): array
    {
        preg_match_all('#<input type="checkbox" name="modules\[\]" value="([^"]+)"([^>]*)>#', $html, $match, PREG_SET_ORDER);
        $keys = [];
        foreach ($match as $box) {
            if (str_contains($box[2], 'checked')) {
                $keys[] = html_entity_decode($box[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $keys;
    }

    /** @return array<string, string> */
    private function cards(string $html): array
    {
        $found = [];
        if (preg_match_all('#<span>([^<]+)</span>\s*<strong>([^<]+)</strong>#', $html, $match, PREG_SET_ORDER)) {
            foreach ($match as $card) {
                $found[html_entity_decode(trim($card[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')] = html_entity_decode(trim($card[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $found;
    }

    private function naira(int $kobo): string
    {
        $naira = intdiv($kobo, 100);
        $rest = str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
        return '₦' . number_format($naira, 0, '.', ',') . '.' . $rest;
    }

    private function text(string $key): string
    {
        return (string) ($this->english[$key] ?? $key);
    }

    private function tweak(string $id): string
    {
        $last = substr($id, -1);
        $swap = $last === 'a' ? 'b' : 'a';
        return substr($id, 0, -1) . $swap;
    }

    private function toPath(string $location): string
    {
        if (str_starts_with($location, $this->origin)) {
            $location = substr($location, strlen($this->origin));
        }
        if (str_starts_with($location, $this->base)) {
            $location = substr($location, strlen($this->base));
        }
        if ($location === '' || $location[0] !== '/') {
            return '/' . ltrim($location, '/');
        }
        return $location;
    }

    private function url(string $path): string
    {
        return $this->base . $path;
    }

    private function snippet(string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if (strlen($text) > 240) {
            return substr($text, 0, 240) . '…';
        }
        return $text === '' ? '(empty page)' : $text;
    }

    private function add(string $page, string $name, bool $pass, string $expected, string $found): void
    {
        $this->rows[] = [
            'page' => $page,
            'name' => $name,
            'pass' => $pass,
            'expected' => $expected,
            'found' => $found,
        ];
    }

    private function printTable(): int
    {
        $failed = 0;
        echo str_pad('RESULT', 8) . str_pad('PAGE', 42) . "CHECK\n";
        foreach ($this->rows as $row) {
            if (!$row['pass']) {
                $failed++;
            }
            $page = $row['page'];
            if (strlen($page) > 40) {
                $page = substr($page, 0, 37) . '...';
            }
            echo str_pad($row['pass'] ? 'PASS' : 'FAIL', 8) . str_pad($page, 42) . $row['name'] . "\n";
            if (!$row['pass']) {
                echo "        expected: " . $row['expected'] . "\n";
                echo "        found:    " . $row['found'] . "\n";
            }
        }
        $passed = count($this->rows) - $failed;
        echo "\n{$passed} passed, {$failed} failed\n";
        return $failed;
    }
}
