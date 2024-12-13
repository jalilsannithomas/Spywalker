<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's roster count
$roster_sql = "SELECT fr.id, COUNT(fra.athlete_id) as athlete_count 
               FROM fantasy_rosters fr 
               LEFT JOIN fantasy_roster_athletes fra ON fr.id = fra.roster_id 
               WHERE fr.user_id = ?
               GROUP BY fr.id";
$stmt = $conn->prepare($roster_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$roster_result = $stmt->get_result();
$roster = $roster_result->fetch_assoc();

if ($roster && $roster['athlete_count'] >= 7) {
    header("Location: manage_roster.php?error=roster_full");
    exit();
}

// Get filter values
$sport_filter = isset($_GET['sport']) ? $_GET['sport'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT ap.*, s.name as sport_name, apm.base_rating, apm.achievement_points,
          (apm.base_rating * 100 + apm.achievement_points + (apm.games_played * 5)) as total_points,
          CASE WHEN fra.athlete_id IS NOT NULL THEN 1 ELSE 0 END as is_collected
          FROM athlete_profiles ap
          JOIN sports s ON ap.sport_id = s.id
          LEFT JOIN athlete_performance_metrics apm ON ap.id = apm.athlete_id
          LEFT JOIN fantasy_roster_athletes fra ON ap.id = fra.athlete_id 
          LEFT JOIN fantasy_rosters fr ON fra.roster_id = fr.id AND fr.user_id = ?
          WHERE 1=1";

$params = [$user_id];
$types = "i";

if ($sport_filter) {
    $query .= " AND ap.sport_id = ?";
    $params[] = $sport_filter;
    $types .= "i";
}

if ($search) {
    $query .= " AND (ap.first_name LIKE ? OR ap.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY total_points DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
$stmt->bind_param($types, ...$params);
}
$stmt->execute();
$athletes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all sports for filter
$sports_sql = "SELECT * FROM sports ORDER BY name";
$sports = $conn->query($sports_sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Browse Athletes - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #2C1810;
            color: #D4AF37;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            font-family: 'Press Start 2P', cursive;
            color: #00ff00;
            text-align: center;
            color: #D4AF37;
            text-shadow: 3px 3px #000;
            margin: 30px 0;
            font-size: 24px;
        }

        .filters {
            background-color: #3C2415;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 2px solid #D4AF37;
        }

        .filters select, .filters input {
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            padding: 8px;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            width: 100%;
            margin-bottom: 10px;
        }

        .athlete-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .athlete-card {
            background-color: #3C2415;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 15px;
            position: relative;
            transition: transform 0.3s ease;
        }

        .athlete-card:hover {
            transform: translateY(-5px);
        }

        .athlete-name {
            font-size: 14px;
            margin-bottom: 10px;
            color: #D4AF37;
        }

        .athlete-sport {
            font-size: 12px;
            color: #A67C00;
            margin-bottom: 15px;
        }

        .athlete-stats {
            font-size: 10px;
            color: #D4AF37;
            margin-top: 10px;
        }

        .collect-btn {
            background-color: #D4AF37;
            color: #2C1810;
            border: none;
            padding: 8px 15px;
            font-family: 'Press Start 2P', cursive;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }

        .collect-btn.collected {
            background-color: #666;
            cursor: not-allowed;
        }

        .collect-btn:hover {
            background-color: #A67C00;
            transform: translateY(-2px);
        }

        .error-message {
            color: #FF6B6B;
            margin-top: 10px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container">
        <h1>BROWSE ATHLETES</h1>
        
        <div class="filters">
            <div class="row">
                <div class="col-md-6">
                    <select id="sport-filter" onchange="updateFilters()">
                        <option value="">All Sports</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo $sport['id']; ?>" <?php echo $sport_filter == $sport['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sport['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" id="search-input" placeholder="Search athletes..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           onkeyup="if(event.key === 'Enter') updateFilters()">
                </div>
            </div>
        </div>

        <div class="athlete-grid">
            <?php foreach ($athletes as $athlete): ?>
                <div class="athlete-card">
                    <div class="athlete-name">
                        <?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?>
                    </div>
                    <div class="athlete-sport">
                        <?php echo htmlspecialchars($athlete['sport_name']); ?>
                    </div>
                    <div class="athlete-stats">
                        Rating: <?php echo number_format(($athlete['base_rating'] ?? 0) * 100); ?><br>
                        Achievement Points: <?php echo number_format($athlete['achievement_points'] ?? 0); ?>
                    </div>
                    <?php if ($athlete['is_collected']): ?>
                        <button class="collect-btn collected" disabled>
                            ALREADY COLLECTED
                        </button>
                    <?php else: ?>
                        <button class="collect-btn" onclick="collectAthlete(<?php echo $athlete['id']; ?>)">
                            COLLECT ATHLETE
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateFilters() {
            const sport = document.getElementById('sport-filter').value;
            const search = document.getElementById('search-input').value;
            window.location.href = `browse_athletes.php?sport=${sport}&search=${encodeURIComponent(search)}`;
        }

        function collectAthlete(athleteId) {
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
                    // Update button state and reload to show updated state
                    window.location.reload();
                } else {
                    console.error('Failed to collect athlete:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error collecting athlete. Please try again.');
            });
        }
    </script>
</body>
</html>
