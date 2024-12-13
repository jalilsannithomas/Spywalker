<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

try {
    // Show table structure
    $show_create = "SHOW CREATE TABLE player_stats";
    $result = $conn->query($show_create);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    } else {
        throw new Exception("Error getting table structure: " . $conn->error);
    }
    
    // Show all foreign keys
    $show_fk = "SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'spywalker'
    AND TABLE_NAME = 'player_stats'
    AND REFERENCED_TABLE_NAME IS NOT NULL";
    
    $result = $conn->query($show_fk);
    if ($result) {
        echo "<h3>Foreign Keys:</h3><pre>";
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    } else {
        throw new Exception("Error getting foreign keys: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
