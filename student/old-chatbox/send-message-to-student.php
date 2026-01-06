<?php
require 'db-config/security.php'; // PDO connection

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$message = trim($_POST['message'] ?? '');
$student_id = intval($_POST['student_id'] ?? 0);

if ($message === '' || $teacher_id === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (to_student_message, teacher_id, student_id)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$message, $teacher_id, $student_id]);

    echo json_encode([
        'status' => 'success'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}
