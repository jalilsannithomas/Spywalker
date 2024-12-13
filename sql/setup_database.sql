-- Create database if not exists
CREATE DATABASE IF NOT EXISTS spywalker;
USE spywalker;

-- Create users table if not exists
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email)
);

-- Create sports table if not exists
CREATE TABLE IF NOT EXISTS sports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL
);

-- Create teams table if not exists
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    sport_id INT NOT NULL,
    coach_id INT NOT NULL,
    FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default sports if not exists
INSERT IGNORE INTO sports (id, name, description) VALUES 
(1, 'Basketball', 'Basketball is a team sport in which two teams compete to score points'),
(2, 'Football', 'American football is a team sport played between two teams'),
(3, 'Soccer', 'Soccer is a team sport played between two teams of eleven players');

-- Create default team if not exists
INSERT IGNORE INTO teams (name, sport_id, coach_id) 
SELECT 'Berekuso Warriors', 1, id 
FROM users 
WHERE username = 'coach_byron' 
LIMIT 1;

-- Create coach_followers table if not exists
CREATE TABLE IF NOT EXISTS coach_followers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    follower_id INT NOT NULL,
    coach_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, coach_id)
);
