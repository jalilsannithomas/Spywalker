<?php
require_once('../includes/auth.php');
require_once('../config/db.php');
require_once('../includes/functions.php');

// Get player ID from URL
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;
$sport_id = isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 0;

// Get player details
$player_query = "SELECT u.*, s.name as sport_name, s.id as sport_id 
                 FROM users u 
                 JOIN team_members tm ON u.id = tm.user_id 
                 JOIN teams t ON tm.team_id = t.id 
                 JOIN sports s ON t.sport_id = s.id 
                 WHERE u.id = ? AND (? = 0 OR s.id = ?)
                 LIMIT 1";
$stmt = $conn->prepare($player_query);
$stmt->bind_param("iii", $player_id, $sport_id, $sport_id);
$stmt->execute();
$player = $stmt->get_result()->fetch_assoc();

if (!$player) {
    die("Player not found");
}

// Get stat categories for the sport
$stats_query = "SELECT * FROM stat_categories WHERE sport_id = ? ORDER BY id";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $player['sport_id']);
$stmt->execute();
$stat_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get player's game stats
$games_query = "SELECT g.*, 
                ht.name as home_team, 
                at.name as away_team,
                GROUP_CONCAT(CONCAT(sc.abbreviation, ':', pgs.value) SEPARATOR ',') as game_stats
                FROM games g
                JOIN teams ht ON g.home_team_id = ht.id
                JOIN teams at ON g.away_team_id = at.id
                JOIN player_game_stats pgs ON g.id = pgs.game_id
                JOIN stat_categories sc ON pgs.stat_category_id = sc.id
                WHERE pgs.player_id = ?
                GROUP BY g.id
                ORDER BY g.game_date DESC";
$stmt = $conn->prepare($games_query);
$stmt->bind_param("i", $player_id);
$stmt->execute();
$games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate averages
$averages = [];
$totals = [];
foreach ($stat_categories as $cat) {
    $avg_query = "SELECT AVG(value) as avg, SUM(value) as total 
                 FROM player_game_stats 
                 WHERE player_id = ? AND stat_category_id = ?";
    $stmt = $conn->prepare($avg_query);
    $stmt->bind_param("ii", $player_id, $cat['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $averages[$cat['abbreviation']] = round($result['avg'], 1);
    $totals[$cat['abbreviation']] = $result['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Stats - <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include('../includes/navbar.php'); ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?> - 
            <?php echo htmlspecialchars($player['sport_name']); ?> Stats
        </h2>

        <!-- Career Averages -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Career Averages</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($averages as $stat => $value): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo htmlspecialchars($stat); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($value); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Career Totals -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Career Totals</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($totals as $stat => $value): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo htmlspecialchars($stat); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($value); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Game Log -->
        <div class="card">
            <div class="card-header">
                <h4>Game Log</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Game</th>
                                <?php foreach ($stat_categories as $cat): ?>
                                    <th><?php echo htmlspecialchars($cat['abbreviation']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): 
                                $game_stats = array_reduce(
                                    explode(',', $game['game_stats']),
                                    function($carry, $item) {
                                        list($key, $value) = explode(':', $item);
                                        $carry[$key] = $value;
                                        return $carry;
                                    },
                                    []
                                );
                            ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($game['game_date'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($game['home_team'] . ' vs ' . $game['away_team']); ?>
                                    </td>
                                    <?php foreach ($stat_categories as $cat): ?>
                                        <td>
                                            <?php echo isset($game_stats[$cat['abbreviation']]) ? 
                                                      htmlspecialchars($game_stats[$cat['abbreviation']]) : 
                                                      '0'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
