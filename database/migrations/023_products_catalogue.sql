-- Product catalogue with sizes/colours and bill of materials.
-- Stock items gain kind (raw/finished) and optional unit cost in kobo.

ALTER TABLE fashion_stock_items
    ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'raw',
    ADD COLUMN unit_cost_kobo BIGINT NULL;

CREATE TABLE IF NOT EXISTS fashion_products (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    sku VARCHAR(80) NOT NULL DEFAULT '',
    description VARCHAR(500) NOT NULL DEFAULT '',
    price_kobo BIGINT NOT NULL DEFAULT 0,
    sizes_json TEXT NOT NULL,
    colours_json TEXT NOT NULL,
    active TINYINT NOT NULL DEFAULT 1,
    stock_item_id CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_products_tenant (tenant_id, active),
    KEY idx_fashion_products_sku (tenant_id, sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_product_bom (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    stock_item_id CHAR(36) NOT NULL,
    quantity_hundredths INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_product_bom_line (tenant_id, product_id, stock_item_id),
    KEY idx_fashion_product_bom_product (tenant_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
