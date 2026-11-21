# Gmingo Fashion — Progress

## Current

| Field | Value |
| --- | --- |
| Completed phases | 1 (audit), 2 (subscription engine) |
| Current phase | None — awaiting approval for Phase 3 |
| Current milestone | — |
| Branch | `feature/phase-2-saas-foundation` |

## Pending

- Phase 3: registration / onboarding / business setup (needs explicit approval).

## Known issues

- Subscription entitlement helpers exist but are not yet enforced on app routes (Phase 4).
- Company types remain `tailor` / `factory` / `brand` until Phase 3 adds boutique and ready-to-wear.
- Roadmap docs were added at Phase 2 close; README still describes a single-shop demo flow for local setup.
- Full `php tests/run.php` is slow (~10+ minutes) largely due to existing combinatorial access tests plus registration work.

## Next actions

1. Obtain approval to start Phase 3.
2. Expand business types and onboarding without breaking trial subscription creation.
3. Keep `subscription_ends_on` behaviour intact for tracking and messaging.
