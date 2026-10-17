-- Public tracking. The token is the only public key. Old orders stay null
-- until the shop opens them. Rate-limit rows use a fixed tenant id because
-- the visitor is not logged in yet.

ALTER TABLE fashion_orders
    ADD COLUMN tracking_token VARCHAR(32) NULL,
    ADD UNIQUE KEY uq_fashion_orders_tracking (tracking_token);

ALTER TABLE fashion_tenants
    ADD COLUMN payment_channel VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN payment_reference_note VARCHAR(190) NOT NULL DEFAULT '',
    ADD COLUMN subscription_ends_on DATE NULL;

CREATE TABLE IF NOT EXISTS fashion_track_limits (
    id CHAR(36) NOT NULL,
    tenant_id CHAR(36) NOT NULL,
    ip_key CHAR(64) NOT NULL,
    hits INT NOT NULL,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fashion_track_limits_ip (ip_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
