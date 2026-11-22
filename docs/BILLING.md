# Gmingo Fashion — SaaS subscription billing (Paystack)

Platform subscription payments for Gmingo Tech Solutions. This is **not** shop order Paystack (`/payment_settings`).

## Public pricing

- `/pricing` shows active catalogue plans from `fashion_plans` (no hard-coded prices).
- Platform admins edit names, prices, features, popularity and order on `/platform/plans`.
- Changing a price requires confirmation, bumps `price_version`, and clears Paystack plan codes unless re-entered so recurring charges cannot silently keep an old amount.
- Active subscription periods are not shortened or extended by a catalogue price edit.

## What exists

- One-shot hosted checkout (`/transaction/initialize` → Paystack → `/account/billing/return`)
- Authoritative fulfillment via webhook `POST /webhooks/saas-paystack` or owner `POST /account/billing/verify`
- Optional recurring: map Paystack plan codes on `/platform/plans/{id}`
- Manual renew when checkout is disabled
- Encrypted gateway secrets in `fashion_saas_gateway_settings` (UI: `/platform/gateway`)
- Config fallback: `saas_paystack_secret_key` / `saas_paystack_public_key` still work

## Test-mode setup

1. In Paystack Dashboard → **Test** mode, copy `pk_test_…` and `sk_test_…`.
2. Set `app_key` (≥32 chars) and `public_base_url` (e.g. `https://your-tunnel.example`) in `config/config.php`.
3. Sign in as platform admin → **Payment gateway**:
   - Environment: Test
   - Paste public key; paste secret (saved encrypted, shown masked)
   - Optional webhook signing secret (otherwise the secret key is used for HMAC)
   - Enable Paystack checkout
   - Run **Test connection**
4. Copy the shown **Webhook URL** into Paystack → Settings → Webhooks (Test).
5. Events to enable: `charge.success`, `subscription.create`, `invoice.create`, `invoice.payment_failed`, `subscription.not_renew`, `subscription.disable`.
6. Shop owner → `/account/subscription` → choose plan → Continue to Paystack (test card).

GET `/account/billing/return` never activates access. Webhook or **Verify payment** must succeed.

## Live-mode checklist

1. Switch Paystack Dashboard to **Live**.
2. On `/platform/gateway`, set Environment **Live**, paste `pk_live_…` / `sk_live_…`, enable checkout, test connection.
3. Point the **live** webhook to the same path on the production origin.
4. Confirm `public_base_url` is the production HTTPS origin (no path).
5. Optionally attach Paystack plan codes on each Gmingo plan for auto-renew.
6. Do **not** seed demo shops on production (`environment` must not be `local`).

## Manual renewal

When checkout is disabled (or no secret configured), owners see **Renew subscription** on `/account/subscription`. Platform admins can still suspend / reactivate shops on `/platform/tenants/{id}`.

## Security notes

- Amounts come from `fashion_plans` (kobo), never from the browser.
- Secrets are AES-GCM sealed with `app_key`; UI never shows full secrets.
- Webhook signatures: HMAC-SHA512 over the raw body.
- Fulfilment is idempotent (checkout status + unique payment reference + webhook receipt keys).
- Shop admins cannot open `/platform/gateway` or `/platform/payments`.
