<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config/db.php';

// Update db.php to use PDO
// $conn = new PDO('mysql:host=localhost;dbname=spywalker', 'username', 'password');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Debug information
    error_log("=== Login Attempt ===");
    error_log("Email: " . $email);
    
    try {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("User found in database: " . ($user ? "Yes" : "No"));
        
        if ($user) {
            error_log("Stored hashed password: " . $user['password']);
            error_log("User role: " . $user['role']);
            
            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['fantasy_team_name'] = $user['fantasy_team_name'] ?? 'My Fantasy Team';
                
                header("Location: dashboard.php");
                exit();
            } else {
                error_log("Password verification failed");
                $error = "Invalid email or password";
            }
        } else {
            error_log("No user found with email: " . $email);
            $error = "Invalid email or password";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "A system error occurred. Please try again later.";
    }
}

// For debugging: Display current database connection info
try {
    $test = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    error_log("Total users in database: " . $test);
} catch (PDOException $e) {
    error_log("Database connection test failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - SpyWalker</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #2C1810;
            font-family: 'Press Start 2P', cursive;
            color: #D4AF37;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .container {
            max-width: 500px;
        }

        .login-card {
            background-color: #FDFBF7;
            border: 4px solid #D4AF37;
            box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
            padding: 30px;
            image-rendering: pixelated;
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border: 2px solid #2C1810;
            pointer-events: none;
        }

        .vintage-title {
            color: #000080;
            font-size: 32px;
            text-align: center;
            margin-bottom: 10px;
            text-shadow: 2px 2px 0 #D4AF37;
            letter-spacing: 2px;
        }

        .vintage-subtitle {
            color: #8B0000;
            font-size: 16px;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-label {
            color: #2C1810;
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .form-control {
            background-color: #FDFBF7;
            border: 2px solid #D4AF37;
            border-radius: 0;
            padding: 12px;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
            color: #2C1810;
            margin-bottom: 20px;
        }

        .form-control:focus {
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
            border-color: #D4AF37;
            outline: none;
        }

        .btn-sign-in {
            background-color: #000080;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            font-size: 14px;
            padding: 12px;
            width: 100%;
            margin-top: 10px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.3);
        }

        .btn-sign-in:hover {
            transform: translateY(2px);
            box-shadow: 2px 2px 0 rgba(0, 0, 0, 0.3);
        }

        .forgot-password {
            text-align: center;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .forgot-password-btn {
            background: none;
            border: none;
            color: #000080;
            text-decoration: underline;
            cursor: pointer;
            font-family: 'Press Start 2P', cursive;
            font-size: 12px;
        }

        .forgot-password-btn:hover {
            color: #000066;
        }

        .admin-email {
            display: none;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 2px solid #D4AF37;
            color: #2C1810;
            font-size: 12px;
        }

        .alert {
            background-color: #2C1810;
            border: 2px solid #D4AF37;
            color: #D4AF37;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
            text-align: center;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.3);
        }

        .alert-success {
            background-color: #006400;
        }

        .alert-danger {
            background-color: #8B0000;
        }

        .join-text {
            color: #2C1810;
            font-size: 12px;
            text-align: center;
            margin-top: 20px;
        }

        .join-link {
            color: #000080;
            text-decoration: none;
            position: relative;
            padding: 0 4px;
        }

        .join-link:hover {
            color: #000066;
            text-decoration: none;
        }

        .join-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 100%;
            height: 2px;
            background-color: #D4AF37;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .join-link:hover::after {
            transform: scaleX(1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <h1 class="vintage-title">SPYWALKER</h1>
            <h2 class="vintage-subtitle">Player Login</h2>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); 
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">EMAIL</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">PASSWORD</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn-sign-in">SIGN IN</button>
            </form>
            
            <div class="forgot-password">
                <button type="button" class="forgot-password-btn" onclick="showAdminEmail()">Forgot Password?</button>
                <div id="adminEmailInfo" class="admin-email">
                    Please email admin@spywalker.com to reset your password
                </div>
            </div>

            <div class="join-text">
                New to the team? <a href="register.php" class="join-link">Join Now</a>
            </div>
        </div>
    </div>

    <script>
        function showAdminEmail() {
            const adminEmail = document.getElementById('adminEmailInfo');
            adminEmail.style.display = adminEmail.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
