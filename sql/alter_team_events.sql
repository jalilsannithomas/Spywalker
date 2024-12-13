-- Add team_id column to team_events table
-- Step 1: Add team_id column as nullable first
ALTER TABLE team_events
ADD COLUMN team_id INT NULL AFTER id;

-- Step 2: Add foreign key constraint
ALTER TABLE team_events
ADD CONSTRAINT fk_team_events_team
FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE;

-- Step 3: Update existing records (replace '1' with an actual team_id that exists in your teams table)
UPDATE team_events SET team_id = 1;

-- Step 4: Make team_id NOT NULL after data is updated
ALTER TABLE team_events
MODIFY COLUMN team_id INT NOT NULL;
