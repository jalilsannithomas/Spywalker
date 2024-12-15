<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['sport_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Sport ID is required']);
    exit();
}

$sport_id = (int)$_GET['sport_id'];

try {
    $query = "SELECT id, name FROM positions WHERE sport_id = ? ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $sport_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($positions);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch positions']);
}
?>
