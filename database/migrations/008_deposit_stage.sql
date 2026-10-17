-- Each shop chooses the stage that waits for its deposit. Percent stays on the tenant.

ALTER TABLE fashion_tenants
    ADD COLUMN deposit_stage_id CHAR(36) NULL;

ALTER TABLE fashion_stage_events
    ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'move';
