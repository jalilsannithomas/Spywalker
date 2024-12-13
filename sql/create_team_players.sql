-- Create team_players table to link athletes with teams
CREATE TABLE IF NOT EXISTS team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    jersey_number INT,
    status ENUM('active', 'injured', 'inactive') DEFAULT 'active',
    joined_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_athlete (team_id, athlete_id)
);
