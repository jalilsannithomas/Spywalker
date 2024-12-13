<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to view messages']);
    exit;
}

$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if (!$other_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get messages between the two users
    $query = "SELECT id, sender_id, receiver_id, message_text, created_at, read_at 
              FROM messages 
              WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              AND id > ?
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("iiiii", $user_id, $other_user_id, $other_user_id, $user_id, $last_message_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $messages = [];
    
    while ($message = $result->fetch_assoc()) {
        // Mark message as read if it's received by current user
        if ($message['receiver_id'] == $user_id && !$message['read_at']) {
            $update_query = "UPDATE messages SET read_at = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $message['id']);
            $update_stmt->execute();
        }
        
        $messages[] = [
            'id' => $message['id'],
            'sender_id' => $message['sender_id'],
            'receiver_id' => $message['receiver_id'],
            'message_text' => htmlspecialchars($message['message_text']),
            'created_at' => $message['created_at'],
            'read_at' => $message['read_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch messages. Please try again.'
    ]);
}
