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
    $athlete_id = $_POST['athlete_id'];
    $team_id = $_POST['team_id'];
    $jersey_number = $_POST['jersey_number'];
    
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
    
    // Check if jersey number is already taken
    $check_query = "SELECT id FROM team_players WHERE team_id = ? AND jersey_number = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $team_id, $jersey_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Jersey number is already taken.";
        header("Location: manage_roster.php");
        exit();
    }
    
    // Add player to team
    $add_query = "INSERT INTO team_players (team_id, athlete_id, jersey_number) VALUES (?, ?, ?)";
    $add_stmt = $conn->prepare($add_query);
    $add_stmt->bind_param("iii", $team_id, $athlete_id, $jersey_number);
    
    if ($add_stmt->execute()) {
        $_SESSION['success_message'] = "Player added successfully.";
    } else {
        $_SESSION['error_message'] = "Error adding player to team.";
    }
}

header("Location: manage_roster.php");
exit();
?>
