<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coach'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['sport_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sport ID is required']);
    exit();
}

$sport_id = (int)$_GET['sport_id'];

// Get teams based on role and sport
if ($role === 'coach') {
    $query = "SELECT id, name FROM teams WHERE sport_id = ? AND coach_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $sport_id, $user_id);
} else {
    $query = "SELECT id, name FROM teams WHERE sport_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sport_id);
}

$stmt->execute();
$result = $stmt->get_result();
$teams = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($teams);
