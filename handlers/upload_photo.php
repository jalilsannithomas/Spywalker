<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$response = ['success' => false, 'message' => '', 'file_path' => ''];

try {
    if (!isset($_FILES['profile_photo'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['profile_photo'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    // 5MB max file size
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Create unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_') . '.' . $ext;
    $upload_path = '../uploads/profile_photos/' . $filename;
    $db_path = 'uploads/profile_photos/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save file');
    }

    // Update database
    $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $db_path, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        // Remove uploaded file if database update fails
        unlink($upload_path);
        throw new Exception('Failed to update database');
    }

    $response['success'] = true;
    $response['message'] = 'Profile photo updated successfully';
    $response['file_path'] = $db_path;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
