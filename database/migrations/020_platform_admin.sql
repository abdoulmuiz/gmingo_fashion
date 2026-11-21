-- Platform tenant for Gmingo operators (plan catalogue owner).
-- Admin users are created by platform_bootstrap() / local seed (password hash stays out of SQL).

INSERT INTO fashion_tenants (
    id, tenant_id, name, phone, deposit_percent, capacity_max,
    payment_bank, payment_account, created_at, company_type,
    subscription_ends_on, onboarding_step, onboarding_completed_at,
    currency, address, logo_path, brand_primary, brand_accent
)
SELECT
    '00000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000001',
    'Gmingo Platform',
    '',
    0,
    NULL,
    NULL,
    NULL,
    NOW(),
    'platform',
    NULL,
    'complete',
    NOW(),
    'NGN',
    '',
    NULL,
    '',
    ''
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_tenants existing
    WHERE existing.id = '00000000-0000-4000-8000-000000000001'
);
