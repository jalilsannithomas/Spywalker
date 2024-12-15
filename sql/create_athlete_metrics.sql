-- Create athlete_performance_metrics table
CREATE TABLE IF NOT EXISTS athlete_performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    athlete_id INT NOT NULL UNIQUE,
    base_rating DECIMAL(4,2) DEFAULT 0.00,
    achievement_points INT DEFAULT 0,
    games_played INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE
);
