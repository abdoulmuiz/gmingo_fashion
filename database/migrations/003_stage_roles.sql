-- Stage role decides which orders are active, ready or delivered.
-- Existing shops take the role from the stage code they already use.

ALTER TABLE fashion_stage_definitions
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'open';

UPDATE fashion_stage_definitions
SET role = 'ready'
WHERE code = 'ready';

UPDATE fashion_stage_definitions
SET role = 'finished'
WHERE code IN ('delivered', 'completed');
