# Security and production readiness

Checklist for running Gmingo Fashion safely.

## Must keep

- Tenant-scoped queries only (`db_select` / `db_insert` / `db_update` / `db_delete` / `db_query` with `tenant_id = :tenant_id`).
- Prepared statements only; escape every user-facing string with `e()`.
- CSRF on every form that changes data; GET routes must not write.
- Login and session handling stay in `app/core/auth.php`.
- Money is whole kobo (`BIGINT`); never floats.
- Stock, payments, and stage moves are append-only.

## Public surfaces

- Order tracking (`/track/{token}`) is rate-limited and binds one tenant by opaque token.
- Storefront (`/shop/{slug}`) is optional, CSRF-protected on order POST, and binds one tenant by unique slug.
- SaaS Paystack webhook (`/webhooks/saas-paystack`) is separate from shop Paystack.

## Before go-live

1. Point `config/config.php` at production MySQL with a non-`_test` database name.
2. Set strong `saas_paystack_*` keys only on the platform host; leave empty to force manual renew.
3. Confirm `base_path` has no host or port.
4. Ensure `storage/` is writable by the web user and not web-browsable for sessions/logs beyond need.
5. Run `php tools/migrate.php` then `php tests/run.php` against `*_test` only.
6. Seed only demo data on non-production hosts.
