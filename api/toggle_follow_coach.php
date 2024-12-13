<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'coach_followers'");
if ($table_check->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS coach_followers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        follower_id INT NOT NULL,
        coach_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_follow (follower_id, coach_id)
    )";
    
    if (!$conn->query($create_table_sql)) {
        error_log("Failed to create coach_followers table: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database setup error']);
        exit;
    }
}

// Debug logging
error_log("Toggle follow coach request received");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get raw input and log it
$raw_input = file_get_contents('php://input');
error_log("Raw input: " . $raw_input);

$data = json_decode($raw_input, true);
$follower_id = $_SESSION['user_id'];
$coach_id = $data['coach_id'] ?? null;
$action = $data['action'] ?? null;

error_log("Follower ID: $follower_id");
error_log("Coach ID: $coach_id");
error_log("Action: $action");

if (!$coach_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Verify that both users exist
    $user_check = $conn->prepare("SELECT id, role FROM users WHERE id IN (?, ?)");
    $user_check->bind_param("ii", $follower_id, $coach_id);
    $user_check->execute();
    $result = $user_check->get_result();
    
    if ($result->num_rows !== 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid user IDs']);
        exit;
    }

    if ($action === 'follow') {
        $stmt = $conn->prepare("INSERT INTO coach_followers (follower_id, coach_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $follower_id, $coach_id);
        error_log("Attempting to follow coach");
    } else {
        $stmt = $conn->prepare("DELETE FROM coach_followers WHERE follower_id = ? AND coach_id = ?");
        $stmt->bind_param("ii", $follower_id, $coach_id);
        error_log("Attempting to unfollow coach");
    }

    $success = $stmt->execute();
    error_log("Query execution result: " . ($success ? 'success' : 'failed'));
    if (!$success) {
        error_log("MySQL Error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        exit;
    }
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Exception occurred: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
