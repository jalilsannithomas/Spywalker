-- Drop existing tables if they exist
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS fantasy_team_players;
DROP TABLE IF EXISTS fantasy_teams;
DROP TABLE IF EXISTS fantasy_leagues;
SET FOREIGN_KEY_CHECKS=1;

-- Create fantasy leagues table
CREATE TABLE IF NOT EXISTS fantasy_leagues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy teams table
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

-- Create fantasy team players table
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
