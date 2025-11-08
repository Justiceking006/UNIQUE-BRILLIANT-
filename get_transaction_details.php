<?php
// get_transaction_details.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction ID required']);
    exit;
}

$transaction_id = intval($_GET['id']);
$db = getDBConnection();

try {
    $stmt = $db->prepare("
        SELECT ft.*, s.first_name, s.last_name, s.student_code, s.class, s.section, s.level, s.department
        FROM fee_transactions ft 
        JOIN students s ON ft.student_id = s.id 
        WHERE ft.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode($transaction);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>