-- Customer-supplied fabric changes are append-only. The order keeps the current yards.

CREATE TABLE IF NOT EXISTS fashion_order_fabric_log (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    field_name VARCHAR(20) NOT NULL,
    old_hundredths INT NULL,
    new_hundredths INT NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_order_fabric_log_seq (seq),
    KEY idx_fashion_order_fabric_log_order (tenant_id, order_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
