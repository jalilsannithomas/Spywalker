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

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Verify the user exists and is actually an athlete or coach
    $check_query = "SELECT id FROM users WHERE id = ? AND role = ? AND role IN ('athlete', 'coach') FOR UPDATE";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindValue(1, $target_id, PDO::PARAM_INT);
    $check_stmt->bindValue(2, $role, PDO::PARAM_STR);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found or invalid role']);
        exit();
    }

    // Check if already following with row locking
    $table_name = $role === 'athlete' ? 'fan_followed_athletes' : 'fan_followed_coaches';
    $id_column = $role === 'athlete' ? 'athlete_id' : 'coach_id';
    
    $check_follow = "SELECT id FROM $table_name WHERE fan_id = ? AND $id_column = ? FOR UPDATE";
    $check_stmt = $conn->prepare($check_follow);
    $check_stmt->bindValue(1, $fan_id, PDO::PARAM_INT);
    $check_stmt->bindValue(2, $target_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Unfollow
        $delete_query = "DELETE FROM $table_name WHERE fan_id = ? AND $id_column = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindValue(1, $fan_id, PDO::PARAM_INT);
        $delete_stmt->bindValue(2, $target_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        $is_following = false;
    } else {
        // Follow - use INSERT IGNORE to handle potential duplicates
        $insert_query = "INSERT IGNORE INTO $table_name (fan_id, $id_column) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindValue(1, $fan_id, PDO::PARAM_INT);
        $insert_stmt->bindValue(2, $target_id, PDO::PARAM_INT);
        $success = $insert_stmt->execute();
        
        if (!$success || $insert_stmt->rowCount() === 0) {
            // If insert failed or no rows were inserted, it might be a duplicate
            $conn->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Already following this user']);
            exit();
        }
        $is_following = true;
    }
    
    // Get updated follower count
    $count_query = "SELECT COUNT(*) as count FROM $table_name WHERE $id_column = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bindValue(1, $target_id, PDO::PARAM_INT);
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $new_count = $count_result['count'];
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'following' => $is_following,
        'new_count' => $new_count
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Follow error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Follow error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
