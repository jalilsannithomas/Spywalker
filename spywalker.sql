-- Drop existing tables in correct order (child tables first)
DROP TABLE IF EXISTS fantasy_roster;
DROP TABLE IF EXISTS fantasy_teams;
DROP TABLE IF EXISTS player_stats;
DROP TABLE IF EXISTS team_stats;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS team_events;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS team_players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS athlete_profiles;
DROP TABLE IF EXISTS coach_profiles;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS sports;
DROP TABLE IF EXISTS users;

-- Create users table (no foreign keys)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL,
    profile_image VARCHAR(255),
    bio TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create sports table
CREATE TABLE sports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create positions table
CREATE TABLE positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sport_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    UNIQUE KEY sport_position (sport_id, name)
);

-- Create athlete profiles table
CREATE TABLE athlete_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    height INT,  -- in inches
    weight INT,  -- in pounds
    sport_id INT NOT NULL,
    position_id INT NOT NULL,
    jersey_number INT,
    years_experience INT DEFAULT 0,
    school_year ENUM('Freshman', 'Sophomore', 'Junior', 'Senior') NOT NULL,
    achievements TEXT,
    stats_visibility BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (position_id) REFERENCES positions(id)
);

-- Create coach profiles table
CREATE TABLE coach_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    sport_id INT NOT NULL,
    specialization VARCHAR(100),
    years_experience INT DEFAULT 0,
    certification TEXT,
    teams_coached TEXT,
    achievements TEXT,
    education TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sport_id) REFERENCES sports(id)
);

-- Create teams table
CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    coach_id INT,
    description TEXT,
    primary_color VARCHAR(7) DEFAULT '#000000',
    secondary_color VARCHAR(7) DEFAULT '#ffffff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (coach_id) REFERENCES coach_profiles(id)
);

-- Create team members table
CREATE TABLE team_players (
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

-- Create matches table
CREATE TABLE matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sport_id INT NOT NULL,
    home_team_id INT NOT NULL,
    away_team_id INT NOT NULL,
    match_date DATETIME NOT NULL,
    home_score INT,
    away_score INT,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (home_team_id) REFERENCES teams(id),
    FOREIGN KEY (away_team_id) REFERENCES teams(id)
);

-- Create player stats table
CREATE TABLE player_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    athlete_id INT NOT NULL,
    minutes_played INT DEFAULT 0,
    points INT DEFAULT 0,
    assists INT DEFAULT 0,
    rebounds INT DEFAULT 0,
    steals INT DEFAULT 0,
    blocks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id)
);

-- Create team stats table
CREATE TABLE team_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    match_id INT NOT NULL,
    total_points INT DEFAULT 0,
    total_assists INT DEFAULT 0,
    total_rebounds INT DEFAULT 0,
    total_steals INT DEFAULT 0,
    total_blocks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (match_id) REFERENCES matches(id)
);

-- Create team events table
CREATE TABLE team_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    event_type ENUM('match', 'training', 'tournament', 'meeting', 'social', 'other') NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create fantasy teams table
CREATE TABLE fantasy_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    team_name VARCHAR(100) NOT NULL,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create fantasy roster table
CREATE TABLE fantasy_roster (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fantasy_team_id INT,
    athlete_id INT,
    active_status BOOLEAN DEFAULT true,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fantasy_team_id) REFERENCES fantasy_teams(id),
    FOREIGN KEY (athlete_id) REFERENCES athlete_profiles(id)
);

-- Insert initial sports
INSERT INTO sports (name, description) VALUES
('Basketball', 'Basketball at Ashesi University'),
('Volleyball', 'Volleyball at Ashesi University'),
('Soccer', 'Soccer at Ashesi University');

-- Insert positions for each sport
INSERT INTO positions (sport_id, name) VALUES
-- Basketball positions
(1, 'Point Guard'),
(1, 'Shooting Guard'),
(1, 'Small Forward'),
(1, 'Power Forward'),
(1, 'Center'),
-- Volleyball positions
(2, 'Setter'),
(2, 'Outside Hitter'),
(2, 'Middle Blocker'),
(2, 'Opposite Hitter'),
(2, 'Libero'),
-- Soccer positions
(3, 'Goalkeeper'),
(3, 'Center Back'),
(3, 'Full Back'),
(3, 'Defensive Midfielder'),
(3, 'Central Midfielder'),
(3, 'Attacking Midfielder'),
(3, 'Winger'),
(3, 'Striker');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@ashesi.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Add indexes for better performance
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_user_username ON users(username);
CREATE INDEX idx_athlete_names ON athlete_profiles(first_name, last_name);
CREATE INDEX idx_coach_names ON coach_profiles(first_name, last_name);
