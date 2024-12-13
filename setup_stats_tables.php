<?php
require_once 'config/db.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents('sql/create_stats_tables.sql');
    
    // Split SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->query($statement);
        }
    }
    
    echo "Successfully created stats tables!";
    
} catch (Exception $e) {
    echo "Error creating tables: " . $e->getMessage();
}
