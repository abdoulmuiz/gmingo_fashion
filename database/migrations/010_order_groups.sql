-- Group orders keep an event date and who pays. A shared payment is split
-- across member orders. Each split is its own append-only payment row.

ALTER TABLE fashion_order_groups
    ADD COLUMN event_date DATE NULL,
    ADD COLUMN payer_customer_id CHAR(36) NULL;

ALTER TABLE fashion_payments
    ADD COLUMN group_id CHAR(36) NULL,
    ADD COLUMN group_payment_id CHAR(36) NULL,
    ADD COLUMN payer_customer_id CHAR(36) NULL;

ALTER TABLE fashion_payments
    ADD KEY idx_fashion_payments_group (tenant_id, group_payment_id);
