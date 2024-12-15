-- Check existing athlete profiles
SELECT u.id, u.first_name, u.last_name, u.role, ap.* 
FROM users u 
LEFT JOIN athlete_profiles ap ON u.id = ap.user_id 
WHERE u.role = 'athlete' 
ORDER BY u.id;
