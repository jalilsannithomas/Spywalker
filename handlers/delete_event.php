<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$event_id = $_POST['event_id'] ?? '';

if (empty($event_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event ID']);
    exit();
}

try {
    // First try to delete from personal events
    $sql = "DELETE FROM events WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt->bind_param("ii", $event_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
        exit();
    }
    
    // If no personal event was deleted, try team events
    $sql = "DELETE FROM team_events WHERE id = ? AND created_by = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $event_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("No event found or you don't have permission to delete it");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting event: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to delete event',
        'message' => $e->getMessage()
    ]);
}
