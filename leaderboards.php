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

// First, check if user has a fantasy team
$team_query = "SELECT ft.* FROM fantasy_teams ft 
               WHERE ft.user_id = ? 
               LIMIT 1";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Create a default league if it doesn't exist
    $league_query = "INSERT INTO fantasy_leagues (name, sport_id, start_date, end_date) 
                    SELECT 'Default League', id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR) 
                    FROM sports 
                    WHERE name = 'Football' 
                    LIMIT 1";
    $conn->query($league_query);
    $league_id = $conn->insert_id;

    // Create new fantasy team
    $create_team = "INSERT INTO fantasy_teams (league_id, user_id, team_name) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($create_team);
    $team_name = "Team " . $user_id;
    $stmt->bind_param("iis", $league_id, $user_id, $team_name);
    $stmt->execute();
    
    // Get the newly created team
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$fantasy_team = $result->fetch_assoc();

// Get player count
$count_query = "SELECT COUNT(*) as player_count 
                FROM fantasy_team_players 
                WHERE team_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $fantasy_team['id']);
$stmt->execute();
$count_result = $stmt->get_result();
$player_count = $count_result->fetch_assoc();

// Get team's players
$players_query = "SELECT 
    ftp.id as roster_id,
    ap.id as ap_id,
    u.username,
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
    AND fp.month_number = MONTH(NOW())
    AND fp.season_year = YEAR(NOW())
WHERE ftp.team_id = ?
GROUP BY ftp.id, ap.id, u.username, u.profile_image, s.name, p.name, t.name, t.id, fp.points_scored
ORDER BY player_points DESC";

$stmt = $conn->prepare($players_query);
$stmt->bind_param("i", $fantasy_team['id']);
$stmt->execute();
$players_result = $stmt->get_result();
$players = $players_result->fetch_all(MYSQLI_ASSOC);

// Get weekly leaderboard for athletes
$weekly_query = "SELECT 
    ap.id as ap_id, 
    ap.first_name, 
    ap.last_name,
    COALESCE(SUM(fp.points_scored), 0) as fantasy_points
FROM athlete_profiles ap
JOIN fantasy_team_players ftp ON ap.id = ftp.athlete_id
LEFT JOIN fantasy_points fp ON ap.id = fp.player_id 
    AND fp.week_number = WEEK(NOW())
    AND fp.season_year = YEAR(NOW())
WHERE ftp.team_id = ?
GROUP BY ap.id, ap.first_name, ap.last_name
ORDER BY fantasy_points DESC
LIMIT 10";

$stmt = $conn->prepare($weekly_query);
$stmt->bind_param('i', $fantasy_team['id']);
$stmt->execute();
$weekly_leaders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly leaderboard for athletes
$monthly_query = "SELECT 
    ap.id as ap_id, 
    ap.first_name, 
    ap.last_name,
    COALESCE(SUM(fp.points_scored), 0) as fantasy_points
FROM athlete_profiles ap
JOIN fantasy_team_players ftp ON ap.id = ftp.athlete_id
LEFT JOIN fantasy_points fp ON ap.id = fp.player_id 
    AND fp.month_number = MONTH(NOW())
    AND fp.season_year = YEAR(NOW())
WHERE ftp.team_id = ?
GROUP BY ap.id, ap.first_name, ap.last_name
ORDER BY fantasy_points DESC
LIMIT 10";

$stmt = $conn->prepare($monthly_query);
$stmt->bind_param('i', $fantasy_team['id']);
$stmt->execute();
$monthly_leaders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get weekly leaderboard for teams
$weekly_teams_query = "SELECT 
    ft.id as team_id,
    ft.team_name,
    COALESCE(SUM(fp.points_scored), 0) as team_points
FROM fantasy_teams ft
LEFT JOIN fantasy_team_players ftp ON ft.id = ftp.team_id
LEFT JOIN fantasy_points fp ON ftp.athlete_id = fp.player_id 
    AND fp.week_number = WEEK(NOW())
    AND fp.season_year = YEAR(NOW())
GROUP BY ft.id, ft.team_name
ORDER BY team_points DESC
LIMIT 10";

$stmt = $conn->prepare($weekly_teams_query);
$stmt->execute();
$weekly_team_leaders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly leaderboard for teams
$monthly_teams_query = "SELECT 
    ft.id as team_id,
    ft.team_name,
    COALESCE(SUM(fp.points_scored), 0) as team_points
FROM fantasy_teams ft
LEFT JOIN fantasy_team_players ftp ON ft.id = ftp.team_id
LEFT JOIN fantasy_points fp ON ftp.athlete_id = fp.player_id 
    AND fp.month_number = MONTH(NOW())
    AND fp.season_year = YEAR(NOW())
GROUP BY ft.id, ft.team_name
ORDER BY team_points DESC
LIMIT 10";

$stmt = $conn->prepare($monthly_teams_query);
$stmt->execute();
$monthly_team_leaders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="fantasy-header editable-title" id="teamNameTitle"><?php echo htmlspecialchars($fantasy_team['team_name'] ?? 'My Fantasy Team'); ?></h1>
            
            <div class="roster-section">
                <?php if (empty($players)): ?>
                    <div class="empty-roster">
                        <p>You haven't collected any athletes yet. Visit the Search page to start collecting athletes for your fantasy team!</p>
                        <a href="browse_athletes.php" class="find-athletes-btn">Find Athletes</a>
                    </div>
                <?php else: ?>
                    <div class="athlete-grid">
                        <?php foreach ($players as $player): ?>
                            <div class="athlete-card">
                                <div class="athlete-name">
                                    <?php echo htmlspecialchars($player['username']); ?>
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

            <div class="leaderboard-grid">
                <div class="leaderboard">
                    <h2 class="leaderboard-title">WEEKLY ATHLETE LEADERBOARD</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>RANK</th>
                                <th>PLAYER</th>
                                <th>POINTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($weekly_leaders as $leader): 
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']); ?></td>
                                    <td><?php echo number_format($leader['fantasy_points']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="leaderboard">
                    <h2 class="leaderboard-title">MONTHLY ATHLETE LEADERBOARD</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>RANK</th>
                                <th>PLAYER</th>
                                <th>POINTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($monthly_leaders as $leader): 
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']); ?></td>
                                    <td><?php echo number_format($leader['fantasy_points']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="leaderboard-grid">
                <div class="leaderboard">
                    <h2 class="leaderboard-title">WEEKLY TEAM LEADERBOARD</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Team</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($weekly_team_leaders as $team): 
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                    <td><?php echo number_format($team['team_points']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="leaderboard">
                    <h2 class="leaderboard-title">MONTHLY TEAM LEADERBOARD</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Team</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($monthly_team_leaders as $team): 
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                    <td><?php echo number_format($team['team_points']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
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

            teamNameTitle.addEventListener('click', function() {
                if (!isEditing) {
                    startEditing();
                }
            });

            function startEditing() {
                isEditing = true;
                originalText = teamNameTitle.textContent.trim();
                teamNameTitle.classList.add('editing');
                
                const input = document.createElement('input');
                input.type = 'text';
                input.value = originalText;
                input.maxLength = 50;
                input.style.background = '#2C1810';
                input.style.color = '#D4AF37';
                input.style.border = '2px solid #D4AF37';
                input.style.padding = '5px';
                input.style.width = '100%';
                input.style.fontFamily = "'Press Start 2P', cursive";
                input.style.fontSize = '1em';
                
                teamNameTitle.textContent = '';
                teamNameTitle.appendChild(input);
                input.focus();

                input.addEventListener('blur', finishEditing);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        input.blur();
                    } else if (e.key === 'Escape') {
                        cancelEditing();
                    }
                });
            }

            function finishEditing() {
                const input = teamNameTitle.querySelector('input');
                const newName = input.value.trim();
                
                if (newName && newName !== originalText) {
                    updateTeamName(newName);
                } else {
                    teamNameTitle.textContent = originalText;
                }
                
                teamNameTitle.classList.remove('editing');
                isEditing = false;
            }

            function cancelEditing() {
                teamNameTitle.textContent = originalText;
                teamNameTitle.classList.remove('editing');
                isEditing = false;
            }

            function updateTeamName(newName) {
                fetch('ajax/update_team_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ team_name: newName })
                })
                .then(response => response.json().then(data => ({ status: response.ok, data })))
                .then(({ status, data }) => {
                    if (status && data.success) {
                        teamNameTitle.textContent = data.team_name;
                    } else {
                        throw new Error(data.error || 'Failed to update team name');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    teamNameTitle.textContent = originalText;
                    alert('Failed to update team name: ' + error.message);
                });
            }
        });
    </script>
</body>
</html>
