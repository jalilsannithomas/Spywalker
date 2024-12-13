<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Debug logging
error_log("Received request: " . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$date = $_POST['date'] ?? '';
$title = $_POST['title'] ?? '';
$event_time = '00:00:00'; // Default time if not specified
$location = 'TBD'; // Default location if not specified

if (empty($date) || empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields', 'received' => ['date' => $date, 'title' => $title]]);
    exit();
}

try {
    // Insert the quick event
    $sql = "INSERT INTO team_events (title, event_date, event_time, location, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt->bind_param("ssssi", $title, $date, $event_time, $location, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Event added successfully',
        'event' => [
            'id' => $conn->insert_id,
            'title' => $title,
            'date' => $date,
            'time' => $event_time,
            'location' => $location
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error adding quick event: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to add event',
        'message' => $e->getMessage()
    ]);
}
