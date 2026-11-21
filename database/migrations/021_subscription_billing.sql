-- SaaS subscription checkout intents (mutable). Separate from append-only
-- fashion_subscription_payments and from shop order Paystack requests.

CREATE TABLE IF NOT EXISTS fashion_subscription_checkouts (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    subscription_id CHAR(36) NULL,
    plan_id CHAR(36) NOT NULL,
    billing_interval VARCHAR(20) NOT NULL,
    intent VARCHAR(20) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    provider VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL,
    authorization_url VARCHAR(500) NULL,
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_subscription_checkouts_ref (provider, provider_reference),
    KEY idx_fashion_subscription_checkouts_tenant (tenant_id, status),
    KEY idx_fashion_subscription_checkouts_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
