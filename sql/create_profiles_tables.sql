-- Drop existing tables first
DROP TABLE IF EXISTS athlete_profiles;
DROP TABLE IF EXISTS coach_profiles;

-- Create athlete_profiles table
CREATE TABLE athlete_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    height INT NOT NULL,
    weight INT,
    sport_id INT NOT NULL,
    position_id INT NOT NULL,
    jersey_number INT,
    years_experience INT DEFAULT 0,
    school_year ENUM('Freshman', 'Sophomore', 'Junior', 'Senior') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (sport_id) REFERENCES sports(id),
    FOREIGN KEY (position_id) REFERENCES positions(id)
);

-- Create coach_profiles table
CREATE TABLE coach_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    sport_id INT NOT NULL,
    specialization VARCHAR(100),
    years_experience INT DEFAULT 0,
    certification VARCHAR(255),
    education VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (sport_id) REFERENCES sports(id)
);
