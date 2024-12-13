<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['athlete_id'])) {
    header("Location: manage_roster.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$athlete_id = $_POST['athlete_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Get or create user's roster
    $roster_sql = "SELECT fr.*, COUNT(fra.athlete_id) as athlete_count 
                   FROM fantasy_rosters fr 
                   LEFT JOIN fantasy_roster_athletes fra ON fr.id = fra.roster_id 
                   WHERE fr.user_id = ?
                   GROUP BY fr.id";
    $stmt = $conn->prepare($roster_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $roster_result = $stmt->get_result();
    $roster = $roster_result->fetch_assoc();

    if (!$roster) {
        // Create new roster
        $create_roster_sql = "INSERT INTO fantasy_rosters (user_id, name) VALUES (?, 'My Roster')";
        $stmt = $conn->prepare($create_roster_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $roster_id = $conn->insert_id;
        $athlete_count = 0;
    } else {
        $roster_id = $roster['id'];
        $athlete_count = $roster['athlete_count'];
    }

    // Check if roster is full
    if ($athlete_count >= 7) {
        throw new Exception("Roster is full");
    }

    // Check if athlete is already in roster
    $check_sql = "SELECT 1 FROM fantasy_roster_athletes WHERE roster_id = ? AND athlete_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $roster_id, $athlete_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Athlete already in roster");
    }

    // Add athlete to roster
    $add_sql = "INSERT INTO fantasy_roster_athletes (roster_id, athlete_id) VALUES (?, ?)";
    $stmt = $conn->prepare($add_sql);
    $stmt->bind_param("ii", $roster_id, $athlete_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();
    header("Location: manage_roster.php?success=athlete_added");

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    header("Location: browse_athletes.php?error=" . urlencode($e->getMessage()));
}
exit();
