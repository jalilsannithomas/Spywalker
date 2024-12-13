-- Create fantasy teams table
CREATE TABLE IF NOT EXISTS fantasy_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_team (user_id)
);

-- Create fantasy team players table to track collected players
CREATE TABLE IF NOT EXISTS fantasy_team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fantasy_team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    acquired_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'benched') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fantasy_team_id) REFERENCES fantasy_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_athlete (fantasy_team_id, athlete_id)
);
