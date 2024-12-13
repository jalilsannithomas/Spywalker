-- Add fantasy_team_name column to users table
ALTER TABLE users ADD COLUMN fantasy_team_name VARCHAR(50) DEFAULT 'My Fantasy Team';
