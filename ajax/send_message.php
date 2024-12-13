<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to send messages']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$receiver_id = $data['receiver_id'] ?? null;
$message = $data['message'] ?? null;

if (!$receiver_id || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

try {
    // Insert message
    $query = "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $_SESSION['user_id'], $receiver_id, $message);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to send message: " . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $conn->insert_id
    ]);
    
} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message. Please try again.'
    ]);
}
