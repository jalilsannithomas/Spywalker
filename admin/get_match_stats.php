<?php
require_once('../config/db.php');

header('Content-Type: application/json');

if (!isset($_GET['match_id'])) {
    echo json_encode(['error' => 'Match ID is required']);
    exit;
}

$match_id = (int)$_GET['match_id'];

// Get match details
$match_query = "SELECT m.*, 
                ht.name as home_team, 
                at.name as away_team,
                ts.points_scored as team_score
                FROM matches m
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                LEFT JOIN team_stats ts ON m.id = ts.match_id
                WHERE m.id = ?";

$stmt = $conn->prepare($match_query);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$result = $stmt->get_result();
$match = $result->fetch_assoc();

if (!$match) {
    echo json_encode(['error' => 'Match not found']);
    exit;
}

// Get player stats for this match
$stats_query = "SELECT ps.*, 
                u.first_name, u.last_name,
                t.name as team_name
                FROM player_stats ps
                JOIN users u ON ps.user_id = u.id
                JOIN teams t ON ps.team_id = t.id
                WHERE ps.match_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$player_stats = [];

while ($stat = $stats_result->fetch_assoc()) {
    $player_stats[] = $stat;
}

echo json_encode([
    'home_team' => $match['home_team'],
    'away_team' => $match['away_team'],
    'home_score' => $match['home_score'],
    'away_score' => $match['away_score'],
    'player_stats' => $player_stats
]);
