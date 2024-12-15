<?php
// Prevent any output buffering issues
ob_start();

// Disable error display in output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up error handling to log rather than display
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Enable error logging
error_log("Starting save_event.php");
error_log("Session data: " . print_r($_SESSION, true));

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize debug info array
$debug_info = [
    'session' => $_SESSION,
    'timestamp' => date('Y-m-d H:i:s'),
    'request' => file_get_contents('php://input'),
    'steps' => []
];

// Function to send JSON response
function send_json_response($success, $message, $debug_info = [], $data = null) {
    $response = [
        'success' => $success, 
        'message' => $message,
        'debug_info' => $debug_info
    ];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    error_log("Sending response: " . print_r($response, true));
    echo json_encode($response);
    exit();
}

try {
    // Verify database connection
    if (!isset($conn)) {
        throw new Exception('Database connection failed');
    }

    // Check session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in');
    }

    $user_id = $_SESSION['user_id'];
    $team_id = $_SESSION['team_id'] ?? null;
    $debug_info['steps'][] = "User ID: $user_id, Team ID: " . ($team_id ?? 'null');
    error_log("User ID: $user_id, Team ID: " . ($team_id ?? 'null'));

    // Get sport_id from the team if available
    $sport_id = null;
    if ($team_id) {
        $team_query = "SELECT sport_id FROM teams WHERE id = :team_id";
        $stmt = $conn->prepare($team_query);
        $stmt->execute([':team_id' => $team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($team) {
            $sport_id = $team['sport_id'];
        }
    }

    // If no team or team has no sport, get the first available sport
    if (!$sport_id) {
        $sport_query = "SELECT id FROM sports LIMIT 1";
        $stmt = $conn->prepare($sport_query);
        $stmt->execute();
        $sport = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sport) {
            throw new Exception('No sports available in the system');
        }
        $sport_id = $sport['id'];
    }
    $debug_info['steps'][] = "Sport ID: $sport_id";
    error_log("Sport ID: $sport_id");

    // Get and decode JSON input
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    $debug_info['steps'][] = "Received data: " . print_r($data, true);
    error_log("Raw input: " . $raw_data);
    error_log("Decoded data: " . print_r($data, true));

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    if (!isset($data['date']) || !isset($data['text'])) {
        throw new Exception('Missing required fields');
    }

    // Start transaction
    $conn->beginTransaction();
    $debug_info['steps'][] = "Started transaction";
    error_log("Started transaction");

    // Insert into events table with all required fields
    $insert_query = "INSERT INTO events (title, sport_id, start_time, end_time, created_at) 
                    VALUES (:title, :sport_id, :start_time, :end_time, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $params = [
        ':title' => $data['text'],
        ':sport_id' => $sport_id,
        ':start_time' => $data['date'],
        ':end_time' => $data['date'] // Using same date for end_time for now
    ];
    $debug_info['steps'][] = "Executing insert with params: " . print_r($params, true);
    error_log("Executing query with params: " . print_r($params, true));
    
    if (!$stmt->execute($params)) {
        throw new Exception('Failed to insert event: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $event_id = $conn->lastInsertId();
    $debug_info['steps'][] = "Event inserted with ID: $event_id";
    error_log("Inserted event with ID: $event_id");

    // If we have a team_id, link the event to the team
    if ($team_id) {
        $team_event_query = "INSERT INTO team_events (event_id, team_id) VALUES (:event_id, :team_id)";
        $stmt = $conn->prepare($team_event_query);
        if (!$stmt->execute([':event_id' => $event_id, ':team_id' => $team_id])) {
            throw new Exception('Failed to link event to team');
        }
        $debug_info['steps'][] = "Event linked to team $team_id";
    }

    // Verify the event was saved
    $verify_query = "SELECT e.*, te.team_id 
                    FROM events e 
                    JOIN team_events te ON e.id = te.event_id 
                    WHERE e.id = :event_id AND te.team_id = :team_id";
    $stmt = $conn->prepare($verify_query);
    $stmt->execute([':event_id' => $event_id, ':team_id' => $team_id]);
    $saved_event = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug_info['steps'][] = "Verification query result: " . print_r($saved_event, true);

    // Commit transaction
    $conn->commit();
    $debug_info['steps'][] = "Transaction committed successfully";
    error_log("Transaction committed");
    
    send_json_response(true, 'Event saved successfully', $debug_info, [
        'eventId' => $event_id,
        'event' => $saved_event
    ]);

} catch (Exception $e) {
    // Rollback transaction if one is active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
        $debug_info['steps'][] = "Transaction rolled back due to error";
        error_log("Transaction rolled back");
    }
    
    $debug_info['error'] = $e->getMessage();
    error_log("Error in save_event.php: " . $e->getMessage());
    send_json_response(false, $e->getMessage(), $debug_info);
}

// Clear any output buffers and end
ob_end_clean();
