<?php
require("../db-config/security.php");
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'], $data['student_id'], $data['status'])) {
        echo json_encode(["success" => false, "error" => "Invalid input"]);
        exit;
    }

    $id = (int)$data['id'];
    $studentId = (int)$data['student_id'];
    $status = (int)$data['status'];

    $stmt = $pdo->prepare("
        INSERT INTO requirements_status (req_id, student_id, is_checked)
        VALUES (:id, :student_id, :status)
        ON DUPLICATE KEY UPDATE is_checked = VALUES(is_checked)
    ");

    $stmt->execute([
        ":id" => $id,
        ":student_id" => $studentId,
        ":status" => $status
    ]);

    // rowCount() will return:
    // 1 → insert
    // 2 → update
    $action = $stmt->rowCount() > 1 ? "updated" : "inserted";

    echo json_encode([
        "success" => true,
        "action" => $action
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
