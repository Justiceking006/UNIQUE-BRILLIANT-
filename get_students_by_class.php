<?php
// get_students_by_class.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['section']) || !isset($_GET['class'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Section and class required']);
    exit;
}

$section = sanitize($_GET['section']);
$class = sanitize($_GET['class']);
$db = getDBConnection();

try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, student_code, class, section, level, department
        FROM students 
        WHERE section = ? AND class = ? AND status = 'approved'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$section, $class]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($students);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>