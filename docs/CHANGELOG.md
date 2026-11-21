# Changelog

## Unreleased

### Phase 2 — SaaS subscription engine (2026-11-21)

- Added migrations `017_subscription_plans.sql` and `018_tenant_subscriptions.sql`.
- Added `app/domain/subscriptions.php` for plans, lifecycle, payments, events, and feature/limit checks.
- Registration now starts a Starter trial and syncs `subscription_ends_on`.
- Platform plan catalogue queries go through guarded helpers in `app/core/db.php`.
- Added `tests/subscriptions_test.php`; schema and test reset lists updated.
- Added roadmap docs: `MASTER_ROADMAP.md`, `PROGRESS.md`, `ARCHITECTURE.md`.

### Phase 1 — Audit

- Baseline audit of the existing multi-tenant fashion application (no schema change).
