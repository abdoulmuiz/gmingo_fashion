-- Staff profile fields for management (FR-35.1).
-- Role values stay on fashion_users.role (owner, manager, staff, tailor).

ALTER TABLE fashion_users
    ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT '',
    ADD COLUMN started_on DATE NULL;
