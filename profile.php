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
        CASE 
            WHEN u.role = 'athlete' THEN a.first_name
            WHEN u.role = 'coach' THEN c.first_name
            ELSE NULL
        END as first_name,
        CASE 
            WHEN u.role = 'athlete' THEN a.last_name
            WHEN u.role = 'coach' THEN c.last_name
            ELSE NULL
        END as last_name,
        a.height, a.weight, p.name as position, a.jersey_number, a.years_experience as athlete_experience,
        a.school_year, a.achievements,
        c.specialization, c.years_experience as coach_experience, c.certification,
        c.education
        FROM users u
        LEFT JOIN athlete_profiles a ON u.id = a.user_id AND u.role = 'athlete'
        LEFT JOIN coach_profiles c ON u.id = c.user_id AND u.role = 'coach'
        LEFT JOIN positions p ON a.position_id = p.id
        WHERE u.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header("Location: index.php");
    exit();
}

// Function to convert inches to feet and inches
function heightToFeetInches($inches) {
    if (!$inches) return "Not specified";
    $feet = floor($inches / 12);
    $remaining_inches = $inches % 12;
    return $feet . "'" . $remaining_inches . '"';
}

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($user['username']); ?>'s Profile - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .profile-header {
            background: var(--vintage-cream);
            border-bottom: 3px solid var(--vintage-brown);
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .profile-image {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 4px solid var(--vintage-gold);
            background: var(--vintage-cream);
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .stat-card {
            background: var(--vintage-cream);
            border: 2px solid var(--vintage-brown);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            color: var(--vintage-navy);
            font-family: 'Graduate', serif;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            color: var(--vintage-brown);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .achievements-list {
            list-style-type: none;
            padding-left: 0;
        }
        
        .achievements-list li {
            margin-bottom: 0.75rem;
            padding: 0.5rem 0.5rem 0.5rem 2rem;
            position: relative;
            background: rgba(255,255,255,0.5);
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        .achievements-list li:hover {
            background: rgba(255,255,255,0.8);
        }
        
        .achievements-list li:before {
            content: "🏆";
            position: absolute;
            left: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .vintage-button {
            background: var(--vintage-navy);
            color: var(--vintage-cream);
            border: 2px solid var(--vintage-gold);
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-family: 'Graduate', serif;
            transition: all 0.3s ease;
        }

        .vintage-button:hover {
            background: var(--vintage-gold);
            color: var(--vintage-navy);
            transform: translateY(-2px);
        }

        .vintage-title {
            font-family: 'Graduate', serif;
            color: var(--vintage-navy);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .vintage-subtitle {
            color: var(--vintage-brown);
            font-family: 'Graduate', serif;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="<?php echo !empty($user['profile_image']) ? 'uploads/profile_images/' . htmlspecialchars($user['profile_image']) : 'uploads/profile_images/default-profile.jpg'; ?>" 
                         alt="Profile" class="profile-image mb-3"
                         onerror="this.src='uploads/profile_images/default-profile.jpg';">
                </div>
                <div class="col-md-9">
                    <h1 class="vintage-title mb-2">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </h1>
                    <h2 class="vintage-subtitle mb-3">
                        <?php echo strtoupper(htmlspecialchars($user['role'])); ?>
                    </h2>
                    <?php if (!empty($user['bio'])): ?>
                        <p class="vintage-text mb-3"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>
                    <?php if ($profile_id === $_SESSION['user_id']): ?>
                        <a href="edit_profile.php" class="vintage-button">EDIT PROFILE</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row">
            <?php if ($user['role'] === 'athlete'): ?>
                <!-- Athlete Stats -->
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Physical Stats</h3>
                        <div class="mb-2">
                            <div class="stat-label">Height</div>
                            <div class="stat-value"><?php echo heightToFeetInches($user['height']); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">Weight</div>
                            <div class="stat-value"><?php echo $user['weight'] ? $user['weight'] . ' lbs' : 'Not specified'; ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">Position</div>
                            <div class="stat-value"><?php echo htmlspecialchars($user['position']); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">Jersey Number</div>
                            <div class="stat-value">#<?php echo htmlspecialchars($user['jersey_number']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Experience</h3>
                        <div class="mb-2">
                            <div class="stat-label">Years Playing</div>
                            <div class="stat-value"><?php echo $user['athlete_experience']; ?> years</div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">School Year</div>
                            <div class="stat-value"><?php echo htmlspecialchars($user['school_year']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
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
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Experience</h3>
                        <div class="mb-2">
                            <div class="stat-label">Years Coaching</div>
                            <div class="stat-value"><?php echo $user['coach_experience']; ?> years</div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">Specialization</div>
                            <div class="stat-value"><?php echo htmlspecialchars($user['specialization']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Qualifications</h3>
                        <div class="mb-2">
                            <div class="stat-label">Education</div>
                            <div class="stat-value"><?php echo nl2br(htmlspecialchars($user['education'])); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="stat-label">Certifications</div>
                            <div class="stat-value"><?php echo nl2br(htmlspecialchars($user['certification'])); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Teams Coached</h3>
                        <?php if ($user['teams_coached']): ?>
                            <div class="stat-value"><?php echo nl2br(htmlspecialchars($user['teams_coached'])); ?></div>
                        <?php else: ?>
                            <p class="vintage-text">No teams listed yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Fan Profile -->
                <div class="col-md-12">
                    <div class="stat-card">
                        <h3 class="vintage-subtitle mb-3">Fan Profile</h3>
                        <p class="vintage-text">Supporting Ashesi University athletics!</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
