<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';
require_once 'calculate_fantasy_points.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['match_id']) || !isset($input['player_stats'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$match_id = filter_var($input['match_id'], FILTER_VALIDATE_INT);
if (!$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
    exit();
}

try {
    $conn->begin_transaction();

    // First verify the match exists and get team IDs
    $match_query = "SELECT home_team_id, away_team_id FROM matches WHERE id = ?";
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

    // Prepare statements for update and insert
    $update_stmt = $conn->prepare("
        UPDATE player_stats 
        SET points = ?, assists = ?, rebounds = ?, steals = ?, blocks = ?
        WHERE player_id = ? AND match_id = ?
    ");

    $insert_stmt = $conn->prepare("
        INSERT INTO player_stats 
        (player_id, match_id, points, assists, rebounds, steals, blocks)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$update_stmt || !$insert_stmt) {
        throw new Exception("Failed to prepare statements");
    }

    // Process each player's stats
    foreach ($input['player_stats'] as $player_stat) {
        $player_id = filter_var($player_stat['player_id'], FILTER_VALIDATE_INT);
        $points = filter_var($player_stat['points'] ?? 0, FILTER_VALIDATE_INT);
        $assists = filter_var($player_stat['assists'] ?? 0, FILTER_VALIDATE_INT);
        $rebounds = filter_var($player_stat['rebounds'] ?? 0, FILTER_VALIDATE_INT);
        $steals = filter_var($player_stat['steals'] ?? 0, FILTER_VALIDATE_INT);
        $blocks = filter_var($player_stat['blocks'] ?? 0, FILTER_VALIDATE_INT);

        if (!$player_id) {
            throw new Exception("Invalid player ID");
        }

        // Check if stats exist for this player and match
        $check_query = "SELECT id FROM player_stats WHERE player_id = ? AND match_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("Failed to prepare check statement");
        }
        
        $check_stmt->bind_param("ii", $player_id, $match_id);
        if (!$check_stmt->execute()) {
            throw new Exception("Failed to check existing stats");
        }
        
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing stats
            $update_stmt->bind_param("iiiiiii", 
                $points, $assists, $rebounds, $steals, $blocks, 
                $player_id, $match_id
            );
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update player stats");
            }
        } else {
            // Insert new stats
            $insert_stmt->bind_param("iiiiiii", 
                $player_id, $match_id, 
                $points, $assists, $rebounds, $steals, $blocks
            );
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to insert player stats");
            }
        }
    }

    // Calculate fantasy points for this match
    updateFantasyPoints($conn, $match_id);

    // Calculate and update team totals
    $teams = [$match_data['home_team_id'], $match_data['away_team_id']];
    foreach ($teams as $team_id) {
        // Calculate team totals from player stats
        $totals_query = "
            SELECT 
                SUM(ps.points) as total_points,
                SUM(ps.assists) as total_assists,
                SUM(ps.rebounds) as total_rebounds,
                SUM(ps.steals) as total_steals,
                SUM(ps.blocks) as total_blocks
            FROM player_stats ps
            JOIN players p ON ps.player_id = p.id
            WHERE ps.match_id = ? AND p.team_id = ?
        ";
        
        $totals_stmt = $conn->prepare($totals_query);
        if (!$totals_stmt) {
            throw new Exception("Failed to prepare totals statement");
        }
        
        $totals_stmt->bind_param("ii", $match_id, $team_id);
        if (!$totals_stmt->execute()) {
            throw new Exception("Failed to calculate team totals");
        }
        
        $totals = $totals_stmt->get_result()->fetch_assoc();

        // Update team stats
        $team_stats_query = "
            INSERT INTO team_stats 
            (team_id, match_id, total_points, total_assists, total_rebounds, total_steals, total_blocks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_points = VALUES(total_points),
            total_assists = VALUES(total_assists),
            total_rebounds = VALUES(total_rebounds),
            total_steals = VALUES(total_steals),
            total_blocks = VALUES(total_blocks)
        ";
        
        $team_stats_stmt = $conn->prepare($team_stats_query);
        if (!$team_stats_stmt) {
            throw new Exception("Failed to prepare team stats statement");
        }
        
        $team_stats_stmt->bind_param("iiiiiii",
            $team_id, $match_id,
            $totals['total_points'],
            $totals['total_assists'],
            $totals['total_rebounds'],
            $totals['total_steals'],
            $totals['total_blocks']
        );
        
        if (!$team_stats_stmt->execute()) {
            throw new Exception("Failed to update team stats");
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Player and team stats updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
