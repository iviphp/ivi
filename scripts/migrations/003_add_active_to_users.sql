-- 003_add_active_to_users.sql
-- Add the 'active' column to the users table

ALTER TABLE users
ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1
AFTER password;
