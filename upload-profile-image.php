<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$response = ['success' => false, 'error' => '', 'image_url' => ''];

try {
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['profile_image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/profile_photos';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    // Update database
    $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $filepath, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        // Remove uploaded file if database update fails
        unlink($filepath);
        throw new Exception('Failed to update database');
    }

    // Return success response
    $response['success'] = true;
    $response['image_url'] = $filepath;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
