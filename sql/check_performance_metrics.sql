-- Check existing performance metrics
SELECT u.id, u.first_name, u.last_name, apm.* 
FROM users u 
LEFT JOIN athlete_performance_metrics apm ON u.id = apm.athlete_id 
WHERE u.role = 'athlete' 
ORDER BY u.id;
