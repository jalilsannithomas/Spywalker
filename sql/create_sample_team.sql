-- First, make sure we have a sport
INSERT IGNORE INTO sports (id, name) VALUES (1, 'Baseball');

-- Create a sample team
INSERT INTO teams (name, sport_id) VALUES ('Sample Team', 1);

-- Get the ID of the team we just created
SELECT @team_id := id FROM teams WHERE name = 'Sample Team' LIMIT 1;

-- Update existing events to use this team
UPDATE team_events SET team_id = @team_id;
