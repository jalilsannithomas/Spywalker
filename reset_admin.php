<?php
require_once 'config/db.php';

try {
    $email = 'admin@spywalker.com';
    $password = 'admin';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // First try to update existing admin
    $stmt = $conn->prepare("
        UPDATE users 
        SET password = ?, 
            first_name = 'Admin',
            last_name = 'User',
            role = 'admin'
        WHERE email = ?
    ");
    
    $result = $stmt->execute([$hashed_password, $email]);
    
    if ($stmt->rowCount() === 0) {
        // If no update happened, create new admin
        $stmt = $conn->prepare("
            INSERT INTO users (email, password, role, first_name, last_name) 
            VALUES (?, ?, 'admin', 'Admin', 'User')
        ");
        $stmt->execute([$email, $hashed_password]);
        echo "New admin user created successfully!\n";
    } else {
        echo "Admin password reset successfully!\n";
    }
    
    echo "Email: admin@spywalker.com\n";
    echo "Password: admin\n";
    echo "Hashed password: " . $hashed_password . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
