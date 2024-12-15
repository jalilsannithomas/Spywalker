<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied";
    exit();
}

$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;
$home_team_id = isset($_GET['home_team_id']) ? intval($_GET['home_team_id']) : 0;
$away_team_id = isset($_GET['away_team_id']) ? intval($_GET['away_team_id']) : 0;

if (!$match_id || !$home_team_id || !$away_team_id) {
    header('HTTP/1.1 400 Bad Request');
    echo "Missing required parameters";
    exit();
}

// Get players from both teams
$players_query = "SELECT u.id, u.first_name, u.last_name, t.id as team_id, t.name as team_name,
                        ps.points, ps.assists, ps.rebounds, ps.steals, ps.blocks, ps.minutes_played
                 FROM team_members tm
                 JOIN users u ON tm.athlete_id = u.id
                 JOIN teams t ON tm.team_id = t.id
                 LEFT JOIN player_stats ps ON ps.athlete_id = u.id AND ps.match_id = ?
                 WHERE tm.team_id IN (?, ?)
                 ORDER BY t.id, u.last_name, u.first_name";

try {
    $stmt = $conn->prepare($players_query);
    $stmt->execute([$match_id, $home_team_id, $away_team_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $current_team = null;
    foreach ($players as $player) {
        if ($current_team !== $player['team_name']) {
            if ($current_team !== null) {
                echo '</div>'; // Close previous team's container
            }
            $current_team = $player['team_name'];
            echo "<h6 class='mt-3'>{$player['team_name']}</h6>";
            echo '<div class="team-players-container">';
        }
        ?>
        <div class="player-stats-form mb-3">
            <h6><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></h6>
            <div class="row">
                <div class="col">
                    <label class="form-label">Minutes</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][minutes_played]" 
                           value="<?php echo $player['minutes_played'] ?? ''; ?>" min="0" max="48">
                </div>
                <div class="col">
                    <label class="form-label">Points</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][points]" 
                           value="<?php echo $player['points'] ?? '0'; ?>" min="0">
                </div>
                <div class="col">
                    <label class="form-label">Assists</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][assists]" 
                           value="<?php echo $player['assists'] ?? '0'; ?>" min="0">
                </div>
                <div class="col">
                    <label class="form-label">Rebounds</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][rebounds]" 
                           value="<?php echo $player['rebounds'] ?? '0'; ?>" min="0">
                </div>
                <div class="col">
                    <label class="form-label">Steals</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][steals]" 
                           value="<?php echo $player['steals'] ?? '0'; ?>" min="0">
                </div>
                <div class="col">
                    <label class="form-label">Blocks</label>
                    <input type="number" class="form-control" 
                           name="player_stats[<?php echo $player['id']; ?>][blocks]" 
                           value="<?php echo $player['blocks'] ?? '0'; ?>" min="0">
                </div>
            </div>
        </div>
        <?php
    }
    if ($current_team !== null) {
        echo '</div>'; // Close last team's container
    }
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Database error: " . $e->getMessage();
    exit();
}
?>
