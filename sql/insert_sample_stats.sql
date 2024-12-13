-- Insert sample stats for athletes
INSERT INTO athlete_stats (athlete_id, points, created_at)
SELECT 
    ap.id,
    ROUND(RAND() * 20 + 10, 1),  -- Random points between 10 and 30
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 7) DAY)  -- Random date within last 7 days
FROM athlete_profiles ap
JOIN users u ON ap.user_id = u.id
WHERE u.role = 'athlete';
