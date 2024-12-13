<?php
require_once('../config/db.php');

header('Content-Type: application/json');

$sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

// Get players who are not in any team for the given sport
$query = "SELECT u.id, u.first_name, u.last_name 
          FROM users u
          JOIN athlete_profiles ap ON u.id = ap.user_id
          LEFT JOIN team_members tm ON u.id = tm.user_id
          WHERE u.role = 'athlete' 
          AND tm.id IS NULL
          AND ap.sport_id = ?
          ORDER BY u.first_name, u.last_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $sport_id);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = $row;
}

echo json_encode($players);
