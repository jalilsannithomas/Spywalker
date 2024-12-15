<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get POST data
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to send messages']);
    exit;
}

if (!$receiver_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

try {
    // First verify receiver exists
    $user_check = "SELECT COUNT(*) FROM users WHERE id = :receiver_id";
    $check_stmt = $conn->prepare($user_check);
    $check_stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Receiver not found']);
        exit;
    }

    // Insert the message
    $sql = "INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at, updated_at) 
            VALUES (:sender_id, :receiver_id, :message, 0, NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':sender_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $message_id = $conn->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'message_id' => $message_id,
            'data' => [
                'id' => $message_id,
                'sender_id' => $_SESSION['user_id'],
                'receiver_id' => $receiver_id,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error sending message']);
}
