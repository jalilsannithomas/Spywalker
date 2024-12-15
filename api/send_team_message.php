<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests
error_log("send_team_message.php accessed - Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw request body: " . file_get_contents('php://input'));

session_start();
require_once '../config/db.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    error_log("Unauthorized access - Session data: " . print_r($_SESSION, true));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Decoded request data: " . print_r($data, true));

$coach_id = $_SESSION['user_id'];
$team_id = $data['team_id'] ?? null;
$message = $data['message'] ?? null;

error_log("Processing team message - Coach ID: $coach_id, Team ID: $team_id");

if (!$team_id || !$message) {
    error_log("Missing required fields - team_id or message");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Team ID and message are required']);
    exit();
}

try {
    // Verify this coach has access to this team
    $team_check = "SELECT id FROM teams WHERE id = :team_id AND coach_id = :coach_id";
    $team_stmt = $conn->prepare($team_check);
    $team_stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
    $team_stmt->bindParam(':coach_id', $coach_id, PDO::PARAM_INT);
    $team_stmt->execute();
    
    if (!$team_stmt->fetch()) {
        error_log("Coach $coach_id attempted to message team $team_id which they don't coach");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to message this team']);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();
    error_log("Started transaction");

    // Get all team members who are athletes
    $members_query = "SELECT tm.athlete_id 
                     FROM team_members tm
                     JOIN users u ON tm.athlete_id = u.id 
                     WHERE tm.team_id = :team_id AND u.role = 'athlete'";
    $members_stmt = $conn->prepare($members_query);
    $members_stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
    $members_stmt->execute();
    $team_members = $members_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $member_count = count($team_members);
    error_log("Found $member_count team members to message");

    if ($member_count === 0) {
        $conn->rollBack();
        error_log("No team members found for team $team_id");
        echo json_encode(['success' => false, 'message' => 'No team members found']);
        exit();
    }

    // Send message to each team member
    $insert_query = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (:sender_id, :receiver_id, :message, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    $success_count = 0;
    foreach ($team_members as $member_id) {
        $insert_stmt->bindParam(':sender_id', $coach_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':receiver_id', $member_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':message', $message, PDO::PARAM_STR);
        
        if ($insert_stmt->execute()) {
            $success_count++;
        } else {
            error_log("Failed to send message to user ID: $member_id - Error: " . print_r($insert_stmt->errorInfo(), true));
        }
    }

    error_log("Successfully sent messages to $success_count out of $member_count members");

    if ($success_count === 0) {
        $conn->rollBack();
        error_log("Failed to send any messages");
        echo json_encode(['success' => false, 'message' => 'Failed to send messages to any team members']);
        exit();
    }

    // Commit transaction
    $conn->commit();
    error_log("Transaction committed");
    echo json_encode(['success' => true, 'message' => "Team message sent successfully to $success_count members"]);

} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error in send_team_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send team message: ' . $e->getMessage()]);
}
