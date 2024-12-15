<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../includes/auth.php');
require_once('../config/db.php');
require_once('../includes/functions.php');

// Only allow admin and coach access
if (!isAdmin() && !isCoach()) {
    error_log("Access denied - User role: " . $_SESSION['role'] . ", User ID: " . $_SESSION['user_id']);
    header("Location: /Spywalker/dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

// Ensure user is admin or coach
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'coach')) {
    error_log("Session check failed - Role: " . ($_SESSION['role'] ?? 'not set'));
    header('Location: ../login.php');
    exit();
}

// For coaches, only allow access to their teams
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
error_log("Managing roster - User ID: $user_id, Role: $role");

// Get list of teams
if ($role === 'admin') {
    $teams_query = "SELECT t.*, s.name as sport_name, 
                          COUNT(tm.id) as player_count 
                   FROM teams t 
                   LEFT JOIN sports s ON t.sport_id = s.id 
                   LEFT JOIN team_members tm ON t.id = tm.team_id 
                   GROUP BY t.id";
    $stmt = $conn->prepare($teams_query);
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $teams_query = "SELECT t.*, s.name as sport_name, 
                          COUNT(tm.id) as player_count 
                   FROM teams t 
                   LEFT JOIN sports s ON t.sport_id = s.id 
                   LEFT JOIN team_members tm ON t.id = tm.team_id 
                   WHERE t.coach_id = ? 
                   GROUP BY t.id";
    $stmt = $conn->prepare($teams_query);
    $stmt->execute([$_SESSION['user_id']]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if team_id is provided
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : ($teams[0]['id'] ?? null);

if (!$team_id) {
    die("No teams available");
}

// Verify user has access to this team
if ($role === 'coach') {
    $access_check = $conn->prepare("SELECT id FROM teams WHERE id = ? AND coach_id = ?");
    $access_check->execute([$team_id, $user_id]);
    if ($access_check->rowCount() === 0) {
        error_log("Access denied - Coach ID: $user_id, Team ID: $team_id");
        header('Location: ../dashboard.php');
        exit();
    }
}

// Get team details
$team_query = "SELECT t.*, s.name as sport_name 
               FROM teams t 
               JOIN sports s ON t.sport_id = s.id 
               WHERE t.id = ?";
$stmt = $conn->prepare($team_query);
$stmt->execute([$team_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current roster with additional details
$roster_query = "SELECT tm.id, tm.team_id, tm.athlete_id, 
                        u.first_name, u.last_name, u.email, u.role,
                        ap.height_feet, ap.height_inches, ap.weight,
                        ap.jersey_number, ap.years_of_experience,
                        ap.school_year
                 FROM team_members tm
                 JOIN users u ON tm.athlete_id = u.id
                 LEFT JOIN athlete_profiles ap ON u.id = ap.user_id
                 WHERE tm.team_id = ?";
$stmt = $conn->prepare($roster_query);
$stmt->execute([$team_id]);
$roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available positions for this sport
$positions_query = "SELECT * FROM positions WHERE sport_id = ?";
$stmt = $conn->prepare($positions_query);
$stmt->execute([$team['sport_id']]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available athletes
$athletes_query = "SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                         ap.height_feet, ap.height_inches, ap.weight,
                         ap.jersey_number, ap.years_of_experience,
                         ap.school_year
                   FROM users u 
                   LEFT JOIN athlete_profiles ap ON u.id = ap.user_id
                   LEFT JOIN team_members tm ON u.id = tm.athlete_id 
                   WHERE u.role = 'athlete' 
                   AND tm.id IS NULL
                   ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($athletes_query);
$stmt->execute();
$available_athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_player'])) {
        $user_id = $_POST['user_id'];
        
        // Insert the player into team_members
        $stmt = $conn->prepare("INSERT INTO team_members (team_id, athlete_id) VALUES (?, ?)");
        $stmt->execute([$team_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Player added successfully.";
        } else {
            $_SESSION['error'] = "Error adding player.";
            error_log("Failed to add player. user_id: $user_id, team_id: $team_id");
        }
        header("Location: manage_roster.php?team_id=" . $team_id);
        exit();
    }
    
    if (isset($_POST['remove_player'])) {
        $member_id = $_POST['member_id'];
        error_log("Removing player with member_id: " . $member_id);
        
        $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ? AND team_id = ?");
        $stmt->execute([$member_id, $team_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Player removed successfully.";
        } else {
            $_SESSION['error'] = "Error removing player. Please try again.";
            error_log("Failed to remove player. member_id: $member_id, team_id: $team_id");
        }
        header("Location: manage_roster.php?team_id=" . $team_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roster - <?php echo htmlspecialchars($team['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/manage-roster.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <?php include('../components/navbar.php'); ?>

    <div class="container mt-4">
        <h1 class="vintage-title">MANAGE ROSTER</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-vintage">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-vintage">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Team Selector -->
        <select class="team-select" onchange="window.location.href='?team_id=' + this.value">
            <?php foreach ($teams as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $team_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['name'] . ' (' . $t['sport_name'] . ' - ' . $t['player_count'] . ' players)'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="row">
            <!-- Current Roster Section -->
            <div class="col-md-6">
                <div class="roster-section">
                    <h2 class="section-title">CURRENT ROSTER</h2>
                    <?php foreach ($roster as $player): ?>
                        <div class="athlete-card">
                            <div class="avatar-circle">
                                <span><?php echo strtoupper(substr($player['first_name'], 0, 1)); ?></span>
                            </div>
                            <div class="player-name">
                                <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
                            </div>
                            <div class="stats-display">
                                <div class="stat-item">
                                    <div>HEIGHT</div>
                                    <div class="stat-value"><?php echo $player['height_feet'] . "'" . $player['height_inches'] . '"'; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div>WEIGHT</div>
                                    <div class="stat-value"><?php echo $player['weight']; ?> lbs</div>
                                </div>
                                <div class="stat-item">
                                    <div>NUMBER</div>
                                    <div class="stat-value">#<?php echo $player['jersey_number']; ?></div>
                                </div>
                            </div>
                            <div class="player-info">
                                <div>Experience: <?php echo $player['years_of_experience']; ?> years</div>
                                <div>Year: <?php echo $player['school_year']; ?></div>
                                <div>Email: <?php echo htmlspecialchars($player['email']); ?></div>
                            </div>
                            <form method="POST" class="text-center">
                                <input type="hidden" name="member_id" value="<?php echo $player['id']; ?>">
                                <button type="submit" name="remove_player" value="1" class="btn btn-remove">REMOVE FROM TEAM</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Available Athletes Section -->
            <div class="col-md-6">
                <div class="roster-section">
                    <h2 class="section-title">AVAILABLE ATHLETES</h2>
                    <?php if (empty($available_athletes)): ?>
                        <p class="text-center">NO AVAILABLE ATHLETES.</p>
                    <?php else: ?>
                        <?php foreach ($available_athletes as $athlete): ?>
                            <div class="athlete-card">
                                <div class="avatar-circle">
                                    <span><?php echo strtoupper(substr($athlete['first_name'], 0, 1)); ?></span>
                                </div>
                                <div class="player-name">
                                    <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                                </div>
                                <div class="stats-display">
                                    <div class="stat-item">
                                        <div>HEIGHT</div>
                                        <div class="stat-value"><?php echo $athlete['height_feet'] . "'" . $athlete['height_inches'] . '"'; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div>WEIGHT</div>
                                        <div class="stat-value"><?php echo $athlete['weight']; ?> lbs</div>
                                    </div>
                                    <?php if ($athlete['jersey_number']): ?>
                                    <div class="stat-item">
                                        <div>NUMBER</div>
                                        <div class="stat-value">#<?php echo $athlete['jersey_number']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="player-info">
                                    <div>Experience: <?php echo $athlete['years_of_experience']; ?> years</div>
                                    <div>Year: <?php echo $athlete['school_year']; ?></div>
                                    <div>Email: <?php echo htmlspecialchars($athlete['email']); ?></div>
                                </div>
                                <form method="POST" class="text-center">
                                    <input type="hidden" name="user_id" value="<?php echo $athlete['id']; ?>">
                                    <button type="submit" name="add_player" class="btn btn-add">ADD TO TEAM</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
