<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// For athlete's personal events
if ($role === 'athlete' && isset($data['personal']) && $data['personal']) {
    if (!isset($data['date']) || !isset($data['text'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    try {
        // Insert personal event
        $sql = "INSERT INTO events (user_id, event_date, event_text) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $data['date'], $data['text']);
        $success = $stmt->execute();

        echo json_encode(['success' => $success]);
        exit();
    } catch (Exception $e) {
        error_log("Error saving personal event: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
}

// For team events (coaches and admins)
if (!in_array($role, ['coach', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Validate team event input
if (!isset($data['team_id']) || !isset($data['title']) || !isset($data['event_date']) || 
    !isset($data['event_time']) || !isset($data['location']) || !isset($data['event_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Insert team event
    $sql = "INSERT INTO team_events (team_id, title, event_date, event_time, location, description, event_type, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssi", 
        $data['team_id'],
        $data['title'],
        $data['event_date'],
        $data['event_time'],
        $data['location'],
        $data['description'],
        $data['event_type'],
        $user_id
    );
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Event added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add event']);
    }
} catch (Exception $e) {
    error_log("Error adding team event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
