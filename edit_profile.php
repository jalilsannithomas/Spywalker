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
$role = $_SESSION['role'];
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/profile_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.');
            }

            $new_filename = uniqid('profile_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Update profile image in database
                $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_filename, $user_id);
                $stmt->execute();
            } else {
                throw new Exception('Failed to upload image.');
            }
        }

        // Update user table
        $bio = mysqli_real_escape_string($conn, $_POST['bio'] ?? '');
        $sql = "UPDATE users SET bio = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $bio, $user_id);
        $stmt->execute();

        // Update role-specific profile
        if ($role === 'athlete') {
            $height_feet = isset($_POST['height_feet']) ? (int)$_POST['height_feet'] : 0;
            $height_inches = isset($_POST['height_inches']) ? (int)$_POST['height_inches'] : 0;
            $height = ($height_feet * 12) + $height_inches;
            
            $sql = "UPDATE athlete_profiles SET 
                    first_name = ?,
                    last_name = ?,
                    height = ?,
                    weight = ?,
                    position_id = ?,
                    jersey_number = ?,
                    years_experience = ?,
                    school_year = ?
                    WHERE user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiiissi", 
                $_POST['first_name'],
                $_POST['last_name'],
                $height,
                $_POST['weight'],
                $_POST['position_id'],
                $_POST['jersey_number'],
                $_POST['years_experience'],
                $_POST['school_year'],
                $user_id
            );
            $stmt->execute();
        } elseif ($role === 'coach') {
            $sql = "UPDATE coach_profiles SET 
                    first_name = ?,
                    last_name = ?,
                    specialization = ?,
                    years_experience = ?,
                    certification = ?,
                    education = ?
                    WHERE user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssissi", 
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['specialization'],
                $_POST['years_experience'],
                $_POST['certification'],
                $_POST['education'],
                $user_id
            );
            $stmt->execute();
        }

        $success_message = "Profile updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Get user data
$sql = "SELECT u.*, 
        CASE 
            WHEN u.role = 'athlete' THEN ap.first_name
            WHEN u.role = 'coach' THEN cp.first_name
        END as first_name,
        CASE 
            WHEN u.role = 'athlete' THEN ap.last_name
            WHEN u.role = 'coach' THEN cp.last_name
        END as last_name,
        ap.height, ap.weight, ap.position_id, ap.jersey_number, 
        ap.years_experience as athlete_experience, ap.school_year,
        cp.specialization, cp.years_experience as coach_experience, 
        cp.certification, cp.education,
        s.id as sport_id, s.name as sport_name,
        p.id as position_id, p.name as position_name
        FROM users u
        LEFT JOIN athlete_profiles ap ON u.id = ap.user_id AND u.role = 'athlete'
        LEFT JOIN coach_profiles cp ON u.id = cp.user_id AND u.role = 'coach'
        LEFT JOIN sports s ON (u.role = 'athlete' AND ap.sport_id = s.id) OR (u.role = 'coach' AND cp.sport_id = s.id)
        LEFT JOIN positions p ON ap.position_id = p.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get all sports for dropdown
$sports_sql = "SELECT id, name FROM sports ORDER BY name";
$sports_result = $conn->query($sports_sql);

// Get positions for current sport
$positions_sql = "SELECT id, name FROM positions WHERE sport_id = ? ORDER BY name";
$positions_stmt = $conn->prepare($positions_sql);
$positions_stmt->bind_param("i", $user['sport_id']);
$positions_stmt->execute();
$positions_result = $positions_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/edit-profile.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            min-height: 100vh;
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
        
        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <div class="row">
                <div class="col-md-4">
                    <div class="profile-upload-container">
                        <img id="profilePreview" 
                             src="<?php echo !empty($user['profile_image']) ? 'uploads/profile_images/' . htmlspecialchars($user['profile_image']) : 'assets/images/default-profile.jpg'; ?>" 
                             alt="Profile Preview" 
                             class="img-fluid">
                        <label for="profileImage" class="custom-file-upload">
                            <i class="bi bi-camera"></i> Change Photo
                        </label>
                        <input type="file" 
                               id="profileImage" 
                               name="profile_image" 
                               accept="image/*" 
                               style="display: none;">
                    </div>
                </div>

                <div class="col-md-8">
                    <h3 class="vintage-subtitle mb-3">Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($role === 'athlete'): ?>
                        <h3 class="vintage-subtitle mb-3 mt-4">Athlete Details</h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sport</label>
                                <select name="sport_id" class="form-control" required>
                                    <?php while ($sport = $sports_result->fetch_assoc()): ?>
                                        <option value="<?php echo $sport['id']; ?>" 
                                            <?php echo ($sport['id'] == $user['sport_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sport['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <select name="position_id" class="form-control" required>
                                    <?php while ($position = $positions_result->fetch_assoc()): ?>
                                        <option value="<?php echo $position['id']; ?>" 
                                            <?php echo ($position['id'] == $user['position_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($position['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jersey Number</label>
                                <input type="number" name="jersey_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['jersey_number'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Years Experience</label>
                                <input type="number" name="years_experience" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['athlete_experience'] ?? '0'); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Height (ft)</label>
                                <input type="number" name="height_feet" class="form-control" 
                                       value="<?php echo floor(($user['height'] ?? 0) / 12); ?>">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Height (in)</label>
                                <input type="number" name="height_inches" class="form-control" 
                                       value="<?php echo ($user['height'] ?? 0) % 12; ?>">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Weight (lbs)</label>
                                <input type="number" name="weight" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['weight'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">School Year</label>
                            <select name="school_year" class="form-control" required>
                                <?php
                                $years = ['Freshman', 'Sophomore', 'Junior', 'Senior'];
                                foreach ($years as $year):
                                ?>
                                    <option value="<?php echo $year; ?>" 
                                        <?php echo ($year == $user['school_year']) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    <?php elseif ($role === 'coach'): ?>
                        <h3 class="vintage-subtitle mb-3 mt-4">Coach Details</h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($user['specialization'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="years_experience" class="form-label">Years Experience</label>
                                <input type="number" class="form-control" id="years_experience" name="years_experience" value="<?php echo htmlspecialchars($user['coach_experience'] ?? '0'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Certification</label>
                            <textarea name="certification" class="form-control" rows="2"><?php echo htmlspecialchars($user['certification'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Education</label>
                            <textarea name="education" class="form-control" rows="2"><?php echo htmlspecialchars($user['education'] ?? ''); ?></textarea>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary vintage-button">Save Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary vintage-button ms-2">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const profileImageInput = document.getElementById('profileImage');
        const profilePreview = document.getElementById('profilePreview');

        profileImageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                profilePreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    </script>
</body>
</html>
