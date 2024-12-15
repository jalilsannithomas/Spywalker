<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'fantasy_setup_error.log');

require_once 'config/db.php';

try {
    // First check if tables already exist
    $check_tables = $conn->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        AND table_name IN ('fantasy_leagues', 'fantasy_teams', 'fantasy_team_players', 'fantasy_points')
    ");
    $table_count = $check_tables->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($table_count == 4) {
        echo "Fantasy league tables already exist!<br>";
        echo "<a href='leaderboards.php'>Go to Fantasy League</a>";
        exit();
    }

    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/database/create_fantasy_tables.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read the SQL file. Check if file exists at: " . __DIR__ . '/database/create_fantasy_tables.sql');
    }
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Begin transaction
    $conn->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...<br>";
            $conn->exec($statement);
        }
    }
    
    // Create fantasy points table if it doesn't exist
    $fantasy_points_sql = "
    CREATE TABLE IF NOT EXISTS fantasy_points (
        id INT PRIMARY KEY AUTO_INCREMENT,
        player_id INT NOT NULL,
        points_scored DECIMAL(10,2) DEFAULT 0,
        week_number INT NOT NULL,
        month_number INT NOT NULL,
        season_year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES athlete_profiles(id) ON DELETE CASCADE,
        UNIQUE KEY unique_player_period (player_id, week_number, month_number, season_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    echo "Creating fantasy_points table...<br>";
    $conn->exec($fantasy_points_sql);
    
    // Commit transaction
    $conn->commit();
    
    echo "Fantasy league tables have been set up successfully!<br>";
    echo "<a href='leaderboards.php'>Go to Fantasy League</a>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error setting up fantasy tables: " . $e->getMessage());
    echo "<h2>Error setting up fantasy tables:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<h3>SQL State:</h3>";
    echo "<pre>" . $e->getCode() . "</pre>";
    if ($e->getPrevious()) {
        echo "<h3>Previous Error:</h3>";
        echo "<pre>" . $e->getPrevious()->getMessage() . "</pre>";
    }
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo "<h2>Error:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
