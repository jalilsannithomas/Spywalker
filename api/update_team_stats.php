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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['team_id']) || !isset($input['match_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$team_id = filter_var($input['team_id'], FILTER_VALIDATE_INT);
$match_id = filter_var($input['match_id'], FILTER_VALIDATE_INT);
$total_points = filter_var($input['total_points'] ?? 0, FILTER_VALIDATE_INT);
$total_assists = filter_var($input['total_assists'] ?? 0, FILTER_VALIDATE_INT);
$total_rebounds = filter_var($input['total_rebounds'] ?? 0, FILTER_VALIDATE_INT);
$total_steals = filter_var($input['total_steals'] ?? 0, FILTER_VALIDATE_INT);
$total_blocks = filter_var($input['total_blocks'] ?? 0, FILTER_VALIDATE_INT);
$home_score = filter_var($input['home_score'] ?? 0, FILTER_VALIDATE_INT);
$away_score = filter_var($input['away_score'] ?? 0, FILTER_VALIDATE_INT);

if (!$team_id || !$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $conn->begin_transaction();

    // First, update the match scores
    $update_match = "UPDATE matches SET home_score = ?, away_score = ? WHERE id = ?";
    $stmt = $conn->prepare($update_match);
    if (!$stmt) {
        throw new Exception("Prepare failed for match update: " . $conn->error);
    }
    $stmt->bind_param("iii", $home_score, $away_score, $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for match update: " . $stmt->error);
    }

    // Then, update or insert team stats
    $check_query = "SELECT id FROM team_stats WHERE team_id = ? AND match_id = ?";
    $stmt = $conn->prepare($check_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for check query: " . $conn->error);
    }
    $stmt->bind_param("ii", $team_id, $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for check query: " . $stmt->error);
    }
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing stats
        $update_query = "UPDATE team_stats 
                        SET total_points = ?, 
                            total_assists = ?, 
                            total_rebounds = ?, 
                            total_steals = ?, 
                            total_blocks = ? 
                        WHERE team_id = ? AND match_id = ?";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for update: " . $conn->error);
        }
        $stmt->bind_param("iiiiiii", 
            $total_points, 
            $total_assists, 
            $total_rebounds, 
            $total_steals, 
            $total_blocks, 
            $team_id, 
            $match_id
        );
    } else {
        // Insert new stats
        $insert_query = "INSERT INTO team_stats 
                        (team_id, match_id, total_points, total_assists, total_rebounds, total_steals, total_blocks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for insert: " . $conn->error);
        }
        $stmt->bind_param("iiiiiii", 
            $team_id, 
            $match_id, 
            $total_points, 
            $total_assists, 
            $total_rebounds, 
            $total_steals, 
            $total_blocks
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for stats update: " . $stmt->error);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Stats updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
