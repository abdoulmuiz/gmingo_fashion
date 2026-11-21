-- Batch work orders and quality checks for production.

CREATE TABLE IF NOT EXISTS fashion_work_orders (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    product_id CHAR(36) NULL,
    title VARCHAR(190) NOT NULL,
    quantity INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planned',
    due_on DATE NULL,
    assigned_user_id CHAR(36) NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_work_orders_tenant (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_quality_checks (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NULL,
    work_order_id CHAR(36) NULL,
    result VARCHAR(20) NOT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    checked_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_quality_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
