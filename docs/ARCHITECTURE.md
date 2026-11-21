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
- Narrow platform query helpers exist for login email lookup, Paystack webhooks, public tracking, and subscription plan catalogue.

## Modules

Per-tenant flags in `fashion_tenant_modules` (`fashion.orders`, `fashion.production`, …). Company type seeds defaults. Route access uses `require_module` / `require_access`.

## Money

Amounts are whole kobo (`BIGINT`). Floats are rejected in `db_bind`. Order payments live in `fashion_payments` (append-only).

## Subscription system (Phase 2)

Separate from customer-order payments.

| Table | Purpose |
| --- | --- |
| `fashion_plans` | Platform plan catalogue (monthly/annual kobo, trial, limits JSON, modules JSON) |
| `fashion_subscriptions` | One current subscription row per tenant |
| `fashion_subscription_payments` | SaaS billing records (append-only) |
| `fashion_subscription_events` | Lifecycle history (append-only) |

Domain: `app/domain/subscriptions.php`.

Statuses: `trial`, `active`, `expired`, `suspended`, `cancelled`.

Entitlement: trial/active/cancelled remain entitled through `expires_on` / trial end; suspended and expired are not. Cancellation does not cut access before the paid period ends.

Compatibility: lifecycle syncs `fashion_tenants.subscription_ends_on` so public tracking and messaging keep working. Registration calls `subscription_start_trial()` for the Starter plan.

Central checks: `subscription_allows()`, `subscription_allows_module()`, `subscription_limit()`, `subscription_within_limit()`. Route-wide enforcement is Phase 4.

## Integration boundaries

`MERGE.md` sketches shared identity/customers/payments for a future multi-product Gmingo core. Logistics and Retail stay out of this repository. Subscription billing (Phase 6) must reuse payment-provider patterns without mixing with order Paystack flows.

## Clock

Domain time uses `app_now()` / `app_today()` (`app/core/clock.php`). Tests may freeze time with `$GLOBALS['gmingo_now']`.
