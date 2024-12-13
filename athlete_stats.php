<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get athlete ID from URL
$athlete_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get athlete details
$athlete_query = "SELECT ap.*, u.username, u.profile_image, s.name as sport_name, p.name as position_name, t.name as team_name
                 FROM athlete_profiles ap
                 JOIN users u ON ap.user_id = u.id
                 LEFT JOIN sports s ON ap.sport_id = s.id
                 LEFT JOIN positions p ON ap.position_id = p.id
                 LEFT JOIN team_members tm ON ap.user_id = tm.user_id
                 LEFT JOIN teams t ON tm.team_id = t.id
                 WHERE ap.id = ?";

$stmt = $conn->prepare($athlete_query);
$stmt->bind_param("i", $athlete_id);
$stmt->execute();
$athlete = $stmt->get_result()->fetch_assoc();

// Get athlete's stats for the last 7 days
$stats_query = "SELECT ast.action_name,
                       SUM(ast.quantity) as total_quantity,
                       sr.points as points_per_action,
                       SUM(ast.quantity * sr.points) as total_points
                FROM athlete_stats ast
                JOIN scoring_rules sr ON sr.sport_id = ? AND sr.action_name = ast.action_name
                WHERE ast.athlete_id = ?
                AND ast.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY ast.action_name
                ORDER BY total_points DESC";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $athlete['sport_id'], $athlete_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total fantasy points
$total_points = array_sum(array_column($stats, 'total_points'));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Stats - <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #2C1810;
            color: #D4AF37;
            font-family: 'Inter', sans-serif;
        }
        .vintage-bg {
            background-color: #2C1810;
            min-height: 100vh;
            padding: 20px;
        }
        .athlete-header {
            background: rgba(44, 24, 16, 0.95);
            border: 2px solid #D4AF37;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .profile-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #D4AF37;
        }
        .athlete-info {
            flex-grow: 1;
        }
        .athlete-info h1 {
            color: #D4AF37;
            font-family: 'Graduate', serif;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }
        .athlete-info p {
            color: #D4AF37;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .stats-breakdown {
            background: rgba(44, 24, 16, 0.95);
            border: 2px solid #D4AF37;
            border-radius: 10px;
            padding: 20px;
        }
        .stats-breakdown h2 {
            color: #D4AF37;
            font-family: 'Graduate', serif;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }
        .stats-table th,
        .stats-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #D4AF37;
            color: #D4AF37;
        }
        .stats-table th {
            background-color: rgba(212, 175, 55, 0.1);
            font-weight: bold;
            font-family: 'Graduate', serif;
        }
        .stats-table tr:hover {
            background-color: rgba(212, 175, 55, 0.05);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .total-points {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 10px;
            text-align: right;
            color: #D4AF37;
        }
    </style>
</head>
<body class="vintage-bg">
    <?php include 'components/navbar.php'; ?>

    <div class="container">
        <div class="athlete-header">
            <img src="<?php echo htmlspecialchars($athlete['profile_image'] ? 'uploads/profile_images/' . $athlete['profile_image'] : 'assets/default_profile.jpg'); ?>" 
                 alt="Profile Image" 
                 class="profile-image">
            <div class="athlete-info">
                <h1><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h1>
                <p><strong>Sport:</strong> <?php echo htmlspecialchars($athlete['sport_name']); ?></p>
                <p><strong>Position:</strong> <?php echo htmlspecialchars($athlete['position_name']); ?></p>
                <p><strong>Team:</strong> <?php echo htmlspecialchars($athlete['team_name']); ?></p>
                <p class="total-points">Total Fantasy Points: <?php echo number_format($total_points, 1); ?></p>
            </div>
        </div>

        <div class="stats-breakdown">
            <h2>Last 7 Days Performance</h2>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Quantity</th>
                        <th>Points per Action</th>
                        <th>Total Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No stats recorded in the last 7 days</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $stat['action_name']))); ?></td>
                            <td><?php echo htmlspecialchars($stat['total_quantity']); ?></td>
                            <td><?php echo number_format($stat['points_per_action'], 1); ?></td>
                            <td><?php echo number_format($stat['total_points'], 1); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
