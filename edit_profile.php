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

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$error_message = '';
$success_message = '';
$user = null;

// If admin is editing another user
if ($role === 'admin' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
}

// Debug output
error_log("Debug: Starting edit_profile.php");
error_log("Debug: User ID = " . $user_id);
error_log("Debug: Role = " . $role);

// Test database connection
try {
    $test_query = $conn->query("SELECT 1");
    error_log("Debug: Database connection test successful");
} catch (PDOException $e) {
    error_log("Debug: Database connection test failed: " . $e->getMessage());
    error_log("Debug: SQL State: " . $e->getCode());
    $error_message = "Database connection error. Please try again later.";
}

// Get user data
if (empty($error_message)) {
    try {
        // First, verify the user exists
        $check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check_user->execute([$user_id]);
        
        if (!$check_user->fetch()) {
            throw new Exception("User ID not found in database");
        }

        // Now get the full user data
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.profile_image,
                COALESCE(apm.base_rating, 0) as base_rating,
                COALESCE(apm.achievement_points, 0) as achievement_points,
                COALESCE(apm.games_played, 0) as games_played
                FROM users u
                LEFT JOIN athlete_performance_metrics apm ON u.id = apm.athlete_id
                WHERE u.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Failed to retrieve user data");
        }
    } catch (Exception $e) {
        error_log("Error in edit_profile.php: " . $e->getMessage());
        $error_message = "Error loading profile: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    try {
        $conn->beginTransaction();
        
        // Handle password change
        if (!empty($_POST['new_password'])) {
            if ($role !== 'admin') {
                // Regular users need to provide current password
                if (empty($_POST['current_password'])) {
                    throw new Exception("Current password is required");
                }
                
                // Verify current password
                $verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $verify->execute([$user_id]);
                $current = $verify->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($_POST['current_password'], $current['password'])) {
                    throw new Exception("Current password is incorrect");
                }
            }
            
            // Update password
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_password->execute([$hashed_password, $user_id]);
        }

        // Update basic user information
        $update_user = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?
            WHERE id = ?
        ");
        
        $update_user->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $user_id
        ]);

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed));
            }
            
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/profile_images/' . $new_filename;
            
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload profile image");
            }
            
            // Update profile image in database
            $update_image = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $update_image->execute([$new_filename, $user_id]);
        }

        // Update athlete metrics if applicable
        if ($user['role'] === 'athlete') {
            $update_metrics = $conn->prepare("
                INSERT INTO athlete_performance_metrics 
                (athlete_id, base_rating, achievement_points, games_played)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                base_rating = VALUES(base_rating),
                achievement_points = VALUES(achievement_points),
                games_played = VALUES(games_played)
            ");
            
            $update_metrics->execute([
                $user_id,
                $_POST['base_rating'] ?? 0,
                $_POST['achievement_points'] ?? 0,
                $_POST['games_played'] ?? 0
            ]);
        }

        $conn->commit();
        $success_message = "Profile updated successfully";
        
        // Refresh user data
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error updating profile: " . $e->getMessage());
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .profile-upload-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
        }

        .profile-upload-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 4px solid #D4AF37;
            border-radius: 50%;
        }

        .profile-upload-container .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(36, 20, 9, 0.8);
            padding: 10px;
            text-align: center;
            border-bottom-left-radius: 50%;
            border-bottom-right-radius: 50%;
            cursor: pointer;
        }

        .profile-upload-container .upload-overlay:hover {
            background: rgba(36, 20, 9, 0.9);
        }

        .profile-upload-container input[type="file"] {
            display: none;
        }

        .edit-form {
            background: rgba(36, 20, 9, 0.9);
            border: 4px solid #D4AF37;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
        }

        .form-control, .form-select {
            background: rgba(36, 20, 9, 0.8);
            border: 2px solid #D4AF37;
            color: #D4AF37;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(36, 20, 9, 0.9);
            border-color: #FFD700;
            color: #FFD700;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }

        .vintage-title {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            text-shadow: 2px 2px #000;
            margin-bottom: 30px;
        }

        .vintage-subtitle {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 1.2em;
            margin-bottom: 20px;
        }

        .btn-update {
            background: #D4AF37;
            border: none;
            color: #241409;
            padding: 10px 20px;
            font-family: 'Press Start 2P', monospace;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-update:hover {
            background: #FFD700;
            transform: translateY(-2px);
        }

        .form-label {
            color: #D4AF37;
            font-family: 'Press Start 2P', monospace;
            font-size: 0.8em;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>
    
    <div class="container">
        <h1 class="vintage-title text-center my-4">Edit Profile</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <div class="row">
                <div class="col-md-4">
                    <div class="profile-upload-container">
                        <img id="profilePreview" 
                             src="<?php echo !empty($user['profile_image']) ? 'uploads/profile_images/' . htmlspecialchars($user['profile_image']) : 'assets/images/default-profile.jpg'; ?>" 
                             alt="Profile Image">
                        <div class="upload-overlay">
                            <label for="profileImage" style="color: #D4AF37; margin: 0; cursor: pointer;">
                                Change Photo
                            </label>
                            <input type="file" id="profileImage" name="profile_image" accept="image/*">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <h3 class="vintage-subtitle mb-3">Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>

                    <?php if ($role === 'admin'): ?>
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select User to Edit</label>
                            <select class="form-control" id="user_id" name="user_id">
                                <?php
                                $users_query = $conn->query("SELECT id, email, first_name, last_name FROM users WHERE id != " . $_SESSION['user_id']);
                                while ($u = $users_query->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($u['id'] == ($user_id ?? '')) ? 'selected' : '';
                                    echo "<option value='" . $u['id'] . "' " . $selected . ">" . 
                                         htmlspecialchars($u['email'] . ' (' . $u['first_name'] . ' ' . $u['last_name'] . ')') . 
                                         "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'athlete'): ?>
                        <h3 class="vintage-subtitle mb-3 mt-4">Athlete Details</h3>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="baseRating" class="form-label">Base Rating</label>
                                <input type="number" step="0.01" min="0" max="1" class="form-control" id="baseRating" 
                                       name="base_rating" value="<?php echo htmlspecialchars($user['base_rating'] ?? 0); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="achievementPoints" class="form-label">Achievement Points</label>
                                <input type="number" min="0" class="form-control" id="achievementPoints" 
                                       name="achievement_points" value="<?php echo htmlspecialchars($user['achievement_points'] ?? 0); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="gamesPlayed" class="form-label">Games Played</label>
                                <input type="number" min="0" class="form-control" id="gamesPlayed" 
                                       name="games_played" value="<?php echo htmlspecialchars($user['games_played'] ?? 0); ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <h3 class="vintage-subtitle mb-3 mt-4">Change Password</h3>
                    <div class="row">
                        <?php if ($role === 'admin'): ?>
                            <div class="col-md-12 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="form-text text-muted">Leave blank to keep current password</small>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-update">Update Profile</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const profileImageInput = document.getElementById('profileImage');
        const profilePreview = document.getElementById('profilePreview');

        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
