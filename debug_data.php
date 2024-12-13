<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>Checking Database Tables</h2>";

// Check users table
$users_query = "SELECT * FROM users";
$users_result = mysqli_query($conn, $users_query);
echo "<h3>Users Table:</h3>";
echo "<pre>";
while ($row = mysqli_fetch_assoc($users_result)) {
    print_r($row);
}
echo "</pre>";

// Check athlete_profiles table
$profiles_query = "SELECT ap.*, s.name as sport_name, p.name as position_name 
                  FROM athlete_profiles ap
                  LEFT JOIN sports s ON ap.sport_id = s.id
                  LEFT JOIN positions p ON ap.position_id = p.id";
$profiles_result = mysqli_query($conn, $profiles_query);
echo "<h3>Athlete Profiles Table:</h3>";
echo "<pre>";
while ($row = mysqli_fetch_assoc($profiles_result)) {
    print_r($row);
}
echo "</pre>";

// Check sports table
$sports_query = "SELECT * FROM sports";
$sports_result = mysqli_query($conn, $sports_query);
echo "<h3>Sports Table:</h3>";
echo "<pre>";
while ($row = mysqli_fetch_assoc($sports_result)) {
    print_r($row);
}
echo "</pre>";

// Check positions table
$positions_query = "SELECT p.*, s.name as sport_name 
                   FROM positions p 
                   LEFT JOIN sports s ON p.sport_id = s.id";
$positions_result = mysqli_query($conn, $positions_query);
echo "<h3>Positions Table:</h3>";
echo "<pre>";
while ($row = mysqli_fetch_assoc($positions_result)) {
    print_r($row);
}
echo "</pre>";
