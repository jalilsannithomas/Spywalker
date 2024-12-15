-- Drop existing tables in correct order
DROP TABLE IF EXISTS team_events;
DROP TABLE IF EXISTS event_attendance;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS athlete_profiles;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS sports;
DROP TABLE IF EXISTS users;

-- Create sports table first
CREATE TABLE IF NOT EXISTS sports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default sports
INSERT IGNORE INTO sports (name, description) VALUES
('Basketball', 'Indoor basketball'),
('Football', 'American football'),
('Soccer', 'Association football/soccer'),
('Baseball', 'Baseball'),
('Volleyball', 'Indoor volleyball');

-- Create positions table with sport_id foreign key
CREATE TABLE IF NOT EXISTS positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    sport_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sport-specific positions
INSERT IGNORE INTO positions (name, sport_id) 
SELECT 'Point Guard', id FROM sports WHERE name = 'Basketball'
UNION ALL
SELECT 'Shooting Guard', id FROM sports WHERE name = 'Basketball'
UNION ALL
SELECT 'Small Forward', id FROM sports WHERE name = 'Basketball'
UNION ALL
SELECT 'Power Forward', id FROM sports WHERE name = 'Basketball'
UNION ALL
SELECT 'Center', id FROM sports WHERE name = 'Basketball'
UNION ALL
SELECT 'Quarterback', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Running Back', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Wide Receiver', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Tight End', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Offensive Line', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Defensive Line', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Linebacker', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Cornerback', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Safety', id FROM sports WHERE name = 'Football'
UNION ALL
SELECT 'Forward', id FROM sports WHERE name = 'Soccer'
UNION ALL
SELECT 'Midfielder', id FROM sports WHERE name = 'Soccer'
UNION ALL
SELECT 'Defender', id FROM sports WHERE name = 'Soccer'
UNION ALL
SELECT 'Goalkeeper', id FROM sports WHERE name = 'Soccer'
UNION ALL
SELECT 'Pitcher', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Catcher', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'First Baseman', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Second Baseman', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Third Baseman', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Shortstop', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Outfielder', id FROM sports WHERE name = 'Baseball'
UNION ALL
SELECT 'Outside Hitter', id FROM sports WHERE name = 'Volleyball'
UNION ALL
SELECT 'Middle Blocker', id FROM sports WHERE name = 'Volleyball'
UNION ALL
SELECT 'Setter', id FROM sports WHERE name = 'Volleyball'
UNION ALL
SELECT 'Libero', id FROM sports WHERE name = 'Volleyball'
UNION ALL
SELECT 'Opposite Hitter', id FROM sports WHERE name = 'Volleyball';

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'athlete', 'coach', 'fan') NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create athlete_profiles table
CREATE TABLE IF NOT EXISTS athlete_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    sport_id INT NOT NULL,
    position_id INT NOT NULL,
    jersey_number INT,
    height_feet INT,
    height_inches INT,
    weight INT,
    year_of_experience INT,
    school_year ENUM('Freshman', 'Sophomore', 'Junior', 'Senior') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (position_id) REFERENCES positions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sport_id INT NOT NULL,
    coach_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (coach_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create team_members table
CREATE TABLE IF NOT EXISTS team_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create events table
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type ENUM('game', 'practice', 'meeting', 'other') NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create team_events table
CREATE TABLE IF NOT EXISTS team_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create event_attendance table
CREATE TABLE IF NOT EXISTS event_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT,
    user_id INT,
    status ENUM('attending', 'not_attending', 'maybe') NOT NULL DEFAULT 'maybe',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user if not exists
INSERT IGNORE INTO users (email, password, first_name, last_name, role)
VALUES ('admin@example.com', '$2y$10$8WxhJz0q0mG4j7oBGpqQe.RPeKH8kL8/GRuHEjqK7YkKicCFJMBtu', 'Admin', 'User', 'admin');
