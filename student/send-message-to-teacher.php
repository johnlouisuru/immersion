<?php
require 'db-config/security.php'; // PDO connection
// Redirect if not logged in
        if (!isLoggedIn()) {
            header('Location: index');
            exit;
        }

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

$student_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');
$teacher_id = intval($_POST['teacher_id'] ?? 0);

if ($message === '' || $teacher_id === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (to_teacher_message, teacher_id, student_id)
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
