<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Verify database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed");
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check session
if (!isset($_SESSION['user_id'])) {  
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];  

// Get POST data
$raw_data = file_get_contents('php://input');
error_log("Received data: " . $raw_data);
$data = json_decode($raw_data, true);

if (!isset($data['date']) || !isset($data['text'])) {
    error_log("Missing required fields. Data received: " . print_r($data, true));
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$date = $data['date'];
$text = $data['text'];

try {
    // Check if event already exists for this date and user
    $check_query = "SELECT id FROM events WHERE user_id = ? AND event_date = ?";
    error_log("Checking for existing event - User ID: $user_id, Date: $date");
    
    $stmt = $conn->prepare($check_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("is", $user_id, $date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    error_log("Found " . $result->num_rows . " existing events");
    
    if ($result->num_rows > 0) {
        // Update existing event
        $row = $result->fetch_assoc();
        $event_id = $row['id'];
        
        // Prepare update statement
        $stmt = $conn->prepare("UPDATE events SET event_text = ? WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        
        error_log("Updating event - ID: $event_id, User ID: $user_id, Text: $text");
        $stmt->bind_param("sii", $text, $event_id, $user_id);
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Update failed: " . $stmt->error);
            throw new Exception("Update failed: " . $stmt->error);
        }
    } else {
        // Insert new event
        $insert_query = "INSERT INTO events (user_id, event_date, event_text) VALUES (?, ?, ?)";
        error_log("Inserting new event - User ID: $user_id, Date: $date, Text: $text");
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Prepare insert failed: " . $conn->error);
        }
        $stmt->bind_param("iss", $user_id, $date, $text);
        $success = $stmt->execute();
        $event_id = $conn->insert_id;
        error_log("New event created with ID: $event_id");
    }

    if (!$success) {
        throw new Exception("Query execution failed");
    }

    echo json_encode([
        'success' => true,
        'eventId' => $event_id
    ]);

} catch (Exception $e) {
    error_log("Error saving event: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
