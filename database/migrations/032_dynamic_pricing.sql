-- Dynamic public pricing: features, popularity, sort order, currency, price_version.
-- Map growth/business codes to professional/enterprise for the public catalogue.

ALTER TABLE fashion_plans
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'NGN' AFTER price_annual_kobo,
    ADD COLUMN features_json TEXT NULL AFTER modules_json,
    ADD COLUMN is_popular TINYINT NOT NULL DEFAULT 0 AFTER features_json,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER is_popular,
    ADD COLUMN icon VARCHAR(40) NOT NULL DEFAULT '' AFTER sort_order,
    ADD COLUMN price_version INT NOT NULL DEFAULT 1 AFTER icon;

UPDATE fashion_plans
SET features_json = '[]'
WHERE features_json IS NULL OR features_json = '';

UPDATE fashion_plans
SET code = 'professional',
    name = 'Professional',
    description = 'For growing shops that need more staff and catalogue room.',
    is_popular = 1,
    sort_order = 20,
    icon = 'professional',
    features_json = '["Orders and production board","Measurements and fittings","Stock and sales","Staff accounts (up to plan limit)","Priority support"]'
WHERE code = 'growth';

UPDATE fashion_plans
SET code = 'enterprise',
    name = 'Enterprise',
    description = 'Higher limits for factories and multi-branch brands.',
    is_popular = 0,
    sort_order = 30,
    icon = 'enterprise',
    features_json = '["Everything in Professional","More staff and branches","Higher product and order limits","Online payments module","Dedicated onboarding help"]'
WHERE code = 'business';

UPDATE fashion_plans
SET name = 'Starter',
    description = 'Trial-friendly plan for a single fashion shop.',
    is_popular = 0,
    sort_order = 10,
    icon = 'starter',
    features_json = '["Orders and customers","Basic production stages","Measurements","WhatsApp message templates","Email support"]'
WHERE code = 'starter';
