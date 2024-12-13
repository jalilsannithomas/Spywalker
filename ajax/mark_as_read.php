<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to mark messages as read']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? null;

if (!$message_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message ID is required']);
    exit;
}

try {
    // Verify the user is the receiver of this message
    $query = "UPDATE messages SET read_at = NOW() 
              WHERE id = ? AND receiver_id = ? AND read_at IS NULL";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to mark message as read: " . $stmt->error);
    }
    
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Message marked as read'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Message not found or already read'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error marking message as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark message as read. Please try again.'
    ]);
}
