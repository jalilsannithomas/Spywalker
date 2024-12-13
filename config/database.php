<?php
// Database configuration
$servername = "localhost";
$username = "root";  // default XAMPP username
$password = "";      // default XAMPP password
$dbname = "spywalker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
