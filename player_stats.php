<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

error_log("SESSION contents: " . print_r($_SESSION, true));
$user_id = $_SESSION['user_id'];

try {
    error_log("User ID from session: " . $user_id);

    // Debug: Check all athlete profiles
    $debug_query = "SELECT u.id as user_id, ap.id as athlete_id, u.first_name, u.last_name 
                   FROM users u 
                   JOIN athlete_profiles ap ON u.id = ap.user_id";
    $stmt = $conn->prepare($debug_query);
    $stmt->execute();
    $all_athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All athlete profiles: " . print_r($all_athletes, true));

    // Debug: Check all player stats with match details
    $debug_stats = "SELECT ps.*, m.match_date, ht.name as home_team, at.name as away_team
                    FROM player_stats ps
                    JOIN matches m ON ps.match_id = m.id
                    LEFT JOIN teams ht ON m.home_team_id = ht.id
                    LEFT JOIN teams at ON m.away_team_id = at.id
                    ORDER BY ps.athlete_id, m.match_date DESC";
    $stmt = $conn->prepare($debug_stats);
    $stmt->execute();
    $all_player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All player stats with match details: " . print_r($all_player_stats, true));

    // First get the athlete_id for the current user
    $athlete_id_query = "SELECT ap.id as athlete_id, u.first_name, u.last_name
                        FROM users u 
                        JOIN athlete_profiles ap ON u.id = ap.user_id 
                        WHERE u.id = ?";
    $stmt = $conn->prepare($athlete_id_query);
    $stmt->execute([$user_id]);
    $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Athlete profile query results: " . print_r($athlete, true));
    
    if (!$athlete) {
        throw new Exception("No athlete profile found for this user.");
    }

    error_log("Found athlete_id: " . $athlete['athlete_id']);

    // Get the athlete's stats using the same approach as manage_stats.php
    $stats_query = "SELECT 
                    u.id, 
                    u.first_name, 
                    u.last_name,
                    SUM(ps.minutes_played) as total_minutes,
                    SUM(ps.points) as total_points,
                    SUM(ps.assists) as total_assists,
                    SUM(ps.rebounds) as total_rebounds,
                    SUM(ps.steals) as total_steals,
                    SUM(ps.blocks) as total_blocks,
                    COUNT(DISTINCT ps.match_id) as games_played
                FROM users u 
                LEFT JOIN player_stats ps ON ps.athlete_id = u.id
                WHERE u.id = ?
                GROUP BY u.id, u.first_name, u.last_name";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$user_id]);
    $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Total stats: " . print_r($total_stats, true));

    // Get match history
    $matches_query = "SELECT 
                        ps.*,
                        m.match_date,
                        ht.name as home_team,
                        at.name as away_team
                    FROM player_stats ps
                    JOIN matches m ON ps.match_id = m.id
                    LEFT JOIN teams ht ON m.home_team_id = ht.id
                    LEFT JOIN teams at ON m.away_team_id = at.id
                    WHERE ps.athlete_id = ?
                    ORDER BY m.match_date DESC";
    
    $stmt = $conn->prepare($matches_query);
    $stmt->execute([$user_id]);
    $match_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Match stats: " . print_r($match_stats, true));

} catch (Exception $e) {
    error_log("Error in player_stats.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("An error occurred: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Stats - <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #2C1810;
            color: #FFD700;
            font-family: 'Press Start 2P', cursive;
            line-height: 1.6;
        }
        .stats-container {
            background: #3d2317;
            border: 4px solid #FFD700;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .stats-header {
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 3px 3px #000;
        }
        .total-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-box {
            background: #2C1810;
            border: 2px solid #FFD700;
            padding: 15px;
            text-align: center;
        }
        .stat-label {
            font-size: 0.8em;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 1.2em;
            color: #FFD700;
        }
        .stats-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        .stats-table th,
        .stats-table td {
            padding: 12px;
            text-align: center;
            border: 2px solid #FFD700;
            font-size: 0.8em;
        }
        .stats-table th {
            background: #3d2317;
        }
        .stats-table tr:hover {
            background: #3d2317;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container">
        <div class="stats-container">
            <div class="stats-header">
                <h1><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>'s Stats</h1>
            </div>

            <div class="total-stats">
                <div class="stat-box">
                    <div class="stat-label">Total Points</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_points']); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Average Points</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_points'] / $total_stats['games_played'], 1); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Assists</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_assists']); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Rebounds</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_rebounds']); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Steals</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_steals']); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Blocks</div>
                    <div class="stat-value"><?php echo number_format($total_stats['total_blocks']); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Games Played</div>
                    <div class="stat-value"><?php echo number_format($total_stats['games_played']); ?></div>
                </div>
            </div>

            <h2>Match History</h2>
            <div class="table-responsive">
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Teams</th>
                            <th>Points</th>
                            <th>Assists</th>
                            <th>Rebounds</th>
                            <th>Steals</th>
                            <th>Blocks</th>
                            <th>Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($match_stats)): ?>
                            <tr>
                                <td colspan="8">No stats recorded yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($match_stats as $stat): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($stat['match_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($stat['home_team']) . ' vs ' . htmlspecialchars($stat['away_team']); ?></td>
                                    <td><?php echo $stat['points']; ?></td>
                                    <td><?php echo $stat['assists']; ?></td>
                                    <td><?php echo $stat['rebounds']; ?></td>
                                    <td><?php echo $stat['steals']; ?></td>
                                    <td><?php echo $stat['blocks']; ?></td>
                                    <td><?php echo $stat['minutes_played']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
