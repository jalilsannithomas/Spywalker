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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Received input: " . print_r($input, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (!isset($input['sport_id']) || !isset($input['home_team_id']) || !isset($input['away_team_id']) || !isset($input['game_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // Convert to appropriate types
    $sport_id = (int)$input['sport_id'];
    $home_team_id = (int)$input['home_team_id'];
    $away_team_id = (int)$input['away_team_id'];
    $game_date = $input['game_date'];
    $venue = isset($input['venue']) ? $input['venue'] : '';
    $season_year = date('Y');

    // Verify coach has permission for at least one of the teams
    if ($role === 'coach') {
        $team_check = $conn->prepare("SELECT id FROM teams WHERE (id = ? OR id = ?) AND coach_id = ?");
        $team_check->bind_param("iii", $home_team_id, $away_team_id, $user_id);
        $team_check->execute();
        if ($team_check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to create games for these teams']);
            exit();
        }
    }

    // Insert new game
    $insert_sql = "INSERT INTO games (sport_id, home_team_id, away_team_id, game_date, venue, season_year) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iiissi", $sport_id, $home_team_id, $away_team_id, $game_date, $venue, $season_year);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Game created successfully', 'game_id' => $conn->insert_id]);
    } else {
        error_log("Error creating game: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create game']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update game details or status
    if (!isset($input['game_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Game ID is required']);
        exit();
    }

    $game_id = (int)$input['game_id'];
    $updates = [];
    $types = '';
    $params = [];

    // Build dynamic update query based on provided fields
    if (isset($input['status'])) {
        $updates[] = 'status = ?';
        $types .= 's';
        $params[] = $input['status'];
    }
    if (isset($input['home_score'])) {
        $updates[] = 'home_score = ?';
        $types .= 'i';
        $params[] = (int)$input['home_score'];
    }
    if (isset($input['away_score'])) {
        $updates[] = 'away_score = ?';
        $types .= 'i';
        $params[] = (int)$input['away_score'];
    }
    if (isset($input['game_date'])) {
        $updates[] = 'game_date = ?';
        $types .= 's';
        $params[] = $input['game_date'];
    }
    if (isset($input['venue'])) {
        $updates[] = 'venue = ?';
        $types .= 's';
        $params[] = $input['venue'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit();
    }

    // Add game_id to params
    $types .= 'i';
    $params[] = $game_id;

    $update_sql = "UPDATE games SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Game updated successfully']);
    } else {
        error_log("Error updating game: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update game']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
