<?php
require_once('../config/db.php');

header('Content-Type: application/json');

if (!isset($_GET['player_id']) || !isset($_GET['match_id'])) {
    echo json_encode(['error' => 'Player ID and Match ID are required']);
    exit;
}

$player_id = (int)$_GET['player_id'];
$match_id = (int)$_GET['match_id'];

// Get player stats for this match
$query = "SELECT * FROM player_stats 
          WHERE user_id = ? AND match_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $player_id, $match_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

if (!$stats) {
    // Return empty stats if none exist
    echo json_encode([
        'minutes_played' => 0,
        'points_scored' => 0,
        'assists' => 0,
        'rebounds' => 0,
        'steals' => 0,
        'blocks' => 0,
        'turnovers' => 0,
        'fouls' => 0
    ]);
} else {
    echo json_encode($stats);
}
