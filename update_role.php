<?php
require_once 'config/db.php';

// Update user role
$user_id = $_SESSION['user_id']; // Your current user ID
$new_role = 'athlete';

$query = "UPDATE users SET role = ? WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$new_role, $user_id]);

echo "Role updated successfully. <a href='dashboard.php'>Return to Dashboard</a>";
?>
