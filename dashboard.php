 <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

// Get user profile information
$query = "SELECT 
            u.*,
            CONCAT(u.first_name, ' ', u.last_name) as full_name
          FROM users u 
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists before accessing array
if (!$user) {
    header("Location: login.php");
    exit();
}

// Now we can safely access $user array
$athlete_info = null;
if ($user['role'] === 'athlete') {
    $athlete_query = "SELECT 
        ap.height_feet,
        ap.height_inches,
        ap.weight,
        ap.position_id,
        ap.sport_id,
        t.name as team_name,
        s.name as sport_name,
        p.name as position_name,
        u.profile_image,
        sp.name as primary_sport,
        tm.team_id
    FROM athlete_profiles ap
    LEFT JOIN team_members tm ON ap.user_id = tm.athlete_id
    LEFT JOIN teams t ON tm.team_id = t.id
    LEFT JOIN sports s ON t.sport_id = s.id
    LEFT JOIN positions p ON ap.position_id = p.id
    LEFT JOIN users u ON ap.user_id = u.id
    LEFT JOIN sports sp ON ap.sport_id = sp.id
    WHERE ap.user_id = ?";

    $athlete_stmt = $conn->prepare($athlete_query);
    $athlete_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $athlete_stmt->execute();
    $athlete_info = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
} 

// Get upcoming games for the athlete's team
if ($athlete_info && $athlete_info['team_id']) {
    $games_query = "SELECT 
        m.*,
        t1.name as home_team,
        t2.name as away_team,
        s.name as sport_name
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    JOIN sports s ON m.sport_id = s.id
    WHERE (m.home_team_id = ? OR m.away_team_id = ?)
    AND m.match_date >= CURDATE()
    ORDER BY m.match_date ASC
    LIMIT 5";
    
    $games_stmt = $conn->prepare($games_query);
    $games_stmt->bindValue(1, $athlete_info['team_id'], PDO::PARAM_INT);
    $games_stmt->bindValue(2, $athlete_info['team_id'], PDO::PARAM_INT);
    $games_stmt->execute();
    $upcoming_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to convert inches to feet and inches display
function formatHeight($feet, $inches) {
    if (!$feet && !$inches) return 'Not set';
    return $feet . "'" . $inches . '"';
}

// Initialize variables with default values
$events_count = 0;
$followed_count = 0;
$team_members_count = 0;
$total_athletes = 0;
$total_wins = 0;
$upcoming_games_count = 0;
$upcoming_games = [];

try {
    if ($user['role'] === 'admin') {
        // Get total users count
        $users_query = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
        $users_result = $conn->query($users_query);
        $total_users = $users_result->fetch(PDO::FETCH_ASSOC)['count'];

        // Get total teams count
        $teams_query = "SELECT COUNT(*) as count FROM teams";
        $teams_result = $conn->query($teams_query);
        $total_teams = $teams_result->fetch(PDO::FETCH_ASSOC)['count'];

        // Get events count (only if events table exists)
        try {
            $events_query = "SELECT COUNT(*) as count FROM events e WHERE e.start_date >= CURDATE()";
            $events_result = $conn->query($events_query);
            $events_count = $events_result->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            // Table might not exist yet
            $events_count = 0;
        }
    } else if ($user['role'] === 'fan') {
        try {
            $events_query = "SELECT COUNT(DISTINCT te.id) as count 
                            FROM events e
                            INNER JOIN team_events te ON te.event_id = e.id
                            INNER JOIN teams t ON te.team_id = t.id
                            INNER JOIN team_members tm ON tm.team_id = t.id
                            INNER JOIN athlete_profiles ap ON ap.user_id = tm.athlete_id
                            INNER JOIN fan_followed_athletes ffa ON ffa.athlete_id = ap.user_id
                            WHERE ffa.fan_id = ? AND e.start_date >= CURDATE()";
            $events_stmt = $conn->prepare($events_query);
            $events_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
            $events_stmt->execute();
            $events_count = $events_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            // Tables might not exist yet
            $events_count = 0;
        }

        // Get followed athletes count
        try {
            $following_query = "SELECT COUNT(DISTINCT athlete_id) as count FROM fan_followed_athletes WHERE fan_id = ?";
            $following_stmt = $conn->prepare($following_query);
            $following_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
            $following_stmt->execute();
            $followed_count = $following_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            // Table might not exist yet
            $followed_count = 0;
        }
    } else if ($user['role'] === 'coach') {
        $team_query = "SELECT t.id as team_id, t.name as team_name
                       FROM teams t 
                       WHERE t.coach_id = ?";
        
        $team_stmt = $conn->prepare($team_query);
        $team_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $team_stmt->execute();
        $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($team) {
            // Get team statistics
            $stats_query = "SELECT 
                (SELECT COUNT(*) FROM team_members WHERE team_id = ?) as total_athletes,
                (SELECT COUNT(*) 
                 FROM matches 
                 WHERE (home_team_id = ? AND home_score > away_score) 
                    OR (away_team_id = ? AND away_score > home_score)) as total_wins,
                (SELECT COUNT(*) 
                 FROM matches 
                 WHERE (home_team_id = ? OR away_team_id = ?) 
                 AND match_date >= CURDATE()) as upcoming_games";
            
            $stats_stmt = $conn->prepare($stats_query);
            $stats_stmt->bindValue(1, $team['team_id'], PDO::PARAM_INT);
            $stats_stmt->bindValue(2, $team['team_id'], PDO::PARAM_INT);
            $stats_stmt->bindValue(3, $team['team_id'], PDO::PARAM_INT);
            $stats_stmt->bindValue(4, $team['team_id'], PDO::PARAM_INT);
            $stats_stmt->bindValue(5, $team['team_id'], PDO::PARAM_INT);
            $stats_stmt->execute();
            $team_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_athletes = $team_stats['total_athletes'] ?? 0;
            $total_wins = $team_stats['total_wins'] ?? 0;
            $upcoming_games_count = $team_stats['upcoming_games'] ?? 0;

            // Get upcoming games
            $games_query = "SELECT m.*,
                                  home.name as home_team_name,
                                  away.name as away_team_name,
                                  s.name as sport_name
                           FROM matches m
                           JOIN teams home ON m.home_team_id = home.id
                           JOIN teams away ON m.away_team_id = away.id
                           JOIN sports s ON m.sport_id = s.id
                           WHERE (m.home_team_id = ? OR m.away_team_id = ?)
                           AND m.match_date >= CURDATE()
                           ORDER BY m.match_date ASC
                           LIMIT 5";
            
            $games_stmt = $conn->prepare($games_query);
            $games_stmt->bindValue(1, $team['team_id'], PDO::PARAM_INT);
            $games_stmt->bindValue(2, $team['team_id'], PDO::PARAM_INT);
            $games_stmt->execute();
            $upcoming_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        try {
            $events_query = "SELECT COUNT(*) as count FROM team_events te 
                            JOIN events e ON te.event_id = e.id 
                            WHERE e.start_date >= CURDATE()";
            $events_result = $conn->query($events_query);
            $events_count = $events_result->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            // Tables might not exist yet
            $events_count = 0;
        }
    }

    // Get team members count if applicable
    if (in_array($user['role'], ['coach', 'athlete']) && $athlete_info && isset($athlete_info['team_id'])) {
        try {
            $team_query = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ?";
            $team_stmt = $conn->prepare($team_query);
            $team_stmt->bindValue(1, $athlete_info['team_id'], PDO::PARAM_INT);
            $team_stmt->execute();
            $team_members_count = $team_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            // Table might not exist yet
            $team_members_count = 0;
        }
    }
} catch (PDOException $e) {
    // Log the error but don't expose it to users
    error_log("Dashboard error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', cursive;
            color: #D4AF37;
        }

        .main-content {
            padding: 2rem;
        }

        .dashboard-section {
            background-color: #241409;
            border: 4px solid #D4AF37;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        .section-title {
            font-size: 1.2rem;
            color: #D4AF37;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            font-size: 1.4rem;
        }

        .stat-card {
            background-color: #3C2A20;
            border: 2px solid #D4AF37;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            background-color: #4A3828;
        }

        .stat-value {
            font-size: 2rem;
            color: #D4AF37;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #D4AF37;
            text-transform: uppercase;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #D4AF37;
            font-size: 0.8rem;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-primary {
            background-color: #D4AF37;
            border-color: #D4AF37;
            color: #241409;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            text-transform: uppercase;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            border-color: #FFD700;
            transform: translateY(-2px);
        }

        .game-item {
            background-color: #3C2A20;
            border: 2px solid #D4AF37;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }

        .game-item:hover {
            transform: translateY(-2px);
            background-color: #4A3828;
        }

        .game-date {
            font-size: 0.7rem;
            color: #D4AF37;
            margin-bottom: 0.5rem;
        }

        .game-teams {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .game-venue {
            font-size: 0.7rem;
            color: #D4AF37;
        }

        .athlete-list {
            list-style: none;
            padding: 0;
        }

        .athlete-item {
            background-color: #3C2A20;
            border: 2px solid #D4AF37;
            border-radius: 4px;
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s;
        }

        .athlete-item:hover {
            transform: translateY(-2px);
            background-color: #4A3828;
        }

        .athlete-name {
            font-size: 0.8rem;
            margin: 0;
        }

        .athlete-info {
            font-size: 0.7rem;
            color: #D4AF37;
            margin: 0;
        }
    </style>
</head>

<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="main-content">
        <div class="content-wrapper">
            <?php if ($user['role'] === 'coach'): ?>
                <!-- Coach Dashboard -->
                <div class="quick-actions">
                    <a href="admin/manage_roster.php" class="quick-action-btn">
                        <i class="bi bi-people-fill"></i>
                        <div>Manage Roster</div>
                    </a>
                    <a href="team_stats.php" class="quick-action-btn">
                        <i class="bi bi-graph-up"></i>
                        <div>Team Stats</div>
                    </a>
                    <a href="team_schedule.php" class="quick-action-btn">
                        <i class="bi bi-calendar-event"></i>
                        <div>Schedule</div>
                    </a>
                    <a href="messages.php" class="quick-action-btn">
                        <i class="bi bi-chat-dots"></i>
                        <div>Messages</div>
                    </a>
                </div>

                <div class="row g-4">
                    <!-- Team Overview Section -->
                    <div class="col-md-6">
                        <div class="dashboard-section">
                            <h2 class="section-title">
                                <i class="bi bi-people"></i> Team Overview
                            </h2>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <i class="bi bi-person-check"></i>
                                    <div class="stat-value"><?php echo $total_athletes ?? 0; ?></div>
                                    <div class="stat-label">Athletes</div>
                                </div>
                                <div class="stat-card">
                                    <i class="bi bi-trophy"></i>
                                    <div class="stat-value"><?php echo $total_wins ?? 0; ?></div>
                                    <div class="stat-label">Wins</div>
                                </div>
                                <div class="stat-card">
                                    <i class="bi bi-calendar-check"></i>
                                    <div class="stat-value"><?php echo $upcoming_games_count ?? 0; ?></div>
                                    <div class="stat-label">Upcoming Games</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Section -->
                    <div class="col-md-6">
                        <div class="dashboard-section">
                            <h2 class="section-title">
                                <i class="bi bi-activity"></i> Recent Activity
                            </h2>
                            <?php if (!empty($recent_activities)): ?>
                                <div class="game-list">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="game-item">
                                            <div class="game-date">
                                                <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                            </div>
                                            <div class="game-teams">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No recent activity to display.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Games Section -->
                    <div class="col-md-6">
                        <div class="dashboard-section">
                            <h2 class="section-title">
                                <i class="bi bi-calendar-event"></i> Upcoming Games
                            </h2>
                            <?php if (!empty($upcoming_games)): ?>
                                <div class="game-list">
                                    <?php foreach ($upcoming_games as $game): ?>
                                        <div class="game-item">
                                            <div class="game-date">
                                                <?php echo date('M d, Y', strtotime($game['match_date'])); ?>
                                            </div>
                                            <div class="game-teams">
                                                <?php echo htmlspecialchars($game['home_team_name']); ?> vs 
                                                <?php echo htmlspecialchars($game['away_team_name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No upcoming games scheduled.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Team Messages Section -->
                    <div class="col-md-6">
                        <div class="dashboard-section">
                            <h2 class="section-title">
                                <i class="bi bi-chat-dots"></i> Team Messages
                            </h2>
                            <?php if (!empty($team_messages)): ?>
                                <div class="game-list">
                                    <?php foreach ($team_messages as $message): ?>
                                        <div class="game-item">
                                            <div class="game-date">
                                                <?php echo date('M d, Y', strtotime($message['created_at'])); ?>
                                            </div>
                                            <div class="game-teams">
                                                <?php echo htmlspecialchars($message['message']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No recent messages.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($user['role'] === 'admin'): ?>
                <!-- Admin Dashboard -->
                <div class="admin-dashboard">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="bi bi-people"></i>
                            <h3>Total Users</h3>
                            <p><?php echo $total_users; ?></p>
                        </div>
                        <div class="stat-card">
                            <i class="bi bi-trophy"></i>
                            <h3>Total Teams</h3>
                            <p><?php echo $total_teams; ?></p>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <a href="admin/manage_teams.php" class="action-btn">Manage Teams</a>
                        <a href="admin/manage_users.php" class="action-btn">Manage Users</a>
                        <a href="admin/manage_matches.php" class="action-btn">Manage Matches</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user['role'] === 'athlete'): ?>
                <!-- Athlete Dashboard -->
                <div class="athlete-dashboard">
                    <div class="row">
                        <!-- Profile Overview -->
                        <div class="col-md-4">
                            <div class="dashboard-section">
                                <h2 class="section-title">Profile Overview</h2>
                                <div class="profile-card">
                                    <div class="profile-header">
                                        <div class="profile-image">
                                            <img src="<?php echo $user['profile_image'] ? 'uploads/profile_images/' . $user['profile_image'] : 'uploads/profile_images/default-avatar.png'; ?>" alt="Profile Image">
                                        </div>
                                        <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                    </div>
                                    <div class="profile-info">
                                        <p><strong>Sport:</strong> <?php echo htmlspecialchars($athlete_info['primary_sport'] ?? 'Not set'); ?></p>
                                        <p><strong>Team:</strong> <?php echo htmlspecialchars($athlete_info['team_name'] ?? 'Not assigned'); ?></p>
                                        <p><strong>Position:</strong> <?php echo htmlspecialchars($athlete_info['position_name'] ?? 'Not set'); ?></p>
                                        <p><strong>Height:</strong> <?php echo formatHeight($athlete_info['height_feet'] ?? null, $athlete_info['height_inches'] ?? null); ?></p>
                                        <p><strong>Weight:</strong> <?php echo $athlete_info['weight'] ? $athlete_info['weight'] . ' lbs' : 'Not set'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Games -->
                        <div class="col-md-8">
                            <div class="dashboard-section">
                                <h2 class="section-title">Upcoming Games</h2>
                                <?php if (!empty($upcoming_games)): ?>
                                    <div class="games-list">
                                        <?php foreach ($upcoming_games as $game): ?>
                                            <div class="game-card">
                                                <div class="game-date">
                                                    <?php echo date('M d, Y', strtotime($game['match_date'])); ?>
                                                    <span class="game-time"><?php echo date('h:i A', strtotime($game['match_date'])); ?></span>
                                                </div>
                                                <div class="teams">
                                                    <span class="team home"><?php echo htmlspecialchars($game['home_team'] ?? 'TBD'); ?></span>
                                                    <span class="vs">vs</span>
                                                    <span class="team away"><?php echo htmlspecialchars($game['away_team'] ?? 'TBD'); ?></span>
                                                </div>
                                                <div class="game-details">
                                                    <span class="sport"><i class="bi bi-trophy"></i> <?php echo htmlspecialchars($game['sport_name']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-games">No upcoming games scheduled.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="dashboard-section">
                                <h2 class="section-title">Quick Actions</h2>
                                <div class="quick-actions">
                                    <a href="messages.php" class="action-btn">
                                        <i class="bi bi-chat-dots"></i>
                                        Team Messages
                                    </a>
                                    <a href="player_stats.php" class="action-btn">
                                        <i class="bi bi-graph-up"></i>
                                        View Stats
                                    </a>
                                    <a href="edit_profile.php" class="action-btn">
                                        <i class="bi bi-person"></i>
                                        Edit Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user['role'] === 'fan'): ?>
                <!-- Fan Dashboard -->
                <div class="fan-dashboard">
                    <div class="row">
                        <!-- Followed Athletes Section -->
                        <div class="col-md-6">
                            <div class="dashboard-section">
                                <h2 class="section-title">
                                    <i class="bi bi-star"></i> Followed Athletes
                                </h2>
                                <div class="followed-athletes">
                                    <?php
                                    $followed_query = "SELECT 
                                        u.id,
                                        u.first_name,
                                        u.last_name,
                                        u.profile_image,
                                        t.name as team_name,
                                        s.name as sport_name
                                    FROM fan_followed_athletes f
                                    JOIN users u ON f.athlete_id = u.id
                                    LEFT JOIN team_members tm ON u.id = tm.athlete_id
                                    LEFT JOIN teams t ON tm.team_id = t.id
                                    LEFT JOIN sports s ON t.sport_id = s.id
                                    WHERE f.fan_id = ?
                                    ORDER BY u.last_name, u.first_name";
                                    
                                    $followed_stmt = $conn->prepare($followed_query);
                                    $followed_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
                                    $followed_stmt->execute();
                                    $followed_athletes = $followed_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>

                                    <?php if (!empty($followed_athletes)): ?>
                                        <div class="athlete-list">
                                            <?php foreach ($followed_athletes as $athlete): ?>
                                                <div class="athlete-card">
                                                    <img src="<?php echo $athlete['profile_image'] ? 'uploads/profile_images/' . $athlete['profile_image'] : 'uploads/profile_images/default-avatar.png'; ?>" 
                                                         alt="<?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>" 
                                                         class="athlete-image">
                                                    <div class="athlete-info">
                                                        <h4><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h4>
                                                        <p><?php echo htmlspecialchars($athlete['team_name'] ?? 'No Team'); ?></p>
                                                        <p><?php echo htmlspecialchars($athlete['sport_name'] ?? 'No Sport'); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-athletes">You haven't followed any athletes yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Games Section -->
                        <div class="col-md-6">
                            <div class="dashboard-section">
                                <h2 class="section-title">
                                    <i class="bi bi-calendar"></i> Upcoming Games
                                </h2>
                                <?php
                                // Get upcoming games for followed athletes' teams
                                $games_query = "SELECT DISTINCT 
                                    m.*,
                                    t1.name as home_team,
                                    t2.name as away_team,
                                    s.name as sport_name
                                FROM matches m
                                JOIN teams t1 ON m.home_team_id = t1.id
                                JOIN teams t2 ON m.away_team_id = t2.id
                                JOIN sports s ON m.sport_id = s.id
                                JOIN team_members tm ON (tm.team_id = t1.id OR tm.team_id = t2.id)
                                JOIN fan_followed_athletes f ON tm.athlete_id = f.athlete_id
                                WHERE f.fan_id = ? AND m.match_date >= CURDATE()
                                ORDER BY m.match_date ASC
                                LIMIT 5";
                                
                                $games_stmt = $conn->prepare($games_query);
                                $games_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
                                $games_stmt->execute();
                                $upcoming_games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php if (!empty($upcoming_games)): ?>
                                    <div class="games-list">
                                        <?php foreach ($upcoming_games as $game): ?>
                                            <div class="game-card">
                                                <div class="game-date">
                                                    <?php echo date('M d, Y', strtotime($game['match_date'])); ?>
                                                    <span class="game-time"><?php echo date('h:i A', strtotime($game['match_date'])); ?></span>
                                                </div>
                                                <div class="teams">
                                                    <span class="team home"><?php echo htmlspecialchars($game['home_team'] ?? 'TBD'); ?></span>
                                                    <span class="vs">vs</span>
                                                    <span class="team away"><?php echo htmlspecialchars($game['away_team'] ?? 'TBD'); ?></span>
                                                </div>
                                                <div class="game-details">
                                                    <span class="sport"><i class="bi bi-trophy"></i> <?php echo htmlspecialchars($game['sport_name']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-games">No upcoming games for followed athletes.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="dashboard-section">
                                <h2 class="section-title">Quick Actions</h2>
                                <div class="quick-actions">
                                    <a href="search_users.php" class="action-btn">
                                        <i class="bi bi-search"></i>
                                        Find Athletes
                                    </a>
                                    <a href="messages.php" class="action-btn">
                                        <i class="bi bi-chat-dots"></i>
                                        Messages
                                    </a>
                                    <a href="profile.php" class="action-btn">
                                        <i class="bi bi-person"></i>
                                        Edit Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
