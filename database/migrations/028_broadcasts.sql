-- Marketing broadcasts to opted-in customers (stored for WhatsApp/copy).

CREATE TABLE IF NOT EXISTS fashion_broadcasts (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    audience VARCHAR(40) NOT NULL DEFAULT 'opt_in',
    recipient_count INT NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_broadcasts_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
