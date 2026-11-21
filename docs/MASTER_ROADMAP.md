# Gmingo Fashion — Master Roadmap

Status values: `NOT STARTED` · `IN PROGRESS` · `BLOCKED` · `COMPLETED` · `NEEDS REVIEW`

| Phase | Title | Status | Depends on |
| --- | --- | --- | --- |
| 1 | Existing system audit | COMPLETED | — |
| 2 | SaaS foundation and subscription engine | COMPLETED | 1 |
| 3 | Registration, onboarding and business setup | COMPLETED | 2 |
| 4 | Subscription access control | COMPLETED | 2, 3 |
| 5 | Gmingo platform administration | COMPLETED | 2, 4 |
| 6 | Subscription billing and payment integration | COMPLETED | 2, 5 |
| 7 | Staff management and RBAC | COMPLETED | 4 |
| 8 | Product catalogue and inventory expansion | COMPLETED | 4 |
| 9 | Sales, POS and returns | COMPLETED | 8 |
| 10 | Expenses, suppliers and purchasing | COMPLETED | 8 |
| 11 | Advanced fashion production management | COMPLETED | 7 |
| 12 | Branch management | COMPLETED | 7, 8 |
| 13 | Reporting and business analytics | COMPLETED | 9, 10 |
| 14 | Customer storefront and online ordering | COMPLETED | 8, 4 |
| 15 | Marketing and CRM | COMPLETED | 3, 4 |
| 16 | UI and Tailwind modernization | COMPLETED | Stable foundation (2–4+) |
| 17 | Security, performance and production readiness | COMPLETED | Core product phases |
| 18 | Final integration and release | NEEDS REVIEW | 17 |

## Phase notes

### Phase 1 — Audit
Mapped the plain-PHP multi-tenant fashion app: auth, tenant DB guards, modules, orders, production, payments, stock, messaging, tracking, and tests. No code changes.

### Phase 2 — SaaS subscription engine
Additive schema and domain for plans, tenant subscriptions, subscription payments, and history. Lifecycle: trial, active, expired, suspended, cancelled. Central `subscription_allows` / limit helpers. Registration starts a Starter trial. `subscription_ends_on` remains for tracking/messaging. No subscription UI or live SaaS checkout in this phase.

### Phase 3 — Registration and onboarding
Extended company types (boutique, ready-to-wear), guided onboarding wizard after HTTP registration, profile fields (address, currency, branding, logo), optional first staff and stock, trial confirmation. Repeat registration while signed in does not create another tenant. Domain `business_register` without `start_onboarding` stays complete for tests/seeds.

### Phase 4 — Subscription access control
`require_access` and `require_module` enforce entitlement. Expired/suspended shops are redirected to `/account/subscription` (renewal stays open). Plan module and limit checks block features and over-limit staff. Manual renew for owners until Phase 6 billing.

### Phase 5 — Gmingo platform administration
Platform tenant + `platform_admin` operator. `/platform` lists shops, edits plans, and suspends/reactivates subscriptions via tenant-bound domain calls. Shop owners are blocked from platform routes; platform admins are redirected away from shop menus. Initial password must be a strong config value (no default `password`); first login forces change (`password_must_change`, migration `030`).

### Phase 6 — Subscription billing and payment integration
Platform SaaS Paystack keys (`saas_paystack_*` in config) drive subscription checkout. Mutable `fashion_subscription_checkouts` hold pending intents. **Authoritative fulfillment** is the webhook `/webhooks/saas-paystack` (and optional CSRF-protected `POST /account/billing/verify`). `GET /account/billing/return` only peeks status and never fulfills. Separate from shop order Paystack. Manual renew remains when SaaS keys are empty.

### Phase 7 — Staff management and RBAC
Shop roles `owner`, `manager`, `staff`, `tailor` with permission keys gated through `user_can` / menu access. `/staff` CRUD for owners and managers (module `fashion.staff`): add, edit, deactivate/reactivate, phone and start date. Billing renew stays owner-only. Attendance, job assignment, and pay history remain for later phases.

### Phase 8 — Product catalogue and inventory expansion
Migration `023_products_catalogue.sql`: products with sizes/colours, BOM lines, stock `kind` and `unit_cost_kobo`. `/products` with catalogue permission when sales or inventory is on. Plan `max_products` enforced. Sizes/colours are JSON lists on the product (no separate variant table).

### Phase 9 — Sales, POS and returns
Migration `024_sales.sql`. `/sales` records retail/wholesale/online lines, reduces linked finished stock via append-only movements, and supports returns that reverse stock. Stock floor is enforced inside `stock_post_movement` with row locks.

### Phase 10 — Expenses, suppliers and purchasing
Migration `025_purchasing.sql`. `/purchasing` for suppliers and purchase orders; receiving posts stock movements and unit cost. `/expenses` records finance expenses in kobo.

### Phase 11 — Advanced fashion production management
Migration `026_work_orders.sql`. `/work-orders` for batch jobs, status changes, and quality checks (pass/fail with reason).

### Phase 12 — Branch management
Migration `027_branches.sql`. `/branches` CRUD with `max_branches` limit; stock movements may carry `branch_id`.

### Phase 13 — Reporting and business analytics
`/reports` summarizes open orders, outstanding balances, sales totals, expenses, and a sales-minus-expenses estimate.

### Phase 14 — Customer storefront and online ordering
Migration `029_storefront.sql`. Optional slug + enable flag; public `/shop/{slug}` lists active products and places online sales (tenant-bound, CSRF on POST). Public order POST is rate-limited by `REMOTE_ADDR` + slug.

### Phase 15 — Marketing and CRM
Migration `028_broadcasts.sql`. `/broadcasts` saves offers counted only against customers with `messages_opt_in = 1`.

### Phase 16 — UI and Tailwind modernization
**Implemented:** local Tailwind 3 build (dev-only Node). Compiled CSS is self-hosted at `public/assets/tailwind.css`. No CDN. See `docs/FRONTEND.md`.

**Partially migrated:** storefront shop and sales screens use selected Tailwind utilities/components alongside legacy classes.

**Legacy CSS still in use:** `public/assets/app.css` remains the primary stylesheet for nearly all screens. Preflight is disabled so Tailwind does not reset existing styles.

### Phase 17 — Security and production readiness
Documented in `docs/SECURITY.md`: platform admin credential rules, stock floor, billing GET non-mutation, storefront rate limits, tenant isolation tests.

### Phase 18 — Final integration and release
Roadmap/progress/architecture/changelog updated for remediation. Expanded automated tests in `tests/release_remediation_test.php`. Status remains **NEEDS REVIEW** until a release commit is approved after verification.
