<?php
require_once 'config/db.php';

// Get all users and their roles
$query = "SELECT id, username, email, role FROM users";
$stmt = $conn->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($users);
echo "</pre>";
?>
