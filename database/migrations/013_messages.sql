-- Shop copies of message templates, and an append-only log of prepared texts.
-- Staff open WhatsApp themselves. Marking a message sent adds a new row.

CREATE TABLE IF NOT EXISTS fashion_message_templates (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    template_key VARCHAR(40) NOT NULL,
    language VARCHAR(10) NOT NULL,
    body VARCHAR(2000) NOT NULL,
    updated_by CHAR(36) NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_message_templates_key (tenant_id, template_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fashion_messages (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    order_id CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    template_key VARCHAR(40) NOT NULL,
    language VARCHAR(10) NOT NULL,
    body VARCHAR(4000) NOT NULL,
    status VARCHAR(20) NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    prepared_id CHAR(36) NULL,
    change_note VARCHAR(190) NULL,
    user_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fashion_messages_tenant (tenant_id, created_at),
    KEY idx_fashion_messages_order (tenant_id, order_id),
    KEY idx_fashion_messages_event (tenant_id, event_key),
    KEY idx_fashion_messages_prepared (tenant_id, prepared_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
