# Gmingo Fashion — Master Roadmap

Status values: `NOT STARTED` · `IN PROGRESS` · `BLOCKED` · `COMPLETED` · `NEEDS REVIEW`

| Phase | Title | Status | Depends on |
| --- | --- | --- | --- |
| 1 | Existing system audit | COMPLETED | — |
| 2 | SaaS foundation and subscription engine | COMPLETED | 1 |
| 3 | Registration, onboarding and business setup | NOT STARTED | 2 |
| 4 | Subscription access control | NOT STARTED | 2, 3 |
| 5 | Gmingo platform administration | NOT STARTED | 2, 4 |
| 6 | Subscription billing and payment integration | NOT STARTED | 2, 5 |
| 7 | Staff management and RBAC | NOT STARTED | 4 |
| 8 | Product catalogue and inventory expansion | NOT STARTED | 4 |
| 9 | Sales, POS and returns | NOT STARTED | 8 |
| 10 | Expenses, suppliers and purchasing | NOT STARTED | 8 |
| 11 | Advanced fashion production management | NOT STARTED | 7 |
| 12 | Branch management | NOT STARTED | 7, 8 |
| 13 | Reporting and business analytics | NOT STARTED | 9, 10 |
| 14 | Customer storefront and online ordering | NOT STARTED | 8, 4 |
| 15 | Marketing and CRM | NOT STARTED | 3, 4 |
| 16 | UI and Tailwind modernization | NOT STARTED | Stable foundation (2–4+) |
| 17 | Security, performance and production readiness | NOT STARTED | Core product phases |
| 18 | Final integration and release | NOT STARTED | 17 |

## Phase notes

### Phase 1 — Audit
Mapped the plain-PHP multi-tenant fashion app: auth, tenant DB guards, modules, orders, production, payments, stock, messaging, tracking, and tests. No code changes.

### Phase 2 — SaaS subscription engine
Additive schema and domain for plans, tenant subscriptions, subscription payments, and history. Lifecycle: trial, active, expired, suspended, cancelled. Central `subscription_allows` / limit helpers. Registration starts a Starter trial. `subscription_ends_on` remains for tracking/messaging. No subscription UI or live SaaS checkout in this phase.

### Phase 3 — Onboarding (next)
Expand business types, guided onboarding, safer registration idempotency. Do not start until explicitly approved.
