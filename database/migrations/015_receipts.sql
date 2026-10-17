-- One receipt per recorded payment. Amounts are never updated.
-- A reversal adds a void row. The receipt number counter is per business.

CREATE TABLE IF NOT EXISTS fashion_receipt_counters (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    prefix VARCHAR(10) NOT NULL DEFAULT 'RC',
    last_number INT NOT NULL DEFAULT 1000,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_receipt_counters_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_receipts (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    payment_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    receipt_number VARCHAR(40) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    method VARCHAR(20) NOT NULL,
    balance_after_kobo BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_receipts_number (tenant_id, receipt_number),
    UNIQUE KEY uq_fashion_receipts_payment (tenant_id, payment_id),
    KEY idx_fashion_receipts_order (tenant_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_receipt_voids (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    receipt_id CHAR(36) NOT NULL,
    payment_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_receipt_voids_receipt (tenant_id, receipt_id),
    KEY idx_fashion_receipt_voids_payment (tenant_id, payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fashion_receipt_counters (id, tenant_id, prefix, last_number)
SELECT UUID(), t.id, 'RC', 1000
FROM fashion_tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_receipt_counters c WHERE c.tenant_id = t.id
);
