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
    $teams = $conn->query($teams_query)->fetch_all(MYSQLI_ASSOC);
} else {
    $teams_query = "SELECT t.*, s.name as sport_name, 
                          COUNT(tm.id) as player_count 
                   FROM teams t 
                   LEFT JOIN sports s ON t.sport_id = s.id 
                   LEFT JOIN team_members tm ON t.id = tm.team_id 
                   WHERE t.coach_id = ? 
                   GROUP BY t.id";
    $stmt = $conn->prepare($teams_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Check if team_id is provided
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : ($teams[0]['id'] ?? null);

if (!$team_id) {
    die("No teams available");
}

// Verify user has access to this team
if ($role === 'coach') {
    $access_check = $conn->prepare("SELECT id FROM teams WHERE id = ? AND coach_id = ?");
    $access_check->bind_param("ii", $team_id, $user_id);
    $access_check->execute();
    if ($access_check->get_result()->num_rows === 0) {
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
$stmt->bind_param("i", $team_id);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();

// Get current roster with additional details
$roster_query = "SELECT tm.id, tm.team_id, tm.user_id, tm.jersey_number, 
                        u.username, u.email, u.role,
                        ap.height, ap.weight,
                        p.name as position_name,
                        tm.position_id
                 FROM team_members tm
                 JOIN users u ON tm.user_id = u.id
                 LEFT JOIN athlete_profiles ap ON u.id = ap.user_id
                 LEFT JOIN positions p ON tm.position_id = p.id
                 WHERE tm.team_id = ?
                 ORDER BY tm.jersey_number";
$stmt = $conn->prepare($roster_query);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$roster = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available positions for this sport
$positions_query = "SELECT * FROM positions WHERE sport_id = ?";
$stmt = $conn->prepare($positions_query);
$stmt->bind_param("i", $team['sport_id']);
$stmt->execute();
$positions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available athletes
$athletes_query = "SELECT u.*, ap.height, ap.weight,
                         p.name as preferred_position
                  FROM users u 
                  LEFT JOIN athlete_profiles ap ON u.id = ap.user_id
                  LEFT JOIN positions p ON ap.position_id = p.id
                  LEFT JOIN team_members tm ON u.id = tm.user_id 
                  WHERE u.role = 'athlete' 
                  AND tm.id IS NULL
                  ORDER BY u.username";
$stmt = $conn->prepare($athletes_query);
$stmt->execute();
$available_athletes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_player'])) {
        $user_id = $_POST['user_id'];
        $jersey_number = $_POST['jersey_number'];
        $position_id = $_POST['position_id'];
        
        // Check if jersey number is already taken
        $check_jersey = $conn->prepare("SELECT id FROM team_members WHERE team_id = ? AND jersey_number = ?");
        $check_jersey->bind_param("ii", $team_id, $jersey_number);
        $check_jersey->execute();
        if ($check_jersey->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Jersey number {$jersey_number} is already taken.";
        } else {
            $stmt = $conn->prepare("INSERT INTO team_members (team_id, user_id, jersey_number, position_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $team_id, $user_id, $jersey_number, $position_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Player added successfully.";
            } else {
                $_SESSION['error'] = "Error adding player: " . $conn->error;
            }
        }
        header("Location: manage_roster.php?team_id=" . $team_id);
        exit();
    }
    
    if (isset($_POST['remove_player'])) {
        $member_id = $_POST['remove_player'];
        
        $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ? AND team_id = ?");
        $stmt->bind_param("ii", $member_id, $team_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Player removed successfully.";
        } else {
            $_SESSION['error'] = "Error removing player: " . $conn->error;
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
                                <span><?php echo strtoupper(substr($player['username'], 0, 1)); ?></span>
                            </div>
                            <div class="player-name">
                                <?php echo htmlspecialchars($player['username']); ?>
                            </div>
                            <div class="stats-display">
                                <div class="stat-item">
                                    <div>HEIGHT</div>
                                    <div class="stat-value"><?php echo $player['height']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div>WEIGHT</div>
                                    <div class="stat-value"><?php echo $player['weight']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div>NUMBER</div>
                                    <div class="stat-value">#<?php echo $player['jersey_number']; ?></div>
                                </div>
                            </div>
                            <div class="player-info">
                                <div>Position: <?php echo htmlspecialchars($player['position_name']); ?></div>
                            </div>
                            <form method="POST" class="text-center">
                                <input type="hidden" name="member_id" value="<?php echo $player['id']; ?>">
                                <button type="submit" name="remove_player" class="btn btn-remove">REMOVE FROM TEAM</button>
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
                                    <span><?php echo strtoupper(substr($athlete['username'], 0, 1)); ?></span>
                                </div>
                                <div class="player-name">
                                    <?php echo htmlspecialchars($athlete['username']); ?>
                                </div>
                                <div class="stats-display">
                                    <div class="stat-item">
                                        <div>HEIGHT</div>
                                        <div class="stat-value"><?php echo $athlete['height']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div>WEIGHT</div>
                                        <div class="stat-value"><?php echo $athlete['weight']; ?></div>
                                    </div>
                                </div>
                                <form method="POST" class="text-center">
                                    <input type="hidden" name="user_id" value="<?php echo $athlete['id']; ?>">
                                    <div class="form-group">
                                        <input type="number" name="jersey_number" class="form-control" placeholder="Jersey Number" required>
                                    </div>
                                    <div class="form-group">
                                        <select name="position_id" class="form-select" required>
                                            <option value="">Select Position</option>
                                            <?php foreach ($positions as $position): ?>
                                                <option value="<?php echo $position['id']; ?>">
                                                    <?php echo htmlspecialchars($position['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_player" class="btn">ADD TO TEAM</button>
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
