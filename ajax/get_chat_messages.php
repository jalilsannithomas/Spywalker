<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', '../logs/chat_debug.log');

// Debug: Log request details
error_log("\n=== GET CHAT MESSAGES REQUEST ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("SESSION: " . print_r($_SESSION, true));
error_log("GET: " . print_r($_GET, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to view messages']);
    exit;
}

$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : -1;

error_log("Fetching messages for user_id: $user_id and other_user_id: $other_user_id, last_message_id: $last_message_id");

if (!$other_user_id) {
    error_log("Invalid other_user_id");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    // First verify both users exist
    $user_check = "SELECT COUNT(*) FROM users WHERE id IN (:user_id, :other_user_id)";
    $check_stmt = $conn->prepare($user_check);
    $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':other_user_id', $other_user_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    $user_count = $check_stmt->fetchColumn();
    error_log("Found $user_count users");
    
    if ($user_count < 2) {
        error_log("One or both users not found");
        throw new Exception("Invalid user(s)");
    }

    // Get messages between the two users
    $sql = "SELECT m.id, m.sender_id, m.receiver_id, m.message as message_text, 
                   m.created_at, m.is_read,
                   CONCAT(u.first_name, ' ', u.last_name) as sender_name
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE ((m.sender_id = :sender_id AND m.receiver_id = :receiver_id) 
            OR (m.sender_id = :receiver_id2 AND m.receiver_id = :sender_id2))";
    
    // Only get messages newer than last_message_id if it's provided and not first load
    if ($last_message_id > -1 && !isset($_GET['first_load'])) {
        $sql .= " AND m.id > :last_message_id";
    }
    
    $sql .= " ORDER BY m.created_at ASC";
    
    error_log("SQL Query: " . $sql);
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $other_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id2', $other_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':sender_id2', $user_id, PDO::PARAM_INT);
    
    if ($last_message_id > -1 && !isset($_GET['first_load'])) {
        $stmt->bindParam(':last_message_id', $last_message_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($messages) . " messages");
    error_log("Messages data: " . print_r($messages, true));
    
    // Mark messages as read
    if (!empty($messages)) {
        $update_query = "UPDATE messages 
                        SET is_read = 1, updated_at = CURRENT_TIMESTAMP 
                        WHERE receiver_id = :user_id 
                        AND sender_id = :other_user_id 
                        AND is_read = 0";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':other_user_id', $other_user_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        $updated_rows = $update_stmt->rowCount();
        error_log("Marked $updated_rows messages as read");
    }
    
    // Return messages in reverse order (oldest first)
    $response = [
        'success' => true, 
        'messages' => array_reverse($messages),
        'debug' => [
            'user_id' => $user_id,
            'other_user_id' => $other_user_id,
            'message_count' => count($messages),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    error_log("Sending response: " . print_r($response, true));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_chat_messages.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch messages',
        'debug' => $e->getMessage()
    ]);
}
