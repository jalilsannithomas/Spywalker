<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Log session info
error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in - redirecting to login");
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$athlete_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
error_log("Viewing athlete_id: " . $athlete_id);

$error_message = '';
$athlete = null;
$stats = null;

try {
    // Log database connection status
    error_log("Database connection status: " . ($conn ? 'connected' : 'not connected'));
    
    // Get athlete information including team details
    $query = "SELECT 
                u.*,
                ap.height_feet,
                ap.height_inches,
                ap.weight,
                ap.years_of_experience,
                ap.school_year,
                ap.jersey_number,
                p.name as position_name,
                COALESCE(apm.base_rating, 0) as base_rating,
                COALESCE(apm.achievement_points, 0) as achievement_points,
                COALESCE(apm.games_played, 0) as games_played,
                CASE WHEN ffa.fan_id IS NOT NULL THEN 1 ELSE 0 END as is_following,
                t.name as team_name,
                t.primary_color,
                t.secondary_color,
                s.name as sport_name,
                CONCAT(c.first_name, ' ', c.last_name) as coach_name
              FROM users u
              LEFT JOIN athlete_profiles ap ON u.id = ap.user_id
              LEFT JOIN positions p ON ap.position_id = p.id
              LEFT JOIN athlete_performance_metrics apm ON u.id = apm.athlete_id
              LEFT JOIN fan_followed_athletes ffa ON u.id = ffa.athlete_id AND ffa.fan_id = :user_id
              LEFT JOIN team_members tm ON u.id = tm.athlete_id
              LEFT JOIN teams t ON tm.team_id = t.id
              LEFT JOIN sports s ON t.sport_id = s.id
              LEFT JOIN users c ON t.coach_id = c.id
              WHERE u.id = :athlete_id AND u.role = 'athlete'";
    
    error_log("Query: " . $query);
    error_log("Parameters - user_id: $user_id, athlete_id: $athlete_id");
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . implode(", ", $conn->errorInfo()));
        throw new Exception("Failed to prepare statement");
    }
    
    if (!$stmt->execute([
        ':user_id' => $user_id,
        ':athlete_id' => $athlete_id
    ])) {
        error_log("Failed to execute statement: " . implode(", ", $stmt->errorInfo()));
        throw new Exception("Failed to execute statement");
    }
    
    $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Athlete data: " . print_r($athlete, true));
    
    if (!$athlete) {
        error_log("No athlete found with ID: $athlete_id");
        throw new Exception("Athlete not found");
    }

} catch (Exception $e) {
    error_log("Error in athlete_profile.php: " . $e->getMessage());
    if ($e instanceof PDOException) {
        error_log("PDO Error Code: " . $e->getCode());
        error_log("Error Info: " . print_r($e->errorInfo, true));
    }
    $error_message = "An error occurred while loading the athlete profile. Details: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Profile - <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .profile-section {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid #D4AF37;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 4px solid #D4AF37;
            object-fit: cover;
            margin-right: 30px;
        }

        .profile-info {
            flex-grow: 1;
        }

        .athlete-name {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 1.5em;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .athlete-role {
            background: #D4AF37;
            color: #241409;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 0.8em;
            display: inline-block;
            margin-bottom: 15px;
            font-family: 'Press Start 2P', monospace;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: rgba(212, 175, 55, 0.1);
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2em;
            margin: 10px 0;
        }

        .stat-label {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7em;
            text-transform: uppercase;
        }

        .action-btn {
            background: #D4AF37;
            border: none;
            color: #241409;
            padding: 10px 20px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            transition: all 0.3s ease;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
        }

        .action-btn:hover {
            background: #FFD700;
            transform: translateY(-2px);
        }

        .action-btn.following {
            background: #28a745;
        }

        .error-message {
            background: rgba(220, 53, 69, 0.9);
            border: 4px solid #dc3545;
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: 'Press Start 2P', monospace;
            text-align: center;
        }

        .return-btn {
            display: inline-block;
            background: #D4AF37;
            color: #241409;
            padding: 10px 20px;
            text-decoration: none;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .return-btn:hover {
            background: #FFD700;
            transform: translateY(-2px);
            color: #241409;
            text-decoration: none;
        }
        
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #dc3545;
            color: #fff;
            border: 1px solid #dc3545;
        }
        
        .retro-text {
            font-family: 'Press Start 2P', monospace;
            font-size: 1.5em;
            color: #D4AF37;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .role-badge {
            background: #D4AF37;
            color: #241409;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 0.8em;
            display: inline-block;
            margin-bottom: 15px;
            font-family: 'Press Start 2P', monospace;
        }
        
        .stats-section {
            margin-top: 30px;
        }
        
        .stat-box {
            background: rgba(212, 175, 55, 0.1);
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2em;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.7em;
            text-transform: uppercase;
        }
        
        .btn-follow {
            background: #D4AF37;
            border: none;
            color: #241409;
            padding: 10px 20px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            transition: all 0.3s ease;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
        }
        
        .btn-follow:hover {
            background: #FFD700;
            transform: translateY(-2px);
        }
        
        .btn-follow.following {
            background: #28a745;
        }
        
        .btn-collect {
            background: #FFD700;
            border: none;
            color: #241409;
            padding: 10px 20px;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            transition: all 0.3s ease;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
        }
        
        .btn-collect:hover {
            background: #FFA500;
            transform: translateY(-2px);
        }
        
        .details-section {
            margin-top: 30px;
        }
        
        .section-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2em;
            color: #D4AF37;
            margin-bottom: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            color: #D4AF37;
        }
        
        .detail-value {
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            color: #fff;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container mt-4">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php else: ?>
            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-image">
                        <img src="<?php echo !empty($athlete['profile_image']) ? 'uploads/profile_images/' . htmlspecialchars($athlete['profile_image']) : 'uploads/profile_images/default-avatar.png'; ?>" 
                             alt="Profile Image" class="img-fluid rounded-circle">
                    </div>
                    <div class="profile-info ms-4">
                        <h1 class="retro-text"><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h1>
                        <div class="role-badge">ATHLETE</div>
                        <?php if ($athlete['team_name']): ?>
                            <p class="team-info">Team: <?php echo htmlspecialchars($athlete['team_name']); ?></p>
                        <?php endif; ?>
                        <button class="btn btn-follow <?php echo $athlete['is_following'] ? 'following' : ''; ?>" 
                                data-athlete-id="<?php echo $athlete_id; ?>"
                                onclick="toggleFollow(this)">
                            <?php echo $athlete['is_following'] ? 'Following' : 'Follow'; ?>
                        </button>
                        <?php if ($_SESSION['role'] === 'fan'): ?>
                            <button class="btn btn-collect" 
                                    data-athlete-id="<?php echo $athlete_id; ?>"
                                    onclick="collectCard(this)">
                                Collect Card
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stats-section">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="stat-value"><?php echo $athlete['base_rating']; ?></div>
                                <div class="stat-label">RATING</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="stat-value"><?php echo $athlete['achievement_points']; ?></div>
                                <div class="stat-label">ACHIEVEMENT POINTS</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="stat-value"><?php echo $athlete['games_played']; ?></div>
                                <div class="stat-label">GAMES PLAYED</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="details-section mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="section-title">Personal Information</h3>
                            <div class="detail-item">
                                <span class="detail-label">Height:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['height_feet'] . "'" . $athlete['height_inches'] . '"'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Weight:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['weight']); ?> lbs</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Position:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['position_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Jersey Number:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['jersey_number']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Experience:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['years_of_experience']); ?> years</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">School Year:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($athlete['school_year']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="section-title">Team Information</h3>
                            <?php if ($athlete['team_name']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Team:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($athlete['team_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Sport:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($athlete['sport_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Primary Color:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($athlete['primary_color']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Secondary Color:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($athlete['secondary_color']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Coach:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($athlete['coach_name']); ?></span>
                                </div>
                            <?php else: ?>
                                <p>Not currently on a team</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleFollow(button) {
        var athleteId = button.getAttribute('data-athlete-id');
        fetch('ajax/toggle_follow.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'athlete_id=' + athleteId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.textContent = data.isFollowing ? 'Following' : 'Follow';
                button.classList.toggle('following', data.isFollowing);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function collectCard(button) {
        var athleteId = button.getAttribute('data-athlete-id');
        fetch('ajax/collect_card.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'athlete_id=' + athleteId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.textContent = 'Collected!';
                button.disabled = true;
                button.style.background = '#28a745';
            } else {
                alert(data.message || 'Failed to collect card');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
