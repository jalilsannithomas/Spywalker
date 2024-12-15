-- Add is_active column to users table
ALTER TABLE users
ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 1 AFTER role;

-- Set all existing users to active
UPDATE users SET is_active = 1;
