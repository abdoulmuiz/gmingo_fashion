-- Stock on hand is the sum of append-only movements. A mistake is a new row.

CREATE TABLE IF NOT EXISTS fashion_stock_items (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    category VARCHAR(20) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    colour VARCHAR(80) NULL,
    minimum_hundredths INT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_stock_items_tenant (tenant_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_stock_movements (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    item_id CHAR(36) NOT NULL,
    change_hundredths INT NOT NULL,
    reason VARCHAR(20) NOT NULL,
    order_id CHAR(36) NULL,
    user_id CHAR(36) NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_stock_movements_seq (seq),
    KEY idx_fashion_stock_movements_item (tenant_id, item_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
