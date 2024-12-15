<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$stats = [
    'users' => [],
    'total_teams' => 0,
    'total_matches' => 0
];
$recent_users = [];
$error = null;

try {
    // Total users by role
    $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['users'][$row['role']] = $row['count'];
    }

    // Total fantasy teams
    $sql = "SELECT COUNT(*) as count FROM fantasy_teams";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_teams'] = $result['count'] ?? 0;

    // Recent user registrations
    $sql = "SELECT id, first_name, last_name, email, role, created_at 
            FROM users 
            ORDER BY created_at DESC 
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($recent_users === false) {
        $recent_users = []; // Reset to empty array if query fails
    }

} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
    $recent_users = []; // Ensure it's an empty array on error
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', cursive;
            color: #D4AF37;
            padding: 20px;
        }

        .dashboard-title {
            color: #D4AF37;
            text-align: center;
            margin: 30px 0;
            text-shadow: 2px 2px #000;
        }

        .stat-card {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid #D4AF37;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stats-title {
            color: #D4AF37;
            font-size: 1em;
            margin-bottom: 15px;
        }

        .stats-number {
            color: #FFD700;
            font-size: 2em;
            margin-bottom: 0;
        }

        .quick-actions {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid #D4AF37;
            border-radius: 15px;
            padding: 20px;
            margin: 30px 0;
        }

        .quick-actions-title {
            color: #D4AF37;
            font-size: 1.2em;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .btn-action {
            background: #D4AF37;
            border: none;
            color: #241409;
            padding: 15px 30px;
            margin: 10px;
            font-size: 0.8em;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action:hover {
            background: #FFD700;
            transform: translateY(-2px);
            color: #241409;
        }

        .recent-section {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid #D4AF37;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .section-title {
            color: #D4AF37;
            font-size: 1.2em;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .table {
            color: #D4AF37;
            margin: 0;
        }

        .table th {
            border-color: #D4AF37;
            font-size: 0.8em;
            padding: 15px;
            text-transform: uppercase;
        }

        .table td {
            border-color: #D4AF37;
            font-size: 0.7em;
            padding: 15px;
        }

        .alert {
            background: rgba(220, 53, 69, 0.9);
            border: 2px solid #dc3545;
            color: #fff;
            margin-bottom: 20px;
            font-size: 0.8em;
            padding: 15px;
        }
    </style>
</head>
<body>
    <?php require_once '../components/navbar.php'; ?>

    <div class="container">
        <h1 class="dashboard-title">ADMIN DASHBOARD</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo $stats['users']['athlete'] ?? 0; ?></h3>
                    <p>ATHLETES</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo $stats['users']['coach'] ?? 0; ?></h3>
                    <p>COACHES</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo $stats['total_teams']; ?></h3>
                    <p>TEAMS</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><?php echo $stats['total_matches']; ?></h3>
                    <p>MATCHES</p>
                </div>
            </div>
        </div>

        <!-- Recent Users Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="recent-section">
                    <h2>Recent User Registrations</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($recent_users)): ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No recent registrations found</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Matches Section -->
        <div class="recent-section mb-4">
            <h2>Recent Matches</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Teams</th>
                            <th>Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center">No matches available. Match functionality coming soon!</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="quick-actions-title">Quick Actions</h3>
            <div class="d-flex flex-wrap justify-content-center">
                <a href="manage_users.php" class="btn-action">
                    <i class="bi bi-people"></i> Manage Users
                </a>
                <a href="manage_teams.php" class="btn-action">
                    <i class="bi bi-trophy"></i> Manage Teams
                </a>
                <a href="manage_matches.php" class="btn-action">
                    <i class="bi bi-controller"></i> Manage Matches
                </a>
                <a href="manage_sports.php" class="btn-action">
                    <i class="bi bi-dribbble"></i> Manage Sports
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
