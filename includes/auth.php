<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user has admin role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user has coach role
function isCoach() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'coach';
}

// Check if user has athlete role
function isAthlete() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'athlete';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /Spywalker/login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /Spywalker/dashboard.php");
        exit();
    }
}

// Redirect if not coach
function requireCoach() {
    requireLogin();
    if (!isCoach()) {
        header("Location: /Spywalker/dashboard.php");
        exit();
    }
}
