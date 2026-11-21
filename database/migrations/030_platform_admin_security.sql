-- Platform admin must change an initial configured password once after bootstrap.

ALTER TABLE fashion_users
    ADD COLUMN password_must_change TINYINT NOT NULL DEFAULT 0;
