<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$athlete_id = $data['athlete_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$athlete_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Get user's fantasy team ID or create one if it doesn't exist
    $team_stmt = $conn->prepare("SELECT id FROM fantasy_teams WHERE user_id = ? LIMIT 1");
    $team_stmt->execute([$user_id]);
    $team = $team_stmt->fetch();

    if (!$team) {
        // Create a new fantasy team for the user
        $create_team_stmt = $conn->prepare("INSERT INTO fantasy_teams (user_id, name) VALUES (?, ?)");
        $create_team_stmt->execute([$user_id, "Team " . $user_id]);
        $team_id = $conn->lastInsertId();
    } else {
        $team_id = $team['id'];
    }

    // Check if athlete exists in athlete_profiles
    $athlete_stmt = $conn->prepare("SELECT id FROM athlete_profiles WHERE user_id = ? LIMIT 1");
    $athlete_stmt->execute([$athlete_id]);
    $athlete = $athlete_stmt->fetch();

    if (!$athlete) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Athlete not found']);
        exit;
    }

    // Check if athlete is already collected
    $check_stmt = $conn->prepare("SELECT id FROM fantasy_team_players WHERE team_id = ? AND athlete_id = ?");
    $check_stmt->execute([$team_id, $athlete['id']]);
    $existing = $check_stmt->fetch();

    if ($existing) {
        // Remove athlete from fantasy team
        $delete_stmt = $conn->prepare("DELETE FROM fantasy_team_players WHERE team_id = ? AND athlete_id = ?");
        $delete_stmt->execute([$team_id, $athlete['id']]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Athlete removed from collection', 'status' => 'uncollected']);
        exit;
    }

    // Add athlete to fantasy team
    $add_stmt = $conn->prepare("INSERT INTO fantasy_team_players (team_id, athlete_id) VALUES (?, ?)");
    $add_stmt->execute([$team_id, $athlete['id']]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Athlete collected successfully', 'status' => 'collected']);

} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Database error in collect_athlete.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    $conn->rollBack();
    error_log("General error in collect_athlete.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
