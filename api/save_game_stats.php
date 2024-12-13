<?php
require_once('../includes/auth.php');
require_once('../config/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$stats = isset($_POST['stats']) ? json_decode($_POST['stats'], true) : [];

if (!$game_id || empty($stats)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $conn->begin_transaction();

    // Delete existing stats for this game
    $delete_stmt = $conn->prepare("DELETE FROM player_game_stats WHERE game_id = ?");
    $delete_stmt->bind_param("i", $game_id);
    $delete_stmt->execute();

    // Insert new stats
    $insert_stmt = $conn->prepare("INSERT INTO player_game_stats (game_id, player_id, stat_category_id, value) VALUES (?, ?, ?, ?)");
    
    foreach ($stats as $stat) {
        $player_id = intval($stat['player_id']);
        $stat_id = intval($stat['stat_id']);
        $value = intval($stat['value']);
        
        $insert_stmt->bind_param("iiii", $game_id, $player_id, $stat_id, $value);
        $insert_stmt->execute();
    }

    // Update game status to completed
    $update_game_stmt = $conn->prepare("UPDATE games SET status = 'completed' WHERE id = ?");
    $update_game_stmt->bind_param("i", $game_id);
    $update_game_stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
