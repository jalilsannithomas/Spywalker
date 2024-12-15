<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

try {
    // First, let's see the table structure
    echo "<h2>Users Table Structure:</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM users");
    echo "<pre>";
    print_r($columns->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

    // Check users table
    $users_query = "SELECT id, first_name, last_name, role FROM users WHERE role = 'athlete'";
    $stmt = $conn->query($users_query);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Athletes in Database:</h2>";
    echo "<pre>";
    print_r($athletes);
    echo "</pre>";

    // Check if tables exist
    $tables = ['athlete_performance_metrics', 'fantasy_teams', 'fantasy_team_players', 'followers'];
    echo "<h2>Table Status:</h2>";
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        echo "$table exists: " . ($check->rowCount() > 0 ? 'Yes' : 'No') . "<br>";
        
        if ($check->rowCount() > 0) {
            $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch();
            echo "Records in $table: " . $count['count'] . "<br>";
        }
    }

} catch (PDOException $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    echo "<br><br>";
    echo "<h2>SQL State:</h2>";
    echo $e->getCode();
}
?>
