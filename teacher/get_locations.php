<?php
header('Content-Type: application/json');
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

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
                            FROM attendance_logs");
    $stmt->execute();
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
