-- Create teams table if it doesn't exist
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id)
);

-- Create team_players table to link players to teams
CREATE TABLE IF NOT EXISTS team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT,
    athlete_id INT,
    jersey_number INT,
    joined_date DATE,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id)
);

-- Create matches table for team games
CREATE TABLE IF NOT EXISTS matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    home_team_id INT,
    away_team_id INT,
    match_date DATE,
    home_score INT DEFAULT 0,
    away_score INT DEFAULT 0,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    season_year INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id)
);

-- Create player_stats table for individual statistics
CREATE TABLE IF NOT EXISTS player_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT,
    player_id INT,
    points INT DEFAULT 0,
    assists INT DEFAULT 0,
    rebounds INT DEFAULT 0,
    steals INT DEFAULT 0,
    blocks INT DEFAULT 0,
    turnovers INT DEFAULT 0,
    minutes_played INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (player_id) REFERENCES athlete_profiles(id)
);
