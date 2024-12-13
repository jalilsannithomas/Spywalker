<?php
require_once 'config/db.php';

// Hash the correct password
$correct_password = password_hash('password', PASSWORD_DEFAULT);

// Update the admin password
$sql = "UPDATE users SET password = ? WHERE email = 'admin@spywalker.com'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correct_password);

if ($stmt->execute()) {
    echo "Admin password has been updated successfully. You can now login with:\n";
    echo "Email: admin@spywalker.com\n";
    echo "Password: password";
} else {
    echo "Error updating password: " . $conn->error;
}
?>
