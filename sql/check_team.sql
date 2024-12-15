-- Check team and its relationships
SELECT t.*, s.name as sport_name, cp.user_id as coach_user_id
FROM teams t
LEFT JOIN sports s ON t.sport_id = s.id
LEFT JOIN coach_profiles cp ON cp.user_id = t.coach_id
WHERE t.coach_id = 6;
