<?php
session_start();
require_once '../config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

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

try {
    // Get and validate the input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['team_name']) || empty(trim($data['team_name']))) {
        throw new Exception('Team name is required');
    }
    
    $team_name = trim($data['team_name']);
    
    // First, get the user's team
    $query = "SELECT id FROM fantasy_teams WHERE user_id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$team) {
        throw new Exception('No fantasy team found for this user');
    }
    
    // Update the team name
    $update_query = "UPDATE fantasy_teams SET team_name = :team_name WHERE id = :team_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':team_name', $team_name, PDO::PARAM_STR);
    $update_stmt->bindParam(':team_id', $team['id'], PDO::PARAM_INT);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Team name updated successfully',
            'team_name' => $team_name
        ]);
    } else {
        throw new Exception('Failed to update team name');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
