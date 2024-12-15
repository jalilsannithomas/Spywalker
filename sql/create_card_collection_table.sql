CREATE TABLE IF NOT EXISTS if0_37912547_spywalker.fan_collected_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fan_id INT NOT NULL,
    athlete_id INT NOT NULL,
    collected_at DATETIME NOT NULL,
    FOREIGN KEY (fan_id) REFERENCES if0_37912547_spywalker.users(id),
    FOREIGN KEY (athlete_id) REFERENCES if0_37912547_spywalker.athlete_profiles(id),
    UNIQUE KEY unique_collection (fan_id, athlete_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
