-- Add experience_years column to listed_players table
ALTER TABLE listed_players
ADD COLUMN experience_years INT DEFAULT 0;

-- Update any existing listed players to have 0 years of experience by default
UPDATE listed_players SET experience_years = 0 WHERE experience_years IS NULL;
