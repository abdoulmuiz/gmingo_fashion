-- Ready-to-wear sales (retail POS). Sale lines reduce finished stock via movements.

CREATE TABLE IF NOT EXISTS fashion_sales (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    sale_number VARCHAR(40) NOT NULL,
    customer_id CHAR(36) NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'retail',
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    total_kobo BIGINT NOT NULL DEFAULT 0,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_sales_number (tenant_id, sale_number),
    KEY idx_fashion_sales_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_sale_lines (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    sale_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    quantity INT NOT NULL,
    unit_price_kobo BIGINT NOT NULL,
    line_total_kobo BIGINT NOT NULL,
    size_label VARCHAR(40) NOT NULL DEFAULT '',
    colour_label VARCHAR(80) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_fashion_sale_lines_sale (tenant_id, sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_sale_counters (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    last_number INT NOT NULL DEFAULT 1000,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_sale_counters_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
