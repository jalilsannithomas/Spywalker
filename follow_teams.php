<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Check if user is logged in and is a fan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'fan') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['team_id']) && isset($_POST['action'])) {
        $team_id = $_POST['team_id'];
        $action = $_POST['action'];

        if ($action === 'follow') {
            $sql = "INSERT IGNORE INTO fan_followed_teams (fan_id, team_id) VALUES (?, ?)";
        } else {
            $sql = "DELETE FROM fan_followed_teams WHERE fan_id = ? AND team_id = ?";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $team_id);
        
        if ($stmt->execute()) {
            $success_message = $action === 'follow' ? 'Team followed successfully!' : 'Team unfollowed successfully!';
        } else {
            $error_message = "Error processing your request. Please try again.";
        }
    }
}

// Get all available teams
$teams_sql = "SELECT t.*, 
              (SELECT COUNT(*) FROM fan_followed_teams WHERE team_id = t.id AND fan_id = ?) as is_following
              FROM teams t
              ORDER BY t.name";
$stmt = $conn->prepare($teams_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teams_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Follow Teams - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --dark-brown: #2C1810;
            --medium-brown: #3C2A20;
            --light-brown: #4E3829;
            --accent-gold: #FFD700;
            --text-light: #E8D5C4;
            --text-muted: #A89386;
        }

        body {
            background-color: var(--dark-brown);
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
        }

        .team-card {
            background-color: var(--medium-brown);
            border: 1px solid var(--accent-gold);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .team-card:hover {
            transform: translateY(-5px);
        }

        .team-name {
            color: var(--accent-gold);
            font-family: 'Graduate', serif;
            margin-bottom: 10px;
        }

        .btn-follow {
            background-color: var(--accent-gold);
            color: var(--dark-brown);
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-follow:hover {
            background-color: #FFE55C;
            transform: scale(1.05);
        }

        .btn-unfollow {
            background-color: var(--light-brown);
            color: var(--text-light);
            border: 1px solid var(--accent-gold);
        }

        .btn-unfollow:hover {
            background-color: #6E4839;
            color: var(--text-light);
        }

        .alert {
            border-radius: 15px;
            border: 1px solid var(--accent-gold);
        }

        .alert-success {
            background-color: var(--medium-brown);
            color: var(--accent-gold);
        }

        .alert-danger {
            background-color: var(--medium-brown);
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container py-4">
        <h1 class="text-center mb-4" style="color: var(--accent-gold); font-family: 'Graduate', serif;">Follow Teams</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php while ($team = $teams_result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="team-card">
                        <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($team['description'] ?? 'No description available'); ?></p>
                        <form method="POST" action="follow_teams.php">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <?php if ($team['is_following']): ?>
                                <input type="hidden" name="action" value="unfollow">
                                <button type="submit" class="btn btn-follow btn-unfollow">
                                    <i class="bi bi-star-fill me-2"></i>Following
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="follow">
                                <button type="submit" class="btn btn-follow">
                                    <i class="bi bi-star me-2"></i>Follow
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
