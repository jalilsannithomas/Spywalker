<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('../config/db.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize variables
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                error_log("=== Starting team addition process ===");
                $name = trim($_POST['team_name']);
                $coach_id = isset($_POST['coach_id']) ? (int)$_POST['coach_id'] : null;
                $sport_id = (int)$_POST['sport_id'];
                $primary_color = trim($_POST['primary_color']);
                $secondary_color = trim($_POST['secondary_color']);
                
                error_log("Attempting to add team with data: " . print_r([
                    'name' => $name,
                    'coach_id' => $coach_id,
                    'sport_id' => $sport_id,
                    'primary_color' => $primary_color,
                    'secondary_color' => $secondary_color
                ], true));
                
                if (!empty($name)) {
                    $conn->beginTransaction();
                    try {
                        // Insert team
                        $stmt = $conn->prepare("INSERT INTO teams (name, coach_id, sport_id, primary_color, secondary_color) VALUES (:name, :coach_id, :sport_id, :primary_color, :secondary_color)");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':coach_id', $coach_id);
                        $stmt->bindParam(':sport_id', $sport_id);
                        $stmt->bindParam(':primary_color', $primary_color);
                        $stmt->bindParam(':secondary_color', $secondary_color);
                        
                        error_log("Executing team insert query...");
                        if (!$stmt->execute()) {
                            $errorInfo = $stmt->errorInfo();
                            error_log("Team insertion error: " . print_r($errorInfo, true));
                            throw new PDOException("Failed to insert team: " . $errorInfo[2]);
                        }
                        
                        $team_id = $conn->lastInsertId();
                        error_log("Team inserted successfully with ID: $team_id");
                        
                        // Verify team was added
                        $verify_query = "SELECT * FROM teams WHERE id = :team_id";
                        $verify_stmt = $conn->prepare($verify_query);
                        $verify_stmt->bindParam(':team_id', $team_id);
                        
                        if (!$verify_stmt->execute()) {
                            $errorInfo = $verify_stmt->errorInfo();
                            error_log("Verification query error: " . print_r($errorInfo, true));
                        } else {
                            $new_team = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                            error_log("Verification of new team: " . print_r($new_team, true));
                        }
                        
                        // Insert players if provided
                        if (isset($_POST['player_id']) && is_array($_POST['player_id'])) {
                            error_log("Adding players to team: " . print_r($_POST['player_id'], true));
                            
                            $player_stmt = $conn->prepare("INSERT INTO team_members (team_id, athlete_id) VALUES (:team_id, :athlete_id)");
                            
                            foreach ($_POST['player_id'] as $index => $player_id) {
                                $player_stmt->bindParam(':team_id', $team_id);
                                $player_stmt->bindParam(':athlete_id', $player_id);
                                
                                if (!$player_stmt->execute()) {
                                    throw new Exception("Error adding player to team: " . implode(", ", $player_stmt->errorInfo()));
                                }
                            }
                        }
                        
                        $conn->commit();
                        $message = "Team added successfully!";
                        error_log("=== Team addition completed successfully ===");
                        
                    } catch (Exception $e) {
                        $conn->rollBack();
                        error_log("Critical error during team addition: " . $e->getMessage());
                        error_log("Error trace: " . $e->getTraceAsString());
                        $error = "Failed to add team: " . $e->getMessage();
                    }
                } else {
                    error_log("Invalid team name provided");
                    $error = "Team name cannot be empty";
                }
                break;

            case 'edit':
                $team_id = (int)$_POST['team_id'];
                $name = trim($_POST['team_name']);
                $coach_id = isset($_POST['coach_id']) ? (int)$_POST['coach_id'] : null;
                $sport_id = (int)$_POST['sport_id'];
                $primary_color = trim($_POST['primary_color']);
                $secondary_color = trim($_POST['secondary_color']);
                
                if (!empty($name)) {
                    try {
                        $stmt = $conn->prepare("UPDATE teams SET name = :name, coach_id = :coach_id, sport_id = :sport_id, primary_color = :primary_color, secondary_color = :secondary_color WHERE id = :team_id");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':coach_id', $coach_id);
                        $stmt->bindParam(':sport_id', $sport_id);
                        $stmt->bindParam(':primary_color', $primary_color);
                        $stmt->bindParam(':secondary_color', $secondary_color);
                        $stmt->bindParam(':team_id', $team_id);
                        
                        if ($stmt->execute()) {
                            $message = "Team updated successfully!";
                            
                            // Update players if provided
                            if (isset($_POST['player_id']) && is_array($_POST['player_id'])) {
                                // First, remove all existing team members
                                $delete_stmt = $conn->prepare("DELETE FROM team_members WHERE team_id = :team_id");
                                $delete_stmt->bindParam(':team_id', $team_id);
                                $delete_stmt->execute();
                                
                                // Then add the new ones
                                $player_stmt = $conn->prepare("INSERT INTO team_members (team_id, athlete_id) VALUES (:team_id, :athlete_id)");
                                
                                foreach ($_POST['player_id'] as $index => $player_id) {
                                    $player_stmt->bindParam(':team_id', $team_id);
                                    $player_stmt->bindParam(':athlete_id', $player_id);
                                    
                                    if (!$player_stmt->execute()) {
                                        throw new Exception("Error updating team players: " . implode(", ", $player_stmt->errorInfo()));
                                    }
                                }
                            }
                        } else {
                            throw new Exception("Error updating team: " . implode(", ", $stmt->errorInfo()));
                        }
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                } else {
                    $error = "Team name is required";
                }
                break;

            case 'delete':
                $team_id = (int)$_POST['team_id'];
                
                try {
                    // First delete team members
                    $delete_members = $conn->prepare("DELETE FROM team_members WHERE team_id = :team_id");
                    $delete_members->bindParam(':team_id', $team_id);
                    $delete_members->execute();
                    
                    // Then delete the team
                    $delete_team = $conn->prepare("DELETE FROM teams WHERE id = :team_id");
                    $delete_team->bindParam(':team_id', $team_id);
                    
                    if ($delete_team->execute()) {
                        $message = "Team deleted successfully!";
                    } else {
                        throw new Exception("Error deleting team: " . implode(", ", $delete_team->errorInfo()));
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
        }
    }
}

// Get all sports with their positions
$sports = [];
$query = "SELECT s.id as sport_id, s.name as sport_name, 
          p.id as position_id, p.name as position_name 
          FROM sports s 
          LEFT JOIN positions p ON s.id = p.sport_id 
          ORDER BY s.id, p.name";
try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Initialize sports array
    $sports = [];
    
    // Fetch all rows using PDO
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sport_id = $row['sport_id'];
        if (!isset($sports[$sport_id])) {
            $sports[$sport_id] = [
                'id' => (string)$sport_id,  // Ensure string type for JSON
                'name' => $row['sport_name'],
                'positions' => []
            ];
        }
        
        // Add position if it exists
        if (!empty($row['position_id'])) {
            $sports[$sport_id]['positions'][] = [
                'id' => (string)$row['position_id'],
                'name' => $row['position_name']
            ];
        }
    }
    
    // Convert indexed array to sequential array for JSON
    $sports = array_values($sports);
    
} catch (PDOException $e) {
    error_log("Error fetching sports and positions: " . $e->getMessage());
    $sports = [];  // Set empty array on error
}

// Get teams with their players
error_log("=== Starting team fetch process ===");
error_log("Database connection state: " . ($conn ? "Connected" : "Not connected"));

$teams_query = "SELECT t.id, t.name, t.sport_id, t.coach_id,
                COALESCE(t.primary_color, '#000000') as primary_color,
                COALESCE(t.secondary_color, '#FFFFFF') as secondary_color,
                s.name as sport_name,
                CONCAT(u.first_name, ' ', u.last_name) as coach_name
                FROM teams t
                LEFT JOIN sports s ON t.sport_id = s.id
                LEFT JOIN users u ON t.coach_id = u.id
                ORDER BY t.name";

try {
    error_log("Preparing teams query...");
    $teams_stmt = $conn->prepare($teams_query);
    
    error_log("Executing teams query...");
    if (!$teams_stmt->execute()) {
        $errorInfo = $teams_stmt->errorInfo();
        error_log("Query execution error: " . print_r($errorInfo, true));
        throw new PDOException("Query execution failed: " . $errorInfo[2]);
    }
    
    $teams = [];
    
    // Debug: Log the number of teams found
    $num_teams = $teams_stmt->rowCount();
    error_log("Number of teams found in database: $num_teams");
    
    // Test direct fetch first
    error_log("Testing direct fetch of first row...");
    $test_row = $teams_stmt->fetch(PDO::FETCH_ASSOC);
    if ($test_row === false) {
        error_log("No rows returned from fetch");
    } else {
        error_log("Successfully fetched first row: " . print_r($test_row, true));
        // Reset the cursor
        $teams_stmt->execute();
    }
    
    while ($team = $teams_stmt->fetch(PDO::FETCH_ASSOC)) {
        error_log("Processing team ID: " . ($team['id'] ?? 'unknown'));
        
        $team_id = $team['id'];
        
        try {
            // Get players for this team
            $players_query = "SELECT tm.id as member_id, u.id as athlete_id,
                             CONCAT(u.first_name, ' ', u.last_name) as player_name
                             FROM team_members tm
                             JOIN users u ON tm.athlete_id = u.id
                             WHERE tm.team_id = :team_id";
            
            error_log("Executing player query for team $team_id: $players_query");
            $players_stmt = $conn->prepare($players_query);
            $players_stmt->bindParam(':team_id', $team_id);
            
            if (!$players_stmt->execute()) {
                $errorInfo = $players_stmt->errorInfo();
                error_log("Error fetching players for team $team_id: " . print_r($errorInfo, true));
                $team['players'] = [];
            } else {
                $team['players'] = $players_stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Found " . count($team['players']) . " players for team $team_id");
                error_log("Players data: " . print_r($team['players'], true));
            }
        } catch (PDOException $e) {
            error_log("Exception while fetching players for team $team_id: " . $e->getMessage());
            $team['players'] = [];
        }
        
        $teams[] = $team;
    }
    
    error_log("=== Team fetch process completed ===");
    error_log("Total teams processed: " . count($teams));
    
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
    error_log("Critical error in teams fetch: " . $errorMessage);
    error_log("Error code: " . $e->getCode());
    error_log("Error trace: " . $e->getTraceAsString());
    
    // Set a more specific error message for display
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        $error = "Unable to connect to the database. Please try again later.";
    } else if (strpos($e->getMessage(), 'Access denied') !== false) {
        $error = "Database access error. Please contact support.";
    } else {
        $error = "Error loading teams: " . $e->getMessage();
    }
    $teams = [];
}

// Debug final state
error_log("Final teams array count: " . count($teams));
if (empty($teams)) {
    error_log("Warning: No teams available for display");
}

// Get all coaches
$coaches_query = "SELECT u.id, u.first_name, u.last_name 
                 FROM users u 
                 WHERE u.role = 'coach'
                 ORDER BY u.first_name, u.last_name";

try {
    $coaches_stmt = $conn->prepare($coaches_query);
    $coaches_stmt->execute();
    $coaches = [];
    
    while ($row = $coaches_stmt->fetch(PDO::FETCH_ASSOC)) {
        $coaches[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching coaches: " . $e->getMessage());
    $coaches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teams - SpyWalker Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #d2b48c;
            background-image: linear-gradient(45deg, #bc8f8f 25%, transparent 25%, transparent 75%, #bc8f8f 75%, #bc8f8f),
                            linear-gradient(45deg, #bc8f8f 25%, transparent 25%, transparent 75%, #bc8f8f 75%, #bc8f8f);
            background-size: 60px 60px;
            background-position: 0 0, 30px 30px;
            padding: 20px;
        }

        .container {
            background-color: rgba(210, 180, 140, 0.9);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }

        .section-title {
            font-family: 'Georgia', serif;
            color: #8b4513;
            font-size: 2.5rem;
            margin-bottom: 30px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .team-card {
            background: #f4e4bc;
            border: 3px solid #8b4513;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 5px 5px 15px rgba(0,0,0,0.2);
            position: relative;
            transition: transform 0.2s;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                rgba(139, 69, 19, 0.05),
                rgba(139, 69, 19, 0.05) 10px,
                rgba(139, 69, 19, 0.08) 10px,
                rgba(139, 69, 19, 0.08) 20px
            );
            border-radius: 12px;
            pointer-events: none;
        }

        .team-name {
            font-family: 'Georgia', serif;
            color: #8b4513;
            font-size: 1.5rem;
            font-weight: bold;
            border-bottom: 2px solid #8b4513;
            margin-bottom: 15px;
            padding-bottom: 10px;
            text-align: center;
        }

        .team-info {
            font-family: 'Courier New', monospace;
            color: #5a3825;
            background: rgba(244, 228, 188, 0.7);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(139, 69, 19, 0.2);
        }

        .team-info p {
            margin-bottom: 8px;
            padding-left: 10px;
            border-left: 3px solid #8b4513;
        }

        .team-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-vintage {
            background: #8b4513;
            color: #f4e4bc;
            border: 2px solid #5a3825;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .btn-vintage:hover {
            background: #5a3825;
            color: #f4e4bc;
            transform: translateY(-2px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
        }

        .btn-vintage-danger {
            background: #8b1a1a;
            border-color: #5a1111;
        }

        .btn-vintage-danger:hover {
            background: #5a1111;
        }

        .add-team-card {
            background: #f4e4bc;
            border: 3px dashed #8b4513;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
        }

        .form-control {
            background: #fff5e6;
            border: 2px solid #8b4513;
            color: #5a3825;
            padding: 10px 15px;
        }

        .form-control:focus {
            background: #fff;
            border-color: #5a3825;
            box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
        }

        .form-label {
            color: #8b4513;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .modal-content {
            background: #f4e4bc;
            border: 3px solid #8b4513;
        }

        .modal-header {
            border-bottom: 2px solid #8b4513;
            background: rgba(139, 69, 19, 0.1);
        }

        .modal-title {
            color: #8b4513;
            font-weight: bold;
            font-family: 'Georgia', serif;
        }

        .btn-close {
            background-color: #8b4513;
            opacity: 0.8;
        }

        .alert {
            background: #f4e4bc;
            border: 2px solid #8b4513;
            color: #8b4513;
        }

        .alert-danger {
            background: #f4d0d0;
            border-color: #8b1a1a;
            color: #8b1a1a;
        }
        
        .form-control, .form-select {
            background-color: #f5e6d3;
            border: 2px solid #8b4513;
            color: #8b4513;
        }

        .form-control:focus, .form-select:focus {
            background-color: #fff5e6;
            border-color: #d2691e;
            box-shadow: 0 0 0 0.25rem rgba(139, 69, 19, 0.25);
        }

        textarea.form-control {
            font-family: 'Courier New', monospace;
            line-height: 1.5;
        }

        .form-control-color {
            padding: 0.375rem;
            height: 38px;
        }

        .form-label {
            color: #8b4513;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .player-row {
            background-color: #f5e6d3;
            border: 1px solid #8b4513;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .player-row:hover {
            background-color: #fff5e6;
        }

        .remove-player {
            color: #8b1a1a;
            cursor: pointer;
        }

        .add-player {
            background-color: #8b4513;
            color: #f5e6d3;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .add-player:hover {
            background-color: #d2691e;
        }

        .players-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 2px solid #8b4513;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="section-title">Manage Teams</h1>
            <div>
                <a href="dashboard.php" class="btn btn-vintage me-2">Back to Dashboard</a>
                <button type="button" class="btn btn-vintage" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                    <i class="fas fa-plus"></i> Add New Team
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Teams Grid -->
        <h2 class="h3 mb-4 text-center" style="color: #8b4513;">Current Teams</h2>
        <div class="row">
            <?php foreach ($teams as $team): ?>
                <div class="col-md-4 mb-4">
                    <div class="team-card">
                        <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                        <div class="team-info">
                            <p><strong>Sport:</strong> <?php echo htmlspecialchars($team['sport_name'] ?? 'Not Specified'); ?></p>
                            <p><strong>Coach:</strong> <?php echo htmlspecialchars($team['coach_name'] ?: 'Not Assigned'); ?></p>
                            <p><strong>Players:</strong> <?php echo count($team['players']); ?></p>
                        </div>
                        
                        <div class="team-actions mt-3">
                            <a href="manage_roster.php?team_id=<?php echo $team['id']; ?>" class="btn btn-vintage">
                                <i class="fas fa-users"></i> Roster
                            </a>
                            <button onclick="editTeam(<?php echo htmlspecialchars(json_encode($team)); ?>)" class="btn btn-vintage">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteTeam(<?php echo $team['id']; ?>)" class="btn btn-vintage btn-vintage-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Team Modal -->
    <div class="modal fade" id="addTeamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTeamForm" method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" class="form-control" name="team_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sport</label>
                            <select class="form-select" name="sport_id" id="sport_id" required>
                                <option value="">Select Sport</option>
                                <?php foreach ($sports as $sport): ?>
                                    <option value="<?php echo $sport['id']; ?>">
                                        <?php echo htmlspecialchars($sport['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Coach</label>
                            <select class="form-select" name="coach_id">
                                <option value="">Select Coach</option>
                                <?php foreach ($coaches as $coach): ?>
                                    <option value="<?php echo $coach['id']; ?>">
                                        <?php echo htmlspecialchars($coach['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Primary Color</label>
                            <input type="color" class="form-control" name="primary_color" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Secondary Color</label>
                            <input type="color" class="form-control" name="secondary_color" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Initial Players</label>
                            <div id="players-list">
                                <!-- Players will be added here dynamically -->
                            </div>
                            <button type="button" class="btn btn-vintage btn-sm mt-2" onclick="addPlayer()">Add Player</button>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-vintage">Add Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div class="modal fade" id="editTeamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editTeamForm">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="team_id" id="edit_team_id">
                        <div class="mb-3">
                            <label for="edit_team_name" class="form-label">Team Name</label>
                            <input type="text" class="form-control" id="edit_team_name" name="team_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_coach_id" class="form-label">Coach</label>
                            <select class="form-select" id="edit_coach_id" name="coach_id">
                                <option value="">Select Coach</option>
                                <?php foreach ($coaches as $coach): ?>
                                    <option value="<?php echo $coach['id']; ?>">
                                        <?php echo htmlspecialchars($coach['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_sport_id" class="form-label">Sport</label>
                            <select class="form-select" id="edit_sport_id" name="sport_id" required>
                                <option value="">Select Sport</option>
                                <?php foreach ($sports as $sport): ?>
                                    <option value="<?php echo htmlspecialchars($sport['id']); ?>">
                                        <?php echo htmlspecialchars($sport['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Team Roster</label>
                            <div class="players-container">
                                <div id="edit-players-list">
                                </div>
                                <button type="button" class="add-player mt-2" onclick="addEditPlayer()">
                                    Add Player
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_primary_color" class="form-label">Primary Team Color</label>
                                <input type="color" class="form-control form-control-color w-100" 
                                       id="edit_primary_color" name="primary_color" value="#000000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_secondary_color" class="form-label">Secondary Team Color</label>
                                <input type="color" class="form-control form-control-color w-100" 
                                       id="edit_secondary_color" name="secondary_color" value="#ffffff">
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-vintage">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store sports and their positions
        const sportsPositions = <?php echo json_encode($sports); ?>;
        console.log('Sports and positions:', sportsPositions);
        
        function addPlayer() {
            console.log('addPlayer called');
            const playersList = document.getElementById('players-list');
            const sportId = document.getElementById('sport_id').value;
            console.log('Selected sport ID:', sportId);
            
            if (!sportId) {
                alert('Please select a sport first');
                return;
            }
            
            const newPlayer = document.createElement('div');
            newPlayer.className = 'player-row mb-2';
            
            // Get available players who are not in any team
            fetch(`get_available_players.php?sport_id=${sportId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(players => {
                    console.log('Available players:', players);
                    let playersOptions = players.map(p => 
                        `<option value="${p.id}">${p.first_name} ${p.last_name}</option>`
                    ).join('');
                    
                    // Get positions for the selected sport
                    let positions = [];
                    const sport = sportsPositions.find(s => s.id === sportId);
                    console.log('Found sport:', sport);
                    if (sport && sport.positions) {
                        positions = sport.positions;
                    }
                    
                    let positionsOptions = positions.map(p => 
                        `<option value="${p.id}">${p.name}</option>`
                    ).join('');
                    
                    newPlayer.innerHTML = `
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2">
                                <select class="form-select" name="player_id[]" required>
                                    <option value="">Select Player</option>
                                    ${playersOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select class="form-select" name="player_position[]">
                                    <option value="">Select Position</option>
                                    ${positionsOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="number" class="form-control" name="player_jersey[]" 
                                       placeholder="Jersey #" min="0" max="99">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.player-row').remove()">
                                    Remove
                                </button>
                            </div>
                        </div>
                    `;
                    
                    playersList.appendChild(newPlayer);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to fetch available players. Please try again.');
                });
        }
        
        // Update positions when sport changes
        document.getElementById('sport_id').addEventListener('change', function() {
            const sportId = this.value;
            const playerRows = document.querySelectorAll('.player-row');
            
            // Get positions for the selected sport
            let positions = [];
            if (sportId) {
                const sport = sportsPositions.find(s => s.id === sportId);
                if (sport && sport.positions) {
                    positions = sport.positions;
                }
            }
            
            // Update position dropdowns
            playerRows.forEach(row => {
                const positionSelect = row.querySelector('select[name="player_position[]"]');
                positionSelect.innerHTML = '<option value="">Select Position</option>' +
                    positions.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
            });
            
            // Update available players
            fetch(`get_available_players.php?sport_id=${sportId}`)
                .then(response => response.json())
                .then(players => {
                    const playersOptions = '<option value="">Select Player</option>' +
                        players.map(p => 
                            `<option value="${p.id}">${p.first_name} ${p.last_name}</option>`
                        ).join('');
                    
                    playerRows.forEach(row => {
                        const playerSelect = row.querySelector('select[name="player_id[]"]');
                        playerSelect.innerHTML = playersOptions;
                    });
                });
        });
        
        function addEditPlayer() {
            const playersList = document.getElementById('edit-players-list');
            const sportId = document.getElementById('edit_sport_id').value;
            const newPlayer = document.createElement('div');
            newPlayer.className = 'player-row mb-2';
            
            // Get available players who are not in any team
            fetch(`get_available_players.php?sport_id=${sportId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(players => {
                    console.log('Available players:', players);
                    let playersOptions = players.map(p => 
                        `<option value="${p.id}">${p.first_name} ${p.last_name}</option>`
                    ).join('');
                    
                    // Get positions for the selected sport
                    let positions = [];
                    const sport = sportsPositions.find(s => s.id === sportId);
                    console.log('Found sport:', sport);
                    if (sport && sport.positions) {
                        positions = sport.positions;
                    }
                    
                    let positionsOptions = positions.map(p => 
                        `<option value="${p.id}">${p.name}</option>`
                    ).join('');
                    
                    newPlayer.innerHTML = `
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2">
                                <select class="form-select" name="player_id[]" required>
                                    <option value="">Select Player</option>
                                    ${playersOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select class="form-select" name="player_position[]">
                                    <option value="">Select Position</option>
                                    ${positionsOptions}
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="number" class="form-control" name="player_jersey[]" 
                                       placeholder="Jersey #" min="0" max="99">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.player-row').remove()">
                                    Remove
                                </button>
                            </div>
                        </div>
                    `;
                    
                    playersList.appendChild(newPlayer);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to fetch available players. Please try again.');
                });
        }
        
        function editTeam(team) {
            document.getElementById('edit_team_id').value = team.id;
            document.getElementById('edit_team_name').value = team.name;
            document.getElementById('edit_coach_id').value = team.coach_id || '';
            document.getElementById('edit_sport_id').value = team.sport_id || '';
            document.getElementById('edit_primary_color').value = team.primary_color || '#000000';
            document.getElementById('edit_secondary_color').value = team.secondary_color || '#ffffff';
            
            const playersList = document.getElementById('edit-players-list');
            playersList.innerHTML = '';
            
            if (team.players) {
                team.players.forEach(player => {
                    const playerRow = document.createElement('div');
                    playerRow.className = 'player-row mb-2';
                    playerRow.innerHTML = `
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2">
                                <select class="form-select" name="player_id[]" required>
                                    <option value="">Select Player</option>
                                    <option value="${player.user_id}" selected>
                                        ${player.player_name}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select class="form-select" name="player_position[]">
                                    <option value="">Select Position</option>
                                    <option value="${player.position_id}" selected>
                                        ${player.position_name}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="number" class="form-control" name="player_jersey[]" 
                                       value="${player.jersey_number}" placeholder="Jersey #" min="0" max="99">
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.player-row').remove()">
                                    Remove
                                </button>
                            </div>
                        </div>
                    `;
                    playersList.appendChild(playerRow);
                });
            }
            
            const modal = new bootstrap.Modal(document.getElementById('editTeamModal'));
            modal.show();
        }
        
        function deleteTeam(teamId) {
            if (confirm('Are you sure you want to delete this team? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';

                const teamIdInput = document.createElement('input');
                teamIdInput.type = 'hidden';
                teamIdInput.name = 'team_id';
                teamIdInput.value = teamId;

                form.appendChild(actionInput);
                form.appendChild(teamIdInput);
                document.body.appendChild(form);

                form.submit();
            }
        }
    </script>
</body>
</html>
