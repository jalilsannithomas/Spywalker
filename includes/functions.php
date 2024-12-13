<?php
/**
 * Common utility functions for the Spywalker application
 */

/**
 * Sanitize and escape HTML output
 */
function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date to a readable string
 */
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

/**
 * Format time to a readable string
 */
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Get user's full name
 */
function getFullName($firstName, $lastName) {
    return trim($firstName . ' ' . $lastName);
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate a random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Check if a string is empty or null
 */
function isEmpty($str) {
    return empty(trim($str));
}

/**
 * Validate integer input
 */
function isValidInteger($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * Get current datetime in MySQL format
 */
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Set flash message in session
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message from session
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
