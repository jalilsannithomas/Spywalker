-- Drop existing tables
DROP TABLE IF EXISTS team_players;
DROP TABLE IF EXISTS teams;

-- Create teams table
CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    coach_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (coach_id) REFERENCES users(id)
);

-- Create team_players table
CREATE TABLE team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    jersey_number INT,
    status ENUM('active', 'inactive', 'injured') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id),
    UNIQUE KEY unique_team_player (team_id, athlete_id)
);
