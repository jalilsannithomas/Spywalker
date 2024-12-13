<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if team_id is provided
if (!isset($_GET['team_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Team ID is required']);
    exit();
}

$team_id = (int)$_GET['team_id'];

try {
    // Get team roster
    $sql = "SELECT 
                CONCAT(ap.first_name, ' ', ap.last_name) as name,
                tm.jersey_number,
                CASE 
                    WHEN ap.position_id = 1 THEN 'Point Guard'
                    WHEN ap.position_id = 2 THEN 'Shooting Guard'
                    WHEN ap.position_id = 3 THEN 'Small Forward'
                    WHEN ap.position_id = 4 THEN 'Power Forward'
                    WHEN ap.position_id = 5 THEN 'Center'
                    ELSE 'N/A'
                END as position
            FROM athlete_profiles ap
            JOIN team_members tm ON tm.athlete_id = ap.id
            WHERE tm.team_id = ?
            ORDER BY ap.last_name, ap.first_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roster = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return roster as JSON
    header('Content-Type: application/json');
    echo json_encode($roster);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
