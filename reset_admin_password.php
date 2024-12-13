<?php
require_once 'config/db.php';

$admin_email = 'admin@spywalker.com';
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $admin_email);

if ($stmt->execute()) {
    echo "Admin password has been reset successfully!";
} else {
    echo "Error resetting password: " . $conn->error;
}
?>
