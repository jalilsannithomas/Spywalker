<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to follow coaches']);
    exit();
}

// Get the POST data
$raw_data = file_get_contents('php://input');
error_log("Raw POST data: " . $raw_data);

$data = json_decode($raw_data, true);
error_log("Decoded data: " . print_r($data, true));

$id = $data['id'] ?? null;
$user_id = $_SESSION['user_id'];

error_log("User ID: " . $user_id);
error_log("Target Coach ID: " . ($id ?? 'null'));

if (!$id) {
    error_log("ID is missing from request");
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

// First verify that the target user is a coach
$check_coach_sql = "SELECT id FROM users WHERE id = ? AND role = 'coach'";
$check_stmt = $conn->prepare($check_coach_sql);
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid coach ID or user is not a coach']);
    exit();
}

// Insert into collected_athletes table (we'll use the same table for following coaches)
$insert_sql = "INSERT INTO collected_athletes (user_id, athlete_id, collected_at) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("ii", $user_id, $id);

if ($stmt->execute()) {
    error_log("Successfully followed coach");
    echo json_encode(['success' => true, 'message' => 'Coach followed successfully']);
} else {
    error_log("Error following coach: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Error following coach: ' . $stmt->error]);
}
