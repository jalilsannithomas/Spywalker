<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

echo "<pre>";

// Check if admin user already exists by either username or email
$check_sql = "SELECT * FROM users WHERE email = 'admin@spywalker.com' OR username = 'admin'";
$result = $conn->query($check_sql);
if ($result->num_rows > 0) {
    echo "Existing admin user found. Deleting...\n";
    $delete_sql = "DELETE FROM users WHERE email = 'admin@spywalker.com' OR username = 'admin'";
    $conn->query($delete_sql);
}

// Create hashed password
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Password hash: " . $hashed_password . "\n";

// First, modify the users table to include admin role
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL";
if ($conn->query($sql)) {
    echo "Table modified successfully\n";
} else {
    echo "Error modifying table: " . $conn->error . "\n";
}

// Generate a unique username with timestamp
$username = 'admin_' . time();

// Then create the admin user with properly hashed password
$sql = "INSERT INTO users (username, email, password, role) VALUES (?, 'admin@spywalker.com', ?, 'admin')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashed_password);

if ($stmt->execute()) {
    echo "Admin user created successfully!\n";
    
    // Verify the user was created
    $verify_sql = "SELECT * FROM users WHERE email = 'admin@spywalker.com'";
    $result = $conn->query($verify_sql);
    if ($user = $result->fetch_assoc()) {
        echo "\nVerification of created user:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "\nYou can now log in with:\n";
        echo "Email: admin@spywalker.com\n";
        echo "Password: admin123\n";
    } else {
        echo "Error: User verification failed\n";
    }
} else {
    echo "Error creating admin user: " . $stmt->error . "\n";
}

echo "</pre>";
?>
