-- Add color columns to teams table
ALTER TABLE teams
ADD COLUMN primary_color VARCHAR(7) DEFAULT '#000000' AFTER coach_id,
ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#FFFFFF' AFTER primary_color;
