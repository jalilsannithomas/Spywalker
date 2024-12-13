-- Add new columns to teams table
ALTER TABLE teams
ADD COLUMN roster TEXT,
ADD COLUMN primary_color VARCHAR(7) DEFAULT '#000000',
ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#ffffff';
