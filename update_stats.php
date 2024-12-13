<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate input
        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        $assists = isset($_POST['assists']) ? intval($_POST['assists']) : 0;
        $rebounds = isset($_POST['rebounds']) ? intval($_POST['rebounds']) : 0;
        $steals = isset($_POST['steals']) ? intval($_POST['steals']) : 0;
        $blocks = isset($_POST['blocks']) ? intval($_POST['blocks']) : 0;

        // Verify that the coach has permission to update these stats
        $sql = "SELECT t.id 
                FROM teams t 
                INNER JOIN coach_profiles cp ON t.coach_id = cp.id
                INNER JOIN matches m ON m.home_team_id = t.id OR m.away_team_id = t.id
                WHERE cp.user_id = ? AND m.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $match_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("You don't have permission to update stats for this match");
        }

        // Check if stats already exist for this player and match
        $sql = "SELECT id FROM player_stats WHERE match_id = ? AND player_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $match_id, $player_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing stats
            $sql = "UPDATE player_stats 
                    SET points = ?, 
                        assists = ?, 
                        rebounds = ?,
                        steals = ?,
                        blocks = ?
                    WHERE match_id = ? AND player_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiiiii", 
                $points, 
                $assists, 
                $rebounds,
                $steals,
                $blocks,
                $match_id, 
                $player_id
            );
        } else {
            // Insert new stats
            $sql = "INSERT INTO player_stats 
                    (match_id, player_id, points, assists, rebounds, steals, blocks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiiiii", 
                $match_id, 
                $player_id, 
                $points, 
                $assists, 
                $rebounds,
                $steals,
                $blocks
            );
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Player statistics have been updated successfully!";
        } else {
            throw new Exception("Error updating player statistics");
        }

        header("Location: team_stats.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: team_stats.php");
    exit();
}
?>
