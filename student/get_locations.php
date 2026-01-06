<?php
header('Content-Type: application/json');
require "db-config/security.php"; // adjust your DB connection

try {
    $stmt = $pdo->prepare("SELECT id, 
                                  latitude, 
                                  longitude, 
                                  barangay, 
                                  municipality, 
                                  province, 
                                  image_path, 
                                  youtube_link, 
                                  is_login, 
                                  DATE_FORMAT(date_created, '%M %d, %Y %l:%i %p') AS date_formatted,
                                  video_path
                            FROM attendance_logs WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
