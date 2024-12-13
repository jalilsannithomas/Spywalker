-- Create team_events table
CREATE TABLE IF NOT EXISTS team_events (
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

-- Add some sample events
INSERT INTO team_events (team_id, title, event_type, event_date, event_time, location, description) VALUES
(1, 'Team Practice', 'training', CURDATE(), '15:00:00', 'Main Field', 'Regular team practice session'),
(1, 'Home Game vs Eagles', 'match', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '19:00:00', 'Home Stadium', 'Regular season game'),
(1, 'Team Meeting', 'meeting', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00', 'Team Room', 'Strategy discussion for upcoming game'),
(2, 'Away Game vs Lions', 'match', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '20:00:00', 'Lions Stadium', 'Regular season game'),
(2, 'Training Session', 'training', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:00:00', 'Practice Field', 'Focus on defensive tactics');
