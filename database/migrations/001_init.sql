-- Fashion tables. This file never drops anything.
-- Money is whole kobo (BIGINT). Ids are UUIDs. Every table has tenant_id.
-- Later features reuse these columns and tables: group orders, customer-supplied
-- fabric, deposits, worker pay, expenses, and a language code on the user.

CREATE TABLE IF NOT EXISTS fashion_tenants (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    deposit_percent INT NULL,
    capacity_max INT NULL,
    payment_bank VARCHAR(190) NULL,
    payment_account VARCHAR(190) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_tenants_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_users (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'owner',
    lang VARCHAR(10) NOT NULL DEFAULT 'en',
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_users_email (email),
    KEY idx_fashion_users_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_customers (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    customer_type VARCHAR(20) NOT NULL DEFAULT 'regular',
    messages_opt_in TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_customers_tenant (tenant_id),
    KEY idx_fashion_customers_phone (tenant_id, phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_order_groups (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    pay_mode VARCHAR(20) NOT NULL DEFAULT 'each',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_order_groups_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_order_counters (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    prefix VARCHAR(10) NOT NULL DEFAULT 'TK',
    last_number INT NOT NULL DEFAULT 1000,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_order_counters_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_garment_types (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    placeholder TINYINT NOT NULL DEFAULT 0,
    lead_days INT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_garment_types_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_orders (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    order_number VARCHAR(40) NOT NULL,
    due_date DATE NOT NULL,
    urgent TINYINT NOT NULL DEFAULT 0,
    group_id CHAR(36) NULL,
    assigned_user_id CHAR(36) NULL,
    customer_supplied_fabric TINYINT NOT NULL DEFAULT 0,
    yards_received INT NULL,
    yards_used INT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_orders_number (tenant_id, order_number),
    KEY idx_fashion_orders_tenant (tenant_id),
    KEY idx_fashion_orders_customer (tenant_id, customer_id),
    KEY idx_fashion_orders_group (tenant_id, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_order_items (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    garment_type_id CHAR(36) NOT NULL,
    quantity INT NOT NULL,
    price_kobo BIGINT NOT NULL,
    fabric VARCHAR(190) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_fashion_order_items_order (tenant_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_stage_definitions (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_fashion_stage_definitions_tenant (tenant_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_stage_events (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    stage_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_stage_events_seq (seq),
    KEY idx_fashion_stage_events_order (tenant_id, order_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_payments (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    method VARCHAR(20) NOT NULL,
    reverses_payment_id CHAR(36) NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'payment',
    user_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_payments_seq (seq),
    KEY idx_fashion_payments_order (tenant_id, order_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_measurement_fields (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    garment_type_id CHAR(36) NOT NULL,
    field_key VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    placeholder TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_fashion_measurement_fields_garment (tenant_id, garment_type_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_measurements (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    garment_type_id CHAR(36) NOT NULL,
    unit VARCHAR(10) NOT NULL,
    values_json TEXT NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_measurements_seq (seq),
    KEY idx_fashion_measurements_customer (tenant_id, customer_id, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_worker_rates (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    user_id CHAR(36) NULL,
    garment_type_id CHAR(36) NOT NULL,
    stage_id CHAR(36) NULL,
    rate_kobo BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_worker_rates_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_expenses (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NULL,
    category VARCHAR(40) NOT NULL,
    amount_kobo BIGINT NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    spent_on DATE NOT NULL,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_expenses_tenant (tenant_id, spent_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
