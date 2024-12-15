-- Add related data for existing users

-- Update athlete profiles that don't have all fields populated
UPDATE athlete_profiles SET
    years_of_experience = CASE user_id
        WHEN 4 THEN 4  -- Jalil
        WHEN 8 THEN 3  -- Descham
        WHEN 25 THEN 4 -- Tom
        WHEN 26 THEN 3 -- James
        WHEN 27 THEN 3 -- David
        WHEN 28 THEN 4 -- Marcus
        WHEN 29 THEN 3 -- Kevin
        WHEN 30 THEN 2 -- Chris
        WHEN 31 THEN 4 -- Mike
        WHEN 32 THEN 3 -- Ryan
        WHEN 33 THEN 2 -- Daniel
        WHEN 34 THEN 4 -- Luis
        WHEN 35 THEN 3 -- Carlos
        WHEN 36 THEN 2 -- Juan
    END,
    school_year = CASE user_id
        WHEN 4 THEN 'Senior'
        WHEN 8 THEN 'Junior'
        WHEN 25 THEN 'Senior'
        WHEN 26 THEN 'Junior'
        WHEN 27 THEN 'Junior'
        WHEN 28 THEN 'Senior'
        WHEN 29 THEN 'Junior'
        WHEN 30 THEN 'Sophomore'
        WHEN 31 THEN 'Senior'
        WHEN 32 THEN 'Junior'
        WHEN 33 THEN 'Sophomore'
        WHEN 34 THEN 'Senior'
        WHEN 35 THEN 'Junior'
        WHEN 36 THEN 'Sophomore'
    END
WHERE user_id IN (4, 8, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36);

-- Update athlete performance metrics for existing athletes
UPDATE athlete_performance_metrics SET
    base_rating = CASE athlete_id
        -- Football Athletes
        WHEN 25 THEN 85.5  -- Tom Wilson - Quarterback
        WHEN 26 THEN 82.3  -- James Anderson - Wide Receiver
        WHEN 27 THEN 79.8  -- David Martinez - Running Back
        -- Basketball Athletes
        WHEN 28 THEN 88.2  -- Marcus Johnson - Point Guard
        WHEN 29 THEN 86.7  -- Kevin Thompson - Center
        WHEN 30 THEN 81.4  -- Chris Lee - Forward
        -- Baseball Athletes
        WHEN 31 THEN 84.6  -- Mike Rodriguez - Pitcher
        WHEN 32 THEN 83.1  -- Ryan Garcia - Catcher
        WHEN 33 THEN 80.9  -- Daniel White - First Base
        -- Soccer Athletes
        WHEN 34 THEN 87.3  -- Luis Hernandez - Forward
        WHEN 35 THEN 85.8  -- Carlos Silva - Midfielder
        WHEN 36 THEN 82.5  -- Juan Torres - Goalkeeper
    END,
    achievement_points = CASE athlete_id
        -- Football Athletes
        WHEN 25 THEN 750  -- Tom Wilson
        WHEN 26 THEN 620  -- James Anderson
        WHEN 27 THEN 580  -- David Martinez
        -- Basketball Athletes
        WHEN 28 THEN 820  -- Marcus Johnson
        WHEN 29 THEN 780  -- Kevin Thompson
        WHEN 30 THEN 550  -- Chris Lee
        -- Baseball Athletes
        WHEN 31 THEN 680  -- Mike Rodriguez
        WHEN 32 THEN 640  -- Ryan Garcia
        WHEN 33 THEN 520  -- Daniel White
        -- Soccer Athletes
        WHEN 34 THEN 790  -- Luis Hernandez
        WHEN 35 THEN 740  -- Carlos Silva
        WHEN 36 THEN 590  -- Juan Torres
    END,
    games_played = CASE athlete_id
        -- Football Athletes
        WHEN 25 THEN 45  -- Tom Wilson
        WHEN 26 THEN 38  -- James Anderson
        WHEN 27 THEN 35  -- David Martinez
        -- Basketball Athletes
        WHEN 28 THEN 42  -- Marcus Johnson
        WHEN 29 THEN 40  -- Kevin Thompson
        WHEN 30 THEN 28  -- Chris Lee
        -- Baseball Athletes
        WHEN 31 THEN 36  -- Mike Rodriguez
        WHEN 32 THEN 34  -- Ryan Garcia
        WHEN 33 THEN 25  -- Daniel White
        -- Soccer Athletes
        WHEN 34 THEN 44  -- Luis Hernandez
        WHEN 35 THEN 41  -- Carlos Silva
        WHEN 36 THEN 32  -- Juan Torres
    END
WHERE athlete_id IN (25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36);

-- Insert performance metrics for athletes that don't have them yet
INSERT IGNORE INTO athlete_performance_metrics (athlete_id, base_rating, achievement_points, games_played)
SELECT id, 
    CASE 
        WHEN id = 4 THEN 83.5  -- Jalil
        WHEN id = 8 THEN 81.2  -- Descham
    END,
    CASE 
        WHEN id = 4 THEN 650  -- Jalil
        WHEN id = 8 THEN 580  -- Descham
    END,
    CASE 
        WHEN id = 4 THEN 35  -- Jalil
        WHEN id = 8 THEN 30  -- Descham
    END
FROM users 
WHERE id IN (4, 8) 
AND id NOT IN (SELECT athlete_id FROM athlete_performance_metrics);

-- Insert coach profiles for existing coaches
INSERT INTO coach_profiles (user_id, sport_id, years_of_experience, created_at, updated_at) VALUES
(9, 1, 10, NOW(), NOW()),   -- John Smith - Football
(10, 2, 8, NOW(), NOW()),   -- Sarah Johnson - Basketball
(11, 3, 12, NOW(), NOW()),  -- Michael Brown - Baseball
(12, 4, 15, NOW(), NOW());  -- Emma Davis - Soccer

-- Insert teams
INSERT INTO teams (name, sport_id, coach_id, primary_color, secondary_color, created_at) VALUES
('Eagles', 1, 9, '#1E4D2B', '#FFD700', NOW()),      -- Football - Dark Green & Gold
('Warriors', 2, 10, '#000080', '#C9082A', NOW()),   -- Basketball - Navy & Red
('Sharks', 3, 11, '#004687', '#FFFFFF', NOW()),     -- Baseball - Royal Blue & White
('United', 4, 12, '#7B2240', '#000000', NOW());     -- Soccer - Burgundy & Black

-- Insert team members (using LIMIT 1 to ensure single row from subquery)
INSERT INTO team_members (team_id, athlete_id, created_at) 
SELECT 
    (SELECT id FROM teams WHERE name = 'Eagles' LIMIT 1),
    id,
    NOW()
FROM users 
WHERE id IN (25, 26, 27);  -- Football athletes

INSERT INTO team_members (team_id, athlete_id, created_at)
SELECT 
    (SELECT id FROM teams WHERE name = 'Warriors' LIMIT 1),
    id,
    NOW()
FROM users 
WHERE id IN (28, 29, 30);  -- Basketball athletes

INSERT INTO team_members (team_id, athlete_id, created_at)
SELECT 
    (SELECT id FROM teams WHERE name = 'Sharks' LIMIT 1),
    id,
    NOW()
FROM users 
WHERE id IN (31, 32, 33);  -- Baseball athletes

INSERT INTO team_members (team_id, athlete_id, created_at)
SELECT 
    (SELECT id FROM teams WHERE name = 'United' LIMIT 1),
    id,
    NOW()
FROM users 
WHERE id IN (34, 35, 36);  -- Soccer athletes
