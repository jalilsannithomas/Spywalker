<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error - SpyWalker</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            background-color: #3C2415;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            max-width: 600px;
            margin: 20px;
        }
        h1 {
            color: #ff6b6b;
            margin-bottom: 1.5rem;
        }
        .back-btn {
            background-color: #D4AF37;
            color: #2C1810;
            border: none;
            padding: 10px 20px;
            margin-top: 1.5rem;
            font-family: 'Press Start 2P', cursive;
            text-decoration: none;
            display: inline-block;
        }
        .back-btn:hover {
            background-color: #A67C00;
            color: #2C1810;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>ERROR ENCOUNTERED</h1>
        <p>An unexpected error occurred. Please try again later.</p>
        <?php if (isset($_GET['message'])): ?>
            <p class="error-details"><?php echo htmlspecialchars($_GET['message']); ?></p>
        <?php endif; ?>
        <a href="index.php" class="back-btn">RETURN HOME</a>
    </div>
</body>
</html>
