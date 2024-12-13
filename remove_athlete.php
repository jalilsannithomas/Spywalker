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

    // Get user's roster
    $roster_sql = "SELECT fr.id FROM fantasy_rosters fr WHERE fr.user_id = ?";
    $stmt = $conn->prepare($roster_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $roster = $stmt->get_result()->fetch_assoc();

    if (!$roster) {
        throw new Exception("Roster not found");
    }

    // Remove athlete from roster
    $remove_sql = "DELETE FROM fantasy_roster_athletes WHERE roster_id = ? AND athlete_id = ?";
    $stmt = $conn->prepare($remove_sql);
    $stmt->bind_param("ii", $roster['id'], $athlete_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Athlete not found in roster");
    }

    // Commit transaction
    $conn->commit();
    header("Location: manage_roster.php?success=athlete_removed");

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    header("Location: manage_roster.php?error=" . urlencode($e->getMessage()));
}
exit();
