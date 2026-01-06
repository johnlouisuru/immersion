<?php
require 'db-config/security.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$student_id = $_SESSION['user_id'];
$teacher_id = intval($_GET['teacher_id'] ?? 0);

if ($teacher_id === 0) {
    echo json_encode([]);
    exit;
}

// Fetch last 50 messages between student and teacher
$stmt = $pdo->prepare("
            SELECT 
            m.*,
            t.lastname AS teacher_name
        FROM messages m
        JOIN teachers t ON t.id = m.teacher_id
        WHERE m.student_id = ? 
        AND m.teacher_id = ?
        ORDER BY m.sent_at ASC
");
$stmt->execute([$student_id, $teacher_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);
