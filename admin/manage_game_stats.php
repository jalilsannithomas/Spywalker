<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coach'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get list of sports
$sports_query = "SELECT id, name FROM sports ORDER BY name";
$sports_result = $conn->query($sports_query);
$sports = $sports_result->fetch_all(MYSQLI_ASSOC);

// Get selected sport
$selected_sport = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;

// Get games based on selected sport
$games = [];
if ($selected_sport) {
    $games_query = "
        SELECT g.*, 
               ht.name as home_team_name, 
               at.name as away_team_name,
               s.name as sport_name
        FROM games g
        JOIN teams ht ON g.home_team_id = ht.id
        JOIN teams at ON g.away_team_id = at.id
        JOIN sports s ON g.sport_id = s.id
        WHERE g.sport_id = ?
        ORDER BY g.game_date DESC
        LIMIT 10";
    
    $stmt = $conn->prepare($games_query);
    $stmt->bind_param("i", $selected_sport);
    $stmt->execute();
    $games_result = $stmt->get_result();
    $games = $games_result->fetch_all(MYSQLI_ASSOC);
}

// Get stat categories for selected sport
$stat_categories = [];
if ($selected_sport) {
    $stats_query = "SELECT * FROM stat_categories WHERE sport_id = ? ORDER BY name";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $selected_sport);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $stat_categories = $stats_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Game Statistics - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Alfa+Slab+One&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .vintage-bg {
            background: #8B4513;
            color: #FFF5E1;
            min-height: 100vh;
        }

        .page-title {
            font-family: 'Alfa Slab One', cursive;
            font-size: 2.5rem;
            color: #FFF5E1;
            text-align: center;
            margin-bottom: 2rem;
        }

        .sport-selector {
            background: rgba(255, 245, 225, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .game-card {
            background: rgba(255, 245, 225, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .team-score {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .stat-input {
            background-color: rgba(255, 245, 225, 0.1);
            border: 1px solid rgba(255, 245, 225, 0.2);
            color: #FFF5E1;
        }

        .stat-input:focus {
            background-color: rgba(255, 245, 225, 0.2);
            color: #FFF5E1;
            border-color: #FFF5E1;
        }

        .btn-vintage {
            background-color: #D2691E;
            color: #FFF5E1;
            border: none;
        }

        .btn-vintage:hover {
            background-color: #A0522D;
            color: #FFF5E1;
        }
    </style>
</head>
<body class="vintage-bg">
    <?php require_once '../components/navbar.php'; ?>

    <div class="container py-4">
        <h1 class="page-title">Game Statistics Management</h1>

        <!-- Sport Selection -->
        <div class="sport-selector">
            <form method="GET" class="mb-4">
                <label for="sport_id" class="form-label">Select Sport:</label>
                <select name="sport_id" id="sport_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select a Sport --</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>" <?php echo $selected_sport == $sport['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_sport): ?>
            <!-- Add New Game Button -->
            <div class="text-end mb-4">
                <button type="button" class="btn btn-vintage" data-bs-toggle="modal" data-bs-target="#addGameModal">
                    Add New Game
                </button>
            </div>

            <!-- Games List -->
            <?php foreach ($games as $game): ?>
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-date">
                            <?php echo date('M d, Y', strtotime($game['game_date'])); ?>
                        </div>
                        <div class="game-status badge bg-<?php echo $game['status'] === 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($game['status']); ?>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-4 text-end">
                            <div class="team-name"><?php echo htmlspecialchars($game['home_team_name']); ?></div>
                            <div class="team-score"><?php echo $game['home_score']; ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="vs">VS</div>
                        </div>
                        <div class="col-4 text-start">
                            <div class="team-name"><?php echo htmlspecialchars($game['away_team_name']); ?></div>
                            <div class="team-score"><?php echo $game['away_score']; ?></div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-vintage" onclick="viewGameStats(<?php echo $game['id']; ?>)">
                            View/Edit Stats
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($games)): ?>
                <div class="text-center">
                    <p>No games found for this sport. Add a new game to get started!</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Add Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Game</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addGameForm">
                        <input type="hidden" name="sport_id" value="<?php echo $selected_sport; ?>">
                        
                        <div class="mb-3">
                            <label for="home_team" class="form-label">Home Team</label>
                            <select name="home_team_id" id="home_team" class="form-control" required>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="away_team" class="form-label">Away Team</label>
                            <select name="away_team_id" id="away_team" class="form-control" required>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="game_date" class="form-label">Game Date</label>
                            <input type="datetime-local" name="game_date" id="game_date" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="venue" class="form-label">Venue</label>
                            <input type="text" name="venue" id="venue" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-vintage" onclick="saveGame()">Save Game</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (<?php echo $selected_sport ?: 'false'; ?>) {
            loadTeams();
        }
    });

    function loadTeams() {
        const sportId = <?php echo $selected_sport ?: 'null'; ?>;
        if (!sportId) return;

        fetch(`../api/get_teams.php?sport_id=${sportId}`)
            .then(response => response.json())
            .then(data => {
                const homeSelect = document.getElementById('home_team');
                const awaySelect = document.getElementById('away_team');
                
                homeSelect.innerHTML = '<option value="">Select Home Team</option>';
                awaySelect.innerHTML = '<option value="">Select Away Team</option>';
                
                data.forEach(team => {
                    homeSelect.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                    awaySelect.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                });
            })
            .catch(error => console.error('Error loading teams:', error));
    }

    function saveGame() {
        const form = document.getElementById('addGameForm');
        const formData = new FormData(form);
        
        fetch('../api/manage_games.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(Object.fromEntries(formData))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Failed to add game');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding the game');
        });
    }

    function viewGameStats(gameId) {
        window.location.href = `edit_game_stats.php?game_id=${gameId}`;
    }
    </script>
</body>
</html>
