<?php
require_once 'config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to execute SQL queries
function executeSQLFile($conn, $sql) {
    try {
        // Split SQL file into individual queries
        $queries = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
        
        foreach ($queries as $query) {
            if ($conn->query($query) === FALSE) {
                throw new Exception("Error executing query: " . $conn->error . "\nQuery: " . $query);
            }
        }
        echo "Successfully executed all queries.<br>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

// Read and execute the SQL file
$sql_file = file_get_contents('sql/create_team_stats_tables.sql');
if ($sql_file === FALSE) {
    die("Error reading SQL file");
}

echo "<h2>Setting up team statistics tables...</h2>";
executeSQLFile($conn, $sql_file);

// Insert some sample data for testing
$sample_data = "
-- Insert sample sports if not exists
INSERT IGNORE INTO sports (name) VALUES 
('Basketball'),
('Football'),
('Soccer');

-- Insert sample teams
INSERT IGNORE INTO teams (name, sport_id) 
SELECT 'Sample Team 1', id FROM sports WHERE name = 'Basketball' LIMIT 1;

INSERT IGNORE INTO teams (name, sport_id) 
SELECT 'Sample Team 2', id FROM sports WHERE name = 'Basketball' LIMIT 1;

-- Link some players to teams (assuming athlete_profiles table exists)
INSERT IGNORE INTO team_players (team_id, athlete_id, jersey_number, joined_date)
SELECT 
    (SELECT id FROM teams WHERE name = 'Sample Team 1' LIMIT 1),
    id,
    FLOOR(RAND() * 100),
    CURRENT_DATE
FROM athlete_profiles
LIMIT 5;

-- Insert a sample match
INSERT IGNORE INTO matches (home_team_id, away_team_id, match_date, home_score, away_score, status, season_year)
SELECT 
    t1.id,
    t2.id,
    CURRENT_DATE,
    75,
    70,
    'completed',
    YEAR(CURRENT_DATE)
FROM teams t1
JOIN teams t2 ON t1.id != t2.id
WHERE t1.name = 'Sample Team 1' AND t2.name = 'Sample Team 2'
LIMIT 1;
";

echo "<h2>Inserting sample data...</h2>";
executeSQLFile($conn, $sample_data);

echo "<h2>Setup completed!</h2>";
echo "<p>You can now return to the <a href='team_stats.php'>Team Stats page</a>.</p>";
?>
