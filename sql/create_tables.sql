-- Create followers table
CREATE TABLE IF NOT EXISTS followers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, followed_id)
);

-- Create fantasy teams table
CREATE TABLE IF NOT EXISTS fantasy_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create fantasy team players table
CREATE TABLE IF NOT EXISTS fantasy_team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES fantasy_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_player (team_id, athlete_id)
);

-- Create athlete performance metrics table
CREATE TABLE IF NOT EXISTS athlete_performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    athlete_id INT NOT NULL,
    base_rating DECIMAL(5,2) DEFAULT 0.00,
    achievement_points INT DEFAULT 0,
    games_played INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (athlete_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_athlete (athlete_id)
);

-- Insert some sample data for testing
INSERT IGNORE INTO athlete_performance_metrics (athlete_id, base_rating, achievement_points, games_played)
SELECT id, RAND() * 0.5 + 0.5, FLOOR(RAND() * 1000), FLOOR(RAND() * 50)
FROM users WHERE role = 'athlete';
