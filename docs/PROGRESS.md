# Gmingo Fashion — Progress

## Current

| Field | Value |
| --- | --- |
| Completed phases | 1–17 (Phase 16 Tailwind build done; UI migration partial) |
| Current phase | 18 — final integration / release readiness |
| Current milestone | Visual redesign shipped in working tree; release commit still awaiting approval |
| Branch | `feature/phase-2-saas-foundation` |

## Pending

- Release commit (only when explicitly approved).
- Optional follow-ups: staff attendance/pay history, richer analytics, live WhatsApp send for broadcasts, broader Tailwind screen migration.

## Known issues / limitations

- Onboarding logo uploads require a real multipart file; branding colours work without a logo.
- Full `php tests/run.php` is slow (~10+ minutes), especially menu access combinations.
- Storefront online sales deduct stock only when the product has a linked `stock_item_id` and inventory is enabled.
- Product “variants” are size/colour JSON lists on `fashion_products`, not a separate variant inventory table.
- Primary UI is the redesigned `app.css` system; Tailwind is available on selected screens only.
- True multi-process concurrent stock races rely on MySQL `FOR UPDATE` in `stock_post_movement`; automated tests cover sequential conflicting deductions.

## Next actions

1. Await approval for a release commit after the final verification report.
2. Configure production `config/config.php` with a strong `platform_admin_password` (12+ chars, not a blocked default) and SaaS Paystack keys per `docs/SECURITY.md`.
3. Run `npm run build:css` on a build machine when changing Tailwind-using views; deploy compiled `public/assets/tailwind.css`.
