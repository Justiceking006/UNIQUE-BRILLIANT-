<?php
// get_staff_details.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Staff ID required']);
    exit;
}

$staff_id = intval($_GET['id']);
$db = getDBConnection();

try {
    $stmt = $db->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        http_response_code(404);
        echo json_encode(['error' => 'Staff not found']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode($staff);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>