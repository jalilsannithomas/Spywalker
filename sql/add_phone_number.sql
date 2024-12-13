-- Add phone_number column to coach_profiles table
ALTER TABLE coach_profiles ADD COLUMN phone_number VARCHAR(20) AFTER certification;
