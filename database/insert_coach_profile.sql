-- Insert coach profile for Byron Steele (assuming user ID 6)
INSERT INTO coach_profiles (user_id, sport_id, team_id)
SELECT 
    6 as user_id,
    (SELECT id FROM sports WHERE name = 'Basketball' LIMIT 1) as sport_id,
    (SELECT id FROM teams WHERE name = 'Warriors' LIMIT 1) as team_id
WHERE NOT EXISTS (
    SELECT 1 FROM coach_profiles WHERE user_id = 6
);
