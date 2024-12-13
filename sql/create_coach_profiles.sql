-- Create coach_profiles table
CREATE TABLE IF NOT EXISTS coach_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    years_experience INT,
    certifications TEXT,
    specialization TEXT,
    achievements TEXT,
    education TEXT,
    coaching_philosophy TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
