<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coach'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Received input: " . print_r($input, true));

if (!isset($input['action']) || !isset($input['athlete_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$action = $input['action'];
$team_id = isset($input['team_id']) ? (int)$input['team_id'] : null;

// Debug logging
error_log("Action: " . $action . ", Team ID: " . $team_id);

// Parse athlete type and ID from the unique_id
$unique_id = $input['athlete_id'];
$is_listed = strpos($unique_id, 'listed_') === 0;
$actual_id = (int)str_replace(['listed_', 'registered_'], '', $unique_id);

// Debug logging
error_log("Unique ID: $unique_id, Is Listed: " . ($is_listed ? 'true' : 'false') . ", Actual ID: $actual_id");

// Verify athlete exists in the correct table
$table_name = $is_listed ? 'listed_players' : 'athlete_profiles';
$athlete_check = $conn->prepare("SELECT id FROM $table_name WHERE id = ?");
$athlete_check->bind_param("i", $actual_id);
$athlete_check->execute();
$athlete_exists = $athlete_check->get_result()->num_rows > 0;

// Debug logging
error_log("Checking athlete in table $table_name with ID $actual_id. Exists: " . ($athlete_exists ? 'true' : 'false'));

if (!$athlete_exists) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
    exit();
}

// Verify coach has permission for this team
if ($role === 'coach' && $team_id) {
    $team_check = $conn->prepare("SELECT id FROM teams WHERE id = ? AND coach_id = ?");
    $team_check->bind_param("ii", $team_id, $user_id);
    $team_check->execute();
    if ($team_check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage this team']);
        exit();
    }
}

// Handle add/remove actions
if ($action === 'add') {
    if (!$team_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID is required for adding athletes']);
        exit();
    }

    // Debug logging
    error_log("Adding athlete: team_id = $team_id, athlete_id = $actual_id, is_listed = " . ($is_listed ? 'true' : 'false'));

    // Check if athlete is already on a team
    $team_check_sql = "SELECT team_id FROM team_members WHERE athlete_id = ? AND is_listed = ?";
    error_log("Team check SQL: " . $team_check_sql);
    
    $team_check = $conn->prepare($team_check_sql);
    $is_listed_int = $is_listed ? 1 : 0;
    $team_check->bind_param("ii", $actual_id, $is_listed_int);
    $team_check->execute();
    $result = $team_check->get_result();
    
    // Debug logging
    error_log("Team check result rows: " . $result->num_rows);
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Athlete is already on a team']);
        exit();
    }

    // Add athlete to team
    $add_sql = "INSERT INTO team_members (team_id, athlete_id, is_listed, join_date) VALUES (?, ?, ?, CURDATE())";
    error_log("Add SQL: " . $add_sql);
    
    $add_stmt = $conn->prepare($add_sql);
    $add_stmt->bind_param("iii", $team_id, $actual_id, $is_listed_int);
    
    if ($add_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Athlete added to team successfully']);
    } else {
        error_log("Error adding athlete: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add athlete to team']);
    }
} elseif ($action === 'remove') {
    // Remove athlete from team
    $remove_sql = "DELETE FROM team_members WHERE athlete_id = ? AND is_listed = ?";
    error_log("Remove SQL: " . $remove_sql);
    
    $remove_stmt = $conn->prepare($remove_sql);
    $is_listed_int = $is_listed ? 1 : 0;
    $remove_stmt->bind_param("ii", $actual_id, $is_listed_int);
    
    if ($remove_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Athlete removed from team successfully']);
    } else {
        error_log("Error removing athlete: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove athlete from team']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
