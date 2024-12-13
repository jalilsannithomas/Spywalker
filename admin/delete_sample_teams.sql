-- First delete from team_players table
DELETE FROM team_players 
WHERE team_id IN (SELECT id FROM teams WHERE name LIKE 'Sample Team%');

-- Then delete from team_members table if it exists
DELETE FROM team_members 
WHERE team_id IN (SELECT id FROM teams WHERE name LIKE 'Sample Team%');

-- Delete any matches involving these teams if they exist
DELETE FROM matches 
WHERE home_team_id IN (SELECT id FROM teams WHERE name LIKE 'Sample Team%')
OR away_team_id IN (SELECT id FROM teams WHERE name LIKE 'Sample Team%');

-- Finally delete the teams
DELETE FROM teams 
WHERE name LIKE 'Sample Team%';
