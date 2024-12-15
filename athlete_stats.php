<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'athlete_stats_error.log');

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get athlete ID from URL or session
$user_id = $_SESSION['user_id'];

try {
    // First get the athlete_id for the current user
    $athlete_id_query = "SELECT ap.id as athlete_id 
                        FROM users u 
                        JOIN athlete_profiles ap ON u.id = ap.user_id 
                        WHERE u.id = ?";
    $stmt = $conn->prepare($athlete_id_query);
    $stmt->execute([$user_id]);
    $athlete_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$athlete_row) {
        throw new Exception("No athlete profile found for this user.");
    }
    
    $athlete_id = $athlete_row['athlete_id'];
    error_log("Found athlete_id: " . $athlete_id . " for user_id: " . $user_id);

    // Debug the actual stats in the database
    $stats_check_query = "SELECT * FROM player_stats WHERE athlete_id = ?";
    $stmt = $conn->prepare($stats_check_query);
    $stmt->execute([$athlete_id]);
    $raw_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Raw stats from database: " . print_r($raw_stats, true));

    // Get athlete details with their aggregated stats - simplified query first
    $simple_stats_query = "SELECT 
                            SUM(points) as total_points,
                            SUM(assists) as total_assists,
                            SUM(rebounds) as total_rebounds,
                            SUM(steals) as total_steals,
                            SUM(blocks) as total_blocks,
                            ROUND(AVG(points), 1) as avg_points,
                            COUNT(DISTINCT match_id) as games_played
                          FROM player_stats 
                          WHERE athlete_id = ?";
    
    $stmt = $conn->prepare($simple_stats_query);
    $stmt->execute([$athlete_id]);
    $simple_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Simple stats query results: " . print_r($simple_stats, true));

    // Now get the full athlete details
    $athlete_query = "SELECT 
                        ap.*,
                        u.first_name,
                        u.last_name,
                        u.profile_image,
                        s.name as sport_name,
                        p.name as position_name,
                        t.name as team_name,
                        COALESCE(ps_stats.total_points, 0) as total_points,
                        COALESCE(ps_stats.total_assists, 0) as total_assists,
                        COALESCE(ps_stats.total_rebounds, 0) as total_rebounds,
                        COALESCE(ps_stats.total_steals, 0) as total_steals,
                        COALESCE(ps_stats.total_blocks, 0) as total_blocks,
                        COALESCE(ps_stats.avg_points, 0) as avg_points,
                        COALESCE(ps_stats.games_played, 0) as games_played
                     FROM users u
                     JOIN athlete_profiles ap ON u.id = ap.user_id
                     LEFT JOIN sports s ON ap.sport_id = s.id
                     LEFT JOIN positions p ON ap.position_id = p.id
                     LEFT JOIN team_members tm ON ap.id = tm.athlete_id
                     LEFT JOIN teams t ON tm.team_id = t.id
                     LEFT JOIN (
                        SELECT 
                            athlete_id,
                            SUM(points) as total_points,
                            SUM(assists) as total_assists,
                            SUM(rebounds) as total_rebounds,
                            SUM(steals) as total_steals,
                            SUM(blocks) as total_blocks,
                            ROUND(AVG(points), 1) as avg_points,
                            COUNT(DISTINCT match_id) as games_played
                        FROM player_stats
                        GROUP BY athlete_id
                     ) ps_stats ON ap.id = ps_stats.athlete_id
                     WHERE ap.id = ?";

    error_log("About to execute query with athlete_id: " . $athlete_id);
    $stmt = $conn->prepare($athlete_query);
    $stmt->execute([$athlete_id]);
    $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Athlete query results: " . print_r($athlete, true));
    
    if (!$athlete) {
        throw new Exception("Athlete profile not found. Please complete your athlete profile first.");
    }

    error_log("Found athlete profile: " . print_r($athlete, true));

    // Get recent match stats (last 7 days)
    $recent_stats_query = "SELECT 
                            ps.*,
                            m.match_date,
                            ht.name as home_team,
                            at.name as away_team
                         FROM player_stats ps
                         JOIN matches m ON ps.match_id = m.id
                         LEFT JOIN teams ht ON m.home_team_id = ht.id
                         LEFT JOIN teams at ON m.away_team_id = at.id
                         WHERE ps.athlete_id = ?
                         AND m.match_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                         ORDER BY m.match_date DESC";

    $stmt = $conn->prepare($recent_stats_query);
    $stmt->execute([$athlete_id]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Stats found: " . print_r($stats, true));

} catch (Exception $e) {
    error_log("Error in athlete_stats.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("An error occurred: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Stats - <?php echo htmlspecialchars($athlete['first_name'] ?? '') . ' ' . htmlspecialchars($athlete['last_name'] ?? ''); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #2C1810;
            color: #FFD700;
            font-family: 'Press Start 2P', cursive;
            line-height: 1.6;
            image-rendering: pixelated;
        }
        .vintage-bg {
            background-color: #2C1810;
            min-height: 100vh;
            padding: 20px;
            image-rendering: pixelated;
        }
        .athlete-header {
            background: #1a0f0a;
            border: 4px solid #FFD700;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            image-rendering: pixelated;
            box-shadow: 8px 8px 0px #000;
        }
        .profile-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 4px solid #FFD700;
            image-rendering: pixelated;
        }
        .athlete-info {
            flex-grow: 1;
        }
        .athlete-info h1 {
            color: #FFD700;
            margin-bottom: 20px;
            font-size: 24px;
            text-shadow: 3px 3px #000;
        }
        .athlete-info p {
            color: #FFD700;
            font-size: 14px;
            margin-bottom: 15px;
            text-shadow: 2px 2px #000;
        }
        .stats-breakdown {
            background: #1a0f0a;
            border: 4px solid #FFD700;
            padding: 20px;
            box-shadow: 8px 8px 0px #000;
            image-rendering: pixelated;
        }
        .stats-breakdown h2 {
            color: #FFD700;
            margin-bottom: 20px;
            text-align: center;
            font-size: 20px;
            text-shadow: 3px 3px #000;
        }
        .stats-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        .stats-table th,
        .stats-table td {
            padding: 12px 15px;
            text-align: left;
            border: 2px solid #FFD700;
            color: #FFD700;
            font-size: 14px;
        }
        .stats-table th {
            background-color: #3d2317;
            font-weight: normal;
        }
        .stats-table tr:hover {
            background-color: #3d2317;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .total-points {
            font-size: 18px;
            margin-top: 10px;
            text-align: right;
            color: #FFD700;
            text-shadow: 2px 2px #000;
        }
        /* Add pixel art borders */
        .pixel-border {
            position: relative;
        }
        .pixel-border::after {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            background: transparent;
            border: 4px solid #FFD700;
            pointer-events: none;
        }
    </style>
</head>
<body class="vintage-bg">
    <?php include 'components/navbar.php'; ?>

    <div class="container">
        <div class="athlete-header pixel-border">
            <img src="<?php echo htmlspecialchars($athlete['profile_image'] ? 'uploads/profile_images/' . $athlete['profile_image'] : 'assets/default_profile.jpg'); ?>" 
                 alt="Profile Image" 
                 class="profile-image">
            <div class="athlete-info">
                <h1><?php echo htmlspecialchars($athlete['first_name'] ?? '') . ' ' . htmlspecialchars($athlete['last_name'] ?? ''); ?></h1>
                <p><strong>Sport:</strong> <?php echo htmlspecialchars($athlete['sport_name'] ?? 'Not Specified'); ?></p>
                <p><strong>Position:</strong> <?php echo htmlspecialchars($athlete['position_name'] ?? 'Not Specified'); ?></p>
                <p><strong>Team:</strong> <?php echo htmlspecialchars($athlete['team_name'] ?? 'Not Specified'); ?></p>
                
                <?php
                // Use simple stats if available, otherwise fall back to main query results
                $stats_to_use = $simple_stats ?? $athlete;
                
                // Debug output
                error_log("Stats being displayed:");
                error_log(print_r($stats_to_use, true));
                ?>
                
                <p class="total-points">Total Points: <?php echo number_format((float)$stats_to_use['total_points'], 1); ?></p>
                <p class="total-points">Total Assists: <?php echo number_format((float)$stats_to_use['total_assists'], 1); ?></p>
                <p class="total-points">Total Rebounds: <?php echo number_format((float)$stats_to_use['total_rebounds'], 1); ?></p>
                <p class="total-points">Total Steals: <?php echo number_format((float)$stats_to_use['total_steals'], 1); ?></p>
                <p class="total-points">Total Blocks: <?php echo number_format((float)$stats_to_use['total_blocks'], 1); ?></p>
                <p class="total-points">Average Points: <?php echo number_format((float)$stats_to_use['avg_points'], 1); ?></p>
                <p class="total-points">Games Played: <?php echo number_format((float)$stats_to_use['games_played'], 0); ?></p>
            </div>
        </div>

        <div class="stats-breakdown pixel-border">
            <h2>Last 7 Days Performance</h2>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Points</th>
                        <th>Match Date</th>
                        <th>Home Team</th>
                        <th>Away Team</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No stats recorded in the last 7 days</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><?php echo number_format($stat['points'], 1); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($stat['match_date']))); ?></td>
                            <td><?php echo htmlspecialchars($stat['home_team']); ?></td>
                            <td><?php echo htmlspecialchars($stat['away_team']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
