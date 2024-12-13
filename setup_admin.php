<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

// Admin credentials
$email = 'admin@spywalker.com';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Generated hash for password: " . $hashedPassword . "\n";

// First, check if admin exists
$checkSql = "SELECT id FROM users WHERE email = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $updateSql = "UPDATE users SET password = ?, role = 'admin', first_name = 'Admin', last_name = 'User' WHERE email = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ss", $hashedPassword, $email);
    
    if ($updateStmt->execute()) {
        echo "Admin account updated successfully!\n";
    } else {
        echo "Error updating admin account: " . $conn->error . "\n";
    }
} else {
    // Create new admin
    $insertSql = "INSERT INTO users (email, password, role, first_name, last_name) VALUES (?, ?, 'admin', 'Admin', 'User')";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("ss", $email, $hashedPassword);
    
    if ($insertStmt->execute()) {
        echo "Admin account created successfully!\n";
    } else {
        echo "Error creating admin account: " . $conn->error . "\n";
    }
}

// Verify the account exists and password works
$verifySql = "SELECT * FROM users WHERE email = ?";
$verifyStmt = $conn->prepare($verifySql);
$verifyStmt->bind_param("s", $email);
$verifyStmt->execute();
$user = $verifyStmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    echo "Verification successful! You can now log in with:\n";
    echo "Email: " . $email . "\n";
    echo "Password: " . $password . "\n";
} else {
    echo "Verification failed! Something went wrong.\n";
}
?>
