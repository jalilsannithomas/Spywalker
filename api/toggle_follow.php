<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is a fan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fan') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only fans can follow athletes and coaches']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$fan_id = $_SESSION['user_id'];
$target_id = $data['target_id'] ?? null;
$role = $data['role'] ?? null;

if (!$target_id || !$role) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Target ID and role are required']);
    exit();
}

// Verify the user exists and is actually an athlete or coach
$check_query = "SELECT id FROM users WHERE id = ? AND role = ? AND role IN ('athlete', 'coach')";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("is", $target_id, $role);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found or invalid role']);
    exit();
}

try {
    // Check if already following
    $table_name = $role === 'athlete' ? 'fan_followed_athletes' : 'fan_followed_coaches';
    $id_column = $role === 'athlete' ? 'athlete_id' : 'coach_id';
    
    $check_follow = "SELECT id FROM $table_name WHERE fan_id = ? AND $id_column = ?";
    $check_stmt = $conn->prepare($check_follow);
    $check_stmt->bind_param("ii", $fan_id, $target_id);
    $check_stmt->execute();
    $follow_result = $check_stmt->get_result();
    
    if ($follow_result->num_rows > 0) {
        // Unfollow
        $delete_query = "DELETE FROM $table_name WHERE fan_id = ? AND $id_column = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $fan_id, $target_id);
        $delete_stmt->execute();
        $is_following = false;
    } else {
        // Follow
        $insert_query = "INSERT INTO $table_name (fan_id, $id_column) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $fan_id, $target_id);
        $insert_stmt->execute();
        $is_following = true;
    }
    
    // Get updated follower count
    $count_query = "SELECT COUNT(*) as count FROM $table_name WHERE $id_column = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $target_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $new_count = $count_result['count'];
    
    echo json_encode([
        'success' => true,
        'following' => $is_following,
        'new_count' => $new_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
