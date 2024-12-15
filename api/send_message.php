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

error_log("Message details - Sender: $sender_id, Receiver: $receiver_id, Message: $message");

// Verify both users exist
try {
    $check_users_sql = "SELECT COUNT(*) as count FROM users WHERE id IN (:sender_id, :receiver_id)";
    $check_stmt = $conn->prepare($check_users_sql);
    $check_stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $user_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($user_count != 2) {
        error_log("Invalid users - count returned: $user_count");
        throw new Exception("Invalid sender or receiver ID");
    }
} catch (Exception $e) {
    error_log("Error checking users: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error validating users: ' . $e->getMessage()]);
    exit;
}

try {
    // Insert the message
    $sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (:sender_id, :receiver_id, :message, NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $error = $conn->errorInfo();
        error_log("Failed to prepare statement: " . print_r($error, true));
        throw new Exception("Failed to prepare statement: " . $error[2]);
    }
    
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        $error = $stmt->errorInfo();
        error_log("Failed to execute statement: " . print_r($error, true));
        throw new Exception("Failed to execute statement: " . $error[2]);
    }
    
    $message_id = $conn->lastInsertId();
    error_log("Message sent successfully with ID: $message_id");
    
    echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'message_id' => $message_id]);
} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
}
?>
