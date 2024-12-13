<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests
error_log("send_message.php accessed - Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw request body: " . file_get_contents('php://input'));

session_start();
header('Content-Type: application/json');

require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in - Session data: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Decoded request data: " . print_r($data, true));

if (!isset($data['receiver_id']) || !isset($data['message'])) {
    error_log("Missing required fields - receiver_id or message");
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $data['receiver_id'];
$message = $data['message'];

error_log("Attempting to send message - From: $sender_id, To: $receiver_id");

try {
    // Insert the message
    $sql = "INSERT INTO messages (sender_id, receiver_id, message_text, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    error_log("Message sent successfully - Message ID: " . $conn->insert_id);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
}
