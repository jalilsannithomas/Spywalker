<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

try {
    // Start transaction
    $conn->begin_transaction();

    // First, get all foreign key constraints
    $fk_query = "SELECT CONSTRAINT_NAME 
                 FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = 'spywalker' 
                 AND TABLE_NAME = 'player_stats' 
                 AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
    
    $result = $conn->query($fk_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $constraint_name = $row['CONSTRAINT_NAME'];
            $drop_fk = "ALTER TABLE player_stats DROP FOREIGN KEY " . $constraint_name;
            if (!$conn->query($drop_fk)) {
                throw new Exception("Error dropping foreign key {$constraint_name}: " . $conn->error);
            }
            echo "Dropped foreign key constraint: {$constraint_name}<br>";
        }
    }

    // Now drop indexes safely
    $conn->query("ALTER TABLE player_stats DROP INDEX IF EXISTS player_id");
    $conn->query("ALTER TABLE player_stats DROP INDEX IF EXISTS match_id");
    $conn->query("ALTER TABLE player_stats DROP INDEX IF EXISTS player_match_unique");
    echo "Dropped indexes<br>";
    
    // Add foreign key constraints
    $add_player_fk = "ALTER TABLE player_stats 
                      ADD CONSTRAINT fk_player_stats_player 
                      FOREIGN KEY (player_id) REFERENCES players(id)";
    
    $add_match_fk = "ALTER TABLE player_stats 
                     ADD CONSTRAINT fk_player_stats_match 
                     FOREIGN KEY (match_id) REFERENCES matches(id)";
    
    // Add unique constraint
    $add_unique = "ALTER TABLE player_stats 
                   ADD CONSTRAINT player_match_unique 
                   UNIQUE (player_id, match_id)";
    
    // Execute the alterations
    if (!$conn->query($add_player_fk)) {
        throw new Exception("Error adding player foreign key: " . $conn->error);
    }
    echo "Added player foreign key constraint<br>";
    
    if (!$conn->query($add_match_fk)) {
        throw new Exception("Error adding match foreign key: " . $conn->error);
    }
    echo "Added match foreign key constraint<br>";
    
    if (!$conn->query($add_unique)) {
        throw new Exception("Error adding unique constraint: " . $conn->error);
    }
    echo "Added unique constraint<br>";
    
    // Show final table structure
    $desc_query = "SHOW CREATE TABLE player_stats";
    $result = $conn->query($desc_query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<pre>Final table structure:\n";
        print_r($row);
        echo "</pre>";
    }
    
    // Commit transaction
    $conn->commit();
    echo "All changes committed successfully";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>
