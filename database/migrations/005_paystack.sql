-- Paystack payment links. A pending request is not a payment and does not change a balance.

ALTER TABLE fashion_customers
    ADD COLUMN email VARCHAR(190) NULL;

ALTER TABLE fashion_payment_settings
    ADD UNIQUE KEY uq_fashion_payment_settings_token (webhook_token);

CREATE TABLE IF NOT EXISTS fashion_payment_requests (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    reference VARCHAR(100) NOT NULL,
    authorization_url VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    needs_review TINYINT NOT NULL DEFAULT 0,
    used_placeholder TINYINT NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_payment_requests_reference (reference),
    KEY idx_fashion_payment_requests_order (tenant_id, order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
