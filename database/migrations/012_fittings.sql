-- Fitting times are stored in UTC. Screens show them in Africa/Lagos.
-- A status change updates who and when. Rows are not deleted.

CREATE TABLE IF NOT EXISTS fashion_fittings (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    starts_at DATETIME NOT NULL,
    fitting_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    note VARCHAR(500) NULL,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    status_by CHAR(36) NOT NULL,
    status_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_fittings_tenant_time (tenant_id, starts_at),
    KEY idx_fashion_fittings_order (tenant_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
