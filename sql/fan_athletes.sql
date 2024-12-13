-- Create table for fans to follow athletes
CREATE TABLE IF NOT EXISTS fan_followed_athletes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fan_id INT NOT NULL,
    athlete_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fan_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fan_athlete (fan_id, athlete_id)
);
