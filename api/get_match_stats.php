<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate required parameters
if (!isset($_GET['team_id']) || !isset($_GET['match_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$team_id = filter_var($_GET['team_id'], FILTER_VALIDATE_INT);
$match_id = filter_var($_GET['match_id'], FILTER_VALIDATE_INT);

if (!$team_id || !$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Get match details first
    $match_query = "SELECT home_team_id, away_team_id, home_score, away_score 
                   FROM matches 
                   WHERE id = ?";
    $stmt = $conn->prepare($match_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $match_result = $stmt->get_result();
    $match_data = $match_result->fetch_assoc();

    if (!$match_data) {
        throw new Exception("Match not found");
    }

    // Get team stats for the specific match
    $stats_query = "SELECT total_points, total_assists, total_rebounds, total_steals, total_blocks 
                   FROM team_stats 
                   WHERE team_id = ? AND match_id = ?";
    
    $stmt = $conn->prepare($stats_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $team_id, $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    if (!$stats) {
        // Return default values if no stats exist
        $stats = [
            'total_points' => 0,
            'total_assists' => 0,
            'total_rebounds' => 0,
            'total_steals' => 0,
            'total_blocks' => 0
        ];
    }

    // Add match scores to the response
    $stats['home_score'] = $match_data['home_score'];
    $stats['away_score'] = $match_data['away_score'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
