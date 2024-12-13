CREATE TABLE IF NOT EXISTS listed_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    height INT,
    sport_id INT,
    position_id INT,
    added_by INT NOT NULL,
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (position_id) REFERENCES positions(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);
