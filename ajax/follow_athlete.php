<?php
session_start();
require_once '../config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Log the incoming request
error_log("Follow athlete request received");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please log in to follow athletes']);
    exit;
}

// Get POST data
$raw_data = file_get_contents('php://input');
error_log("Raw request data: " . $raw_data);

$data = json_decode($raw_data, true);
$athlete_id = $data['athlete_id'] ?? null;

error_log("Athlete ID from request: " . ($athlete_id ?? 'not set'));

if (!$athlete_id) {
    error_log("Invalid athlete ID");
    echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
    exit;
}

try {
    // Check if already following
    $check_query = "SELECT * FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?";
    error_log("Check query: " . $check_query);
    error_log("Parameters: fan_id=" . $_SESSION['user_id'] . ", athlete_id=" . $athlete_id);
    
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception("Prepare check query failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("ii", $_SESSION['user_id'], $athlete_id);
    if (!$check_stmt->execute()) {
        throw new Exception("Execute check query failed: " . $check_stmt->error);
    }
    
    $result = $check_stmt->get_result();
    $is_following = $result->num_rows > 0;
    error_log("Current follow status: " . ($is_following ? "following" : "not following"));

    if ($is_following) {
        // Unfollow
        $query = "DELETE FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?";
        error_log("Attempting to unfollow");
    } else {
        // Follow
        $query = "INSERT INTO fan_followed_athletes (fan_id, athlete_id) VALUES (?, ?)";
        error_log("Attempting to follow");
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare action query failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $_SESSION['user_id'], $athlete_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute action query failed: " . $stmt->error);
    }

    error_log("Action completed successfully");
    echo json_encode([
        'success' => true, 
        'is_following' => !$is_following,
        'message' => !$is_following ? 'Successfully followed athlete' : 'Successfully unfollowed athlete'
    ]);

} catch (Exception $e) {
    error_log("Error in follow_athlete.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'debug_info' => [
            'user_id' => $_SESSION['user_id'],
            'athlete_id' => $athlete_id,
            'error' => $e->getMessage()
        ]
    ]);
}
