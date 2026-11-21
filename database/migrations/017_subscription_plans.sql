-- Platform subscription plans. Shared catalogue for every fashion business.
-- tenant_id is the platform catalogue id (not a shop). Money is whole kobo.

CREATE TABLE IF NOT EXISTS fashion_plans (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    price_monthly_kobo BIGINT NOT NULL,
    price_annual_kobo BIGINT NOT NULL,
    trial_available TINYINT NOT NULL DEFAULT 0,
    trial_days INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    limits_json TEXT NOT NULL,
    modules_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_plans_code (code),
    KEY idx_fashion_plans_tenant (tenant_id),
    KEY idx_fashion_plans_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fashion_plans (
    id, tenant_id, code, name, description,
    price_monthly_kobo, price_annual_kobo, trial_available, trial_days, active,
    limits_json, modules_json, created_at, updated_at
)
SELECT
    '11111111-1111-4111-8111-111111111111',
    '00000000-0000-4000-8000-000000000001',
    'starter',
    'Starter',
    'Trial-friendly plan for a single fashion shop.',
    1500000,
    15000000,
    1,
    14,
    1,
    '{"max_staff":3,"max_branches":1,"max_products":50,"max_storage_mb":256,"max_orders_month":200}',
    '["fashion.orders","fashion.production","fashion.finance","fashion.measurements","fashion.communication","fashion.inventory","fashion.sales","fashion.staff","fashion.online_payments"]',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_plans existing WHERE existing.code = 'starter'
);

INSERT INTO fashion_plans (
    id, tenant_id, code, name, description,
    price_monthly_kobo, price_annual_kobo, trial_available, trial_days, active,
    limits_json, modules_json, created_at, updated_at
)
SELECT
    '22222222-2222-4222-8222-222222222222',
    '00000000-0000-4000-8000-000000000001',
    'growth',
    'Growth',
    'For growing shops that need more staff and catalogue room.',
    3500000,
    35000000,
    1,
    14,
    1,
    '{"max_staff":10,"max_branches":3,"max_products":500,"max_storage_mb":2048,"max_orders_month":2000}',
    '["fashion.orders","fashion.production","fashion.finance","fashion.measurements","fashion.communication","fashion.inventory","fashion.sales","fashion.staff","fashion.online_payments"]',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_plans existing WHERE existing.code = 'growth'
);

INSERT INTO fashion_plans (
    id, tenant_id, code, name, description,
    price_monthly_kobo, price_annual_kobo, trial_available, trial_days, active,
    limits_json, modules_json, created_at, updated_at
)
SELECT
    '33333333-3333-4333-8333-333333333333',
    '00000000-0000-4000-8000-000000000001',
    'business',
    'Business',
    'Higher limits for factories and multi-branch brands.',
    7500000,
    75000000,
    0,
    0,
    1,
    '{"max_staff":50,"max_branches":20,"max_products":5000,"max_storage_mb":10240,"max_orders_month":20000}',
    '["fashion.orders","fashion.production","fashion.finance","fashion.measurements","fashion.communication","fashion.inventory","fashion.sales","fashion.staff","fashion.online_payments"]',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_plans existing WHERE existing.code = 'business'
);
