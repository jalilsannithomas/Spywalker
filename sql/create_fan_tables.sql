-- Create fan_followed_athletes table
CREATE TABLE IF NOT EXISTS fan_followed_athletes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fan_id INT NOT NULL,
    athlete_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fan_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (fan_id, athlete_id)
);
