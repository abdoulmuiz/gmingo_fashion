# Security and production readiness

Checklist for running Gmingo Fashion safely.

## Must keep

- Tenant-scoped queries only (`db_select` / `db_insert` / `db_update` / `db_delete` / `db_query` with `tenant_id = :tenant_id`).
- Prepared statements only; escape every user-facing string with `e()`.
- CSRF on every form that changes data; GET routes must not write.
- Login and session handling stay in `app/core/auth.php`.
- Money is whole kobo (`BIGINT`); never floats.
- Stock, payments, and stage moves are append-only.
- Stock deductions cannot drive quantity below zero (`stock_post_movement` with row lock).

## Platform administrator credentials

- Set `platform_admin_email` and a strong `platform_admin_password` in `config/config.php` (copied from `config.example.php`).
- Empty, short (<12), or blocked defaults (`password`, `admin`, etc.) are rejected; bootstrap/seed fail closed.
- Passwords are stored with `password_hash`. First login requires change via `/platform/password` (`password_must_change`).
- Never put the live password in docs, logs, or browser output. Tests use a dedicated value only in `tests/bootstrap.php`.

## Public surfaces

- Order tracking (`/track/{token}`) is rate-limited and binds one tenant by opaque token.
- Storefront (`/shop/{slug}`) is optional, CSRF-protected on order POST, binds one tenant by unique slug, and rate-limits orders by `REMOTE_ADDR` + slug (no trusted `X-Forwarded-For`).
- Unknown storefront slugs return HTTP 404 via `HttpException` / error handler.
- SaaS Paystack webhook (`/webhooks/saas-paystack`) is separate from shop Paystack.
- `GET /account/billing/return` only displays verification status; fulfillment is webhook- or `POST /account/billing/verify`-driven.

## Before go-live

1. Point `config/config.php` at production MySQL with a non-`_test` database name.
2. Set a strong `platform_admin_password` and unique `platform_admin_email` before seeding.
3. Configure SaaS Paystack on `/platform/gateway` (encrypted DB secrets) or set `saas_paystack_*` in config; leave disabled for manual renew. See `docs/BILLING.md`.
4. Set `public_base_url` to the production HTTPS origin and register the Paystack webhook URL.
5. Confirm `base_path` has no host or port.
6. Ensure `storage/` is writable by the web user and not web-browsable for sessions/logs beyond need.
7. Deploy compiled `public/assets/tailwind.css` with the release (Node not required on the server).
8. Run `php tools/migrate.php` then `php tests/run.php` against `*_test` only.
9. Seed only demo data on non-production hosts.
