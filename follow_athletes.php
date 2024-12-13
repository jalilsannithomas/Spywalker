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
    if (isset($_POST['athlete_id']) && isset($_POST['action'])) {
        $athlete_id = $_POST['athlete_id'];
        $action = $_POST['action'];

        // Get the athlete's user_id
        $user_id_sql = "SELECT user_id FROM athlete_profiles WHERE id = ?";
        $stmt = $conn->prepare($user_id_sql);
        $stmt->bind_param("i", $athlete_id);
        $stmt->execute();
        $athlete_user = $stmt->get_result()->fetch_assoc();

        if ($athlete_user) {
            $athlete_user_id = $athlete_user['user_id'];

            if ($action === 'follow') {
                $sql = "INSERT INTO fan_followed_athletes (fan_id, athlete_id) VALUES (?, ?)";
            } else {
                $sql = "DELETE FROM fan_followed_athletes WHERE fan_id = ? AND athlete_id = ?";
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $athlete_user_id);
            
            if ($stmt->execute()) {
                $success_message = $action === 'follow' ? 'Athlete followed successfully!' : 'Athlete unfollowed successfully!';
            } else {
                $error_message = "Error processing your request. Please try again.";
            }
        }
    }
}

// Get all available athletes
$athletes_sql = "SELECT DISTINCT
    ap.id, 
    ap.first_name, 
    ap.last_name, 
    ap.position_id,
    t.name as team_name,
    s.name as sport_name,
    (SELECT COUNT(*) FROM fan_followed_athletes 
     WHERE athlete_id = ap.user_id AND fan_id = ?) as is_following
FROM athlete_profiles ap
LEFT JOIN team_players tp ON tp.athlete_id = ap.id
LEFT JOIN teams t ON t.id = tp.team_id
LEFT JOIN sports s ON s.id = t.sport_id
ORDER BY ap.last_name, ap.first_name";

$stmt = $conn->prepare($athletes_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$athletes_result = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Follow Athletes - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --dark-brown: #2C1810;
            --medium-brown: #3C2415;
            --light-brown: #4E2E1C;
            --accent-gold: #FFD700;
            --text-light: #F4E4BC;
            --text-muted: #A89882;
        }

        body {
            background-color: var(--dark-brown) !important;
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .container {
            background-color: var(--dark-brown);
        }

        .athlete-card {
            background-color: var(--medium-brown);
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
            transition: transform 0.2s;
            overflow: hidden;
            height: 550px;
        }

        .athlete-card::before {
            content: "ATHLETE CARD";
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-gold);
            color: var(--dark-brown);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
            font-family: 'Graduate', serif;
        }

        .athlete-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.2);
        }

        .athlete-photo {
            width: 100%;
            height: 200px;
            background-color: var(--light-brown);
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--accent-gold);
        }

        .athlete-photo i {
            font-size: 4rem;
            color: var(--text-muted);
        }

        .athlete-name {
            color: var(--accent-gold);
            font-family: 'Graduate', serif;
            font-size: 1.3rem;
            margin-bottom: 10px;
            text-align: center;
            border-bottom: 2px solid var(--accent-gold);
            padding-bottom: 10px;
        }

        .athlete-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            margin: 15px 0;
            text-align: center;
            border: 1px solid var(--accent-gold);
            padding: 10px;
            border-radius: 8px;
            background-color: var(--dark-brown);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 1.2rem;
            color: var(--accent-gold);
            font-family: 'Graduate', serif;
        }

        .athlete-info {
            text-align: left;
            margin: 15px 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .athlete-info div {
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .athlete-info span {
            color: var(--text-light);
        }

        .btn-follow {
            width: calc(100% - 40px);
            background: linear-gradient(145deg, #FFD700, #FFC800);
            color: #1A0F0A;
            border: 2px solid #FFE55C;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 800;
            font-size: 1rem;
            font-family: 'Graduate', serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            margin: 0;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4),
                       inset 0 2px 10px rgba(255, 255, 255, 0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .btn-follow:hover {
            transform: translateY(-2px);
            background: linear-gradient(145deg, #FFE44D, #FFD700);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5),
                       inset 0 2px 10px rgba(255, 255, 255, 0.3);
            border-color: #FFF0AA;
        }

        .btn-follow.following {
            background: linear-gradient(145deg, #FFD700, #FFC800);
            color: #1A0F0A;
        }

        .btn-follow.following:hover {
            background: linear-gradient(145deg, #FFE44D, #FFD700);
        }

        .btn-follow i {
            margin-right: 8px;
            font-size: 1rem;
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.1));
        }

        .page-title {
            color: var(--accent-gold);
            font-family: 'Graduate', serif;
            text-align: center;
            margin: 40px 0;
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container py-4">
        <h1 class="page-title">Follow Athletes</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="row">
            <?php while ($athlete = $athletes_result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="athlete-card">
                        <div class="athlete-photo">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <h3 class="athlete-name">
                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                        </h3>
                        
                        <div class="athlete-stats">
                            <div class="stat-item">
                                <span class="stat-label">Height</span>
                                <span class="stat-value">6'2"</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Weight</span>
                                <span class="stat-value">185</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Number</span>
                                <span class="stat-value">#23</span>
                            </div>
                        </div>

                        <div class="athlete-info">
                            <div>Position: <span><?php echo htmlspecialchars($athlete['position_id']); ?></span></div>
                            <div>Team: <span><?php echo htmlspecialchars($athlete['team_name']); ?></span></div>
                            <div>Sport: <span><?php echo htmlspecialchars($athlete['sport_name']); ?></span></div>
                        </div>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="athlete_id" value="<?php echo $athlete['id']; ?>">
                            <?php if ($athlete['is_following']): ?>
                                <input type="hidden" name="action" value="unfollow">
                                <button type="submit" class="btn btn-follow following">
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
