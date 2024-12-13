<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests
error_log("send_team_message.php accessed - Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw request body: " . file_get_contents('php://input'));

session_start();
require_once '../config/db.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    error_log("Unauthorized access - Session data: " . print_r($_SESSION, true));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Decoded request data: " . print_r($data, true));

$coach_id = $_SESSION['user_id'];
$team_id = $data['team_id'] ?? null;
$message = $data['message'] ?? null;

error_log("Processing team message - Coach ID: $coach_id, Team ID: $team_id");

if (!$team_id || !$message) {
    error_log("Missing required fields - team_id or message");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Team ID and message are required']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    error_log("Started transaction");

    // Get all team members who are athletes (join with users table to check role)
    $members_query = "SELECT tm.user_id 
                     FROM team_members tm
                     JOIN users u ON tm.user_id = u.id 
                     WHERE tm.team_id = ? AND u.role = 'athlete'";
    $members_stmt = $conn->prepare($members_query);
    $members_stmt->bind_param("i", $team_id);
    $members_stmt->execute();
    $members_result = $members_stmt->get_result();
    
    $member_count = $members_result->num_rows;
    error_log("Found $member_count team members to message");

    // Send message to each team member
    $insert_query = "INSERT INTO messages (sender_id, receiver_id, message_text, created_at) VALUES (?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    $success_count = 0;
    while ($member = $members_result->fetch_assoc()) {
        $insert_stmt->bind_param("iis", $coach_id, $member['user_id'], $message);
        if ($insert_stmt->execute()) {
            $success_count++;
        } else {
            error_log("Failed to send message to user ID: " . $member['user_id'] . " - Error: " . $insert_stmt->error);
        }
    }

    error_log("Successfully sent messages to $success_count out of $member_count members");

    // Commit transaction
    $conn->commit();
    error_log("Transaction committed");
    echo json_encode(['success' => true, 'message' => "Team message sent successfully to $success_count members"]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error in send_team_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send team message: ' . $e->getMessage()]);
}
