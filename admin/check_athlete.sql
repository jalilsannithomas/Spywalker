-- Check athlete profile
SELECT u.username, u.id, ap.* 
FROM users u 
LEFT JOIN athlete_profiles ap ON u.id = ap.user_id 
WHERE u.username LIKE '%jalil%';
