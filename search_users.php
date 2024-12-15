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

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$user_id = $_SESSION['user_id'];
$featured_athletes = [];
$results = [];
$error_message = '';
$debug_info = [];

try {
    // Ensure we're connected to the database
    if (!$conn) {
        throw new Exception("Database connection not established");
    }

    // Build the search parameters
    $params = [];
    $conditions = [];
    
    if (!empty($search_term)) {
        // Make search term case-insensitive and more lenient
        $search_param = "%" . strtolower($search_term) . "%";
        $conditions[] = "(LOWER(u.first_name) LIKE :search1 OR LOWER(u.last_name) LIKE :search2)";
        $params[':search1'] = $search_param;
        $params[':search2'] = $search_param;
        
        $debug_info['search_condition'] = [
            'term' => $search_term,
            'param' => $search_param,
            'sql' => $conditions[count($conditions)-1]
        ];
    }
    
    if ($role_filter !== 'all') {
        $params[':role'] = $role_filter;
        $conditions[] = "u.role = :role";
        $debug_info['role_filter'] = $role_filter;
    }
    
    // Build the query with all necessary joins
    $query = "SELECT DISTINCT u.*, 
                     COALESCE(apm.base_rating, 0) as base_rating,
                     COALESCE(apm.achievement_points, 0) as achievement_points,
                     COALESCE(apm.games_played, 0) as games_played,
                     CASE WHEN ffa.fan_id IS NOT NULL THEN 1 ELSE 0 END as is_following
              FROM users u
              LEFT JOIN athlete_performance_metrics apm ON u.id = apm.athlete_id
              LEFT JOIN fan_followed_athletes ffa ON u.id = ffa.athlete_id AND ffa.fan_id = :user_id";
    
    // Start WHERE clause
    $where_conditions = [];
    
    if (!empty($conditions)) {
        $where_conditions = array_merge($where_conditions, $conditions);
    }
    
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query .= " ORDER BY u.first_name, u.last_name LIMIT 50";
    
    $params[':user_id'] = $user_id;
    
    $debug_info['query'] = $query;
    $debug_info['params'] = $params;
    
    // Execute the search query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . implode(", ", $conn->errorInfo()));
    }
    
    if (!$stmt->execute($params)) {
        throw new Exception("Failed to execute statement: " . implode(", ", $stmt->errorInfo()));
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['results_count'] = count($results);
    
    // Get collection status for results
    $collected_cards = [];
    if (isset($_SESSION['user_id'])) {
        $collect_stmt = $conn->prepare("SELECT athlete_id FROM fan_collected_cards WHERE fan_id = :user_id");
        $collect_stmt->execute([':user_id' => $_SESSION['user_id']]);
        $collected_cards = $collect_stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    foreach ($results as &$user) {
        $user['is_collected'] = in_array($user['id'], $collected_cards);
    }

    // Get featured athletes if no search is being performed
    if (empty($search_term)) {
        try {
            $featured_query = "SELECT u.*,
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
                              WHERE u.role = :role 
                              ORDER BY RAND() LIMIT 8";
            $featured_stmt = $conn->prepare($featured_query);
            $featured_stmt->execute([':role' => 'athlete', ':user_id' => $user_id]);
            $featured_results = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add collection status to featured athletes
            foreach ($featured_results as $athlete) {
                $athlete['is_collected'] = in_array($athlete['id'], $collected_cards);
                $featured_athletes[] = $athlete;
            }
        } catch (Exception $e) {
            $featured_athletes = []; // Fail gracefully for featured athletes
        }
    }

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $debug_info['error'] = [
        'message' => $e->getMessage(),
        'code' => $e instanceof PDOException ? $e->getCode() : null,
        'info' => $e instanceof PDOException ? $e->errorInfo : null
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Search Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="text-center text-gold mb-4">Find Players, Coaches & Teams</h1>
        
        <div class="search-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="athlete" <?php echo $role_filter === 'athlete' ? 'selected' : ''; ?>>Athletes</option>
                        <option value="coach" <?php echo $role_filter === 'coach' ? 'selected' : ''; ?>>Coaches</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="search-btn w-100">Search</button>
                </div>
            </form>
        </div>

        <?php if (!empty($search_term) || isset($error_message)): ?>
        <div class="results-section mt-4">
            <h2 class="section-title">Search Results</h2>
            
            <?php if (!empty($debug_info['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($debug_info['error']['message']); ?>
                    <?php if (isset($_GET['debug'])): ?>
                        <hr>
                        <h4>Debug Information:</h4>
                        <pre><?php print_r($debug_info); ?></pre>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($results)): ?>
                <div class="alert alert-info">
                    No users found matching your search criteria: "<?php echo htmlspecialchars($search_term); ?>"
                    <?php if ($role_filter !== 'all'): ?>
                        with role "<?php echo htmlspecialchars($role_filter); ?>"
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($results as $user): ?>
                    <div class="col-md-3 mb-4">
                        <div class="user-card">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </div>
                            <div class="user-role"><?php echo strtoupper($user['role']); ?></div>
                            
                            <div class="action-buttons">
                                <a href="<?php echo $user['role'] === 'athlete' ? 'athlete_profile.php' : 'profile.php'; ?>?id=<?php echo $user['id']; ?>" class="action-btn">
                                    VIEW PROFILE
                                </a>
                                
                                <?php if ($user['role'] === 'athlete' && $user['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="toggleFollow(<?php echo $user['id']; ?>)" 
                                            class="action-btn <?php echo $user['is_following'] ? 'following' : ''; ?>">
                                        <?php echo $user['is_following'] ? 'UNFOLLOW' : 'FOLLOW'; ?>
                                    </button>
                                    <button onclick="collectAthlete(<?php echo $user['id']; ?>, event)" 
                                            class="action-btn <?php echo $user['is_collected'] ? 'collected' : ''; ?>">
                                        <?php echo $user['is_collected'] ? 'UNCOLLECT' : 'COLLECT'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($featured_athletes)): ?>
        <div class="featured-section">
            <h2 class="section-title">Featured Athletes</h2>
            <div class="row">
                <?php foreach ($featured_athletes as $athlete): ?>
                <div class="col-md-3 mb-4">
                    <div class="user-card">
                        <div class="user-name">
                            <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                        </div>
                        <div class="user-role"><?php echo strtoupper($athlete['role']); ?></div>
                        
                        <div class="action-buttons">
                            <a href="<?php echo $athlete['role'] === 'athlete' ? 'athlete_profile.php' : 'profile.php'; ?>?id=<?php echo $athlete['id']; ?>" class="action-btn">
                                VIEW PROFILE
                            </a>
                            
                            <?php if ($athlete['role'] === 'athlete' && $athlete['id'] != $user_id): ?>
                                <button onclick="toggleFollow(<?php echo $athlete['id']; ?>)" 
                                        class="action-btn <?php echo isset($athlete['is_following']) && $athlete['is_following'] ? 'following' : ''; ?>">
                                    <?php echo isset($athlete['is_following']) && $athlete['is_following'] ? 'UNFOLLOW' : 'FOLLOW'; ?>
                                </button>
                                <button onclick="collectAthlete(<?php echo $athlete['id']; ?>, event)" 
                                        class="action-btn <?php echo isset($athlete['is_collected']) && $athlete['is_collected'] ? 'collected' : ''; ?>">
                                    <?php echo isset($athlete['is_collected']) && $athlete['is_collected'] ? 'UNCOLLECT' : 'COLLECT'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function collectAthlete(athleteId, event) {
            event.preventDefault();
            const button = event.currentTarget;
            
            fetch('ajax/collect_athlete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ athlete_id: athleteId })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to collect athlete');
                }
                
                if (data.status === 'collected') {
                    button.classList.add('collected');
                    button.textContent = 'UNCOLLECT';
                } else if (data.status === 'uncollected') {
                    button.classList.remove('collected');
                    button.textContent = 'COLLECT';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message);
            });
        }

        function toggleFollow(athleteId) {
            const formData = new FormData();
            formData.append('athlete_id', athleteId);

            fetch('ajax/toggle_follow.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                const button = event.target;
                if (data.status === 'following') {
                    button.classList.add('following');
                    button.textContent = 'UNFOLLOW';
                } else {
                    button.classList.remove('following');
                    button.textContent = 'FOLLOW';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Followed/Unfollowed');
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
