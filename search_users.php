<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Debug database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the required tables exist
$tables_query = "SHOW TABLES LIKE 'athlete_profiles'";
$tables_result = $conn->query($tables_query);
if ($tables_result->num_rows == 0) {
    die("athlete_profiles table does not exist");
}

// Simplified query to just get athletes
$check_query = "SELECT * FROM users WHERE role = 'athlete' LIMIT 1";
$check_result = $conn->query($check_query);
if (!$check_result) {
    die("Error checking athletes: " . $conn->error);
}
echo "<!-- Number of athletes found: " . $check_result->num_rows . " -->";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

// Get featured athletes
$featured_query = "SELECT u.id, u.username, u.role, 
                         CONCAT('uploads/profile_images/', u.profile_image) as profile_image,
                         ap.first_name, ap.last_name, ap.height,
                         s.name as sport_name,
                         p.name as position_name,
                         (SELECT COUNT(*) FROM fan_followed_athletes WHERE athlete_id = u.id) as follower_count,
                         EXISTS(SELECT 1 FROM fan_followed_athletes 
                               WHERE fan_id = ? AND athlete_id = u.id) as is_following,
                         EXISTS(SELECT 1 FROM fantasy_team_players 
                               WHERE user_id = ? AND athlete_id = u.id) as in_fantasy,
                         10.5 as fantasy_points
                  FROM users u
                  JOIN athlete_profiles ap ON u.id = ap.user_id
                  LEFT JOIN sports s ON ap.sport_id = s.id
                  LEFT JOIN positions p ON ap.position_id = p.id
                  WHERE u.role = 'athlete'
                  ORDER BY follower_count DESC
                  LIMIT 8";

$featured_stmt = $conn->prepare($featured_query);
if (!$featured_stmt) {
    error_log("Error preparing featured athletes query: " . $conn->error);
$featured_athletes = [];
} else {
    $featured_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    if (!$featured_stmt->execute()) {
        error_log("Error executing featured athletes query: " . $featured_stmt->error);
        $featured_athletes = [];
    } else {
        $featured_athletes = $featured_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        error_log("Found " . count($featured_athletes) . " featured athletes");
    }
}

// If no athletes found in database, use sample data
if (empty($featured_athletes)) {
    error_log("No featured athletes found - using sample data");
    // Add sample data for testing
    $featured_athletes = [
        [
            'id' => 1,
            'username' => 'johndoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'height' => 72,
            'sport_name' => 'Basketball',
            'position_name' => 'Guard',
            'fantasy_points' => 15.5,
            'years_experience' => 2,
            'profile_image' => '',
            'is_following' => false,
            'in_fantasy' => false,
            'games_played' => 32,
            'points_per_game' => 15.5,
            'assists' => 4.2,
            'rebounds' => 6.8,
            'steals' => 1.4,
            'blocks' => 0.8
        ]
    ];
} else {
    error_log("First athlete data: " . print_r($featured_athletes[0], true));
}

function getSportAbbreviation($sport) {
    $abbreviations = [
        'Basketball' => 'BSKT BALL',
        'Football' => 'FTBALL',
        'Baseball' => 'BSBALL',
        'Soccer' => 'SCCR',
        'Volleyball' => 'VBALL',
        'Track and Field' => 'TRACK',
        'Swimming' => 'SWIM',
        'Tennis' => 'TNIS',
        'Golf' => 'GOLF',
        'Wrestling' => 'WRSTL'
    ];
    
    return $abbreviations[$sport] ?? strtoupper(substr($sport, 0, 6));
}

$query = "SELECT u.*, 
                 CASE 
                     WHEN u.role = 'athlete' THEN 
                         (SELECT s.name FROM sports s 
                          JOIN athlete_profiles ap ON s.id = ap.sport_id 
                          WHERE ap.user_id = u.id)
                     WHEN u.role = 'coach' THEN 
                         (SELECT GROUP_CONCAT(s.name) FROM sports s 
                          JOIN teams t ON s.id = t.sport_id 
                          WHERE t.coach_id = u.id)
                     ELSE NULL
                 END as sports,
                 CASE 
                     WHEN u.role = 'athlete' THEN 
                         (SELECT t.name FROM teams t 
                          JOIN team_members tm ON t.id = tm.team_id 
                          WHERE tm.user_id = u.id)
                     WHEN u.role = 'coach' THEN 
                         (SELECT GROUP_CONCAT(t.name) FROM teams t 
                          WHERE t.coach_id = u.id)
                     ELSE NULL
                 END as teams
          FROM users u
          WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
}

$query .= " LIMIT 50";

$search_param = "%$search_term%";
if ($role_filter !== 'all') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $role_filter);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Users - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/search.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="search-title">Find Players, Coaches & Fans</h1>
        
        <div class="search-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name or username..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="athlete" <?php echo $role_filter === 'athlete' ? 'selected' : ''; ?>>Athletes</option>
                        <option value="coach" <?php echo $role_filter === 'coach' ? 'selected' : ''; ?>>Coaches</option>
                        <option value="fan" <?php echo $role_filter === 'fan' ? 'selected' : ''; ?>>Fans</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="search-btn w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="results-section">
            <?php if (empty($search_term)): ?>
                <div class="text-center">
                    <p class="mb-0">Enter a name or username to start searching</p>
                </div>
            <?php elseif (empty($results)): ?>
                <div class="text-center">
                    <p class="mb-0">No users found matching your search criteria</p>
                </div>
            <?php else: ?>
                <div class="search-results">
                    <?php foreach ($results as $user): ?>
                        <div class="search-result-card">
                            <div class="role-badge <?php echo strtolower($user['role']); ?>">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </div>
                            
                            <div class="user-info">
                                <h3 class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                                <span class="username">@<?php echo htmlspecialchars($user['username']); ?></span>
                                
                                <?php if (!empty($user['sports'])): ?>
                                    <div class="sport-info">
                                        <span class="sport-icon">🏃</span>
                                        <span><?php echo htmlspecialchars($user['sports']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user['teams'])): ?>
                                    <div class="team-info">
                                        <span class="team-icon">👥</span>
                                        <span><?php echo htmlspecialchars($user['teams']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="view_profile.php?id=<?php echo $user['id']; ?>" class="view-profile-btn">
                                View Profile
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Featured Athletes Section -->
        <div class="featured-section">
            <h2 class="section-title">Featured Athletes</h2>
            <?php if (empty($featured_athletes)): ?>
                <div class="alert alert-info">No featured athletes found.</div>
            <?php else: ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($featured_athletes as $athlete): ?>
                        <div class="col-lg-4 col-md-6 mb-4" key="<?php echo $athlete['id']; ?>">
                        <div class="athlete-card">
                                <div class="athlete-card-header">
                                    <?php if (!empty($athlete['sport_name'])): ?>
                                        <div class="sport-tag"><?php echo htmlspecialchars($athlete['sport_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($athlete['fantasy_points'])): ?>
                                        <div class="fantasy-points"><?php echo number_format($athlete['fantasy_points'], 1); ?> FP</div>
                                    <?php endif; ?>
                            </div>
                                
                                <div class="profile-image-container">
                                    <?php 
                                        $profile_image_path = !empty($athlete['profile_image']) && $athlete['profile_image'] != 'uploads/profile_images/' ? 
                                            htmlspecialchars($athlete['profile_image']) : 
                                            'uploads/profile_images/default-profile.jpg';
                                    ?>
                                    <img src="<?php echo $profile_image_path; ?>" 
                                         alt="<?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>"
                                         class="athlete-image"
                                         onerror="this.src='uploads/profile_images/default-profile.jpg';">
                            </div>

                                <div class="athlete-info">
                                    <h3><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h3>
                                    <div class="player-info">
                                        <span><?php echo htmlspecialchars($athlete['position_name'] ?? 'Position N/A'); ?></span>
                                        <?php if (!empty($athlete['height'])): ?>
                                            <span class="height-tag">
                                                <?php 
                                                    $feet = floor($athlete['height'] / 12);
                                                    $inches = $athlete['height'] % 12;
                                                    echo htmlspecialchars($feet . "'" . $inches . '"'); 
                                                ?>
                                            </span>
                                <?php endif; ?>
                            </div>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn follow-btn <?php echo $athlete['is_following'] ? 'active' : ''; ?>"
                                            onclick="handleFollow(<?php echo $athlete['id']; ?>, this)">
                                        <i class="bi bi-<?php echo $athlete['is_following'] ? 'person-check' : 'person-plus'; ?>"></i>
                                        <?php echo $athlete['is_following'] ? 'Following' : 'Follow'; ?>
                                    </button>
                                    <button class="btn fantasy-btn <?php echo $athlete['in_fantasy'] ? 'active' : ''; ?>"
                                            onclick="handleCollect(<?php echo $athlete['id']; ?>, this)">
                                        <i class="bi bi-trophy<?php echo $athlete['in_fantasy'] ? '-fill' : ''; ?>"></i>
                                        <?php echo $athlete['in_fantasy'] ? 'Collected' : 'Collect'; ?>
                                    </button>
                                    <button class="btn message-btn"
                                            onclick="openMessageModal(<?php echo $athlete['id']; ?>, '<?php echo htmlspecialchars(addslashes($athlete['first_name'] . ' ' . $athlete['last_name']), ENT_QUOTES); ?>')">
                                        <i class="bi bi-envelope"></i> Message
                            </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleFollow(athleteId, button) {
            fetch('ajax/follow_athlete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    athlete_id: athleteId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = data.is_following ? 
                        '<i class="bi bi-person-check"></i> Following' : 
                        '<i class="bi bi-person-plus"></i> Follow';
                    button.classList.toggle('active');
                } else {
                    alert(data.message || 'Error following athlete');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error following athlete');
            });
        }

        function handleCollect(athleteId, button) {
            fetch('ajax/collect_athlete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    athlete_id: athleteId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = data.is_collected ? 
                        '<i class="bi bi-trophy-fill"></i> Collected' : 
                        '<i class="bi bi-trophy"></i> Collect';
                    button.classList.toggle('active');
                } else {
                    alert(data.message || 'Error collecting athlete');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error collecting athlete');
            });
        }

        function openMessageModal(athleteId, athleteName) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('messageModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'messageModal';
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Message to <span id="athleteName"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <textarea id="messageText" class="form-control" rows="4" placeholder="Type your message..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="sendMessage(${athleteId})">Send</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            // Update athlete name and show modal
            document.getElementById('athleteName').textContent = athleteName;
            new bootstrap.Modal(modal).show();
        }

        function sendMessage(athleteId) {
            const messageText = document.getElementById('messageText').value.trim();
            if (!messageText) {
                alert('Please enter a message');
                return;
            }

            fetch('ajax/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    athlete_id: athleteId,
                    message: messageText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
                    alert('Message sent successfully!');
                } else {
                    alert(data.message || 'Error sending message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message');
            });
        }
    </script>
</body>
</html>
