-- Add venue column to matches table
ALTER TABLE matches ADD COLUMN venue VARCHAR(255) NOT NULL DEFAULT '';
