<?php
require_once('../includes/auth.php');
require_once('../config/db.php');
require_once('../includes/functions.php');

// Only allow coach access
if (!isCoach()) {
    header("Location: /Spywalker/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id = $_POST['player_id'];
    $team_id = $_POST['team_id'];
    
    // Verify the coach owns this team
    $verify_query = "SELECT id FROM teams WHERE id = ? AND coach_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $team_id, $_SESSION['user_id']);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "You don't have permission to manage this team.";
        header("Location: manage_roster.php");
        exit();
    }
    
    // Remove player from team
    $remove_query = "DELETE FROM team_players WHERE team_id = ? AND athlete_id = ?";
    $remove_stmt = $conn->prepare($remove_query);
    $remove_stmt->bind_param("ii", $team_id, $player_id);
    
    if ($remove_stmt->execute()) {
        $_SESSION['success_message'] = "Player removed successfully.";
    } else {
        $_SESSION['error_message'] = "Error removing player from team.";
    }
}

header("Location: manage_roster.php");
exit();
?>
