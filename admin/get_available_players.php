<?php
require_once('../config/db.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log access to this script
error_log("get_available_players.php accessed");

header('Content-Type: application/json');

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
error_log("Received sport_id: " . $sport_id);

if (!$sport_id) {
    error_log("No sport_id provided or invalid sport_id");
    http_response_code(400);
    echo json_encode(['error' => 'Sport ID is required']);
    exit;
}

try {
    // First verify if the sport exists
    $sport_check = $conn->prepare("SELECT id FROM sports WHERE id = ?");
    $sport_check->execute([$sport_id]);
    if (!$sport_check->fetch()) {
        error_log("Sport ID $sport_id not found in database");
        http_response_code(404);
        echo json_encode(['error' => 'Sport not found']);
        exit;
    }

    // Get available players for the sport using users table for names
    $query = "SELECT DISTINCT u.id, u.first_name, u.last_name 
              FROM users u 
              INNER JOIN athlete_profiles ap ON u.id = ap.user_id
              WHERE u.role = 'athlete' 
              AND ap.sport_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM team_members tm 
                  WHERE tm.athlete_id = u.id
              )
              ORDER BY u.first_name, u.last_name";

    error_log("Executing query: " . str_replace('?', $sport_id, $query));
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . print_r($conn->errorInfo(), true));
        throw new PDOException("Failed to prepare statement");
    }

    if (!$stmt->execute([$sport_id])) {
        error_log("Failed to execute query: " . print_r($stmt->errorInfo(), true));
        throw new PDOException("Query execution failed: " . implode(", ", $stmt->errorInfo()));
    }
    
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($players) . " players");
    
    if (empty($players)) {
        error_log("No players found for sport_id: " . $sport_id);
        echo json_encode([]);
    } else {
        error_log("Returning players: " . print_r($players, true));
        echo json_encode($players);
    }
    
} catch (PDOException $e) {
    error_log("Database error in get_available_players.php: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in get_available_players.php: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
