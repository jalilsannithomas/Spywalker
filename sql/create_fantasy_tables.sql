-- Create fantasy_points table to store calculated fantasy points
CREATE TABLE IF NOT EXISTS fantasy_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    match_id INT NOT NULL,
    points_scored DECIMAL(10,2) DEFAULT 0, -- Fantasy points scored in the match
    week_number INT NOT NULL,              -- Week number of the season
    month_number INT NOT NULL,             -- Month number of the season
    season_year INT NOT NULL,              -- Season year
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id),
    FOREIGN KEY (match_id) REFERENCES matches(id),
    UNIQUE KEY unique_player_match (player_id, match_id)
);

-- Create fantasy_leaderboards table to store aggregated scores
CREATE TABLE IF NOT EXISTS fantasy_leaderboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    total_points DECIMAL(10,2) DEFAULT 0,
    week_number INT NOT NULL,
    month_number INT NOT NULL,
    season_year INT NOT NULL,
    ranking INT DEFAULT 0,
    leaderboard_type ENUM('weekly', 'monthly', 'season') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id),
    UNIQUE KEY unique_player_period (player_id, week_number, month_number, season_year, leaderboard_type)
);
