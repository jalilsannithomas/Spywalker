-- Create sports table if it doesn't exist
CREATE TABLE IF NOT EXISTS sports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sport_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default sports
INSERT INTO sports (id, name, description) VALUES
(1, 'Basketball', 'Professional basketball with standard NBA rules'),
(2, 'Football', 'American football following NFL regulations'),
(3, 'Baseball', 'Professional baseball following MLB rules'),
(4, 'Soccer', 'Association football/soccer following FIFA rules'),
(5, 'Volleyball', 'Professional volleyball following FIVB rules'),
(6, 'Tennis', 'Professional tennis following ATP/WTA rules'),
(7, 'Hockey', 'Ice hockey following NHL regulations')
ON DUPLICATE KEY UPDATE 
    description = VALUES(description);
