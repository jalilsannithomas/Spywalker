-- Insert Charles Yeboah
INSERT INTO listed_players (first_name, last_name, height, sport_id, position_id, added_by)
SELECT 
    'Charles',
    'Yeboah',
    75, -- 6'3"
    (SELECT id FROM sports WHERE name = 'Basketball'),
    (SELECT id FROM positions WHERE name = 'Forward'),
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1);

-- Insert Cade Cunningham
INSERT INTO listed_players (first_name, last_name, height, sport_id, position_id, added_by)
SELECT 
    'Cade',
    'Cunningham',
    78, -- 6'6"
    (SELECT id FROM sports WHERE name = 'Basketball'),
    (SELECT id FROM positions WHERE name = 'Guard'),
    (SELECT id FROM users WHERE role = 'admin' LIMIT 1);
