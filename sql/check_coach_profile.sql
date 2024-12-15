-- Check if coach profile exists
SELECT cp.*, u.username, u.first_name, u.last_name 
FROM coach_profiles cp 
JOIN users u ON cp.user_id = u.id 
WHERE cp.user_id = 6;
