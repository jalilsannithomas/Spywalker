<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

try {
    // Drop tables in correct order (child tables first)
    $tables_to_drop = [
        "team_events",
        "event_attendance",
        "events",
        "team_members",
        "teams",
        "athlete_profiles",
        "coach_profiles",
        "positions",  
        "sports",     
        "users"
    ];
    
    foreach ($tables_to_drop as $table) {
        $sql = "DROP TABLE IF EXISTS $table";
        $conn->exec($sql);
        echo "Dropped table $table if it existed\n";
    }
    
    // Create users table
    $sql = "CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role ENUM('admin', 'athlete', 'coach', 'fan') NOT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    echo "Users table created successfully!\n";

    // Create sports table
    $sql = "CREATE TABLE sports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    echo "Sports table created successfully!\n";

    // Insert default sports
    $sql = "INSERT INTO sports (name, description) VALUES
        ('Basketball', 'Indoor basketball'),
        ('Football', 'American football'),
        ('Soccer', 'Association football/soccer'),
        ('Baseball', 'Baseball'),
        ('Volleyball', 'Indoor volleyball')";
    $conn->exec($sql);
    echo "Default sports added!\n";

    // Create positions table
    $sql = "CREATE TABLE positions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(50) NOT NULL,
        sport_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sport_id) REFERENCES sports(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    echo "Positions table created successfully!\n";

    // Insert default positions for basketball
    $basketball_id = $conn->query("SELECT id FROM sports WHERE name = 'Basketball'")->fetch(PDO::FETCH_ASSOC)['id'];
    $sql = "INSERT INTO positions (name, sport_id) VALUES
        ('Point Guard', $basketball_id),
        ('Shooting Guard', $basketball_id),
        ('Small Forward', $basketball_id),
        ('Power Forward', $basketball_id),
        ('Center', $basketball_id)";
    $conn->exec($sql);
    echo "Default basketball positions added!\n";

    // Create athlete_profiles table
    $sql = "CREATE TABLE athlete_profiles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        sport_id INT NOT NULL,
        position_id INT NOT NULL,
        jersey_number INT,
        height_feet INT,
        height_inches INT,
        weight INT,
        years_of_experience INT,
        school_year ENUM('Freshman', 'Sophomore', 'Junior', 'Senior', 'Graduate') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (sport_id) REFERENCES sports(id),
        FOREIGN KEY (position_id) REFERENCES positions(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    echo "Athlete profiles table created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error setting up database: " . $e->getMessage();
    error_log("Database Error: " . $e->getMessage());
}
