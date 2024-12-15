-- Create athlete_stats table if it doesn't exist
CREATE TABLE IF NOT EXISTS athlete_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    athlete_id INT NOT NULL,
    points DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add some sample stats if needed (commented out for safety)
/*
INSERT INTO athlete_stats (athlete_id, points) 
SELECT id, 10.5 
FROM athlete_profiles 
WHERE user_id = YOUR_USER_ID 
LIMIT 1;
*/
