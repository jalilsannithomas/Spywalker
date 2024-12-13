<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// For athletes, handle quick notes differently
if ($role === 'athlete') {
    if (!isset($_POST['title']) || !isset($_POST['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // Get athlete's team
    $team_query = "SELECT tp.team_id 
                   FROM team_players tp 
                   JOIN athlete_profiles ap ON tp.athlete_id = ap.id 
                   WHERE ap.user_id = ? 
                   LIMIT 1";
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No team found for athlete']);
        exit();
    }
    
    $team = $result->fetch_assoc();
    
    // Insert the quick note
    $sql = "INSERT INTO team_events (team_id, title, event_type, event_date, event_time, location) 
            VALUES (?, ?, 'note', ?, '00:00:00', 'N/A')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $team['team_id'], $_POST['title'], $_POST['date']);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Note added successfully',
            'event' => [
                'id' => $stmt->insert_id,
                'title' => $_POST['title'],
                'event_type' => 'note'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add note']);
    }
    exit();
}

// For coaches and admins, handle full event creation
if ($role !== 'admin' && $role !== 'coach') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Regular event creation logic for coaches and admins
$required_fields = ['team_id', 'title', 'event_type', 'event_date', 'event_time', 'location'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$team_id = filter_var($_POST['team_id'], FILTER_VALIDATE_INT);
$title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
$event_type = filter_var($_POST['event_type'], FILTER_SANITIZE_STRING);
$event_date = filter_var($_POST['event_date'], FILTER_SANITIZE_STRING);
$event_time = filter_var($_POST['event_time'], FILTER_SANITIZE_STRING);
$location = filter_var($_POST['location'], FILTER_SANITIZE_STRING);
$description = isset($_POST['description']) ? filter_var($_POST['description'], FILTER_SANITIZE_STRING) : '';

// Verify team exists and user has permission
if ($role === 'coach') {
    $team_check = $conn->prepare("SELECT id FROM teams WHERE id = ? AND coach_id = ?");
    $team_check->bind_param("ii", $team_id, $user_id);
} else {
    $team_check = $conn->prepare("SELECT id FROM teams WHERE id = ?");
    $team_check->bind_param("i", $team_id);
}

$team_check->execute();
if ($team_check->get_result()->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid team ID or insufficient permissions']);
    exit();
}

// Validate event_type
$valid_event_types = ['match', 'training', 'tournament', 'meeting', 'social', 'other'];
if (!in_array($event_type, $valid_event_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event type']);
    exit();
}

// Insert the event
$sql = "INSERT INTO team_events (team_id, title, event_type, event_date, event_time, location, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("issssss", $team_id, $title, $event_type, $event_date, $event_time, $location, $description);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Event created successfully',
        'event' => [
            'id' => $stmt->insert_id,
            'title' => $title,
            'event_type' => $event_type,
            'event_date' => $event_date,
            'event_time' => $event_time,
            'location' => $location,
            'description' => $description
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create event']);
}
?>
