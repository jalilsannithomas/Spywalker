-- Drop existing tables
DROP TABLE IF EXISTS fantasy_roster;
DROP TABLE IF EXISTS fantasy_teams;
DROP TABLE IF EXISTS player_stats;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS athlete_profiles;
DROP TABLE IF EXISTS coach_profiles;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS sports;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
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

-- Create team_members table
CREATE TABLE team_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    jersey_number INT,
    position VARCHAR(50),
    status ENUM('active', 'inactive', 'injured') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_team_member (team_id, user_id)
);

-- Insert default sport
INSERT INTO sports (name, description) VALUES 
('Basketball', 'Basketball is a team sport in which two teams compete to score points by shooting a ball through a hoop.');

-- Insert admin user
INSERT INTO users (username, email, password, role, first_name, last_name) VALUES 
('admin', 'admin@spywalker.com', '$2y$10$YourHashedPasswordHere', 'admin', 'Admin', 'User');

-- Insert sample team
INSERT INTO teams (name, sport_id, description) VALUES 
('Berekuso Warriors', 1, 'The mighty warriors of Berekuso');
