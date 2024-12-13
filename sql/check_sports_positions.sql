-- Check existing sports
SELECT * FROM sports;

-- Check existing positions
SELECT p.*, s.name as sport_name 
FROM positions p 
LEFT JOIN sports s ON p.sport_id = s.id;
