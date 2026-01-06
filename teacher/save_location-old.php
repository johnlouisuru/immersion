<?php
require("db-config/security.php");
header("Content-Type: application/json");
ini_set('display_errors', 0);   // hide raw PHP errors
error_reporting(E_ALL);

try {
    if (!isset($_POST['latitude'], $_POST['longitude'])) {
        echo json_encode(["success" => false, "message" => "Missing coordinates"]);
        exit;
    }

    $lat = $_POST['latitude'];
    $lon = $_POST['longitude'];
    $is_login = $_POST['is_login'];
    $studentId = $_SESSION['student_id'];
    $imagePath = null;
    $sectionId = 1;

    // Handle photo upload
    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath; // store relative path in DB
        } else {
            echo json_encode(["success" => false, "message" => "Image upload failed"]);
            exit;
        }
    }

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (latitude, longitude, image_path, is_login, student_id, section_id) 
        VALUES (:lat, :lon, :img, :is_login, :student_id, :section_id)
    ");
    $stmt->execute([
        ":lat" => $lat,
        ":lon" => $lon,
        ":img" => $imagePath,
        ":is_login" => $is_login,
        ":student_id" => $studentId,
        ":section_id" => $sectionId
    ]);

    echo json_encode(["success" => true, "message" => "Location + photo saved"]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
