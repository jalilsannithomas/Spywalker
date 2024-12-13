<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode(['error' => 'Method Not Allowed']));
}

$user_id = $_SESSION['user_id'];
$athlete_id = intval($_POST['athlete_id'] ?? 0);

if ($athlete_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'Invalid athlete ID']));
}

// Get user's fantasy team
$team_query = "SELECT id FROM fantasy_teams WHERE user_id = ?";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$team = $result->fetch_assoc();

if (!$team) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'You need to create a fantasy team first']));
}

// Check if player is already on the team
$check_query = "SELECT id FROM fantasy_team_players WHERE fantasy_team_id = ? AND athlete_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $team['id'], $athlete_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'Player is already on your team']));
}

// Add player to team
$insert_query = "INSERT INTO fantasy_team_players (fantasy_team_id, athlete_id) VALUES (?, ?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("ii", $team['id'], $athlete_id);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Player added to your team']);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to add player to team']);
}
?>
