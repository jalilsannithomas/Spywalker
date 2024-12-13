<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

// First, make sure admin role exists in ENUM
$alterSql = "ALTER TABLE users MODIFY COLUMN role ENUM('athlete', 'coach', 'fan', 'admin') NOT NULL";
$conn->query($alterSql);

// Admin credentials
$username = 'admin';
$email = 'admin@spywalker.com';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Setting up admin account...\n";

// First, check if admin exists
$checkSql = "SELECT id FROM users WHERE email = ? OR username = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ss", $email, $username);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $updateSql = "UPDATE users SET 
                    username = ?,
                    email = ?,
                    password = ?,
                    role = 'admin',
                    bio = 'System Administrator',
                    is_active = TRUE
                  WHERE email = ? OR username = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssss", $username, $email, $hashedPassword, $email, $username);
    
    if ($updateStmt->execute()) {
        echo "Admin account updated successfully!\n";
    } else {
        echo "Error updating admin account: " . $conn->error . "\n";
    }
} else {
    // Create new admin
    $insertSql = "INSERT INTO users (username, email, password, role, bio, is_active) 
                  VALUES (?, ?, ?, 'admin', 'System Administrator', TRUE)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("sss", $username, $email, $hashedPassword);
    
    if ($insertStmt->execute()) {
        echo "Admin account created successfully!\n";
    } else {
        echo "Error creating admin account: " . $conn->error . "\n";
    }
}

// Verify the account exists and password works
$verifySql = "SELECT * FROM users WHERE email = ? OR username = ?";
$verifyStmt = $conn->prepare($verifySql);
$verifyStmt->bind_param("ss", $email, $username);
$verifyStmt->execute();
$user = $verifyStmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    echo "\nVerification successful! You can now log in with:\n";
    echo "Email: " . $email . "\n";
    echo "Password: " . $password . "\n";
    echo "Role: " . $user['role'] . "\n";
} else {
    echo "\nVerification failed! Something went wrong.\n";
}
?>
