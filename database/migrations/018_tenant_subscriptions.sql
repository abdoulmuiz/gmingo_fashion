-- One current subscription row per fashion business.
-- Separate from customer-order payments in fashion_payments.
-- subscription_ends_on on fashion_tenants stays for tracking/messaging compatibility.

CREATE TABLE IF NOT EXISTS fashion_subscriptions (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    plan_id CHAR(36) NOT NULL,
    status VARCHAR(20) NOT NULL,
    billing_interval VARCHAR(20) NOT NULL DEFAULT 'monthly',
    started_on DATE NOT NULL,
    trial_starts_on DATE NULL,
    trial_ends_on DATE NULL,
    current_period_start DATE NULL,
    current_period_end DATE NULL,
    expires_on DATE NULL,
    cancelled_at DATETIME NULL,
    suspended_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_subscriptions_tenant (tenant_id),
    KEY idx_fashion_subscriptions_plan (plan_id),
    KEY idx_fashion_subscriptions_status (tenant_id, status),
    KEY idx_fashion_subscriptions_expires (expires_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_subscription_payments (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    subscription_id CHAR(36) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    provider VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(190) NULL,
    status VARCHAR(20) NOT NULL,
    paid_at DATETIME NULL,
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_subscription_payments_seq (seq),
    UNIQUE KEY uq_fashion_subscription_payments_ref (provider, provider_reference),
    KEY idx_fashion_subscription_payments_sub (tenant_id, subscription_id, seq),
    KEY idx_fashion_subscription_payments_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_subscription_events (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    subscription_id CHAR(36) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NULL,
    from_plan_id CHAR(36) NULL,
    to_plan_id CHAR(36) NULL,
    from_interval VARCHAR(20) NULL,
    to_interval VARCHAR(20) NULL,
    actor_user_id CHAR(36) NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_subscription_events_seq (seq),
    KEY idx_fashion_subscription_events_sub (tenant_id, subscription_id, seq),
    KEY idx_fashion_subscription_events_type (tenant_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing shops keep working: give them a starter trial when none exists.
-- Do not overwrite a shop that already set subscription_ends_on.
INSERT INTO fashion_subscriptions (
    id, tenant_id, plan_id, status, billing_interval, started_on,
    trial_starts_on, trial_ends_on, current_period_start, current_period_end,
    expires_on, cancelled_at, suspended_at, created_at, updated_at
)
SELECT
    UUID(),
    t.id,
    '11111111-1111-4111-8111-111111111111',
    'trial',
    'monthly',
    CURDATE(),
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    NULL,
    NULL,
    NOW(),
    NOW()
FROM fashion_tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_subscriptions s WHERE s.tenant_id = t.id
);

UPDATE fashion_tenants t
INNER JOIN fashion_subscriptions s ON s.tenant_id = t.id
SET t.subscription_ends_on = s.expires_on
WHERE t.subscription_ends_on IS NULL
  AND s.expires_on IS NOT NULL;
