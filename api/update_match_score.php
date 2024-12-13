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

if (!isset($input['match_id']) || !isset($input['home_score']) || !isset($input['away_score'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$match_id = filter_var($input['match_id'], FILTER_VALIDATE_INT);
$home_score = filter_var($input['home_score'], FILTER_VALIDATE_INT);
$away_score = filter_var($input['away_score'], FILTER_VALIDATE_INT);

if (!$match_id || !is_int($home_score) || !is_int($away_score)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Update the match scores
    $update_match = "UPDATE matches SET home_score = ?, away_score = ? WHERE id = ?";
    $stmt = $conn->prepare($update_match);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iii", $home_score, $away_score, $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("No match found with ID: " . $match_id);
    }

    echo json_encode(['success' => true, 'message' => 'Match score updated successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
