<?php
require_once 'config/db.php';

try {
    // Check if admin already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute(['admin@spywalker.com']);
    
    if ($check->fetch()) {
        echo "Admin user already exists!";
        exit;
    }
    
    // Create admin user with hashed password
    $password = 'admin';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, role, first_name, last_name) 
        VALUES (?, ?, 'admin', 'Admin', 'User')
    ");
    
    $stmt->execute(['admin@spywalker.com', $hashed_password]);
    
    echo "Admin user created successfully!\n";
    echo "Email: admin@spywalker.com\n";
    echo "Password: admin";
    
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
}
?>
