<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['athlete_id'])) {
    $athlete_id = $_POST['athlete_id'];
    
    // Get the athlete's user_id which is needed for the fan_followed_athletes table
    $user_id_sql = "SELECT user_id FROM athlete_profiles WHERE id = ?";
    $stmt = $conn->prepare($user_id_sql);
    $stmt->bind_param("i", $athlete_id);
    $stmt->execute();
    $athlete_user = $stmt->get_result()->fetch_assoc();
    
    if ($athlete_user) {
        $athlete_user_id = $athlete_user['user_id'];
        
        // Check if already following
        $check_sql = "SELECT * FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $_SESSION['user_id'], $athlete_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Unfollow
            $sql = "DELETE FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $athlete_user_id);
            $stmt->execute();
        } else {
            // Follow
            $sql = "INSERT INTO fan_followed_athletes (fan_id, athlete_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $athlete_user_id);
            $stmt->execute();
        }
    }
}

// Get all athletes with their follow status
$sql = "SELECT 
    ap.*,
    t.name as team_name,
    s.name as sport_name,
    CASE WHEN ffa.fan_id IS NOT NULL THEN 1 ELSE 0 END as is_following
FROM athlete_profiles ap
LEFT JOIN team_players tp ON ap.id = tp.athlete_id
LEFT JOIN teams t ON t.id = tp.team_id
LEFT JOIN sports s ON t.sport_id = s.id
LEFT JOIN fan_followed_athletes ffa ON ffa.athlete_id = ap.user_id AND ffa.fan_id = ?
ORDER BY ap.last_name, ap.first_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$athletes = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athletes List - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Graduate', sans-serif;
            background-color: #2C1810;
            color: #D4AF37;
        }
        .athlete-card {
            background-color: #3C2415;
            border: 1px solid #D4AF37;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .athlete-card:hover {
            transform: translateY(-5px);
        }
        .btn-follow {
            background-color: #D4AF37;
            color: #2C1810;
            border: none;
            transition: all 0.3s;
        }
        .btn-follow:hover {
            background-color: #C19B2C;
            color: #2C1810;
        }
        .btn-unfollow {
            background-color: #6c3d1e;
            color: #D4AF37;
            border: 1px solid #D4AF37;
        }
        .btn-unfollow:hover {
            background-color: #522d16;
            color: #D4AF37;
        }
        .athlete-name {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .athlete-info {
            font-family: 'Inter', sans-serif;
            font-size: 0.9em;
            color: #D4AF37;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container py-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <h2 class="mb-4">Athletes List</h2>
        
        <div class="row">
            <?php foreach ($athletes as $athlete): ?>
                <div class="col-md-4">
                    <div class="athlete-card">
                        <div class="athlete-name">
                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                        </div>
                        <div class="athlete-info mb-3">
                            <div><strong>Team:</strong> <?php echo htmlspecialchars($athlete['team_name']); ?></div>
                            <div><strong>Sport:</strong> <?php echo htmlspecialchars($athlete['sport_name']); ?></div>
                        </div>
                        <form method="POST" action="athlete_list.php">
                            <input type="hidden" name="athlete_id" value="<?php echo $athlete['id']; ?>">
                            <?php if ($athlete['is_following']): ?>
                                <input type="hidden" name="action" value="unfollow">
                                <button type="submit" class="btn btn-unfollow w-100">
                                    <i class="bi bi-person-dash-fill"></i> Unfollow
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="follow">
                                <button type="submit" class="btn btn-follow w-100">
                                    <i class="bi bi-person-plus-fill"></i> Follow
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
