<?php
require_once 'db.php';

$sql = file_get_contents(__DIR__ . '/create_fantasy_team_players.sql');

if ($conn->multi_query($sql)) {
    echo "Fantasy team players table created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
