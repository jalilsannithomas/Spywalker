-- Create fantasy leagues table if it doesn't exist
CREATE TABLE IF NOT EXISTS fantasy_leagues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy teams table if it doesn't exist
CREATE TABLE IF NOT EXISTS fantasy_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    league_id INT NOT NULL,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES fantasy_leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_league (user_id, league_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy team players table if it doesn't exist
CREATE TABLE IF NOT EXISTS fantasy_team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES fantasy_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_athlete (team_id, athlete_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy points table if it doesn't exist
CREATE TABLE IF NOT EXISTS fantasy_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    match_id INT NOT NULL,
    points_scored DECIMAL(10,2) DEFAULT 0,
    week_number INT NOT NULL,
    month_number INT NOT NULL,
    season_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id),
    FOREIGN KEY (match_id) REFERENCES matches(id),
    UNIQUE KEY unique_player_match (player_id, match_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy leaderboards table if it doesn't exist
CREATE TABLE IF NOT EXISTS fantasy_leaderboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    total_points DECIMAL(10,2) DEFAULT 0,
    week_number INT NOT NULL,
    month_number INT NOT NULL,
    season_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES users(id),
    UNIQUE KEY unique_player_period (player_id, week_number, month_number, season_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create events table if it doesn't exist
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type ENUM('practice', 'game', 'tournament', 'meeting', 'other') NOT NULL DEFAULT 'other',
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create team_events table to link events with teams
CREATE TABLE IF NOT EXISTS team_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    team_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_event (team_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
