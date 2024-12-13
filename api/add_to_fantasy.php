<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to add to your fantasy roster']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$athlete_id = $data['user_id'] ?? null;
$role = $data['role'] ?? null;

if (!$athlete_id || !$role || $role !== 'athlete') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Can only add athletes to fantasy roster.']);
    exit();
}

// Verify the user exists and is actually an athlete
$check_query = "SELECT id FROM users WHERE id = ? AND role = 'athlete'";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $athlete_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Athlete not found']);
    exit();
}

try {
    // Check if athlete is already in user's fantasy roster
    $check_query = "SELECT id FROM fantasy_team_players WHERE user_id = ? AND athlete_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $user_id, $athlete_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This athlete is already in your fantasy roster']);
        exit();
    }
    
    // Check if user has reached the maximum number of athletes (5)
    $count_query = "SELECT COUNT(*) as count FROM fantasy_team_players WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    
    if ($count_result['count'] >= 5) {
        echo json_encode(['success' => false, 'message' => 'Your fantasy roster is full (maximum 5 athletes)']);
        exit();
    }
    
    // Add athlete to fantasy roster
    $insert_query = "INSERT INTO fantasy_team_players (user_id, athlete_id, created_at) VALUES (?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("ii", $user_id, $athlete_id);
    $insert_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Successfully added to your fantasy roster']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
