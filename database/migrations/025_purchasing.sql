-- Suppliers and purchase orders. Receiving posts append-only stock movements.

CREATE TABLE IF NOT EXISTS fashion_suppliers (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    note VARCHAR(500) NOT NULL DEFAULT '',
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_suppliers_tenant (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_purchase_orders (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    supplier_id CHAR(36) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    ordered_on DATE NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    received_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_po_tenant (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_purchase_order_lines (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    purchase_order_id CHAR(36) NOT NULL,
    stock_item_id CHAR(36) NOT NULL,
    quantity_hundredths INT NOT NULL,
    unit_cost_kobo BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_fashion_po_lines (tenant_id, purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
