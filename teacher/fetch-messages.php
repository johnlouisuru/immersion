<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isGoogleAuthenticated() || !isLoggedIn()) {
            // header('Location: complete-profile');
            // exit;
            die('Unauthorized!');
        }
header('Content-Type: application/json');

if (!isset($_GET['sid'])) {
    echo json_encode([]);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$student_id = intval($_GET['sid'] ?? 0);
// $messages = "SID : ".$student_id. " TID : ".$teacher_id;
// echo json_encode($messages);
// return;
if ($student_id === 0) {
    echo json_encode([]);
    exit;
}

// Fetch last 50 messages between student and teacher
$stmt = $pdo->prepare("
            SELECT 
            m.*,
            s.firstname AS student_name
        FROM messages m
        JOIN students s ON s.id = m.student_id
        WHERE m.student_id = ? 
        AND m.teacher_id = ?
        ORDER BY m.sent_at ASC
");
$stmt->execute([$student_id, $teacher_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);
