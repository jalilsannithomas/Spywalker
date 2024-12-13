<?php
require_once('../config/db.php');
require_once('../includes/auth.php');
require_once('../includes/functions.php');

// Get game ID from URL
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

// Get game details
$game_query = "SELECT g.*, ht.name as home_team, at.name as away_team 
               FROM games g 
               JOIN teams ht ON g.home_team_id = ht.id 
               JOIN teams at ON g.away_team_id = at.id 
               WHERE g.id = ?";
$stmt = $conn->prepare($game_query);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

// Get players from both teams
$players_query = "SELECT p.id, p.first_name, p.last_name, t.name as team_name, t.id as team_id 
                 FROM team_members tm 
                 JOIN users p ON tm.user_id = p.id 
                 JOIN teams t ON tm.team_id = t.id 
                 WHERE t.id IN (?, ?) 
                 ORDER BY t.id, p.last_name";
$stmt = $conn->prepare($players_query);
$stmt->bind_param("ii", $game['home_team_id'], $game['away_team_id']);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get stat categories for basketball
$stats_query = "SELECT * FROM stat_categories WHERE sport_id = 1 ORDER BY id"; // Assuming basketball sport_id = 1
$stats = $conn->query($stats_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Basketball Stats - <?php echo htmlspecialchars($game['home_team'] . ' vs ' . $game['away_team']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include('../includes/navbar.php'); ?>

    <div class="container mt-4">
        <h2 class="mb-4">Record Basketball Stats</h2>
        <h3><?php echo htmlspecialchars($game['home_team'] . ' vs ' . $game['away_team']); ?></h3>
        <p>Date: <?php echo date('F j, Y', strtotime($game['game_date'])); ?></p>

        <div class="row">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($game['home_team']); ?> (Home)</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <?php foreach ($stats as $stat): ?>
                                    <th><?php echo htmlspecialchars($stat['abbreviation']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): 
                                if ($player['team_id'] == $game['home_team_id']): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                                    <?php foreach ($stats as $stat): ?>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm stat-input" 
                                                   data-player="<?php echo $player['id']; ?>" 
                                                   data-stat="<?php echo $stat['id']; ?>" 
                                                   min="0" 
                                                   value="0">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($game['away_team']); ?> (Away)</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <?php foreach ($stats as $stat): ?>
                                    <th><?php echo htmlspecialchars($stat['abbreviation']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): 
                                if ($player['team_id'] == $game['away_team_id']): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></td>
                                    <?php foreach ($stats as $stat): ?>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm stat-input" 
                                                   data-player="<?php echo $player['id']; ?>" 
                                                   data-stat="<?php echo $stat['id']; ?>" 
                                                   min="0" 
                                                   value="0">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col">
                <button id="saveStats" class="btn btn-primary">Save Stats</button>
                <a href="manage_game_stats.php" class="btn btn-secondary">Back to Games</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#saveStats').click(function() {
            const stats = [];
            $('.stat-input').each(function() {
                const input = $(this);
                stats.push({
                    player_id: input.data('player'),
                    stat_id: input.data('stat'),
                    value: input.val()
                });
            });

            $.ajax({
                url: '../api/save_basketball_stats.php',
                method: 'POST',
                data: {
                    game_id: <?php echo $game_id; ?>,
                    stats: JSON.stringify(stats)
                },
                success: function(response) {
                    if (response.success) {
                        alert('Stats saved successfully!');
                        window.location.href = 'manage_game_stats.php';
                    } else {
                        alert('Error saving stats: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error saving stats. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>
