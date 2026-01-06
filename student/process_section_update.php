<?php
require "db-config/security.php";
header('Content-Type: application/json');

$id = (int) $_POST['id'];
$section_name = trim($_POST['section_name']);
$teacher_id = isset($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : 0;
$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

try {
    // Start transaction
    $pdo->beginTransaction();

    // 1️⃣ Update sections table
    $sql = "
        UPDATE sections
        SET section_name = ?, teacher_id = ?, is_active = ?
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$section_name, $teacher_id ?: null, $is_active, $id]);

    // 2️⃣ Update teacher's section_id ONLY if teacher_id is valid (>0)
    if ($teacher_id > 0) {
        $stmtTeacher = $pdo->prepare("
            UPDATE teachers
            SET section_id = :section_id
            WHERE id = :teacher_id
        ");
        $stmtTeacher->execute([
            ':section_id' => $id,
            ':teacher_id' => $teacher_id
        ]);
    }

    // Commit transaction
    $pdo->commit();

    $_SESSION['message'] = '✅ Section updated successfully';
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['message'] = "❌ Database Error: " . $e->getMessage();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
