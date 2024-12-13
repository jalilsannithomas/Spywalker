<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

try {
    echo "<h2>Current Table Structure:</h2>";
    
    // Get current table structure
    $desc_query = "DESCRIBE player_stats";
    $result = $conn->query($desc_query);
    if ($result) {
        echo "<pre>Table Fields:\n";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    }
    
    // Get current indexes and constraints
    $index_query = "SHOW INDEX FROM player_stats";
    $result = $conn->query($index_query);
    if ($result) {
        echo "<pre>Indexes:\n";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    }
    
    // Create new table structure
    $conn->query("DROP TABLE IF EXISTS player_stats_new");
    
    $create_table = "CREATE TABLE player_stats_new (
        id INT PRIMARY KEY AUTO_INCREMENT,
        player_id INT NOT NULL,
        match_id INT NOT NULL,
        points INT DEFAULT 0,
        assists INT DEFAULT 0,
        rebounds INT DEFAULT 0,
        steals INT DEFAULT 0,
        blocks INT DEFAULT 0,
        FOREIGN KEY (player_id) REFERENCES players(id),
        FOREIGN KEY (match_id) REFERENCES matches(id),
        UNIQUE KEY player_match_unique (player_id, match_id)
    )";
    
    if ($conn->query($create_table)) {
        echo "Created new table structure successfully<br>";
        
        // Copy data from old table to new table
        $copy_data = "INSERT INTO player_stats_new (player_id, match_id, points, assists, rebounds, steals, blocks)
                      SELECT player_id, match_id, points, assists, rebounds, steals, blocks 
                      FROM player_stats";
        
        if ($conn->query($copy_data)) {
            echo "Copied data successfully<br>";
            
            // Rename tables
            $conn->query("DROP TABLE player_stats");
            $conn->query("RENAME TABLE player_stats_new TO player_stats");
            echo "Table renamed successfully<br>";
        } else {
            throw new Exception("Error copying data: " . $conn->error);
        }
    } else {
        throw new Exception("Error creating new table: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
