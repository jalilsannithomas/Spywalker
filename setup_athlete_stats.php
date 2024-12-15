<?php
require_once 'config/db.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents('sql/create_athlete_stats_table.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo "Athlete stats table created successfully!";
    
} catch(PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}
?>
