-- A customer can have a saved language. Empty means the shop default.

ALTER TABLE fashion_customers
    ADD COLUMN lang VARCHAR(10) NULL;
