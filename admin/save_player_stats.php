<?php
require_once('../config/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$player_id = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
$match_id = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
$minutes_played = isset($_POST['minutes_played']) ? (int)$_POST['minutes_played'] : 0;
$points_scored = isset($_POST['points_scored']) ? (int)$_POST['points_scored'] : 0;
$assists = isset($_POST['assists']) ? (int)$_POST['assists'] : 0;
$rebounds = isset($_POST['rebounds']) ? (int)$_POST['rebounds'] : 0;
$steals = isset($_POST['steals']) ? (int)$_POST['steals'] : 0;
$blocks = isset($_POST['blocks']) ? (int)$_POST['blocks'] : 0;
$turnovers = isset($_POST['turnovers']) ? (int)$_POST['turnovers'] : 0;
$fouls = isset($_POST['fouls']) ? (int)$_POST['fouls'] : 0;

if (!$player_id || !$match_id) {
    echo json_encode(['success' => false, 'message' => 'Player ID and Match ID are required']);
    exit;
}

// Get team_id for the player
$team_query = "SELECT team_id FROM team_members WHERE user_id = ?";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $player_id);
$stmt->execute();
$result = $stmt->get_result();
$team = $result->fetch_assoc();

if (!$team) {
    echo json_encode(['success' => false, 'message' => 'Player is not assigned to a team']);
    exit;
}

$team_id = $team['team_id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Check if stats already exist for this player and match
    $check_query = "SELECT id FROM player_stats WHERE user_id = ? AND match_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $player_id, $match_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing stats
        $update_query = "UPDATE player_stats SET 
                        minutes_played = ?,
                        points_scored = ?,
                        assists = ?,
                        rebounds = ?,
                        steals = ?,
                        blocks = ?,
                        turnovers = ?,
                        fouls = ?
                        WHERE user_id = ? AND match_id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iiiiiiiiii", 
            $minutes_played, $points_scored, $assists, $rebounds,
            $steals, $blocks, $turnovers, $fouls,
            $player_id, $match_id
        );
    } else {
        // Insert new stats
        $insert_query = "INSERT INTO player_stats 
                        (user_id, team_id, match_id, minutes_played, points_scored, 
                         assists, rebounds, steals, blocks, turnovers, fouls)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iiiiiiiiiii", 
            $player_id, $team_id, $match_id, $minutes_played, $points_scored,
            $assists, $rebounds, $steals, $blocks, $turnovers, $fouls
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Error saving player stats: " . $stmt->error);
    }
    
    // Update team stats (total points)
    $team_stats_query = "INSERT INTO team_stats (team_id, match_id, points_scored)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE points_scored = points_scored + ?";
    
    $stmt = $conn->prepare($team_stats_query);
    $stmt->bind_param("iiii", $team_id, $match_id, $points_scored, $points_scored);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating team stats: " . $stmt->error);
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
