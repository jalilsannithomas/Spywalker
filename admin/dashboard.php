<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get statistics
$stats = [];

// Total users by role
$sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $stats['users'][$row['role']] = $row['count'];
}

// Total teams
$sql = "SELECT COUNT(*) as count FROM teams";
$result = $conn->query($sql);
$stats['total_teams'] = $result->fetch_assoc()['count'];

// Total matches
$sql = "SELECT COUNT(*) as count FROM matches";
$result = $conn->query($sql);
$stats['total_matches'] = $result->fetch_assoc()['count'];

// Recent user registrations
$sql = "SELECT username, email, role, DATE_FORMAT(created_at, '%M %d, %Y') as joined_date 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5";
$recent_users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Recent matches
$sql = "SELECT m.*, 
        ht.name as home_team_name, 
        at.name as away_team_name,
        DATE_FORMAT(m.match_date, '%M %d, %Y') as formatted_date
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        ORDER BY m.match_date DESC
        LIMIT 5";
$recent_matches = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', sans-serif;
            background-color: #2C1810;
            color: #D4AF37;
        }

        .container {
            background-color: #2C1810;
            padding: 2rem;
        }

        .dashboard-title {
            color: #D4AF37;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .stat-card {
            background-color: #3C2415;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #D4AF37;
        }

        .stat-label {
            color: #D4AF37;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }

        .quick-actions {
            margin-top: 2rem;
        }

        .quick-actions-title {
            color: #D4AF37;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-btn {
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            padding: 1rem 2rem;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background-color: #D4AF37;
            color: #2C1810;
            transform: translateY(-2px);
        }

        .data-section {
            background-color: #3C2415;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .section-title {
            color: #D4AF37;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .pixel-table {
            color: #D4AF37;
            background-color: #3C2415;
            border-collapse: collapse;
        }

        .pixel-table thead th {
            background-color: #2C1810;
            color: #D4AF37;
            border: 1px solid #D4AF37;
            border-bottom: 2px solid #D4AF37;
        }

        .pixel-table tbody td {
            color: #D4AF37;
            border: 1px solid rgba(212, 175, 55, 0.2);
            background-color: #3C2415;
        }

        .pixel-table tbody tr:hover td {
            background-color: #4D2F1C;
        }

        .role-badge {
            background-color: #2C1810;
            color: #D4AF37;
            border: 1px solid #D4AF37;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .role-badge.athlete {
            background-color: #3C2415;
        }

        .role-badge.coach {
            background-color: #4D2F1C;
        }
    </style>
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>

    <div class="container">
        <h1 class="dashboard-title">ADMIN DASHBOARD</h1>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['users']['athlete'] ?? 0; ?></div>
                <div class="stat-label">ATHLETES</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['users']['coach'] ?? 0; ?></div>
                <div class="stat-label">COACHES</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_teams']; ?></div>
                <div class="stat-label">TEAMS</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_matches']; ?></div>
                <div class="stat-label">MATCHES</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="quick-actions-title">QUICK ACTIONS</h2>
            <div class="action-buttons">
                <a href="manage_users.php" class="action-btn">
                    <i class="bi bi-people-fill"></i>
                    MANAGE USERS
                </a>
                <a href="manage_teams.php" class="action-btn">
                    <i class="bi bi-trophy-fill"></i>
                    MANAGE TEAMS
                </a>
                <a href="manage_matches.php" class="action-btn">
                    <i class="bi bi-calendar-event-fill"></i>
                    MANAGE MATCHES
                </a>
                <a href="manage_sports.php" class="action-btn">
                    <i class="bi bi-dribbble"></i>
                    MANAGE SPORTS
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Recent Users -->
            <div class="col-md-6">
                <div class="data-section">
                    <h2 class="section-title">RECENT USERS</h2>
                    <div class="table-responsive">
                        <table class="pixel-table">
                            <thead>
                                <tr>
                                    <th>USERNAME</th>
                                    <th>ROLE</th>
                                    <th>JOINED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['joined_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Matches -->
            <div class="col-md-6">
                <div class="data-section">
                    <h2 class="section-title">RECENT MATCHES</h2>
                    <div class="table-responsive">
                        <table class="pixel-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>MATCH</th>
                                    <th>SCORE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_matches as $match): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($match['formatted_date']); ?></td>
                                    <td><?php echo htmlspecialchars($match['home_team_name'] . ' vs ' . $match['away_team_name']); ?></td>
                                    <td><?php echo isset($match['home_score']) ? $match['home_score'] . ' - ' . $match['away_score'] : 'TBD'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
