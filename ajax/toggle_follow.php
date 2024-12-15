<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get form data
$athlete_id = $_POST['athlete_id'] ?? null;
$follower_id = $_SESSION['user_id'];

if (!$athlete_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
    exit;
}

try {
    // Check if already following
    $check_stmt = $conn->prepare("SELECT id FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?");
    $check_stmt->execute([$follower_id, $athlete_id]);
    $existing = $check_stmt->fetch();

    if ($existing) {
        // Unfollow
        $stmt = $conn->prepare("DELETE FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?");
        $stmt->execute([$follower_id, $athlete_id]);
        echo json_encode(['success' => true, 'following' => false]);
    } else {
        // Follow
        $stmt = $conn->prepare("INSERT INTO fan_followed_athletes (fan_id, athlete_id) VALUES (?, ?)");
        $stmt->execute([$follower_id, $athlete_id]);
        echo json_encode(['success' => true, 'following' => true]);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
