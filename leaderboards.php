<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Helper function to safely handle null values in htmlspecialchars
function safe_html($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    // Enable detailed error logging
    error_log("Starting fantasy league data fetch for user_id: " . $user_id);
    
    // Check if fantasy tables exist
    $check_tables = $conn->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        AND table_name IN ('fantasy_leagues', 'fantasy_teams', 'fantasy_team_players', 'fantasy_points')
    ");
    $table_count = $check_tables->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Fantasy tables count: " . $table_count);

    if ($table_count < 4) {
        error_log("Missing fantasy tables. Redirecting to setup.");
        header("Location: setup_fantasy_league.php");
        exit();
    }

    // First, check if user has a fantasy team
    $team_query = "SELECT * FROM fantasy_teams WHERE user_id = ?";
    $stmt = $conn->prepare($team_query);
    $stmt->execute([$user_id]);
    $fantasy_team = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Fantasy team query result: " . ($fantasy_team ? json_encode($fantasy_team) : 'No team found'));

    if (!$fantasy_team) {
        error_log("No fantasy team found. Checking for default league.");
        // Check if there's a default league
        $league_check = $conn->query("SELECT id FROM fantasy_leagues WHERE name = 'Default League' LIMIT 1");
        $league = $league_check->fetch(PDO::FETCH_ASSOC);
        error_log("Default league check result: " . ($league ? json_encode($league) : 'No league found'));
        
        if (!$league) {
            error_log("No default league found. Creating a new one.");
            // Create a default league
            $league_query = "INSERT INTO fantasy_leagues (name, sport_id, start_date, end_date) 
                            SELECT 'Default League', id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR) 
                            FROM sports 
                            WHERE name = 'Basketball' 
                            LIMIT 1";
            $conn->query($league_query);
            $league_id = $conn->lastInsertId();
            error_log("Default league created with ID: " . $league_id);
        } else {
            $league_id = $league['id'];
            error_log("Using existing default league with ID: " . $league_id);
        }

        // Create new fantasy team
        $create_team = "INSERT INTO fantasy_teams (league_id, user_id, team_name) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($create_team);
        $team_name = "Team " . $user_id;
        $stmt->execute([$league_id, $user_id, $team_name]);
        error_log("New fantasy team created with name: " . $team_name);
        
        // Get the newly created team
        $stmt = $conn->prepare($team_query);
        $stmt->execute([$user_id]);
        $fantasy_team = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("New fantasy team query result: " . json_encode($fantasy_team));
    }

    // Get player count
    $count_query = "SELECT COUNT(*) as player_count 
                    FROM fantasy_team_players 
                    WHERE team_id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->execute([$fantasy_team['id']]);
    $player_count = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Player count for team: " . $player_count['player_count']);

    // Get team's players
    $players_query = "SELECT 
        ftp.id as roster_id,
        ap.id as ap_id,
        CONCAT(u.first_name, ' ', u.last_name) as athlete_name,
        u.profile_image,
        s.name as sport_name,
        p.name as position_name,
        t.name as team_name,
        t.id as team_id,
        COALESCE(fp.points_scored, 0) as player_points
    FROM fantasy_team_players ftp
    JOIN athlete_profiles ap ON ftp.athlete_id = ap.id
    LEFT JOIN users u ON ap.user_id = u.id
    LEFT JOIN sports s ON ap.sport_id = s.id
    LEFT JOIN positions p ON ap.position_id = p.id
    LEFT JOIN team_players tp ON ap.id = tp.athlete_id
    LEFT JOIN teams t ON tp.team_id = t.id
    LEFT JOIN fantasy_points fp ON ap.id = fp.player_id 
        AND fp.week_number = WEEK(NOW())
        AND fp.season_year = YEAR(NOW())
        AND fp.month_number = MONTH(NOW())
    WHERE ftp.team_id = ?
    GROUP BY ftp.id, ap.id, u.first_name, u.last_name, u.profile_image, s.name, p.name, t.name, t.id, fp.points_scored
    ORDER BY player_points DESC";

    $stmt = $conn->prepare($players_query);
    $stmt->execute([$fantasy_team['id']]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Players query result: " . json_encode($players));

    // Fetch weekly team leaderboard
    $weekly_query = "
        SELECT 
            ft.id as team_id,
            ft.team_name,
            CONCAT(u.first_name, ' ', u.last_name) as manager_name,
            COALESCE(SUM(fp.points_scored), 0) as total_points
        FROM fantasy_teams ft
        JOIN users u ON ft.user_id = u.id
        LEFT JOIN fantasy_team_players ftp ON ft.id = ftp.team_id
        LEFT JOIN fantasy_points fp ON ftp.athlete_id = fp.player_id
            AND fp.week_number = :week_number 
            AND fp.season_year = :year
        GROUP BY ft.id, ft.team_name, u.first_name, u.last_name
        ORDER BY total_points DESC
        LIMIT 10";

    $stmt = $conn->prepare($weekly_query);
    $stmt->execute([
        ':week_number' => date('W'),  // Get current week number
        ':year' => date('Y')  // Get current year
    ]);
    $weekly_team_leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch monthly team leaderboard
    $monthly_query = "
        SELECT 
            ft.id as team_id,
            ft.team_name,
            CONCAT(u.first_name, ' ', u.last_name) as manager_name,
            COALESCE(SUM(fp.points_scored), 0) as total_points
        FROM fantasy_teams ft
        JOIN users u ON ft.user_id = u.id
        LEFT JOIN fantasy_team_players ftp ON ft.id = ftp.team_id
        LEFT JOIN fantasy_points fp ON ftp.athlete_id = fp.player_id
            AND fp.month_number = :month_number 
            AND fp.season_year = :year
        GROUP BY ft.id, ft.team_name, u.first_name, u.last_name
        ORDER BY total_points DESC
        LIMIT 10";

    $stmt = $conn->prepare($monthly_query);
    $stmt->execute([
        ':month_number' => date('n'),  // Get current month number (1-12)
        ':year' => date('Y')  // Get current year
    ]);
    $monthly_team_leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get weekly leaderboard for athletes
    $weekly_query = "SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as athlete_name,
        SUM(fp.points_scored) as total_points,
        fp.week_number
    FROM fantasy_team_players ftp
    JOIN users u ON ftp.athlete_id = u.id
    LEFT JOIN fantasy_points fp ON u.id = fp.player_id
    WHERE ftp.team_id = ? 
    AND fp.week_number = WEEK(CURRENT_DATE)
    AND fp.season_year = YEAR(CURRENT_DATE)
    GROUP BY u.id, fp.week_number
    ORDER BY total_points DESC
    LIMIT 10";
    
    $stmt = $conn->prepare($weekly_query);
    $stmt->execute([$fantasy_team['id']]);
    $weekly_leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Weekly leaders query result: " . json_encode($weekly_leaders));

    // Get monthly leaderboard for athletes
    $monthly_query = "SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as athlete_name,
        SUM(fp.points_scored) as total_points,
        fp.month_number
    FROM fantasy_team_players ftp
    JOIN users u ON ftp.athlete_id = u.id
    LEFT JOIN fantasy_points fp ON u.id = fp.player_id
    WHERE ftp.team_id = ?
    AND fp.month_number = MONTH(CURRENT_DATE)
    AND fp.season_year = YEAR(CURRENT_DATE)
    GROUP BY u.id, fp.month_number
    ORDER BY total_points DESC
    LIMIT 10";
    
    $stmt = $conn->prepare($monthly_query);
    $stmt->execute([$fantasy_team['id']]);
    $monthly_leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Monthly leaders query result: " . json_encode($monthly_leaders));

} catch (PDOException $e) {
    error_log("Error in leaderboards.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("<h2>Database Error:</h2><pre>" . $e->getMessage() . "\n\nWeekly Query: " . $weekly_query . 
        "\n\nMonthly Query: " . $monthly_query . 
        "\n\nParameters: week=" . date('W') . ", month=" . date('n') . ", year=" . date('Y') . "</pre>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fantasy League - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #2C1810;
            color: #D4AF37;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .fantasy-section {
            border: 4px solid #D4AF37;
            background-color: #3C2415;
            padding: 20px;
            margin-bottom: 30px;
        }

        h1, h2, h3 {
            color: #D4AF37;
            text-shadow: 2px 2px #000;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .roster-section {
            border: 2px solid #D4AF37;
            padding: 20px;
            margin-bottom: 30px;
            background-color: rgba(60, 36, 21, 0.8);
        }

        .empty-roster {
            text-align: center;
            padding: 40px;
        }

        .find-athletes-btn {
            display: inline-block;
            background-color: #D4AF37;
            color: #2C1810;
            padding: 10px 20px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            margin-top: 20px;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8em;
        }

        .find-athletes-btn:hover {
            background-color: #FFD700;
        }

        .athlete-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .athlete-card {
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            padding: 15px;
            position: relative;
        }

        .athlete-name {
            font-size: 0.9em;
            margin-bottom: 10px;
            color: #FFD700;
        }

        .athlete-sport {
            font-size: 0.7em;
            color: #D4AF37;
            margin-bottom: 8px;
        }

        .athlete-stats {
            font-size: 0.7em;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .leaderboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .leaderboard {
            border: 2px solid #D4AF37;
            padding: 15px;
            background-color: rgba(60, 36, 21, 0.8);
        }

        .leaderboard-title {
            font-size: 1em;
            margin-bottom: 15px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #D4AF37;
            font-size: 0.7em;
        }

        th {
            color: #FFD700;
            text-transform: uppercase;
        }

        .navbar {
            background-color: #2C1810;
            border-bottom: 4px solid #D4AF37;
            padding: 1rem;
        }

        .navbar-brand {
            color: #D4AF37 !important;
            font-family: 'Press Start 2P', cursive;
            font-size: 1.2rem;
        }

        .nav-link {
            color: #D4AF37 !important;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8rem;
            padding: 0.5rem 1rem !important;
            margin: 0 0.5rem;
        }

        .nav-link:hover {
            color: #FFD700 !important;
        }

        .navbar-toggler {
            border-color: #D4AF37;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23D4AF37' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .editable-title {
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 5px;
        }

        .editable-title:hover {
            background-color: rgba(212, 175, 55, 0.1);
        }

        .editable-title:hover::after {
            content: ' (click to edit)';
            font-size: 0.5em;
            color: #D4AF37;
            opacity: 0.7;
            vertical-align: middle;
        }

        .editable-title.editing {
            background-color: transparent;
        }

        .editable-title.editing:hover::after {
            content: '';
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container">
        <div class="fantasy-section">
            <div id="teamNameTitle" class="fantasy-team-name" style="cursor: pointer;">
                <?php echo htmlspecialchars($fantasy_team['team_name']); ?> (CLICK TO EDIT)
            </div>
            
            <div class="roster-section">
                <?php if (empty($players)): ?>
                    <div class="empty-roster">
                        <p>You haven't collected any athletes yet. Visit the Search page to start collecting athletes for your fantasy team!</p>
                        <a href="search_users.php" class="find-athletes-btn">Find Athletes</a>
                    </div>
                <?php else: ?>
                    <div class="athlete-grid">
                        <?php foreach ($players as $player): ?>
                            <div class="athlete-card">
                                <div class="athlete-name">
                                    <?php echo htmlspecialchars($player['athlete_name']); ?>
                                </div>
                                <div class="athlete-sport">
                                    <?php echo htmlspecialchars($player['sport_name']); ?>
                                    <?php if ($player['position_name']): ?>
                                        - <?php echo htmlspecialchars($player['position_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="athlete-stats">
                                    <div>
                                        <div>Points:</div>
                                        <div><?php echo number_format($player['player_points']); ?></div>
                                    </div>
                                    <?php if ($player['team_name']): ?>
                                        <div>
                                            <div>Team:</div>
                                            <div><?php echo htmlspecialchars($player['team_name']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="leaderboard-container">
                <!-- Weekly Team Leaderboard -->
                <div class="leaderboard weekly-team-leaderboard">
                    <h2>WEEKLY TEAM LEADERBOARD</h2>
                    <table>
                        <tr>
                            <th>RANK</th>
                            <th>TEAM</th>
                            <th>MANAGER</th>
                            <th>POINTS</th>
                        </tr>
                        <?php foreach ($weekly_team_leaders as $rank => $team): ?>
                        <tr>
                            <td><?php echo $rank + 1; ?></td>
                            <td class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td><?php echo htmlspecialchars($team['manager_name']); ?></td>
                            <td><?php echo number_format($team['total_points']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <!-- Monthly Team Leaderboard -->
                <div class="leaderboard monthly-team-leaderboard">
                    <h2>MONTHLY TEAM LEADERBOARD</h2>
                    <table>
                        <tr>
                            <th>RANK</th>
                            <th>TEAM</th>
                            <th>MANAGER</th>
                            <th>POINTS</th>
                        </tr>
                        <?php foreach ($monthly_team_leaders as $rank => $team): ?>
                        <tr>
                            <td><?php echo $rank + 1; ?></td>
                            <td class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td><?php echo htmlspecialchars($team['manager_name']); ?></td>
                            <td><?php echo number_format($team['total_points']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teamNameTitle = document.getElementById('teamNameTitle');
            let isEditing = false;
            let originalText = '';

            if (teamNameTitle) {
                teamNameTitle.addEventListener('click', function() {
                    if (!isEditing) {
                        startEditing();
                    }
                });
            }

            function startEditing() {
                isEditing = true;
                originalText = teamNameTitle.textContent.replace('(CLICK TO EDIT)', '').trim();
                
                const input = document.createElement('input');
                input.type = 'text';
                input.value = originalText;
                input.maxLength = 50;
                input.className = 'team-name-input';
                input.style.width = '100%';
                input.style.padding = '5px';
                input.style.fontSize = '16px';
                
                teamNameTitle.textContent = '';
                teamNameTitle.appendChild(input);
                input.focus();

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        const newName = input.value.trim();
                        if (newName && newName !== originalText) {
                            saveTeamName(newName);
                        } else {
                            cancelEditing();
                        }
                    } else if (e.key === 'Escape') {
                        cancelEditing();
                    }
                });

                input.addEventListener('blur', function() {
                    const newName = input.value.trim();
                    if (newName && newName !== originalText) {
                        saveTeamName(newName);
                    } else {
                        cancelEditing();
                    }
                });
            }

            function cancelEditing() {
                teamNameTitle.textContent = originalText + ' (CLICK TO EDIT)';
                isEditing = false;
            }

            function updateAllTeamNames(newName) {
                // Update team name in all leaderboards
                const weeklyTeamCells = document.querySelectorAll('.weekly-team-leaderboard .team-name');
                const monthlyTeamCells = document.querySelectorAll('.monthly-team-leaderboard .team-name');
                
                weeklyTeamCells.forEach(cell => {
                    if (cell.textContent.trim() === originalText) {
                        cell.textContent = newName;
                    }
                });
                
                monthlyTeamCells.forEach(cell => {
                    if (cell.textContent.trim() === originalText) {
                        cell.textContent = newName;
                    }
                });
            }

            function saveTeamName(newName) {
                fetch('ajax/update_team_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        team_name: newName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        teamNameTitle.textContent = newName + ' (CLICK TO EDIT)';
                        updateAllTeamNames(newName);
                        isEditing = false;
                    } else {
                        throw new Error(data.error || 'Failed to update team name');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    teamNameTitle.textContent = originalText + ' (CLICK TO EDIT)';
                    isEditing = false;
                    alert(error.message || 'Failed to update team name. Please try again.');
                });
            }
        });
    </script>
</body>
</html>
