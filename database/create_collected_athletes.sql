-- Create collected athletes table
CREATE TABLE IF NOT EXISTS collected_athletes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    athlete_id INT NOT NULL,
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_athlete (user_id, athlete_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
