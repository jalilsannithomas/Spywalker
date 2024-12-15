<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';
require_once 'config/config.php';
require_once 'controllers/AuthController.php';
require_once 'middleware/Auth.php';

// Use config.php's base_url instead of hardcoding
$base_path = $base_url;

// Create AuthController instance
$auth = new AuthController($conn);

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$path = str_replace($base_path, '', $request);
$path = explode('?', $path)[0];

// Router
switch ($path) {
    case '':
    case '/':
        if (isset($_SESSION['user_id'])) {
            header("Location: " . $base_path . "dashboard.php");
        } else {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>SpyWalker - Ashesi Sports Platform</title>
                <link href="https://fonts.googleapis.com/css2?family=Graduate&family=Inter:wght@400;600&display=swap" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="assets/css/style.css" rel="stylesheet">
                <style>
                    .hero-section {
                        padding: 4rem 0;
                        text-align: center;
                        position: relative;
                    }
                    
                    .feature-card {
                        background: var(--vintage-cream);
                        border: 2px solid var(--vintage-brown);
                        border-radius: 8px;
                        padding: 2rem;
                        margin-bottom: 2rem;
                        text-align: center;
                        transition: transform 0.3s ease;
                    }
                    
                    .feature-card:hover {
                        transform: translateY(-5px);
                    }
                    
                    .feature-icon {
                        font-size: 2.5rem;
                        color: var(--vintage-navy);
                        margin-bottom: 1rem;
                    }
                    
                    .welcome-banner {
                        background: var(--vintage-cream);
                        border: 2px solid var(--vintage-gold);
                        border-radius: 8px;
                        padding: 2rem;
                        margin-bottom: 2rem;
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <div class="container mt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="hero-section">
                                <h1 class="vintage-title mb-3">SpyWalker</h1>
                                <h2 class="vintage-subtitle mb-4">Ashesi University Sports Network</h2>
                                
                                <?php if (isset($_SESSION['user_id'])) { ?>
                                    <div class="welcome-banner">
                                        <h3 class="mb-3">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
                                        <p class="vintage-text mb-4">You're signed in as: <?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></p>
                                        <a href="logout.php" class="btn btn-primary vintage-button">Sign Out</a>
                                    </div>
                                <?php } else { ?>
                                    <p class="vintage-text mb-5">Connect with Ashesi University athletes, coaches, and sports enthusiasts.</p>
                                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center mb-5">
                                        <a href="register.php" class="btn btn-primary vintage-button px-4 py-3">Join the Team</a>
                                        <a href="login.php" class="btn btn-outline-primary vintage-button px-4 py-3">Sign In</a>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="row mt-5">
                                <div class="col-md-4">
                                    <div class="feature-card">
                                        <div class="feature-icon">🏃</div>
                                        <h3 class="vintage-subtitle">Athletes</h3>
                                        <p class="vintage-text">Create your profile, showcase your achievements, and connect with coaches.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="feature-card">
                                        <div class="feature-icon">👨‍🏫</div>
                                        <h3 class="vintage-subtitle">Coaches</h3>
                                        <p class="vintage-text">Manage your teams, scout talent, and communicate with athletes.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="feature-card">
                                        <div class="feature-icon">🎯</div>
                                        <h3 class="vintage-subtitle">Fans</h3>
                                        <p class="vintage-text">Follow your favorite athletes and stay updated with sports events.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
        }
        exit();
        break;
        
    case 'login':
        require __DIR__ . '/views/pages/login.php';
        break;
        
    case 'register':
        require __DIR__ . '/views/pages/register.php';
        break;
        
    case 'auth/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new AuthController();
            $result = $auth->register($_POST);
            header('Content-Type: application/json');
            echo json_encode($result);
        }
        break;
        
    case 'auth/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new AuthController();
            $result = $auth->login($_POST['email'], $_POST['password']);
            header('Content-Type: application/json');
            echo json_encode($result);
        }
        break;
        
    case 'auth/logout':
        $auth->logout();
        break;
        
    case 'logout':
        $auth->logout();
        break;
        
    default:
        if (isset($_SESSION['user_id'])) {
            header("Location: " . $base_path . "dashboard.php");
        } else {
            header("Location: " . $base_path . "login.php");
        }
        exit();
        break;
}
?>