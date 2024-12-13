<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['message_id'])) {
    echo json_encode(['success' => false, 'message' => 'Message ID is required']);
    exit();
}

$message_id = $input['message_id'];
$user_id = $_SESSION['user_id'];

try {
    // Verify the message exists and belongs to the current user
    $check_sql = "SELECT id FROM messages WHERE id = ? AND receiver_id = ? AND is_read = FALSE";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception('Failed to prepare check statement: ' . $conn->error);
    }

    $check_stmt->bind_param("ii", $message_id, $user_id);
    if (!$check_stmt->execute()) {
        throw new Exception('Failed to execute check statement: ' . $check_stmt->error);
    }

    $result = $check_stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Invalid message or already marked as read');
    }

    // Mark the message as read
    $update_sql = "UPDATE messages SET is_read = TRUE WHERE id = ? AND receiver_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }

    $update_stmt->bind_param("ii", $message_id, $user_id);
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to execute update statement: ' . $update_stmt->error);
    }

    if ($update_stmt->affected_rows === 0) {
        throw new Exception('Failed to mark message as read');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read'
    ]);

} catch (Exception $e) {
    error_log("Error in mark_as_read.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
