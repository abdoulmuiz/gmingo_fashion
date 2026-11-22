-- Platform SaaS Paystack gateway settings (encrypted secrets).
-- Optional Paystack plan codes on catalogue plans for recurring billing.
-- Webhook receipt log for idempotent event handling.

CREATE TABLE IF NOT EXISTS fashion_saas_gateway_settings (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 0,
    environment VARCHAR(10) NOT NULL DEFAULT 'test',
    public_key VARCHAR(190) NOT NULL DEFAULT '',
    secret_key_encrypted TEXT NOT NULL,
    secret_key_last4 VARCHAR(8) NOT NULL DEFAULT '',
    webhook_secret_encrypted TEXT NOT NULL,
    webhook_secret_last4 VARCHAR(8) NOT NULL DEFAULT '',
    connected_at DATETIME NULL,
    updated_by CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_saas_gateway_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_saas_webhook_receipts (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    provider_reference VARCHAR(190) NULL,
    payload_hash CHAR(64) NOT NULL,
    processed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_saas_webhook_event (event_key),
    KEY idx_fashion_saas_webhook_ref (provider_reference),
    KEY idx_fashion_saas_webhook_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fashion_plans
    ADD COLUMN paystack_plan_code_monthly VARCHAR(80) NOT NULL DEFAULT '' AFTER modules_json,
    ADD COLUMN paystack_plan_code_annual VARCHAR(80) NOT NULL DEFAULT '' AFTER paystack_plan_code_monthly;

ALTER TABLE fashion_subscriptions
    ADD COLUMN paystack_customer_code VARCHAR(80) NULL AFTER suspended_at,
    ADD COLUMN paystack_subscription_code VARCHAR(80) NULL AFTER paystack_customer_code,
    ADD COLUMN cancel_at_period_end TINYINT NOT NULL DEFAULT 0 AFTER paystack_subscription_code;
