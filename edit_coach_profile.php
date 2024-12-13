<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/db.php';

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current coach profile
$query = "SELECT cp.*, u.first_name, u.last_name 
          FROM users u 
          LEFT JOIN coach_profiles cp ON u.id = cp.user_id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$coach = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $years_experience = $_POST['years_experience'];
    $certifications = $_POST['certifications'];
    $specialization = $_POST['specialization'];
    $achievements = $_POST['achievements'];
    $education = $_POST['education'];
    $coaching_philosophy = $_POST['coaching_philosophy'];

    // Check if profile exists
    $check_query = "SELECT id FROM coach_profiles WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;

    if ($exists) {
        // Update existing profile
        $update_query = "UPDATE coach_profiles 
                        SET years_experience = ?, 
                            certifications = ?,
                            specialization = ?,
                            achievements = ?,
                            education = ?,
                            coaching_philosophy = ?
                        WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("isssssi", 
            $years_experience, 
            $certifications,
            $specialization,
            $achievements,
            $education,
            $coaching_philosophy,
            $user_id
        );
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    } else {
        // Insert new profile
        $insert_query = "INSERT INTO coach_profiles 
                        (user_id, years_experience, certifications, specialization, 
                         achievements, education, coaching_philosophy)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iisssss", 
            $user_id,
            $years_experience, 
            $certifications,
            $specialization,
            $achievements,
            $education,
            $coaching_philosophy
        );
        
        if ($insert_stmt->execute()) {
            $success_message = "Profile created successfully!";
        } else {
            $error_message = "Error creating profile: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coach Profile - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Press Start 2P', cursive;
            background-color: #2C1810;
            color: #D4AF37;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-section {
            background: rgba(44, 24, 16, 0.9);
            border: 2px solid #D4AF37;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .section-title {
            color: #D4AF37;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-label {
            color: #D4AF37;
            margin-bottom: 10px;
            display: block;
        }

        .form-control {
            background: rgba(44, 24, 16, 0.8);
            border: 1px solid #D4AF37;
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            width: 100%;
            margin-bottom: 15px;
        }

        .form-control:focus {
            outline: none;
            border-color: #FFD700;
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn-primary {
            background-color: #D4AF37;
            border: none;
            color: #2C1810;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #FFD700;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: #28a745;
            color: #28a745;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: #dc3545;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php require_once 'components/navbar.php'; ?>

    <div class="container">
        <div class="form-section">
            <h2 class="section-title">Edit Coach Profile</h2>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="years_experience" class="form-label">Years of Experience</label>
                    <input type="number" class="form-control" id="years_experience" name="years_experience" 
                           value="<?php echo htmlspecialchars($coach['years_experience'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label for="certifications" class="form-label">Certifications</label>
                    <textarea class="form-control" id="certifications" name="certifications" 
                              rows="3"><?php echo htmlspecialchars($coach['certifications'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="specialization" class="form-label">Specialization</label>
                    <input type="text" class="form-control" id="specialization" name="specialization" 
                           value="<?php echo htmlspecialchars($coach['specialization'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label for="achievements" class="form-label">Achievements</label>
                    <textarea class="form-control" id="achievements" name="achievements" 
                              rows="3"><?php echo htmlspecialchars($coach['achievements'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="education" class="form-label">Education</label>
                    <textarea class="form-control" id="education" name="education" 
                              rows="3"><?php echo htmlspecialchars($coach['education'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="coaching_philosophy" class="form-label">Coaching Philosophy</label>
                    <textarea class="form-control" id="coaching_philosophy" name="coaching_philosophy" 
                              rows="5"><?php echo htmlspecialchars($coach['coaching_philosophy'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Profile</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
