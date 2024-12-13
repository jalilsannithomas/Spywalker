<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get all teams
$teams_query = "SELECT id, name FROM teams ORDER BY name";
$teams_result = $conn->query($teams_query);
$teams = $teams_result->fetch_all(MYSQLI_ASSOC);

// Get selected team
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

// Get team roster and matches
$team_players = [];
$team_matches = [];

if ($selected_team) {
    // Get team's players
    $players_query = "SELECT tm.user_id as id, tm.jersey_number, 
                            u.first_name, u.last_name,
                            p.name as position_name
                     FROM team_members tm
                     JOIN users u ON tm.user_id = u.id
                     LEFT JOIN positions p ON tm.position_id = p.id
                     WHERE tm.team_id = ?
                     ORDER BY tm.jersey_number";
    $stmt = $conn->prepare($players_query);
    $stmt->bind_param("i", $selected_team);
    $stmt->execute();
    $team_players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get team's matches
    $matches_query = "SELECT m.*, 
                            ht.name as home_team_name, 
                            at.name as away_team_name,
                            ts_home.points_scored as home_score,
                            ts_away.points_scored as away_score
                     FROM matches m
                     JOIN teams ht ON m.home_team_id = ht.id
                     JOIN teams at ON m.away_team_id = at.id
                     LEFT JOIN team_stats ts_home ON m.id = ts_home.match_id AND ts_home.team_id = m.home_team_id
                     LEFT JOIN team_stats ts_away ON m.id = ts_away.match_id AND ts_away.team_id = m.away_team_id
                     WHERE m.home_team_id = ? OR m.away_team_id = ?
                     ORDER BY m.match_date DESC";
    $stmt = $conn->prepare($matches_query);
    $stmt->bind_param("ii", $selected_team, $selected_team);
    $stmt->execute();
    $team_matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stats - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/manage-stats.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>
    
    <div class="container">
        <div class="page-title">
            <h1 class="title-main">Manage Stats</h1>
            <p class="title-sub">View and manage team statistics</p>
        </div>

        <div class="team-select-container">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="team_id" class="select-label">Select Team:</label>
                    <select name="team_id" id="team_id" class="pixel-select" onchange="this.form.submit()">
                        <option value="">Select a team...</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $selected_team == $team['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_team): ?>
            <div class="stats-card">
                <div class="card-header">
                    <h2 class="card-title">Team Statistics</h2>
                </div>
                <div class="table-responsive">
                    <table class="pixel-table">
                        <thead>
                            <tr>
                                <th>Player Name</th>
                                <th>Matches Played</th>
                                <th>Wins</th>
                                <th>Losses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_players as $player): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td><button class="pixel-btn" onclick="viewPlayerStats(<?php echo $player['id']; ?>)">View Details</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="stats-card">
                <div class="card-header">
                    <h2 class="card-title">Recent Matches</h2>
                </div>
                <div class="table-responsive">
                    <table class="pixel-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Opponent</th>
                                <th>Result</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_matches as $match): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($match['match_date'])); ?></td>
                                <td><?php echo htmlspecialchars($match['home_team_name'] == $match['away_team_name'] ? $match['away_team_name'] : $match['home_team_name']); ?></td>
                                <td>
                                    <?php if ($match['home_score'] !== null && $match['away_score'] !== null): ?>
                                        <?php if ($match['home_score'] > $match['away_score']): ?>
                                            Win
                                        <?php elseif ($match['home_score'] < $match['away_score']): ?>
                                            Loss
                                        <?php else: ?>
                                            Tie
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Pending
                                    <?php endif; ?>
                                </td>
                                <td><button class="pixel-btn" onclick="viewMatchStats(<?php echo $match['id']; ?>)">View Details</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pixel-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Statistics Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="statsModalContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="pixel-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPlayerStats(playerId) {
            // Add your code to load and display player stats in the modal
            const modal = new bootstrap.Modal(document.getElementById('statsModal'));
            modal.show();
        }

        function viewMatchStats(matchId) {
            // Add your code to load and display match stats in the modal
            const modal = new bootstrap.Modal(document.getElementById('statsModal'));
            modal.show();
        }
    </script>
</body>
</html>
