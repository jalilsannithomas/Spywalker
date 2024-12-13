<?php
require_once 'config/db.php';

echo "Checking database tables...\n\n";

// Check coach_profiles
$sql = "SELECT cp.*, u.email 
        FROM coach_profiles cp 
        JOIN users u ON u.id = cp.user_id";
$result = $conn->query($sql);
echo "Coach Profiles:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, User ID: {$row['user_id']}, Email: {$row['email']}\n";
}

// Check teams
$sql = "SELECT t.*, cp.user_id as coach_user_id 
        FROM teams t 
        LEFT JOIN coach_profiles cp ON t.coach_id = cp.id";
$result = $conn->query($sql);
echo "\nTeams:\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']}, Coach User ID: {$row['coach_user_id']}\n";
}

// Check team_members
$sql = "SELECT tm.*, ap.first_name, ap.last_name, t.name as team_name 
        FROM team_members tm
        JOIN athlete_profiles ap ON tm.athlete_id = ap.id
        JOIN teams t ON tm.team_id = t.id";
$result = $conn->query($sql);
echo "\nTeam Members:\n";
while ($row = $result->fetch_assoc()) {
    echo "Team: {$row['team_name']}, Player: {$row['first_name']} {$row['last_name']}, Jersey: {$row['jersey_number']}\n";
}
