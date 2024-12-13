<?php
session_start();
require_once '../config/db.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$team_name = trim($data['team_name'] ?? '');

if (empty($team_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Team name cannot be empty']);
    exit();
}

// Sanitize the team name
$team_name = htmlspecialchars($team_name, ENT_QUOTES, 'UTF-8');

// Update the team name in the fantasy_teams table
$user_id = $_SESSION['user_id'];

try {
    // First verify the team exists
    $verify_query = "SELECT id FROM fantasy_teams WHERE user_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if (!$verify_stmt) {
        throw new Exception("Failed to prepare verify query: " . $conn->error);
    }
    
    $verify_stmt->bind_param("i", $user_id);
    if (!$verify_stmt->execute()) {
        throw new Exception("Failed to execute verify query: " . $verify_stmt->error);
    }
    
    $result = $verify_stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("No fantasy team found for user");
    }
    
    // Update the team name
    $update_query = "UPDATE fantasy_teams SET team_name = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    if (!$update_stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }
    
    $update_stmt->bind_param("si", $team_name, $user_id);
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to execute update query: " . $update_stmt->error);
    }
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("No changes made to team name");
    }
    
    echo json_encode(['success' => true, 'team_name' => $team_name]);
    
} catch (Exception $e) {
    error_log("Error updating team name: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
