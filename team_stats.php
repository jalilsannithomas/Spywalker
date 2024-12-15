<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$team_stats = null;
$player_stats = [];
$recent_matches = [];
$stat_sheet = [];
$sport_type = '';

try {
    // Get team stats based on user role
    if ($role === 'fan') {
        $sql = "SELECT 
            CONCAT(ap.first_name, ' ', ap.last_name) as player_name,
            COALESCE(t.name, 'No Team') as team_name,
            COALESCE(s.name, 'No Sport') as sport_name,
            COUNT(DISTINCT m.id) as games_played,
            COALESCE(SUM(ps.points), 0) as total_points,
            COALESCE(ROUND(AVG(ps.points), 1), 0) as avg_points,
            COALESCE(MAX(ps.points), 0) as highest_points,
            ap.position_id,
            ap.id as athlete_id,
            t.id as team_id,
            t.coach_id
        FROM fan_followed_athletes ffa
        JOIN athlete_profiles ap ON ffa.athlete_id = ap.user_id
        LEFT JOIN team_players tp ON ap.id = tp.athlete_id
        LEFT JOIN teams t ON t.id = tp.team_id
        LEFT JOIN sports s ON ap.sport_id = s.id
        LEFT JOIN matches m ON (m.home_team_id = t.id OR m.away_team_id = t.id) AND m.status = 'completed'
        LEFT JOIN player_stats ps ON ps.player_id = ap.id AND ps.match_id = m.id
        WHERE ffa.fan_id = :user_id
        GROUP BY ap.id, ap.first_name, ap.last_name, t.name, s.name, ap.position_id, t.coach_id
        ORDER BY ap.last_name, ap.first_name";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $followed_athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($followed_athletes)) {
            // Get recent matches for followed athletes
            $athlete_teams = array_unique(array_column($followed_athletes, 'team_id'));
            $team_placeholders = implode(',', array_fill(0, count($athlete_teams), '?'));
            
            $sql = "SELECT 
                        m.*,
                        ht.name as home_team_name,
                        at.name as away_team_name,
                        DATE_FORMAT(m.match_date, '%M %d, %Y') as formatted_date
                    FROM matches m
                    JOIN teams ht ON m.home_team_id = ht.id
                    JOIN teams at ON m.away_team_id = at.id
                    WHERE (m.home_team_id IN ($team_placeholders) OR m.away_team_id IN ($team_placeholders))
                        AND m.status = 'completed'
                    ORDER BY m.match_date DESC
                    LIMIT 5";
            
            $stmt = $conn->prepare($sql);
            $values = array_merge($athlete_teams, $athlete_teams);
            $stmt->execute($values);
            $recent_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else if ($role === 'athlete' || $role === 'admin' || $role === 'coach') {
        // Get user's team and sport information
        if ($role === 'coach') {
            $sql = "SELECT t.id as team_id, t.name as team_name, s.name as sport_name, s.id as sport_id,
                    t.primary_color, t.secondary_color
                    FROM teams t 
                    JOIN sports s ON t.sport_id = s.id 
                    WHERE t.coach_id = :user_id 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $team_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$team_result) {
                error_log("No team found for coach ID: " . $user_id);
            }
        } else {
            $sql = "SELECT t.id as team_id, t.name as team_name, s.name as sport_name, s.id as sport_id,
                    t.primary_color, t.secondary_color
                    FROM teams t 
                    INNER JOIN sports s ON t.sport_id = s.id 
                    INNER JOIN team_players tp ON t.id = tp.team_id 
                    INNER JOIN athlete_profiles ap ON tp.athlete_id = ap.id 
                    WHERE ap.user_id = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $team_result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($team_result) {
            $sport_type = strtolower($team_result['sport_name']);
            
            // Get team statistics
            $sql = "SELECT 
                        t.id as team_id,
                        t.name as team_name,
                        s.name as sport_name,
                        COUNT(DISTINCT m.id) as total_matches,
                        SUM(CASE 
                            WHEN (m.home_team_id = t.id AND m.home_score > m.away_score) OR 
                                 (m.away_team_id = t.id AND m.away_score > m.home_score) 
                            THEN 1 ELSE 0 
                        END) as wins,
                        SUM(CASE 
                            WHEN m.home_score = m.away_score THEN 1 
                            ELSE 0 
                        END) as draws,
                        SUM(CASE 
                            WHEN (m.home_team_id = t.id AND m.home_score < m.away_score) OR 
                                 (m.away_team_id = t.id AND m.away_score < m.home_score) 
                            THEN 1 ELSE 0 
                        END) as losses,
                        ROUND(AVG(
                            CASE 
                                WHEN m.home_team_id = t.id THEN m.home_score
                                WHEN m.away_team_id = t.id THEN m.away_score
                            END
                        ), 1) as avg_score
                    FROM teams t
                    JOIN " . ($role === 'athlete' ? 'team_players tp ON t.id = tp.team_id
                    JOIN athlete_profiles ap ON tp.athlete_id = ap.id' : 'coach_profiles cp ON cp.user_id = t.coach_id AND cp.sport_id = t.sport_id') . "
                    JOIN sports s ON t.sport_id = s.id
                    LEFT JOIN matches m ON (m.home_team_id = t.id OR m.away_team_id = t.id) AND m.status = 'completed'
                    WHERE " . ($role === 'athlete' ? 'ap.user_id = :user_id' : 'cp.user_id = :user_id') . "
                    GROUP BY t.id, t.name, s.name";

            error_log("Team stats query: " . $sql);
            error_log("Team ID: " . $team_result['team_id']);
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $team_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$team_stats) {
                error_log("No team stats found for team ID: " . $team_result['team_id']);
            }

            if ($team_stats) {
                // Get recent matches
                $sql = "SELECT 
                            m.*,
                            ht.name as home_team_name,
                            at.name as away_team_name,
                            DATE_FORMAT(m.match_date, '%M %d, %Y') as formatted_date
                        FROM matches m
                        JOIN teams ht ON m.home_team_id = ht.id
                        JOIN teams at ON m.away_team_id = at.id
                        WHERE (m.home_team_id = :team_id OR m.away_team_id = :team_id)
                            AND m.status = 'completed'
                        ORDER BY m.match_date DESC
                        LIMIT 5";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':team_id', $team_stats['team_id'], PDO::PARAM_INT);
                $stmt->execute();
                $recent_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get stat sheet data for the latest match
                $sql = "SELECT 
                            CONCAT(ap.first_name, ' ', ap.last_name) as player_name,
                            tp.jersey_number,
                            COALESCE(ps.points, 0) as points,
                            COALESCE(ps.assists, 0) as assists,
                            COALESCE(ps.rebounds, 0) as rebounds,
                            COALESCE(ps.steals, 0) as steals,
                            COALESCE(ps.blocks, 0) as blocks,
                            COALESCE(ps.minutes_played, 0) as minutes_played,
                            m.match_date,
                            CASE 
                                WHEN m.home_team_id = tp.team_id THEN at.name
                                ELSE ht.name
                            END as opposition
                        FROM team_players tp
                        JOIN athlete_profiles ap ON tp.athlete_id = ap.id
                        LEFT JOIN (
                            SELECT 
                                player_id,
                                match_id,
                                points,
                                assists,
                                rebounds,
                                steals,
                                blocks,
                                minutes_played
                            FROM player_stats
                            WHERE match_id IN (
                                SELECT id 
                                FROM matches 
                                WHERE status = 'completed'
                                AND (home_team_id = :team_id OR away_team_id = :team_id)
                            )
                        ) ps ON ps.player_id = ap.id
                        LEFT JOIN matches m ON ps.match_id = m.id
                        LEFT JOIN teams ht ON m.home_team_id = ht.id
                        LEFT JOIN teams at ON m.away_team_id = at.id
                        WHERE tp.team_id = :team_id
                        ORDER BY tp.jersey_number ASC, player_name ASC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':team_id', $team_stats['team_id'], PDO::PARAM_INT);
                $stmt->execute();
                $stat_sheet = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get match details for the stat sheet
                $match_date = !empty($stat_sheet) ? $stat_sheet[0]['match_date'] : date('Y-m-d');
                $opposition = !empty($stat_sheet) ? $stat_sheet[0]['opposition'] : '—';
            }
        } else {
            throw new Exception("No team found for the user");
        }
    } else {
        throw new Exception("Stats are only available for athletes, admins, and fans");
    }

} catch (PDOException $e) {
    error_log("Database error in team_stats.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching statistics. Please try again later.";
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Stats - SpyWalker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            color: #D4AF37;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .container {
            background-color: #3C2A20;
            border: 4px solid #D4AF37;
            border-radius: 0;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
            image-rendering: pixelated;
        }

        h2 {
            color: #D4AF37;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-size: 20px;
            text-shadow: 2px 2px #000;
            letter-spacing: 2px;
            position: relative;
            padding-left: 10px;
        }

        h2:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 100%;
            background-color: #D4AF37;
        }

        .table {
            background-color: transparent !important;
            color: #D4AF37 !important;
            border: 2px solid #D4AF37 !important;
            margin-bottom: 30px;
            image-rendering: pixelated;
        }

        .table > :not(caption) > * > * {
            background-color: transparent !important;
            color: #D4AF37 !important;
            border-bottom-color: #D4AF37 !important;
            padding: 12px !important;
        }

        .table > thead {
            border-bottom: 2px solid #D4AF37 !important;
            background-color: #241409 !important;
        }

        th {
            background-color: #241409 !important;
            color: #D4AF37 !important;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: normal;
            letter-spacing: 1px;
            border: none !important;
            text-shadow: 1px 1px #000;
        }

        td {
            font-size: 12px;
            border: 1px solid #4A3828 !important;
            position: relative;
        }

        tr {
            background-color: transparent !important;
            transition: all 0.3s ease;
        }

        tr:hover {
            background-color: rgba(212, 175, 55, 0.1) !important;
            transform: translateX(4px);
        }

        tr:nth-child(even) {
            background-color: rgba(74, 56, 40, 0.3) !important;
        }

        .welcome-message {
            background-color: #241409;
            border: 2px solid #D4AF37;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5);
        }

        .welcome-message h3 {
            color: #D4AF37;
            font-size: 16px;
            margin-bottom: 15px;
            text-shadow: 2px 2px #000;
        }

        .welcome-message p {
            font-size: 12px;
            line-height: 1.8;
            margin-bottom: 10px;
        }

        .table-responsive {
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            border-radius: 0;
            overflow: hidden;
            margin-bottom: 30px;
            padding: 1px;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5);
        }

        /* Navbar Pixel Art Styling */
        .navbar {
            background-color: #241409 !important;
            border-bottom: 4px solid #D4AF37;
            padding: 1rem;
            box-shadow: 0 4px 0 rgba(0, 0, 0, 0.5);
        }

        .navbar-brand {
            color: #D4AF37 !important;
            font-size: 16px;
            text-shadow: 2px 2px #000;
            letter-spacing: 2px;
        }

        .nav-link {
            color: #D4AF37 !important;
            font-size: 12px;
            text-transform: uppercase;
            padding: 8px 16px !important;
            margin: 0 4px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            border: 2px solid #D4AF37;
            background-color: rgba(212, 175, 55, 0.1);
            transform: translateY(-2px);
        }

        .navbar-toggler {
            border: 2px solid #D4AF37 !important;
            padding: 4px 8px;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(212, 175, 55, 1)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($role === 'coach' && isset($team_result)): ?>
            <div class="team-info" style="background: <?php echo htmlspecialchars($team_result['primary_color']); ?>; 
                                        color: <?php echo htmlspecialchars($team_result['secondary_color']); ?>;
                                        padding: 20px; 
                                        margin: 20px 0; 
                                        border-radius: 10px;
                                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                <h2 style="margin-bottom: 15px;">Team Information</h2>
                <p><strong>Team Name:</strong> <?php echo htmlspecialchars($team_result['team_name']); ?></p>
                <p><strong>Sport:</strong> <?php echo htmlspecialchars($team_result['sport_name']); ?></p>
                <div class="color-display">
                    <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo htmlspecialchars($team_result['primary_color']); ?>; margin-right: 10px;"></span>
                    <span style="display: inline-block; width: 20px; height: 20px; background: <?php echo htmlspecialchars($team_result['secondary_color']); ?>;"></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($team_stats) && empty($followed_athletes)): ?>
            <div class="alert alert-warning">
                No team statistics available. This could be because:
                <ul>
                    <li>You are not assigned as a coach to any team</li>
                    <li>Your team has not played any matches yet</li>
                    <li>There might be an issue with the team assignment</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($role === 'fan'): ?>
            <?php if (empty($followed_athletes)): ?>
                <div class="alert">
                    You are not following any athletes yet. Visit the <a href="athlete_list.php">Athletes List</a> to follow some athletes!
                </div>
            <?php else: ?>
                <h2>Followed Athletes Statistics</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Sport</th>
                                <th>Games</th>
                                <th>Total Points</th>
                                <th>Average Points</th>
                                <th>Highest Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followed_athletes as $athlete): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($athlete['player_name'] ?? 'Unknown Player'); ?></td>
                                    <td><?php echo htmlspecialchars($athlete['team_name'] ?? 'No Team'); ?></td>
                                    <td><?php echo htmlspecialchars($athlete['sport_name'] ?? 'No Sport'); ?></td>
                                    <td><?php echo $athlete['games_played'] ?? 0; ?></td>
                                    <td><?php echo $athlete['total_points'] ?? 0; ?></td>
                                    <td><?php echo $athlete['avg_points'] ?? 0; ?></td>
                                    <td><?php echo $athlete['highest_points'] ?? 0; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($recent_matches)): ?>
                    <h2>Recent Matches</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Home Team</th>
                                    <th>Away Team</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_matches as $match): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($match['formatted_date']); ?></td>
                                        <td><?php echo htmlspecialchars($match['home_team_name']); ?></td>
                                        <td><?php echo htmlspecialchars($match['away_team_name']); ?></td>
                                        <td><?php echo $match['home_score'] . ' - ' . $match['away_score']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($team_stats): ?>
                <h2>Team Statistics</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Team Name</th>
                                <th>Sport</th>
                                <th>Games Played</th>
                                <th>Wins</th>
                                <th>Losses</th>
                                <th>Win Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($team_stats['team_name']); ?></td>
                                <td><?php echo htmlspecialchars($team_stats['sport_name']); ?></td>
                                <td><?php echo $team_stats['total_matches']; ?></td>
                                <td><?php echo $team_stats['wins']; ?></td>
                                <td><?php echo $team_stats['losses']; ?></td>
                                <td><?php echo $team_stats['total_matches'] > 0 ? number_format(($team_stats['wins'] / $team_stats['total_matches'] * 100), 1) : '0.0'; ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if ($role === 'athlete'): ?>
                    <h2>Personal Statistics</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>Points Per Action</th>
                                    <th>Total Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Points Scored</td>
                                    <td><?php echo isset($stat_sheet[0]['points']) ? $stat_sheet[0]['points'] : '0'; ?></td>
                                    <td>1.0</td>
                                    <td><?php echo isset($stat_sheet[0]['points']) ? $stat_sheet[0]['points'] * 1.0 : '0.0'; ?></td>
                                </tr>
                                <tr>
                                    <td>Rebound</td>
                                    <td><?php echo isset($stat_sheet[0]['rebounds']) ? $stat_sheet[0]['rebounds'] : '0'; ?></td>
                                    <td>1.2</td>
                                    <td><?php echo isset($stat_sheet[0]['rebounds']) ? number_format($stat_sheet[0]['rebounds'] * 1.2, 1) : '0.0'; ?></td>
                                </tr>
                                <tr>
                                    <td>Assist</td>
                                    <td><?php echo isset($stat_sheet[0]['assists']) ? $stat_sheet[0]['assists'] : '0'; ?></td>
                                    <td>1.5</td>
                                    <td><?php echo isset($stat_sheet[0]['assists']) ? number_format($stat_sheet[0]['assists'] * 1.5, 1) : '0.0'; ?></td>
                                </tr>
                                <tr>
                                    <td>Steal</td>
                                    <td><?php echo isset($stat_sheet[0]['steals']) ? $stat_sheet[0]['steals'] : '0'; ?></td>
                                    <td>2.0</td>
                                    <td><?php echo isset($stat_sheet[0]['steals']) ? number_format($stat_sheet[0]['steals'] * 2.0, 1) : '0.0'; ?></td>
                                </tr>
                                <tr>
                                    <td>Block</td>
                                    <td><?php echo isset($stat_sheet[0]['blocks']) ? $stat_sheet[0]['blocks'] : '0'; ?></td>
                                    <td>2.0</td>
                                    <td><?php echo isset($stat_sheet[0]['blocks']) ? number_format($stat_sheet[0]['blocks'] * 2.0, 1) : '0.0'; ?></td>
                                </tr>
                                <tr>
                                    <td>Turnover</td>
                                    <td>0</td>
                                    <td>-1.0</td>
                                    <td>0.0</td>
                                </tr>
                                <tr>
                                    <td>Total Fantasy Points</td>
                                    <td colspan="3"><?php 
                                        $total_points = 
                                            ($stat_sheet[0]['points'] ?? 0) * 1.0 +
                                            ($stat_sheet[0]['rebounds'] ?? 0) * 1.2 +
                                            ($stat_sheet[0]['assists'] ?? 0) * 1.5 +
                                            ($stat_sheet[0]['steals'] ?? 0) * 2.0 +
                                            ($stat_sheet[0]['blocks'] ?? 0) * 2.0;
                                        echo number_format($total_points, 1);
                                    ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($stat_sheet)): ?>
                        <div class="welcome-message">
                            <h3>Welcome to Your Stats Page!</h3>
                            <p>You haven't recorded any statistics yet for <?php echo htmlspecialchars($team_stats['sport_name']); ?>.</p>
                            <p>Your performance statistics will appear here once they are recorded during matches.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
