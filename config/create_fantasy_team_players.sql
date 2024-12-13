CREATE TABLE IF NOT EXISTS fantasy_team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    athlete_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_collection (user_id, athlete_id)
);
