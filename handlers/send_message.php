<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Log incoming request
error_log("Message handler called with POST data: " . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not authenticated");
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Validate input
if (!isset($_POST['receiver_id']) || !isset($_POST['content']) || empty(trim($_POST['content']))) {
    error_log("Missing required fields: receiver_id=" . ($_POST['receiver_id'] ?? 'not set') . ", content=" . ($_POST['content'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$content = trim($_POST['content']);

try {
    // Validate receiver exists
    $check_sql = "SELECT id FROM users WHERE id = ? AND is_active = TRUE";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception('Failed to prepare check statement: ' . $conn->error);
    }

    $check_stmt->bind_param("i", $receiver_id);
    if (!$check_stmt->execute()) {
        throw new Exception('Failed to execute check statement: ' . $check_stmt->error);
    }

    $result = $check_stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Invalid recipient');
    }

    // Insert message
    $sql = "INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
    }

    $stmt->bind_param("iis", $sender_id, $receiver_id, $content);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute insert statement: ' . $stmt->error);
    }

    error_log("Message sent successfully: ID=" . $stmt->insert_id);
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $stmt->insert_id
    ]);

} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
