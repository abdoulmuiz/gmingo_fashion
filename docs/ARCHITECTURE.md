# Gmingo Fashion — Architecture

Plain PHP 8.1+, MySQL, Apache (XAMPP). No Laravel/React/Vue. Server-rendered views under `app/fashion/views/`.

## Layers

| Layer | Location | Role |
| --- | --- | --- |
| Front controller | `public/index.php` | Boot, dispatch |
| Core | `app/core/` | Auth, CSRF, DB guards, router, clock, i18n |
| Domain | `app/domain/` | Business rules |
| HTTP | `app/fashion/controllers/` + `app/routes.php` | Request handling |
| Payments adapters | `app/payments/` | Order Paystack / manual providers |
| Config | `config/config.php` | Secrets and DB (not public) |
| Migrations | `database/migrations/` | Additive numbered SQL |

## Authentication

- Session identity only via `current_user_id()` / `current_tenant_id()` in `app/core/auth.php`.
- Passwords: `password_hash` / `password_verify`.
- Login rate limiting and CSRF on state-changing POSTs.

## Tenant isolation

- Every `fashion_*` business table carries `tenant_id`.
- `db_select` / `db_insert` / `db_update` / `db_delete` / `db_query` bind tenant from the session.
- Callers cannot supply `tenant_id` through helper params.
- Narrow platform query helpers exist for login email lookup, Paystack webhooks, public tracking, storefront slug lookup, and subscription plan catalogue.

## Modules

Per-tenant flags in `fashion_tenant_modules` (`fashion.orders`, `fashion.production`, …). Company type seeds defaults. Route access uses `require_module` / `require_access`.

## Money

Amounts are whole kobo (`BIGINT`). Floats are rejected in `db_bind`. Order payments live in `fashion_payments` (append-only).

## Subscription system (Phase 2)

Separate from customer-order payments.

| Table | Purpose |
| --- | --- |
| `fashion_plans` | Platform plan catalogue (monthly/annual kobo, currency, features, popularity, sort order, price_version, trial, limits/modules JSON) |
| `fashion_subscriptions` | One current subscription row per tenant |
| `fashion_subscription_payments` | SaaS billing records (append-only) |
| `fashion_subscription_events` | Lifecycle history (append-only) |

Domain: `app/domain/subscriptions.php`.

Statuses: `trial`, `active`, `expired`, `suspended`, `cancelled`.

Entitlement: trial/active/cancelled remain entitled through `expires_on` / trial end; suspended and expired are not. Cancellation does not cut access before the paid period ends.

Compatibility: lifecycle syncs `fashion_tenants.subscription_ends_on` so public tracking and messaging keep working. Registration calls `subscription_start_trial()` for the Starter plan.

Central checks: `subscription_allows()`, `subscription_allows_module()`, `subscription_limit()`, `subscription_within_limit()`, `subscription_has_app_access()`, `require_subscription_entitled()`.

Route enforcement (Phase 4): `require_access` redirects unentitled shops to `/account/subscription`; `/more`, account, and onboarding stay open. `require_module` also requires entitlement and plan module inclusion. Staff hires respect `max_staff`.

## Staff and RBAC (Phase 7)

Shop user roles: `owner`, `manager`, `staff`, `tailor` (plus `platform_admin` on the platform tenant).

Permissions are checked with `user_can()` / `require_permission()` in `app/domain/staff.php`. Menu routes declare a `permission` in `menu_catalog()`.

Staff management UI: `/staff` (requires module `fashion.staff` and `staff.manage`). Deactivated users cannot sign in. `max_staff` counts active users only.

## Subscription billing (Phase 6)

Platform (Gmingo) SaaS Paystack — not shop `fashion_payment_settings`. Secrets prefer encrypted `fashion_saas_gateway_settings` (`/platform/gateway`); config `saas_paystack_*` remains a fallback. See `docs/BILLING.md`.

| Piece | Role |
| --- | --- |
| `fashion_saas_gateway_settings` | Encrypted platform keys, env, enable flag |
| `fashion_subscription_checkouts` | Mutable pending/paid checkout intents |
| `fashion_saas_webhook_receipts` | Idempotent webhook event keys |
| `app/payments/SaasPaystack.php` | Initialize / verify / webhook parse |
| `app/domain/billing.php` | Owner checkout start, fulfill, webhook lifecycle |
| `/webhooks/saas-paystack` | Authoritative fulfill + subscription/invoice events |
| `/account/billing/return` | GET peek only (pending / paid / failed UI) |
| `POST /account/billing/verify` | Optional owner-triggered verify (CSRF + server-side Paystack verify) |
| `/platform/gateway` | Platform payment gateway settings |
| `/platform/payments` | Cross-tenant SaaS payment list |

Successful fulfill calls `subscription_fulfill_checkout` → activate or renew, then append-only `fashion_subscription_payments`. Fulfillment is idempotent. Optional Paystack plan codes on `fashion_plans` enable recurring initialize. Manual renew stays available when checkout is disabled.

## Platform administration (Phase 5)

Fixed platform tenant id (`subscription_platform_tenant_id()`). Operator users have role `platform_admin` and sign in to `/platform`.

Bootstrap requires a securely configured `platform_admin_password` (no empty/default/`password` values). New platform admins are created with `password_must_change = 1` until `/platform/password` succeeds. Seed refuses to run without a strong password.

Cross-tenant reads use `admin_platform_query` (only from `app/domain/platform.php`). Mutations bind the target shop with `auth_bind_tenant()` then call existing subscription lifecycle helpers.

Routes: `/platform`, `/platform/plans`, `/platform/tenants/{id}` (suspend / reactivate / change plan), `/platform/password`, `/platform/gateway`, `/platform/payments`. Public `/pricing` reads the active plan catalogue (no hard-coded amounts).

## Onboarding (Phase 3)

HTTP registration passes `start_onboarding` so new owners land on `/onboarding`. Domain registration used by tests/seeds omits that flag and marks onboarding complete.

Steps: business → contact → branding → preferences → staff → inventory (if module on) → subscription confirm. Owners may skip a step or finish later. Logos are stored under `storage/logos/` and served only to the owning tenant via `/onboarding/logo`.

## Catalogue, sales and purchasing (Phases 8–10)

| Area | Tables / routes |
| --- | --- |
| Products + BOM | `fashion_products`, `fashion_product_bom`, `/products` |
| Stock extras | `kind`, `unit_cost_kobo` on `fashion_stock_items` |
| Sales / returns | `fashion_sales`, `fashion_sale_lines`, `/sales` |
| Purchasing | `fashion_suppliers`, `fashion_purchase_orders`, `/purchasing` |
| Expenses | existing `fashion_expenses`, `/expenses` |

Sales and purchase receiving post append-only `fashion_stock_movements`. All movements go through `stock_post_movement`, which locks the stock item (`FOR UPDATE`), recomputes balance, and refuses below-zero quantity. `stock_adjust` delegates to the same path.

## Production, branches, reports, CRM (Phases 11–15)

| Area | Tables / routes |
| --- | --- |
| Work orders | `fashion_work_orders`, `fashion_quality_checks`, `/work-orders` |
| Branches | `fashion_branches`, optional `branch_id` on movements, `/branches` |
| Reports | `/reports` (aggregates only) |
| Storefront | tenant `storefront_slug` / `storefront_enabled`, public `/shop/{slug}` (order POST rate-limited) |
| Broadcasts | `fashion_broadcasts`, `/broadcasts` (opt-in customers only) |

## Frontend assets (Phase 16)

- Runtime CSS: `public/assets/app.css` (legacy) + optional compiled `public/assets/tailwind.css`.
- Build (dev machine only): `npm install` then `npm run build:css`. Node is not a production app dependency.
- Details: `docs/FRONTEND.md`.

## Integration boundaries

`MERGE.md` sketches shared identity/customers/payments for a future multi-product Gmingo core. Logistics and Retail stay out of this repository. Subscription SaaS Paystack is configured separately from per-shop order Paystack. See `docs/SECURITY.md` for go-live checks.

## Clock

Domain time uses `app_now()` / `app_today()` (`app/core/clock.php`). Tests may freeze time with `$GLOBALS['gmingo_now']`.
