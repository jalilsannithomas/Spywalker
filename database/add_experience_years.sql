-- Add experience_years column to athlete_profiles table
ALTER TABLE athlete_profiles
ADD COLUMN experience_years INT DEFAULT 0;
