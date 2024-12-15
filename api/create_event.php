<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coach'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Validate input
    $required_fields = ['title', 'eventType', 'startTime', 'endTime', 'eventDate', 'teamId'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Prepare event data
    $eventDate = $_POST['eventDate'];
    $startTime = $eventDate . ' ' . $_POST['startTime'];
    $endTime = $eventDate . ' ' . $_POST['endTime'];
    
    // Start transaction
    $conn->beginTransaction();

    // Insert event
    $sql = "INSERT INTO events (title, description, event_type, start_time, end_time, location, created_by) 
            VALUES (:title, :description, :event_type, :start_time, :end_time, :location, :created_by)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':title' => $_POST['title'],
        ':description' => $_POST['description'] ?? null,
        ':event_type' => $_POST['eventType'],
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':location' => $_POST['location'] ?? null,
        ':created_by' => $_SESSION['user_id']
    ]);

    $event_id = $conn->lastInsertId();

    // Link event to team
    $sql = "INSERT INTO team_events (team_id, event_id) VALUES (:team_id, :event_id)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':team_id' => $_POST['teamId'],
        ':event_id' => $event_id
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'event_id' => $event_id]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
