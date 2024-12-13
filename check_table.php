<?php
require_once 'config/db.php';

// Check table structure
$sql = "DESCRIBE player_stats";
$result = $conn->query($sql);

if ($result) {
    echo "player_stats table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
