<?php
// get_student_fee_data.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([]);
    exit;
}

$student_id = intval($_GET['student_id'] ?? 0);
$term = sanitize($_GET['term'] ?? '');

if (!$student_id || !$term) {
    echo json_encode([]);
    exit;
}

$db = getDBConnection();

// Get total fee for the term
$fee_stmt = $db->prepare("
    SELECT total_fee FROM student_fees 
    WHERE student_id = ? AND term = ? AND status = 'active'
");
$fee_stmt->execute([$student_id, $term]);
$fee_data = $fee_stmt->fetch(PDO::FETCH_ASSOC);

// Get total paid amount for the term
$paid_stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_paid 
    FROM fee_transactions 
    WHERE student_id = ? AND term = ? AND transaction_type = 'payment' AND status = 'paid'
");
$paid_stmt->execute([$student_id, $term]);
$paid_data = $paid_stmt->fetch(PDO::FETCH_ASSOC);

$result = [
    'total_fee' => $fee_data ? floatval($fee_data['total_fee']) : null,
    'paid_amount' => floatval($paid_data['total_paid'])
];

header('Content-Type: application/json');
echo json_encode($result);
?>