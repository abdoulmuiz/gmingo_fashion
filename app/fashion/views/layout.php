<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/access.php';
require_once dirname(__DIR__, 2) . '/domain/platform.php';

/** @var string $title */
/** @var string $view_file */
$current = request_path();
$isPlatformAdmin = current_user_id() !== null && is_platform_admin();
$isLanding = $current === '/' && current_user_id() === null;
$showNav = !in_array($current, ['/login', '/register', '/pricing', '/onboarding', '/pay/return'], true)
    && !$isLanding
    && !str_starts_with($current, '/onboarding/')
    && !str_starts_with($current, '/shop/');
$navItems = $showNav
    ? ($isPlatformAdmin
        ? [
            ['/platform', 'platform_home'],
            ['/platform/tenants', 'platform_tenants'],
            ['/platform/plans', 'platform_plans'],
            ['/platform/payments', 'platform_payments'],
            ['/platform/gateway', 'platform_gateway'],
        ]
        : menu_links('nav'))
    : [];
$sideItems = [];
if ($showNav && !$isPlatformAdmin) {
    $seen = [];
    foreach (array_merge(menu_links('nav'), menu_links('more')) as $item) {
        $path = (string) $item[0];
        if (isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        $sideItems[] = $item;
    }
} elseif ($showNav) {
    $sideItems = $navItems;
}
// Brand must not advertise / when subscription (or access) blocks home — that href
// would disagree with can_access('/') in menu checks and confuse blocked shops.
$brandHref = '/';
if ($isPlatformAdmin) {
    $brandHref = '/platform';
} elseif (current_user_id() !== null && current_tenant_id() !== null && !can_access('/')) {
    $brandHref = '/account/subscription';
}
$isAuthGuest = in_array($current, ['/login', '/register'], true);
$isPricing = $current === '/pricing';
$isLoginPage = $current === '/login';
$isAppShell = $showNav && !$isLanding;
$shellUserName = '';
$shellUserRole = '';
$ordersBadge = 0;
$messagesBadge = 0;
if ($isAppShell && !$isPlatformAdmin && current_user_id() !== null) {
    require_once dirname(__DIR__, 2) . '/domain/dashboard.php';
    require_once dirname(__DIR__, 2) . '/domain/modules.php';
    $shellUserName = dashboard_user_name();
    $shellUserRole = dashboard_user_role_label();
    if (module_enabled('fashion.orders') && can_access('/orders')) {
        require_once dirname(__DIR__, 2) . '/domain/orders.php';
        try {
            $ordersBadge = order_count('active', '');
        } catch (Throwable $e) {
            $ordersBadge = 0;
        }
    }
    if (module_enabled('fashion.communication') && can_access('/messages')) {
        require_once dirname(__DIR__, 2) . '/domain/messages.php';
        try {
            $messagesBadge = message_list_count('queue', '');
        } catch (Throwable $e) {
            $messagesBadge = 0;
        }
    }
}
$bodyClass = ($showNav ? 'has-nav' : 'is-guest')
    . ($isPricing ? ' is-pricing' : '')
    . ($isLanding ? ' is-landing' : '')
    . ($isAppShell ? ' is-app-shell' : '');
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(t('app_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Kaushan+Script&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=<?= e((string) filemtime(dirname(__DIR__, 3) . '/public/assets/app.css')) ?>">
    <?php
    $tailwindCss = dirname(__DIR__, 3) . '/public/assets/tailwind.css';
    if (is_file($tailwindCss)):
    ?>
    <link rel="stylesheet" href="<?= e(url('/assets/tailwind.css')) ?>?v=<?= e((string) filemtime($tailwindCss)) ?>">
    <?php endif; ?>
</head>
<body class="<?= e(trim($bodyClass)) ?>">
    <div class="shell">
        <?php if ($isLanding): ?>
            <header class="lp-header">
                <div class="lp-trust">
                    <div class="lp-trust-inner">
                        <div class="lp-trust-left">
                            <span><?= e(t('landing_trust_businesses')) ?></span>
                            <span><?= e(t('landing_trust_support')) ?></span>
                            <span><?= e(t('landing_trust_secure')) ?></span>
                        </div>
                        <div class="lp-trust-actions">
                            <a class="lp-trust-login" href="<?= e(url('/login')) ?>"><?= e(t('login')) ?></a>
                            <a class="btn btn-small lp-btn-trust" href="<?= e(url('/register')) ?>"><?= e(t('landing_get_started_free')) ?> <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </div>
                <div class="lp-nav">
                    <div class="lp-nav-inner">
                        <a class="brand" href="<?= e(url('/')) ?>">
                            <span class="brand-logo-wrap">
                                <img
                                    class="brand-logo"
                                    src="<?= e(url('/assets/img/logo-gmingo-fashion.png')) ?>"
                                    alt="<?= e(t('app_name')) ?>"
                                    width="160"
                                    height="48"
                                    onerror="this.onerror=null;this.style.display='none';"
                                >
                            </span>
                            <span class="brand-product"><?= e(t('app_product')) ?></span>
                        </a>
                        <button class="lp-nav-toggle" type="button" aria-expanded="false" aria-controls="lp-nav-links" data-lp-nav-toggle>
                            <span class="sr-only"><?= e(t('main_menu')) ?></span>
                            <span aria-hidden="true"></span>
                        </button>
                        <nav class="lp-nav-links" id="lp-nav-links" aria-label="<?= e(t('main_menu')) ?>" data-lp-nav-links>
                            <a class="is-current" href="<?= e(url('/')) ?>"><?= e(t('home')) ?></a>
                            <a href="#features"><?= e(t('landing_nav_features')) ?></a>
                            <a href="#pricing"><?= e(t('pricing_title')) ?></a>
                            <a href="#showcase"><?= e(t('landing_nav_solutions')) ?></a>
                            <a href="#about"><?= e(t('landing_nav_about')) ?></a>
                            <a href="#contact"><?= e(t('landing_nav_contact')) ?></a>
                        </nav>
                    </div>
                </div>
            </header>
        <?php elseif (!$isAppShell): ?>
            <header class="site-header">
                <div class="utility">
                    <div class="utility-inner">
                        <span class="utility-brand"><?= e(t('app_kicker')) ?></span>
                        <span class="utility-note"><?= e(t('home_body')) ?></span>
                    </div>
                </div>
                <div class="mast">
                    <div class="mast-inner">
                        <a class="brand" href="<?= e(url($brandHref)) ?>">
                            <span class="brand-logo-wrap">
                                <img
                                    class="brand-logo"
                                    src="<?= e(url('/assets/img/logo-gmingo-fashion.png')) ?>"
                                    alt="<?= e(t('app_name')) ?>"
                                    width="160"
                                    height="48"
                                    onerror="this.onerror=null;this.style.display='none';"
                                >
                            </span>
                            <span class="brand-product"><?= e(t('app_product')) ?></span>
                        </a>
                        <div class="mast-actions">
                            <?php if (!$isLoginPage): ?>
                                <a class="btn btn-quiet btn-small<?= $current === '/pricing' ? ' is-current' : '' ?>" href="<?= e(url('/pricing')) ?>"><?= e(t('pricing_title')) ?></a>
                                <a class="btn btn-quiet btn-small" href="<?= e(url('/login')) ?>"><?= e(t('login')) ?></a>
                            <?php endif; ?>
                            <a class="btn btn-small<?= $current === '/register' ? ' is-current' : '' ?>" href="<?= e(url('/register')) ?>"><?= e(t('register')) ?></a>
                        </div>
                    </div>
                </div>
            </header>
        <?php endif; ?>
        <div class="frame">
            <?php if ($isAppShell): ?>
                <div class="dash-sidebar-backdrop" data-sidebar-backdrop hidden></div>
                <aside class="dash-sidebar" id="dash-sidebar" aria-label="<?= e(t('main_menu')) ?>">
                    <a class="dash-sidebar-brand" href="<?= e(url($brandHref)) ?>">
                        <img
                            class="brand-logo brand-logo-sidebar"
                            src="<?= e(url('/assets/img/logo-gmingo-fashion.png')) ?>"
                            alt="<?= e(t('app_name')) ?>"
                            width="150"
                            height="45"
                            onerror="this.onerror=null;this.src='data:image/svg+xml,'+encodeURIComponent('<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;150&quot; height=&quot;45&quot;></svg>');this.classList.add('img-missing');"
                        >
                    </a>
                    <nav class="dash-sidebar-nav">
                        <?php foreach ($sideItems as [$path, $label]): ?>
                            <?php
                            $badge = 0;
                            if ($path === '/orders') {
                                $badge = $ordersBadge;
                            } elseif ($path === '/messages') {
                                $badge = $messagesBadge;
                            }
                            ?>
                            <a class="<?= e(nav_active($path)) ?>" href="<?= e(url($path)) ?>">
                                <?= ui_icon(ui_menu_icon($path), 'ui-icon ui-icon-nav') ?>
                                <span class="dash-nav-label"><?= e(t($label)) ?></span>
                                <?php if ($badge > 0): ?>
                                    <span class="dash-nav-badge"><?= e((string) min(99, $badge)) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <?php
                    $showSidebarUpgrade = false;
                    if (!$isPlatformAdmin && can_access('/account/subscription')) {
                        require_once dirname(__DIR__, 2) . '/domain/subscriptions.php';
                        $showSidebarUpgrade = subscription_show_upgrade_cta();
                    }
                    ?>
                    <?php if ($showSidebarUpgrade): ?>
                        <div class="dash-sidebar-upgrade">
                            <img
                                class="dash-upgrade-crown-img"
                                src="<?= e(url('/assets/img/crown.jpeg')) ?>"
                                alt="<?= e(t('dash_sidebar_upgrade')) ?>"
                                width="40"
                                height="36"
                                onerror="this.onerror=null;this.style.display='none';"
                            >
                            <p class="dash-upgrade-title"><?= e(t('dash_sidebar_upgrade')) ?></p>
                            <p class="dash-upgrade-help"><?= e(t('dash_sidebar_upgrade_help')) ?></p>
                            <a class="btn btn-small dash-upgrade-btn" href="<?= e(url('/account/subscription')) ?>#bill-plans"><?= e(t('dash_sidebar_view_plans')) ?> →</a>
                        </div>
                    <?php endif; ?>
                    <p class="dash-sidebar-version"><?= e(t('app_name')) ?> v2.0.0</p>
                </aside>
                <nav class="bottom-nav" aria-label="<?= e(t('main_menu')) ?>">
                    <?php foreach ($navItems as [$path, $label]): ?>
                        <a class="<?= e(nav_active($path)) ?>" href="<?= e(url($path)) ?>">
                            <?= ui_icon(ui_menu_icon($path), 'ui-icon ui-icon-nav') ?>
                            <span><?= e(t($label)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="column">
                <?php if ($isAppShell): ?>
                    <header class="dash-topbar">
                        <button class="dash-menu-toggle" type="button" data-sidebar-toggle aria-controls="dash-sidebar" aria-expanded="false">
                            <span class="sr-only"><?= e(t('main_menu')) ?></span>
                            <?= ui_icon('menu', 'ui-icon') ?>
                        </button>
                        <form class="dash-search" method="get" action="<?= e(url(can_access('/customers') ? '/customers' : (can_access('/orders') ? '/orders' : '/'))) ?>" role="search">
                            <span class="dash-search-icon" aria-hidden="true"><?= ui_icon('search', 'ui-icon') ?></span>
                            <label class="sr-only" for="dash-search-q"><?= e(t('dash_search_label')) ?></label>
                            <input id="dash-search-q" type="search" name="q" placeholder="<?= e(t('dash_search_anything')) ?>" autocomplete="off" data-global-search>
                        </form>
                        <div class="dash-topbar-user">
                            <button class="dash-bell" type="button" aria-label="<?= e(t('dash_notifications')) ?>">
                                <?= ui_icon('bell', 'ui-icon') ?>
                                <?php if ($messagesBadge > 0): ?>
                                    <span class="dash-nav-badge dash-bell-badge"><?= e((string) min(99, $messagesBadge)) ?></span>
                                <?php endif; ?>
                            </button>
                            <span class="dash-avatar" aria-hidden="true"><?= e(strtoupper(substr($shellUserName !== '' ? $shellUserName : 'G', 0, 1))) ?></span>
                            <span class="dash-user-meta">
                                <strong><?= e($shellUserName !== '' ? $shellUserName : t('app_name')) ?></strong>
                                <span><?= e($shellUserRole !== '' ? $shellUserRole : t('dash_role_staff')) ?></span>
                            </span>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-quiet btn-small" type="submit"><?= e(t('logout')) ?></button>
                            </form>
                        </div>
                    </header>
                <?php endif; ?>
                <main class="app<?= $isAuthGuest ? ' app-guest' : '' ?><?= $isLanding ? ' app-landing' : '' ?><?= $isAppShell ? ' app-shell' : '' ?>">
                    <?php require $view_file; ?>
                </main>
                <?php if ($isLanding): ?>
                    <footer class="lp-footer">
                        <div class="lp-wrap lp-footer-inner">
                            <div class="lp-footer-brand">
                                <span class="brand-logo-wrap footer-logo-wrap">
                                    <img
                                        class="footer-logo"
                                        src="<?= e(url('/assets/img/logo-gmingo-fashion.png')) ?>"
                                        alt="<?= e(t('app_name')) ?>"
                                        width="140"
                                        height="42"
                                        onerror="this.onerror=null;this.style.display='none';"
                                    >
                                </span>
                                <strong><?= e(t('company_name')) ?></strong>
                                <p><?= e(t('landing_footer_tagline')) ?></p>
                            </div>
                            <nav class="lp-footer-cols" aria-label="<?= e(t('main_menu')) ?>">
                                <div>
                                    <strong><?= e(t('landing_footer_product')) ?></strong>
                                    <a href="#features"><?= e(t('landing_nav_features')) ?></a>
                                    <a href="#pricing"><?= e(t('pricing_title')) ?></a>
                                    <a href="<?= e(url('/register')) ?>"><?= e(t('register')) ?></a>
                                </div>
                                <div>
                                    <strong><?= e(t('landing_footer_company')) ?></strong>
                                    <a href="#about"><?= e(t('landing_nav_about')) ?></a>
                                    <a href="#contact"><?= e(t('landing_nav_contact')) ?></a>
                                    <a href="<?= e(url('/login')) ?>"><?= e(t('login')) ?></a>
                                </div>
                                <div>
                                    <strong><?= e(t('landing_footer_support')) ?></strong>
                                    <a href="<?= e(url('/pricing')) ?>"><?= e(t('pricing_view_all_plans')) ?></a>
                                    <a href="<?= e(url('/register')) ?>"><?= e(t('pricing_start_trial')) ?></a>
                                </div>
                            </nav>
                        </div>
                        <div class="lp-footer-bottom">
                            <div class="lp-wrap">
                                <p><?= e(t('landing_copyright')) ?></p>
                            </div>
                        </div>
                    </footer>
                    <script>
                    (function () {
                        var toggle = document.querySelector('[data-lp-nav-toggle]');
                        var links = document.querySelector('[data-lp-nav-links]');
                        if (!toggle || !links) return;
                        toggle.addEventListener('click', function () {
                            var open = links.classList.toggle('is-open');
                            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                        });
                        links.querySelectorAll('a').forEach(function (a) {
                            a.addEventListener('click', function () {
                                links.classList.remove('is-open');
                                toggle.setAttribute('aria-expanded', 'false');
                            });
                        });
                    })();
                    </script>
                <?php elseif (!$isAppShell): ?>
                    <footer class="site-footer">
                        <div class="footer-inner">
                            <div class="footer-brand">
                                <span class="brand-logo-wrap footer-logo-wrap">
                                    <img
                                        class="footer-logo"
                                        src="<?= e(url('/assets/img/logo-gmingo-fashion.png')) ?>"
                                        alt="<?= e(t('app_name')) ?>"
                                        width="140"
                                        height="42"
                                        onerror="this.onerror=null;this.style.display='none';"
                                    >
                                </span>
                                <strong><?= e(t('app_name')) ?></strong>
                                <p><?= e(t('home_title')) ?></p>
                            </div>
                            <?php if ($navItems !== []): ?>
                                <nav class="footer-nav" aria-label="<?= e(t('main_menu')) ?>">
                                    <?php foreach ($navItems as [$path, $label]): ?>
                                        <a href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a>
                                    <?php endforeach; ?>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </footer>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($isAppShell): ?>
        <script>
        (function () {
            var sidebar = document.getElementById('dash-sidebar');
            var toggle = document.querySelector('[data-sidebar-toggle]');
            var backdrop = document.querySelector('[data-sidebar-backdrop]');
            var search = document.querySelector('[data-global-search]');
            function setOpen(open) {
                if (!sidebar) return;
                document.body.classList.toggle('sidebar-open', open);
                if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (backdrop) backdrop.hidden = !open;
            }
            if (toggle) {
                toggle.addEventListener('click', function () {
                    setOpen(!document.body.classList.contains('sidebar-open'));
                });
            }
            if (backdrop) {
                backdrop.addEventListener('click', function () { setOpen(false); });
            }
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                    if (!search) return;
                    e.preventDefault();
                    search.focus();
                    search.select();
                }
                if (e.key === 'Escape') setOpen(false);
            });
        })();
        </script>
    <?php endif; ?>
    <?php
    if (!isset($page_toasts) || !is_array($page_toasts)) {
        $page_toasts = [];
    }
    require __DIR__ . '/partials/ui_toast.php';
    ?>
</body>
</html>