-- A measurement field can carry its own unit label.
-- Saved measurement values stay in fashion_measurements.

ALTER TABLE fashion_measurement_fields
    ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT '';
