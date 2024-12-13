<?php
session_start();
require_once('../config/db.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Debug database connection
if ($conn->connect_error) {
    die("<!-- Connection failed: " . $conn->connect_error . " -->");
}

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
}

// Get all sports with error checking
$sports_query = "SELECT id, name FROM sports ORDER BY name";
echo "<!-- Executing query: " . htmlspecialchars($sports_query) . " -->";

$sports_result = $conn->query($sports_query);
if (!$sports_result) {
    die("<!-- Query failed: " . $conn->error . " -->");
}

$sports = [];
while ($row = $sports_result->fetch_assoc()) {
    $sports[] = $row;
    echo "<!-- Found sport: " . htmlspecialchars(json_encode($row)) . " -->";
}

echo "<!-- Total sports found: " . count($sports) . " -->";

// Get all teams with error checking
$teams_query = "SELECT id, name FROM teams ORDER BY name";
echo "<!-- Executing query: " . htmlspecialchars($teams_query) . " -->";

$teams_result = $conn->query($teams_query);
if (!$teams_result) {
    die("<!-- Query failed: " . $conn->error . " -->");
}

$teams = [];
while ($row = $teams_result->fetch_assoc()) {
    $teams[] = $row;
    echo "<!-- Found team: " . htmlspecialchars(json_encode($row)) . " -->";
}

echo "<!-- Total teams found: " . count($teams) . " -->";

// Process form submission for adding new match
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    error_log("Processing add match form...");
    
    $sport_id = $_POST['sport_id'] ?? '';
    $home_team_id = $_POST['team1_id'] ?? '';
    $away_team_id = $_POST['team2_id'] ?? '';
    $match_date = $_POST['match_date'] ?? '';
    $match_time = $_POST['match_time'] ?? '';
    $venue = $_POST['venue'] ?? '';
    
    error_log("Received values: sport_id=$sport_id, home_team_id=$home_team_id, away_team_id=$away_team_id, date=$match_date, time=$match_time, venue=$venue");
    
    // Validate inputs
    if (empty($sport_id) || empty($home_team_id) || empty($away_team_id) || empty($match_date) || empty($match_time)) {
        $error_message = "All fields except venue are required";
        error_log("Validation failed: " . $error_message);
    } else {
        // Insert new match
        $datetime = date('Y-m-d H:i:s', strtotime("$match_date $match_time"));
        $insert_query = "INSERT INTO matches (sport_id, home_team_id, away_team_id, match_date, venue) 
                        VALUES (?, ?, ?, ?, ?)";
        error_log("Executing query: " . $insert_query);
        error_log("With values: sport_id=$sport_id, home_team_id=$home_team_id, away_team_id=$away_team_id, datetime=$datetime, venue=$venue");
        
        try {
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iiiss", $sport_id, $home_team_id, $away_team_id, $datetime, $venue);
            
            if ($stmt->execute()) {
                $success_message = "Match added successfully";
                error_log("Match added successfully with ID: " . $conn->insert_id);
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error adding match: " . $stmt->error;
                error_log("Error adding match: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
            error_log("Exception adding match: " . $e->getMessage());
        }
    }
}

// Process edit match form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    error_log("Processing edit match form...");
    
    $match_id = $_POST['match_id'] ?? '';
    $sport_id = $_POST['sport_id'] ?? '';
    $home_team_id = $_POST['team1_id'] ?? '';
    $away_team_id = $_POST['team2_id'] ?? '';
    $match_date = $_POST['match_date'] ?? '';
    $match_time = $_POST['match_time'] ?? '';
    $venue = $_POST['venue'] ?? '';
    
    error_log("Edit values: match_id=$match_id, sport_id=$sport_id, home_team_id=$home_team_id, away_team_id=$away_team_id, date=$match_date, time=$match_time, venue=$venue");
    
    // Validate inputs
    if (empty($match_id) || empty($sport_id) || empty($home_team_id) || empty($away_team_id) || empty($match_date) || empty($match_time)) {
        $error_message = "All fields except venue are required";
        error_log("Edit validation failed: " . $error_message);
    } else {
        // Create datetime string in MySQL format
        $datetime = date('Y-m-d H:i:s', strtotime("$match_date $match_time"));
        $update_query = "UPDATE matches 
                        SET sport_id = ?, 
                            home_team_id = ?, 
                            away_team_id = ?, 
                            match_date = ?,
                            venue = ?
                        WHERE id = ?";
        
        error_log("Executing update query with datetime: $datetime");
        
        try {
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("iiissi", $sport_id, $home_team_id, $away_team_id, $datetime, $venue, $match_id);
            
            if ($stmt->execute()) {
                $success_message = "Match updated successfully";
                error_log("Match updated successfully");
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error updating match: " . $stmt->error;
                error_log("Error updating match: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
            error_log("Exception updating match: " . $e->getMessage());
        }
    }
}

// Get all matches with team names - moved after form processing
$matches_query = "SELECT m.id, m.match_date, m.sport_id, m.home_team_id, m.away_team_id, m.venue,
                 ht.name as home_team_name,
                 at.name as away_team_name,
                 s.name as sport_name
                 FROM matches m
                 LEFT JOIN teams ht ON m.home_team_id = ht.id
                 LEFT JOIN teams at ON m.away_team_id = at.id
                 LEFT JOIN sports s ON m.sport_id = s.id
                 ORDER BY m.match_date DESC";

error_log("Fetching matches with query: " . $matches_query);
$matches_result = $conn->query($matches_query);

if (!$matches_result) {
    error_log("Error fetching matches: " . $conn->error);
} else {
    $matches = [];
    while ($row = $matches_result->fetch_assoc()) {
        $matches[] = $row;
        error_log("Found match: " . print_r($row, true));
    }
    error_log("Total matches found: " . count($matches));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Matches - SpyWalker Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .admin-card {
            background: var(--vintage-cream, #fff);
            border: 2px solid var(--vintage-brown, #8B4513);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .page-title {
            font-family: 'Press Start 2P', cursive;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .add-match-btn {
            background-color: #4CAF50;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .add-match-btn:hover {
            background-color: #3e8e41;
        }
        
        .matches-table {
            font-family: 'Press Start 2P', cursive;
            font-size: 16px;
            border-collapse: collapse;
            width: 100%;
        }
        
        .matches-table th, .matches-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .matches-table th {
            background-color: #f0f0f0;
        }
        
        .action-btn {
            background-color: #4CAF50;
            color: #fff;
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background-color: #3e8e41;
        }
    </style>
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>
    
    <div class="container py-4">
        <h1 class="page-title">MANAGE MATCHES</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add Match Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addMatchModal">
            + Add New Match
        </button>

        <div class="admin-card">
            <div class="table-responsive">
                <table class="matches-table">
                    <thead>
                        <tr>
                            <th>Sport</th>
                            <th>Home Team</th>
                            <th>Away Team</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($matches)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No matches found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($match['sport_name']); ?></td>
                                    <td><?php echo htmlspecialchars($match['home_team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($match['away_team_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($match['match_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($match['venue'] ?? ''); ?></td>
                                    <td>
                                        <button class="action-btn" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $match['id']; ?>">
                                            ✏️
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <button type="submit" name="action" value="delete" class="action-btn" onclick="return confirm('Are you sure you want to delete this match?')">
                                                ❌
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Match Modal -->
                                <div class="modal fade" id="editModal<?php echo $match['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Match</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Sport</label>
                                                        <select class="form-select" name="sport_id" required>
                                                            <?php foreach ($sports as $sport): ?>
                                                                <option value="<?php echo $sport['id']; ?>" <?php echo $sport['id'] == $match['sport_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($sport['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Home Team</label>
                                                        <select class="form-select" name="team1_id" required>
                                                            <?php foreach ($teams as $team): ?>
                                                                <option value="<?php echo $team['id']; ?>" <?php echo $team['id'] == $match['home_team_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($team['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Away Team</label>
                                                        <select class="form-select" name="team2_id" required>
                                                            <?php foreach ($teams as $team): ?>
                                                                <option value="<?php echo $team['id']; ?>" <?php echo $team['id'] == $match['away_team_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($team['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Date</label>
                                                        <input type="date" class="form-control" name="match_date" 
                                                               value="<?php echo date('Y-m-d', strtotime($match['match_date'])); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Time</label>
                                                        <input type="time" class="form-control" name="match_time" 
                                                               value="<?php echo date('H:i', strtotime($match['match_date'])); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Venue</label>
                                                        <input type="text" class="form-control" name="venue" 
                                                               value="<?php echo htmlspecialchars($match['venue'] ?? ''); ?>">
                                                    </div>

                                                    <div class="text-end">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Match Modal -->
    <div class="modal fade" id="addMatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Match</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Sport</label>
                            <select class="form-select" name="sport_id" required>
                                <option value="">Select Sport</option>
                                <?php foreach ($sports as $sport): ?>
                                    <option value="<?php echo $sport['id']; ?>">
                                        <?php echo htmlspecialchars($sport['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Home Team</label>
                            <select class="form-select" name="team1_id" required>
                                <option value="">Select Home Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Away Team</label>
                            <select class="form-select" name="team2_id" required>
                                <option value="">Select Away Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="match_date" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="match_time" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Venue</label>
                            <input type="text" class="form-control" name="venue" required>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Match</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent selecting same team for home and away
        document.querySelectorAll('select[name="team1_id"], select[name="team2_id"]').forEach(select => {
            select.addEventListener('change', function() {
                const team1Select = this.closest('form').querySelector('select[name="team1_id"]');
                const team2Select = this.closest('form').querySelector('select[name="team2_id"]');
                
                if (team1Select.value && team2Select.value && team1Select.value === team2Select.value) {
                    alert('Home and Away teams cannot be the same!');
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html>
