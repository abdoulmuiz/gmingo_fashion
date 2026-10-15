-- Payment mode per business. Existing payments stay manual staff entries.
-- This file only adds columns, a table, and the online payments switch.

ALTER TABLE fashion_payments
    ADD COLUMN provider VARCHAR(20) NOT NULL DEFAULT 'manual',
    ADD COLUMN provider_reference VARCHAR(190) NULL,
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'staff';

ALTER TABLE fashion_payments
    ADD UNIQUE KEY uq_fashion_payments_reference (tenant_id, provider_reference);

CREATE TABLE IF NOT EXISTS fashion_payment_settings (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'manual',
    paystack_public_key VARCHAR(255) NOT NULL DEFAULT '',
    paystack_secret_key_encrypted VARCHAR(500) NOT NULL DEFAULT '',
    paystack_key_last4 VARCHAR(4) NOT NULL DEFAULT '',
    webhook_token VARCHAR(64) NOT NULL,
    connected_at DATETIME NULL,
    updated_by CHAR(36) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_payment_settings_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fashion_tenant_modules (id, tenant_id, module_key, enabled, changed_at, changed_by)
SELECT UUID(), owners.tenant_id, 'fashion.online_payments', 0, NOW(), owners.user_id
FROM (
    SELECT tenant_id, MIN(id) AS user_id
    FROM fashion_users
    GROUP BY tenant_id
) owners
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_tenant_modules existing
    WHERE existing.tenant_id = owners.tenant_id
      AND existing.module_key = 'fashion.online_payments'
);
