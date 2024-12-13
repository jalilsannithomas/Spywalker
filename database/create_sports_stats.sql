-- Set foreign key checks to 0 to allow dropping tables with foreign key constraints
SET FOREIGN_KEY_CHECKS=0;

-- Drop tables in reverse order of dependencies
DROP TABLE IF EXISTS leaderboard_entries;
DROP TABLE IF EXISTS leaderboards;
DROP TABLE IF EXISTS fantasy_team_players;
DROP TABLE IF EXISTS fantasy_teams;
DROP TABLE IF EXISTS fantasy_leagues;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS player_game_stats;
DROP TABLE IF EXISTS stat_categories;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS sports;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Create sports table
CREATE TABLE IF NOT EXISTS sports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
);

-- Create team_members table
CREATE TABLE IF NOT EXISTS team_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'player',
    jersey_number VARCHAR(10),
    position VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create table for game/match records
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sport_id INT NOT NULL,
    home_team_id INT NOT NULL,
    away_team_id INT NOT NULL,
    game_date DATETIME NOT NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    home_score INT DEFAULT 0,
    away_score INT DEFAULT 0,
    venue VARCHAR(255),
    season_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create stat_categories table
CREATE TABLE IF NOT EXISTS stat_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sport_id INT,
    name VARCHAR(50) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    description TEXT,
    points_value DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
);

-- Create player_game_stats table
CREATE TABLE IF NOT EXISTS player_game_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id INT NOT NULL,
    player_id INT NOT NULL,
    stat_category_id INT NOT NULL,
    value INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stat_category_id) REFERENCES stat_categories(id) ON DELETE CASCADE
);

-- Insert default sports
INSERT INTO sports (name, description) VALUES
('Basketball', 'Basketball related statistics'),
('Soccer', 'Soccer/Football related statistics'),
('Volleyball', 'Volleyball related statistics'),
('Baseball', 'Baseball related statistics');

-- Insert Basketball stat categories
INSERT INTO stat_categories (sport_id, name, abbreviation, description, points_value) VALUES
(1, 'Points', 'PTS', 'Total points scored', 1.00),
(1, 'Rebounds', 'REB', 'Total rebounds (offensive + defensive)', 1.25),
(1, 'Assists', 'AST', 'Number of assists', 1.50),
(1, 'Steals', 'STL', 'Number of steals', 2.00),
(1, 'Blocks', 'BLK', 'Number of blocked shots', 2.00),
(1, 'Turnovers', 'TO', 'Number of turnovers', -1.00),
(1, 'Three Pointers Made', '3PM', 'Number of three-pointers made', 0.50),
(1, 'Free Throws Made', 'FTM', 'Number of free throws made', 0.25),
(1, 'Minutes Played', 'MIN', 'Minutes played in game', 0.00);

-- Insert Soccer stat categories
INSERT INTO stat_categories (sport_id, name, abbreviation, description, points_value) VALUES
(2, 'Goals', 'GLS', 'Number of goals scored', 4.00),
(2, 'Assists', 'AST', 'Number of assists', 3.00),
(2, 'Shots on Goal', 'SOG', 'Number of shots on goal', 1.00),
(2, 'Saves', 'SV', 'Number of saves (goalkeeper)', 2.00),
(2, 'Clean Sheets', 'CS', 'Clean sheets (no goals allowed)', 4.00),
(2, 'Yellow Cards', 'YC', 'Number of yellow cards', -1.00),
(2, 'Red Cards', 'RC', 'Number of red cards', -3.00),
(2, 'Minutes Played', 'MIN', 'Minutes played in game', 0.00);

-- Insert Volleyball stat categories
INSERT INTO stat_categories (sport_id, name, abbreviation, description, points_value) VALUES
(3, 'Kills', 'K', 'Number of kills', 2.00),
(3, 'Blocks', 'BLK', 'Number of blocks', 2.00),
(3, 'Aces', 'ACE', 'Number of service aces', 1.50),
(3, 'Digs', 'DIG', 'Number of digs', 1.00),
(3, 'Assists', 'AST', 'Number of assists', 1.00),
(3, 'Service Errors', 'SE', 'Number of service errors', -1.00),
(3, 'Attack Errors', 'AE', 'Number of attack errors', -1.00);

-- Insert Baseball stat categories
INSERT INTO stat_categories (sport_id, name, abbreviation, description, points_value) VALUES
(4, 'Hits', 'H', 'Number of hits', 1.00),
(4, 'Runs', 'R', 'Number of runs scored', 1.00),
(4, 'RBIs', 'RBI', 'Runs batted in', 1.00),
(4, 'Home Runs', 'HR', 'Number of home runs', 4.00),
(4, 'Stolen Bases', 'SB', 'Number of stolen bases', 2.00),
(4, 'Strikeouts', 'K', 'Number of strikeouts (pitching)', 1.00),
(4, 'Walks', 'BB', 'Number of walks', 1.00),
(4, 'Innings Pitched', 'IP', 'Number of innings pitched', 3.00),
(4, 'Earned Runs', 'ER', 'Number of earned runs allowed', -1.00);

-- Create fantasy_leagues table
CREATE TABLE IF NOT EXISTS fantasy_leagues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
);

-- Create fantasy_teams table
CREATE TABLE IF NOT EXISTS fantasy_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    league_id INT NOT NULL,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES fantasy_leagues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create fantasy_team_players table
CREATE TABLE IF NOT EXISTS fantasy_team_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fantasy_team_id INT NOT NULL,
    player_id INT NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fantasy_team_id) REFERENCES fantasy_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create table for weekly/monthly leaderboards
CREATE TABLE IF NOT EXISTS leaderboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    period_type ENUM('weekly', 'monthly', 'season') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES fantasy_leagues(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for leaderboard entries
CREATE TABLE IF NOT EXISTS leaderboard_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leaderboard_id INT NOT NULL,
    fantasy_team_id INT NOT NULL,
    points DECIMAL(10,2) NOT NULL,
    rank INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leaderboard_id) REFERENCES leaderboards(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (fantasy_team_id) REFERENCES fantasy_teams(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
