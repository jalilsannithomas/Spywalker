<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

try {
    echo "<h2>Database Connection Test</h2>";
    
    // Test database connection
    echo "<p>Connected to database successfully!</p>";
    
    // Check if sports table exists and has data
    $sports_query = "SELECT * FROM sports WHERE name = 'Basketball' LIMIT 1";
    $result = $conn->query($sports_query);
    $sport = $result->fetch(PDO::FETCH_ASSOC);
    echo "<p>Sports table check: " . ($sport ? "Found Basketball sport with ID: " . $sport['id'] : "No Basketball sport found") . "</p>";
    
    // Check fantasy tables
    $tables = ['fantasy_leagues', 'fantasy_teams', 'fantasy_team_players', 'fantasy_points'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        echo "<p>Table '$table': " . ($check->rowCount() > 0 ? "Exists" : "Does not exist") . "</p>";
    }
    
    // Show all tables in database
    echo "<h3>All Tables in Database:</h3>";
    $tables_query = "SHOW TABLES";
    $tables_result = $conn->query($tables_query);
    echo "<ul>";
    while ($table = $tables_result->fetch(PDO::FETCH_NUM)) {
        echo "<li>" . htmlspecialchars($table[0]) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h2>Database Error:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<h3>Error Code:</h3>";
    echo "<pre>" . htmlspecialchars($e->getCode()) . "</pre>";
}
