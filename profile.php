<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get profile ID from URL, default to logged-in user
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];

// Get user data
$sql = "SELECT u.*,
        a.height_feet,
        a.height_inches,
        a.weight,
        a.jersey_number,
        p.name as position_name
        FROM users u
        LEFT JOIN athlete_profiles a ON u.id = a.user_id AND u.role = 'athlete'
        LEFT JOIN positions p ON a.position_id = p.id
        WHERE u.id = :profile_id";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':profile_id', $profile_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit();
}

// Function to convert inches to feet and inches
function heightToFeetInches($feet, $inches) {
    if (!$feet && !$inches) return "Not specified";
    return $feet . "'" . $inches . '"';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SpyWalker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid var(--border-color);
            padding: 20px;
            margin-top: 20px;
            image-rendering: pixelated;
            box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.2);
        }
        
        .profile-image {
            width: 128px;
            height: 128px;
            border: 4px solid var(--border-color);
            padding: 4px;
            background: var(--secondary-color);
            image-rendering: pixelated;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            font-family: 'Press Start 2P', cursive;
            color: var(--primary-color);
            font-size: 24px;
            text-shadow: 4px 4px 0 rgba(0, 0, 0, 0.3);
            margin: 20px 0;
            letter-spacing: 2px;
        }

        .profile-role {
            background: var(--primary-color);
            color: var(--secondary-color);
            padding: 8px 16px;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
            image-rendering: pixelated;
        }

        .stat-section {
            background: rgba(36, 20, 9, 0.8);
            border: 4px solid var(--border-color);
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
        }

        .stat-label {
            color: var(--primary-color);
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .stat-value {
            color: var(--text-color);
            font-family: 'Press Start 2P', cursive;
            font-size: 14px;
        }

        .vintage-subtitle {
            font-family: 'Press Start 2P', cursive;
            color: var(--primary-color);
            font-size: 16px;
            text-transform: uppercase;
            border-bottom: 4px solid var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 16px;
            text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="container">
        <div class="profile-container">
            <div class="text-center">
                <img src="<?php echo isset($user['profile_image']) && !empty($user['profile_image']) ? 'uploads/profile_images/' . htmlspecialchars($user['profile_image']) : 'images/default-profile.jpg'; ?>" 
                     alt="Profile Picture" class="profile-image">
                <h1 class="profile-name"><?php echo isset($user['first_name']) ? strtoupper($user['first_name'] . ' ' . $user['last_name']) : 'UNNAMED PLAYER'; ?></h1>
                <div class="profile-role"><?php echo isset($user['role']) ? ucfirst($user['role']) : 'Player'; ?></div>
            </div>
            <?php if (!empty($user['bio'])): ?>
                <p class="vintage-text mb-3"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            <?php endif; ?>
            <?php if ($profile_id === $_SESSION['user_id']): ?>
                <a href="edit_profile.php" class="vintage-button">EDIT PROFILE</a>
            <?php endif; ?>
            <div class="row">
                <?php if ($user['role'] === 'athlete'): ?>
                    <!-- Athlete Stats -->
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Physical Stats</h3>
                            <div class="mb-2">
                                <div class="stat-label">Height</div>
                                <div class="stat-value"><?php echo heightToFeetInches($user['height_feet'], $user['height_inches']); ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">Weight</div>
                                <div class="stat-value"><?php echo $user['weight'] ? $user['weight'] . ' lbs' : 'Not specified'; ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">Position</div>
                                <div class="stat-value"><?php echo $user['position_name'] ?? 'Not specified'; ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">Jersey Number</div>
                                <div class="stat-value">#<?php echo htmlspecialchars($user['jersey_number']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Experience</h3>
                            <div class="mb-2">
                                <div class="stat-label">Years Playing</div>
                                <div class="stat-value"><?php echo $user['years_experience']; ?> years</div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">School Year</div>
                                <div class="stat-value"><?php echo htmlspecialchars($user['school_year']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Achievements</h3>
                            <?php if (!empty($user['achievements'])): ?>
                                <ul class="achievements-list">
                                    <?php foreach (explode("\n", $user['achievements']) as $achievement): ?>
                                        <li><?php echo htmlspecialchars($achievement); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No achievements listed yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($user['role'] === 'coach'): ?>
                    <!-- Coach Stats -->
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Experience</h3>
                            <div class="mb-2">
                                <div class="stat-label">Years Coaching</div>
                                <div class="stat-value"><?php echo isset($user['years_experience']) ? $user['years_experience'] . ' years' : 'Not specified'; ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">Specialization</div>
                                <div class="stat-value"><?php echo isset($user['specialization']) ? htmlspecialchars($user['specialization']) : 'Not specified'; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Qualifications</h3>
                            <div class="mb-2">
                                <div class="stat-label">Education</div>
                                <div class="stat-value"><?php echo isset($user['education']) ? nl2br(htmlspecialchars($user['education'])) : 'Not specified'; ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="stat-label">Certifications</div>
                                <div class="stat-value"><?php echo isset($user['certification']) ? nl2br(htmlspecialchars($user['certification'])) : 'Not specified'; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Teams Coached</h3>
                            <?php if (isset($user['teams_coached']) && $user['teams_coached']): ?>
                                <div class="stat-value"><?php echo nl2br(htmlspecialchars($user['teams_coached'])); ?></div>
                            <?php else: ?>
                                <p class="vintage-text">No teams listed yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Fan Profile -->
                    <div class="col-md-12">
                        <div class="stat-section">
                            <h3 class="vintage-subtitle mb-3">Fan Profile</h3>
                            <p class="vintage-text">Supporting Ashesi University athletics!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
