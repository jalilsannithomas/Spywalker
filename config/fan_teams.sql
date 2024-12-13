-- Create table for fans to follow teams
CREATE TABLE IF NOT EXISTS fan_followed_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fan_id INT NOT NULL,
    team_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fan_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fan_team (fan_id, team_id)
);
