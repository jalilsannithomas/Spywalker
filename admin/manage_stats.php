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
$teams_stmt = $conn->query($teams_query);
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected team
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

// Get team roster and matches
$team_players = [];
$team_matches = [];

if ($selected_team) {
    // Get team's players with their stats
    $players_query = "SELECT 
                        u.id, 
                        u.first_name, 
                        u.last_name,
                        SUM(ps.minutes_played) as total_minutes,
                        SUM(ps.points) as total_points,
                        SUM(ps.assists) as total_assists,
                        SUM(ps.rebounds) as total_rebounds,
                        SUM(ps.steals) as total_steals,
                        SUM(ps.blocks) as total_blocks
                     FROM team_members tm
                     JOIN users u ON tm.athlete_id = u.id
                     LEFT JOIN player_stats ps ON ps.athlete_id = u.id
                     WHERE tm.team_id = ?
                     GROUP BY u.id, u.first_name, u.last_name
                     ORDER BY u.last_name, u.first_name";
    
    $stmt = $conn->prepare($players_query);
    $stmt->execute([$selected_team]);
    $team_players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get team's matches
    $matches_query = "SELECT m.*, 
                            ht.name as home_team_name, 
                            at.name as away_team_name,
                            ts_home.total_points as home_score,
                            ts_away.total_points as away_score,
                            ts_home.total_assists as home_assists,
                            ts_away.total_assists as away_assists,
                            ts_home.total_rebounds as home_rebounds,
                            ts_away.total_rebounds as away_rebounds,
                            ts_home.total_steals as home_steals,
                            ts_away.total_steals as away_steals,
                            ts_home.total_blocks as home_blocks,
                            ts_away.total_blocks as away_blocks
                     FROM matches m
                     JOIN teams ht ON m.home_team_id = ht.id
                     JOIN teams at ON m.away_team_id = at.id
                     LEFT JOIN team_stats ts_home ON m.id = ts_home.match_id AND ts_home.team_id = m.home_team_id
                     LEFT JOIN team_stats ts_away ON m.id = ts_away.match_id AND ts_away.team_id = m.away_team_id
                     WHERE m.home_team_id = ? OR m.away_team_id = ?
                     ORDER BY m.match_date DESC";
    $stmt = $conn->prepare($matches_query);
    $stmt->bindValue(1, $selected_team, PDO::PARAM_INT);
    $stmt->bindValue(2, $selected_team, PDO::PARAM_INT);
    $stmt->execute();
    $team_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle POST request for updating stats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stats'])) {
    try {
        $match_id = $_POST['match_id'];
        $home_team_id = $_POST['home_team_id'];
        $away_team_id = $_POST['away_team_id'];
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Update home team stats
        $home_stats_sql = "INSERT INTO team_stats (team_id, match_id, total_points, total_assists, total_rebounds, total_steals, total_blocks) 
                          VALUES (?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          total_points = VALUES(total_points),
                          total_assists = VALUES(total_assists),
                          total_rebounds = VALUES(total_rebounds),
                          total_steals = VALUES(total_steals),
                          total_blocks = VALUES(total_blocks)";
        
        $home_stmt = $conn->prepare($home_stats_sql);
        $home_stmt->execute([
            $home_team_id,
            $match_id,
            $_POST['home_points'],
            $_POST['home_assists'],
            $_POST['home_rebounds'],
            $_POST['home_steals'],
            $_POST['home_blocks']
        ]);
        
        // Update away team stats
        $away_stmt = $conn->prepare($home_stats_sql);
        $away_stmt->execute([
            $away_team_id,
            $match_id,
            $_POST['away_points'],
            $_POST['away_assists'],
            $_POST['away_rebounds'],
            $_POST['away_steals'],
            $_POST['away_blocks']
        ]);

        // Update individual player stats
        if (isset($_POST['player_stats']) && is_array($_POST['player_stats'])) {
            $player_stats_sql = "INSERT INTO player_stats 
                                (athlete_id, match_id, minutes_played, points, assists, rebounds, steals, blocks, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE 
                                minutes_played = VALUES(minutes_played),
                                points = VALUES(points),
                                assists = VALUES(assists),
                                rebounds = VALUES(rebounds),
                                steals = VALUES(steals),
                                blocks = VALUES(blocks),
                                updated_at = NOW()";
            
            $player_stmt = $conn->prepare($player_stats_sql);
            
            foreach ($_POST['player_stats'] as $player_id => $stats) {
                error_log("Saving stats for player ID: " . $player_id);
                error_log("Stats data: " . print_r($stats, true));
                
                $player_stmt->execute([
                    $player_id,
                    $match_id,
                    $stats['minutes_played'] ?? 0,
                    $stats['points'] ?? 0,
                    $stats['assists'] ?? 0,
                    $stats['rebounds'] ?? 0,
                    $stats['steals'] ?? 0,
                    $stats['blocks'] ?? 0
                ]);
                
                error_log("Player stats saved. Rows affected: " . $player_stmt->rowCount());
            }
        }
        
        // Update match status to completed
        $match_update_sql = "UPDATE matches SET status = 'completed' WHERE id = ?";
        $match_stmt = $conn->prepare($match_update_sql);
        $match_stmt->execute([$match_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Match statistics updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error updating statistics: " . $e->getMessage();
    }
    
    // Redirect back to the same page with team selection
    header("Location: manage_stats.php?team_id=" . $selected_team);
    exit();
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
    <style>
        .table td, .table th {
            color: #FFD700; /* Yellow color */
            vertical-align: middle;
        }
        
        .table thead th {
            color: #FFD700;
            font-weight: bold;
        }
    </style>
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
                    <table class="table">
                        <thead>
                            <tr>
                                <th>PLAYER NAME</th>
                                <th>MINUTES</th>
                                <th>POINTS</th>
                                <th>ASSISTS</th>
                                <th>REBOUNDS</th>
                                <th>STEALS</th>
                                <th>BLOCKS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_players as $player): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                                    <td><?php echo $player['total_minutes'] ?? 0; ?></td>
                                    <td><?php echo $player['total_points'] ?? 0; ?></td>
                                    <td><?php echo $player['total_assists'] ?? 0; ?></td>
                                    <td><?php echo $player['total_rebounds'] ?? 0; ?></td>
                                    <td><?php echo $player['total_steals'] ?? 0; ?></td>
                                    <td><?php echo $player['total_blocks'] ?? 0; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary view-stats-btn" 
                                                data-player-id="<?php echo $player['id']; ?>"
                                                data-player-name="<?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>">
                                            View Details
                                        </button>
                                    </td>
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
                    <table class="table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>OPPONENT</th>
                                <th>RESULT</th>
                                <th>SCORE</th>
                                <th>ASSISTS</th>
                                <th>REBOUNDS</th>
                                <th>STEALS</th>
                                <th>BLOCKS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_matches as $match): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($match['match_date'])); ?></td>
                                <td>
                                    <?php 
                                    echo ($match['home_team_id'] == $selected_team) 
                                        ? $match['away_team_name'] 
                                        : $match['home_team_name']; 
                                    ?>
                                </td>
                                <td><?php echo $match['status']; ?></td>
                                <td>
                                    <?php
                                    if ($match['home_score'] !== null || $match['away_score'] !== null) {
                                        echo $match['home_score'] . ' - ' . $match['away_score'];
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $match['home_assists'] ?? '-'; ?></td>
                                <td><?php echo $match['home_rebounds'] ?? '-'; ?></td>
                                <td><?php echo $match['home_steals'] ?? '-'; ?></td>
                                <td><?php echo $match['home_blocks'] ?? '-'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary update-stats-btn"
                                            data-match-id="<?php echo $match['id']; ?>"
                                            data-home-team-id="<?php echo $match['home_team_id']; ?>"
                                            data-away-team-id="<?php echo $match['away_team_id']; ?>"
                                            data-home-team="<?php echo $match['home_team_name']; ?>"
                                            data-away-team="<?php echo $match['away_team_name']; ?>">
                                        Update Stats
                                    </button>
                                </td>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Match Statistics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="statsForm" method="POST">
                        <input type="hidden" name="update_stats" value="1">
                        <input type="hidden" name="match_id" id="match_id">
                        <input type="hidden" name="home_team_id" id="home_team_id">
                        <input type="hidden" name="away_team_id" id="away_team_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="home-team-name"></h6>
                                <div class="mb-3">
                                    <label class="form-label">Points</label>
                                    <input type="number" class="form-control" name="home_points" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assists</label>
                                    <input type="number" class="form-control" name="home_assists" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rebounds</label>
                                    <input type="number" class="form-control" name="home_rebounds" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Steals</label>
                                    <input type="number" class="form-control" name="home_steals" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Blocks</label>
                                    <input type="number" class="form-control" name="home_blocks" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="away-team-name"></h6>
                                <div class="mb-3">
                                    <label class="form-label">Points</label>
                                    <input type="number" class="form-control" name="away_points" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assists</label>
                                    <input type="number" class="form-control" name="away_assists" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rebounds</label>
                                    <input type="number" class="form-control" name="away_rebounds" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Steals</label>
                                    <input type="number" class="form-control" name="away_steals" required min="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Blocks</label>
                                    <input type="number" class="form-control" name="away_blocks" required min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6>Player Statistics</h6>
                                <div id="playerStatsForms"></div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Statistics</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statsModal = new bootstrap.Modal(document.getElementById('statsModal'));
            
            document.querySelectorAll('.update-stats-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const matchId = this.dataset.matchId;
                    const homeTeamId = this.dataset.homeTeamId;
                    const awayTeamId = this.dataset.awayTeamId;
                    const homeTeam = this.dataset.homeTeam;
                    const awayTeam = this.dataset.awayTeam;
                    
                    document.getElementById('match_id').value = matchId;
                    document.getElementById('home_team_id').value = homeTeamId;
                    document.getElementById('away_team_id').value = awayTeamId;
                    document.querySelector('.home-team-name').textContent = homeTeam;
                    document.querySelector('.away-team-name').textContent = awayTeam;
                    
                    // Fetch and display player stats forms
                    fetch(`get_team_players.php?match_id=${matchId}&home_team_id=${homeTeamId}&away_team_id=${awayTeamId}`)
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('playerStatsForms').innerHTML = html;
                        });
                    
                    statsModal.show();
                });
            });
        });
    </script>
</body>
</html>
