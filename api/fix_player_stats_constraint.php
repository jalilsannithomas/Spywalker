<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

try {
    // Drop the incorrect foreign key constraint
    $drop_constraint = "ALTER TABLE player_stats DROP FOREIGN KEY player_stats_ibfk_1";
    if (!$conn->query($drop_constraint)) {
        throw new Exception("Error dropping constraint: " . $conn->error);
    }
    
    // Add the correct foreign key constraint
    $add_constraint = "ALTER TABLE player_stats 
                      ADD CONSTRAINT player_stats_player_fk 
                      FOREIGN KEY (player_id) REFERENCES players(id)";
    if (!$conn->query($add_constraint)) {
        throw new Exception("Error adding constraint: " . $conn->error);
    }
    
    echo "Successfully updated foreign key constraint";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
