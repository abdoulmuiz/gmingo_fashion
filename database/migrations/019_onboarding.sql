-- Onboarding profile fields for new shops.
-- Existing tenants are marked complete so their workflows stay open.

ALTER TABLE fashion_tenants
    ADD COLUMN onboarding_step VARCHAR(40) NOT NULL DEFAULT 'complete',
    ADD COLUMN onboarding_completed_at DATETIME NULL,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'NGN',
    ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN logo_path VARCHAR(255) NULL,
    ADD COLUMN brand_primary VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN brand_accent VARCHAR(20) NOT NULL DEFAULT '';

UPDATE fashion_tenants
SET onboarding_completed_at = COALESCE(onboarding_completed_at, created_at)
WHERE onboarding_completed_at IS NULL;
