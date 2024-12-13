<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method Not Allowed');
}

$user_id = $_SESSION['user_id'];
$team_name = trim($_POST['team_name'] ?? '');

if (empty($team_name)) {
    header('Location: ../leaderboards.php?error=Team name is required');
    exit();
}

// Check if user already has a team
$check_query = "SELECT id FROM fantasy_teams WHERE user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header('Location: ../leaderboards.php?error=You already have a fantasy team');
    exit();
}

// Create new team
$insert_query = "INSERT INTO fantasy_teams (user_id, team_name) VALUES (?, ?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("is", $user_id, $team_name);

if ($stmt->execute()) {
    header('Location: ../leaderboards.php?success=Team created successfully');
} else {
    header('Location: ../leaderboards.php?error=Failed to create team');
}
exit();
?>
