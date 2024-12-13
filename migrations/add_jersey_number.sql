-- Add jersey_number column to players table
ALTER TABLE players
ADD COLUMN jersey_number VARCHAR(10) DEFAULT NULL AFTER name;
