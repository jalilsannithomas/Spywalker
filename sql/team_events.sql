CREATE TABLE IF NOT EXISTS team_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    event_type ENUM('match', 'training', 'tournament', 'meeting', 'social', 'other') NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
