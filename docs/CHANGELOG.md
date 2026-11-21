# Changelog

## Unreleased

### Visual redesign — professional Gmingo chrome (2026-11-21)

- Rebuilt `public/assets/app.css` tokens, atmosphere, header/footer, forms, nav, cards, and motion.
- Elevated login/register auth panels; refined layout guest chrome and More menu links.
- Aligned Tailwind `gmingo` palette with the navy/blue system.

### Release-readiness remediation (2026-11-21)

- Public storefront allowlisted in auth tests; unknown `/shop/{slug}` returns 404.
- Platform admin: no insecure default password; `password_must_change` + `/platform/password` (`030`).
- Stock floor + `FOR UPDATE` enforced in `stock_post_movement` for sales, adjustments, storefront.
- Billing: `GET /account/billing/return` peeks only; fulfill via webhook or `POST /account/billing/verify`.
- Storefront order rate limiting by IP + slug.
- Phase 16: local self-hosted Tailwind 3 build (`docs/FRONTEND.md`); partial UI migration.
- Expanded tenant/security coverage in `tests/release_remediation_test.php`.

### Phases 8–18 — Catalogue through release (2026-11-21)

- Phase 8: products/BOM catalogue, stock kind and unit cost (`023`).
- Phase 9: ready-to-wear sales, returns, stock movements (`024`, `/sales`).
- Phase 10: suppliers, purchase orders, receiving, expenses (`025`, `/purchasing`, `/expenses`).
- Phase 11: work orders and quality checks (`026`, `/work-orders`).
- Phase 12: branches and optional movement `branch_id` (`027`, `/branches`).
- Phase 13: summary reports (`/reports`).
- Phase 14: optional public storefront (`029`, `/shop/{slug}`, settings).
- Phase 15: opt-in broadcasts (`028`, `/broadcasts`).
- Phase 16: local Tailwind build + incremental adoption; legacy `app.css` remains primary.
- Phase 17: `docs/SECURITY.md` production checklist (updated for remediation).
- Phase 18: roadmap/progress/architecture alignment; domain + remediation tests (release commit pending approval).

### Phase 7 — Staff management and RBAC (2026-11-21)

- Added migration `022_staff_rbac.sql` (phone, started_on on users).
- Added roles `manager` and `tailor` with permission map; menu/access checks use `user_can`.
- Added `/staff` management (create, edit, deactivate, reactivate) for owners/managers.
- Billing renew requires `billing.manage` (owners). Active-only staff count for plan limits.
- Added `tests/staff_rbac_test.php`.

### Phase 6 — Subscription billing (2026-11-21)

- Added migration `021_subscription_billing.sql` (`fashion_subscription_checkouts`).
- Added platform SaaS Paystack helpers and `billing_checkout_start` / webhook / return fulfill.
- Account subscription page offers Paystack checkout when SaaS keys are configured; manual renew otherwise.
- Added `tests/billing_test.php`.

### Phase 5 — Gmingo platform administration (2026-11-21)

- Added migration `020_platform_admin.sql` and `platform_bootstrap()` for the platform tenant/admin.
- Added `/platform` console to manage plans and shop subscriptions (suspend / reactivate / change plan).
- Guarded cross-tenant admin queries in `app/core/db.php`; shop users cannot open platform routes.
- Added `tests/platform_admin_test.php`; demo seed creates `platform@gmingo.test`.

### Phase 4 — Subscription access control (2026-11-21)

- Wired `require_access` / `require_module` to subscription entitlement and plan modules.
- Unentitled shops redirect to `/account/subscription`; More/account/onboarding stay reachable.
- Added account subscription page with manual owner renew.
- Onboarding staff save enforces `max_staff`.
- Added `tests/subscription_access_test.php`; menu access accepts subscription redirects.

### Phase 3 — Registration and onboarding (2026-11-21)

- Added migration `019_onboarding.sql` (onboarding step, currency, address, branding, logo).
- Extended company types with boutique and ready-to-wear; shared brand-like module defaults.
- Added onboarding wizard domain, routes, and views after HTTP registration.
- Repeat registration while signed in no longer creates another tenant or subscription.
- Added `tests/onboarding_test.php`.

### Phase 2 — SaaS subscription engine (2026-11-21)

- Added migrations `017_subscription_plans.sql` and `018_tenant_subscriptions.sql`.
- Added `app/domain/subscriptions.php` for plans, lifecycle, payments, events, and feature/limit checks.
- Registration now starts a Starter trial and syncs `subscription_ends_on`.
- Platform plan catalogue queries go through guarded helpers in `app/core/db.php`.
- Added `tests/subscriptions_test.php`; schema and test reset lists updated.
- Added roadmap docs: `MASTER_ROADMAP.md`, `PROGRESS.md`, `ARCHITECTURE.md`.

### Phase 1 — Audit

- Baseline audit of the existing multi-tenant fashion application (no schema change).
