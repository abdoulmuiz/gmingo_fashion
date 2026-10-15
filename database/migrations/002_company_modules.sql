-- Company type, module switches, and the activity log.
-- This file only adds columns and tables.

ALTER TABLE fashion_tenants
    ADD COLUMN company_type VARCHAR(20) NOT NULL DEFAULT 'tailor';

ALTER TABLE fashion_order_items
    ADD COLUMN product_id CHAR(36) NULL;

CREATE TABLE IF NOT EXISTS fashion_tenant_modules (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    module_key VARCHAR(40) NOT NULL,
    enabled TINYINT NOT NULL,
    changed_at DATETIME NOT NULL,
    changed_by CHAR(36) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_tenant_modules_key (tenant_id, module_key),
    KEY idx_fashion_tenant_modules_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_activity_log (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    action VARCHAR(40) NOT NULL,
    module_key VARCHAR(40) NOT NULL,
    enabled TINYINT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_activity_log_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing companies were tailor shops. Give them the tailor module set once.
INSERT INTO fashion_tenant_modules (id, tenant_id, module_key, enabled, changed_at, changed_by)
SELECT UUID(), owners.tenant_id, modules.module_key, modules.enabled, NOW(), owners.user_id
FROM (
    SELECT tenant_id, MIN(id) AS user_id
    FROM fashion_users
    GROUP BY tenant_id
) owners
JOIN (
    SELECT 'fashion.orders' AS module_key, 1 AS enabled
    UNION ALL SELECT 'fashion.production', 1
    UNION ALL SELECT 'fashion.finance', 1
    UNION ALL SELECT 'fashion.measurements', 1
    UNION ALL SELECT 'fashion.communication', 1
    UNION ALL SELECT 'fashion.inventory', 0
    UNION ALL SELECT 'fashion.sales', 0
    UNION ALL SELECT 'fashion.staff', 0
) modules
WHERE NOT EXISTS (
    SELECT 1 FROM fashion_tenant_modules existing
    WHERE existing.tenant_id = owners.tenant_id AND existing.module_key = modules.module_key
);
