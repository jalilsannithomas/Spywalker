DROP TABLE IF EXISTS team_members;

CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    athlete_id INT NOT NULL,
    is_listed BOOLEAN NOT NULL DEFAULT FALSE,
    join_date DATE NOT NULL,
    UNIQUE KEY unique_athlete (athlete_id, is_listed),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
