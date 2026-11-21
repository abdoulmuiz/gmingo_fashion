-- Multi-branch support. Stock movements may reference a branch.

CREATE TABLE IF NOT EXISTS fashion_branches (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_branches_tenant (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fashion_stock_movements
    ADD COLUMN branch_id CHAR(36) NULL;
