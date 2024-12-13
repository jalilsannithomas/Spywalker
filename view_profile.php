<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

if (!$profile_id) {
    header("Location: dashboard.php");
    exit();
}

// Get user profile information
$query = "SELECT u.*, 
                 CASE 
                    WHEN u.role = 'athlete' THEN s.name 
                    WHEN u.role = 'coach' THEN cs.name 
                 END as sport_name,
                 ap.height, ap.weight, p.name as position_name,
                 (SELECT COUNT(*) FROM collected_athletes 
                  WHERE athlete_id = u.id) as follower_count,
                 EXISTS(SELECT 1 FROM collected_athletes 
                       WHERE user_id = ? AND athlete_id = u.id) as is_collected,
                 cp.sport_id as coach_sport_id,
                 cp.specialization,
                 cp.years_experience,
                 cp.certification,
                 cp.education,
                 cp.first_name as coach_first_name,
                 cp.last_name as coach_last_name
          FROM users u 
          LEFT JOIN athlete_profiles ap ON u.id = ap.user_id AND u.role = 'athlete'
          LEFT JOIN sports s ON ap.sport_id = s.id
          LEFT JOIN positions p ON ap.position_id = p.id
          LEFT JOIN coach_profiles cp ON u.id = cp.user_id AND u.role = 'coach'
          LEFT JOIN sports cs ON cp.sport_id = cs.id
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $current_user_id, $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: dashboard.php");
    exit();
}

// Get additional profile details based on role
$profile_details = null;
if ($user['role'] === 'athlete') {
    $profile_details = [
        'sport_name' => $user['sport_name'] ?? 'Not specified',
        'position' => $user['position_name'] ?? 'Position not specified',
        'height' => $user['height'] ? sprintf('%d\'%d"', floor($user['height']/12), $user['height']%12) : 'Height not specified'
    ];
} elseif ($user['role'] === 'coach') {
    // Get coach profile details
    $profile_details = [
        'name' => $user['coach_first_name'] . ' ' . $user['coach_last_name'],
        'sport_name' => $user['sport_name'] ?? 'Not specified',
        'specialization' => $user['specialization'] ?? 'Not specified',
        'years_experience' => $user['years_experience'] ?? '0',
        'certification' => $user['certification'] ?? 'Not specified',
        'education' => $user['education'] ?? 'Not specified'
    ];

    // Get team and sport details for coach
    $profile_details['team_name'] = $user['coach_team_name'] ?? 'Not assigned';

    // Get coach's win/loss record
    $record_query = "SELECT 
                        COUNT(*) as total_matches,
                        SUM(CASE 
                            WHEN (m.home_team_id = t.id AND m.home_score > m.away_score) 
                                OR (m.away_team_id = t.id AND m.away_score > m.home_score) 
                            THEN 1 ELSE 0 END) as wins,
                        SUM(CASE 
                            WHEN m.home_score = m.away_score THEN 1 
                            ELSE 0 END) as draws
                    FROM teams t
                    LEFT JOIN matches m ON t.id = m.home_team_id OR t.id = m.away_team_id
                    WHERE t.coach_id = ?";
    $stmt = $conn->prepare($record_query);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    
    if ($record && $record['total_matches'] > 0) {
        $wins = $record['wins'];
        $draws = $record['draws'];
        $losses = $record['total_matches'] - $wins - $draws;
        $profile_details['record'] = "$wins-$losses-$draws";
    }
}

// Get recent activity
$activity = [];
if ($user['role'] === 'athlete') {
    // Get recent matches
    $activity_query = "SELECT m.match_date, t1.name as home_team, t2.name as away_team,
                             s.name as sport_name
                      FROM matches m
                      JOIN teams t1 ON m.home_team_id = t1.id
                      JOIN teams t2 ON m.away_team_id = t2.id
                      JOIN sports s ON m.sport_id = s.id
                      JOIN team_members tm ON (tm.team_id = t1.id OR tm.team_id = t2.id)
                      WHERE tm.user_id = ?
                      ORDER BY m.match_date DESC
                      LIMIT 5";
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user['role'] === 'coach') {
    // Get recent team matches
    $activity_query = "SELECT m.match_date, t1.name as home_team, t2.name as away_team,
                             s.name as sport_name, 
                             CASE 
                                 WHEN m.home_team_id = t.id AND m.home_score > m.away_score THEN 'Won'
                                 WHEN m.away_team_id = t.id AND m.away_score > m.home_score THEN 'Won'
                                 WHEN m.home_score = m.away_score THEN 'Draw'
                                 ELSE 'Lost'
                             END as result
                      FROM matches m
                      JOIN teams t ON m.home_team_id = t.id OR m.away_team_id = t.id
                      JOIN teams t1 ON m.home_team_id = t1.id
                      JOIN teams t2 ON m.away_team_id = t2.id
                      JOIN sports s ON m.sport_id = s.id
                      WHERE t.coach_id = ?
                      ORDER BY m.match_date DESC
                      LIMIT 5";
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #2C1810;
            color: #D4AF37;
        }
        .container {
            padding: 20px;
        }
        .profile-header {
            background-color: #3C2415;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 2px solid #D4AF37;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid #D4AF37;
            object-fit: cover;
        }
        .profile-info {
            flex-grow: 1;
        }
        .profile-name {
            font-size: 24px;
            margin-bottom: 10px;
            color: #D4AF37;
        }
        .profile-role {
            font-size: 14px;
            color: #A67C00;
            margin-bottom: 15px;
        }
        .profile-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 18px;
            color: #D4AF37;
        }
        .stat-label {
            font-size: 12px;
            color: #A67C00;
        }
        .profile-section {
            background-color: #3C2415;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #D4AF37;
        }
        .section-title {
            font-size: 18px;
            color: #D4AF37;
            margin-bottom: 15px;
        }
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .action-btn {
            background-color: #D4AF37;
            color: #2C1810;
            border: none;
            padding: 8px 15px;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .action-btn:hover {
            background-color: #A67C00;
            transform: translateY(-2px);
        }
        .action-btn.collected {
            background-color: #666;
            cursor: not-allowed;
        }
        .activity-item {
            background-color: #2C1810;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #D4AF37;
        }
        .activity-date {
            font-size: 12px;
            color: #A67C00;
        }
        .activity-details {
            margin-top: 10px;
            font-size: 14px;
        }
        .message-btn {
            background-color: #4CAF50 !important;
            margin-left: 10px;
        }
        .message-btn:hover {
            background-color: #45a049 !important;
        }
        .result {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .result.won {
            color: #4CAF50;
        }
        .result.lost {
            color: #f44336;
        }
        .result.draw {
            color: #ff9800;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container">
        <div class="profile-header">
            <img src="<?php echo $user['profile_image'] ? 'uploads/profile_images/' . htmlspecialchars($user['profile_image']) : 'assets/images/default-profile.jpg'; ?>" 
                 alt="Profile Image" class="profile-image">
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <div class="profile-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
                <div class="profile-stats">
                    <?php if ($user['role'] === 'athlete'): ?>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($user['follower_count']); ?></div>
                            <div class="stat-label">Followers</div>
                        </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars($user['sport_name'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Sport</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user['role'] === 'athlete'): ?>
            <div class="profile-section">
                <h2 class="section-title">Athlete Details</h2>
                <div class="athlete-details">
                    <p><strong>Sport:</strong> <?php echo htmlspecialchars($profile_details['sport_name']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($profile_details['position']); ?></p>
                    <p><strong>Height:</strong> <?php echo htmlspecialchars($profile_details['height']); ?></p>
                </div>
                <?php if ($current_user_id !== $profile_id): ?>
                    <div class="profile-actions">
                        <button class="action-btn <?php echo $user['is_collected'] ? 'collected' : ''; ?>"
                                onclick="collectAthlete(<?php echo (int)$user['id']; ?>)"
                                <?php echo $user['is_collected'] ? 'disabled' : ''; ?>>
                            <?php echo $user['is_collected'] ? 'COLLECTED' : 'COLLECT ATHLETE'; ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'coach'): ?>
            <div class="profile-section">
                <h2 class="section-title">Coach Details</h2>
                <div class="coach-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($profile_details['name']); ?></p>
                    <p><strong>Team:</strong> <?php echo htmlspecialchars($profile_details['team_name']); ?></p>
                    <p><strong>Sport:</strong> <?php echo htmlspecialchars($profile_details['sport_name']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($profile_details['specialization']); ?></p>
                    <p><strong>Years of Experience:</strong> <?php echo htmlspecialchars($profile_details['years_experience']); ?></p>
                    <p><strong>Certification:</strong> <?php echo htmlspecialchars($profile_details['certification']); ?></p>
                    <p><strong>Education:</strong> <?php echo htmlspecialchars($profile_details['education']); ?></p>
                    <?php if (isset($profile_details['record'])): ?>
                        <p><strong>Record:</strong> <?php echo htmlspecialchars($profile_details['record']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($current_user_id !== $profile_id): ?>
                    <div class="profile-actions">
                        <button class="action-btn <?php echo $user['is_collected'] ? 'collected' : ''; ?>"
                                onclick="collectCoach(<?php echo (int)$user['id']; ?>)"
                                <?php echo $user['is_collected'] ? 'disabled' : ''; ?>>
                            <?php echo $user['is_collected'] ? 'FOLLOWING' : 'FOLLOW COACH'; ?>
                        </button>
                        <button class="action-btn message-btn" 
                                onclick="location.href='messages.php?user_id=<?php echo (int)$user['id']; ?>'">
                            MESSAGE COACH
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($activity)): ?>
                <div class="profile-section">
                    <h2 class="section-title">Recent Team Activity</h2>
                    <?php foreach ($activity as $item): ?>
                        <div class="activity-item">
                            <div class="activity-date">
                                <?php echo date('M j, Y', strtotime($item['match_date'])); ?>
                            </div>
                            <div class="activity-details">
                                <?php echo htmlspecialchars($item['home_team']); ?> 
                                vs 
                                <?php echo htmlspecialchars($item['away_team']); ?>
                                <?php if (isset($item['result'])): ?>
                                    - <span class="result <?php echo strtolower($item['result']); ?>">
                                        <?php echo htmlspecialchars($item['result']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($activity) && $user['role'] === 'athlete'): ?>
            <div class="profile-section">
                <h2 class="section-title">Recent Activity</h2>
                <?php foreach ($activity as $item): ?>
                    <div class="activity-item">
                        <div class="activity-date">
                            <?php echo date('M j, Y', strtotime($item['match_date'])); ?>
                        </div>
                        <div class="activity-details">
                            <?php echo htmlspecialchars($item['home_team']); ?> 
                            vs 
                            <?php echo htmlspecialchars($item['away_team']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function collectAthlete(athleteId) {
            // Convert athleteId to number to ensure proper JSON
            const id = parseInt(athleteId);
            console.log('Collecting athlete with ID:', id);
            
            fetch('ajax/collect_athlete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error collecting athlete. Please try again.');
            });
        }

        function collectCoach(coachId) {
            const id = parseInt(coachId);
            console.log('Following coach with ID:', id);
            
            fetch('ajax/collect_coach.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error following coach. Please try again.');
            });
        }
    </script>
</body>
</html>
