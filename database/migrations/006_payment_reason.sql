-- A reversal keeps its reason on the new row. The original payment is not changed.

ALTER TABLE fashion_payments
    ADD COLUMN reason VARCHAR(500) NULL;
