<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../includes/auth.php');
require_once('../config/db.php');
require_once('../includes/functions.php');

// Only allow coach access
if (!isCoach()) {
    header("Location: /Spywalker/dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';
$user_id = $_SESSION['user_id'];

// Get coach's team
$team_query = "SELECT t.*, s.name as sport_name, 
                     COUNT(tp.athlete_id) as player_count 
              FROM teams t 
              LEFT JOIN sports s ON t.sport_id = s.id
              LEFT JOIN team_players tp ON t.id = tp.team_id
              WHERE t.coach_id = ?
              GROUP BY t.id";

$team_stmt = $conn->prepare($team_query);
$team_stmt->bind_param("i", $user_id);
$team_stmt->execute();
$team_result = $team_stmt->get_result();
$team = $team_result->fetch_assoc();

if (!$team) {
    $error_message = "You are not currently assigned to coach any team.";
} else {
    $team_id = $team['id'];
    
    // Get current roster
    $roster_query = "SELECT tp.*, a.first_name, a.last_name, a.position 
                    FROM team_players tp
                    JOIN athletes a ON tp.athlete_id = a.id
                    WHERE tp.team_id = ?
                    ORDER BY a.last_name, a.first_name";
                    
    $roster_stmt = $conn->prepare($roster_query);
    $roster_stmt->bind_param("i", $team_id);
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    
    // Get available athletes
    $available_query = "SELECT a.* 
                       FROM athletes a
                       LEFT JOIN team_players tp ON a.id = tp.athlete_id
                       WHERE tp.athlete_id IS NULL
                       ORDER BY a.last_name, a.first_name";
                       
    $available_stmt = $conn->prepare($available_query);
    $available_stmt->execute();
    $available_result = $available_stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Team Roster - SpyWalker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
        }
        .vintage-title {
            font-family: 'Press Start 2P', cursive;
            color: #00ff00;
            text-align: center;
            margin-bottom: 2rem;
            text-shadow: 2px 2px #003300;
        }
        .roster-section {
            background-color: #2a2a2a;
            border: 2px solid #00ff00;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            font-family: 'Press Start 2P', cursive;
            color: #00ff00;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .table {
            color: #00ff00;
            background-color: #1a1a1a;
        }
        .table th {
            border-color: #00ff00;
        }
        .table td {
            border-color: #00ff00;
        }
        .btn-outline-success {
            color: #00ff00;
            border-color: #00ff00;
        }
        .btn-outline-success:hover {
            background-color: #00ff00;
            color: #1a1a1a;
        }
        .btn-outline-danger {
            color: #ff0000;
            border-color: #ff0000;
        }
        .btn-outline-danger:hover {
            background-color: #ff0000;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
    <?php include('../components/navbar.php'); ?>

    <div class="container mt-4">
        <h1 class="vintage-title">MANAGE TEAM ROSTER</h1>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($team): ?>
        <div class="row">
            <!-- Team Info -->
            <div class="col-12 mb-4">
                <div class="roster-section">
                    <h2 class="section-title">TEAM INFORMATION</h2>
                    <table class="table">
                        <tr>
                            <th>Team Name:</th>
                            <td><?php echo htmlspecialchars($team['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Sport:</th>
                            <td><?php echo htmlspecialchars($team['sport_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Current Players:</th>
                            <td><?php echo $team['player_count']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Current Roster -->
            <div class="col-md-6">
                <div class="roster-section">
                    <h2 class="section-title">CURRENT ROSTER</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Jersey #</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($player = $roster_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($player['position']); ?></td>
                                <td><?php echo htmlspecialchars($player['jersey_number']); ?></td>
                                <td>
                                    <form action="remove_player.php" method="post" style="display: inline;">
                                        <input type="hidden" name="player_id" value="<?php echo $player['athlete_id']; ?>">
                                        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Available Athletes -->
            <div class="col-md-6">
                <div class="roster-section">
                    <h2 class="section-title">AVAILABLE ATHLETES</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($athlete = $available_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($athlete['position']); ?></td>
                                <td>
                                    <form action="add_player.php" method="post" style="display: inline;">
                                        <input type="hidden" name="athlete_id" value="<?php echo $athlete['id']; ?>">
                                        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                                        <input type="number" name="jersey_number" placeholder="Jersey #" required style="width: 80px; margin-right: 5px;">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Add</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
