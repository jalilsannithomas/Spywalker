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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!isset($_GET['match_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Match ID is required']);
    exit();
}

$match_id = filter_var($_GET['match_id'], FILTER_VALIDATE_INT);

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
    exit();
}

try {
    // Get match details with team names
    $match_query = "
        SELECT m.id, m.home_team_id, m.away_team_id,
               ht.name as home_team_name,
               at.name as away_team_name
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        WHERE m.id = ?";
        
    $stmt = $conn->prepare($match_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $match_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $match_result = $stmt->get_result();
    $match_data = $match_result->fetch_assoc();
    
    if (!$match_data) {
        throw new Exception("Match not found");
    }

    // Get players from both teams with their stats
    $players_query = "
        SELECT 
            p.id,
            p.name,
            p.jersey_number,
            p.position,
            t.name as team_name,
            t.id as team_id,
            COALESCE(ps.points, 0) as points,
            COALESCE(ps.assists, 0) as assists,
            COALESCE(ps.rebounds, 0) as rebounds,
            COALESCE(ps.steals, 0) as steals,
            COALESCE(ps.blocks, 0) as blocks
        FROM players p
        JOIN teams t ON p.team_id = t.id
        LEFT JOIN player_stats ps ON p.id = ps.player_id AND ps.match_id = ?
        WHERE p.team_id IN (?, ?)
        ORDER BY p.team_id, p.jersey_number";

    $stmt = $conn->prepare($players_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iii", 
        $match_id,
        $match_data['home_team_id'],
        $match_data['away_team_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $players = [];
    
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    
    // Group players by home and away teams
    $home_team_players = array_filter($players, function($p) use ($match_data) {
        return $p['team_id'] == $match_data['home_team_id'];
    });
    
    $away_team_players = array_filter($players, function($p) use ($match_data) {
        return $p['team_id'] == $match_data['away_team_id'];
    });
    
    $response = [
        'home_team' => array_values($home_team_players), // Reset array keys
        'away_team' => array_values($away_team_players), // Reset array keys
        'match_info' => [
            'home_team_name' => $match_data['home_team_name'],
            'away_team_name' => $match_data['away_team_name']
        ]
    ];
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
