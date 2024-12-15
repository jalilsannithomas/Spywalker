<?php
// Database configuration for InfinityFree
$db_host = 'sql213.infinityfree.com'; // InfinityFree database host
$db_name = 'if0_37912547_spywalker';  // InfinityFree database name
$db_user = 'if0_37912547';            // InfinityFree database username
$db_pass = 'bhzJLgtN2UkmN';           // InfinityFree database password
$db_port = '3306';                    // MySQL port

try {
    // Add error reporting before connection attempt
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Debug connection parameters
    error_log("Attempting database connection with:");
    error_log("Host: $db_host");
    error_log("Database: $db_name");
    error_log("User: $db_user");
    
    $conn = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5, // Add connection timeout
        ]
    );
    
    // Debug successful connection
    error_log("Database connection successful");
    
} catch(PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        header("Location: error.php?message=" . urlencode("Unable to connect to database server"));
    } else if (strpos($e->getMessage(), 'Access denied') !== false) {
        header("Location: error.php?message=" . urlencode("Invalid database credentials"));
    } else {
        header("Location: error.php?message=" . urlencode("Database connection error: " . $e->getMessage()));
    }
    exit();
}
?>