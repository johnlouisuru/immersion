<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}
header("Content-Type: application/json");

if (isset($_GET['section_id']) && isset($_GET['isLogin'])) {
    $section_id = (int) $_GET['section_id']; // sanitize input
    $isLogin = (int) $_GET['isLogin']; // sanitize input

    $sql = "SELECT a.latitude, a.longitude, a.video_path, a.date_created, a.is_login,
                   a.student_id, a.section_id,
                   s.firstname, s.lastname
            FROM attendance_logs AS a
            INNER JOIN students AS s ON a.student_id = s.id
            WHERE a.section_id = :section_id 
              AND a.is_login = :is_login
              AND DATE(a.date_created) = CURDATE()";

    $params = [":section_id" => $section_id, ":is_login" => $isLogin];
} else {
    $sql = "SELECT a.latitude, a.longitude, a.video_path, a.date_created, a.is_login,
                   a.student_id, a.section_id,
                   s.firstname, s.lastname
            FROM attendance_logs AS a
            INNER JOIN students AS s ON a.student_id = s.id
            WHERE DATE(a.date_created) = CURDATE()";

    $params = [];
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $locations]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
