<?php
require("db-config/security.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $section_name = trim($_POST['section_name'] ?? '');
    $teacher_id  = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;

    // Validation
    if (empty($section_name)) {
        $_SESSION['message'] = "❌ Section Name must not be empty.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // ✅ Insert section
        $stmt = $pdo->prepare("
            INSERT INTO sections (section_name, teacher_id)
            VALUES (:section_name, :teacher_id)
        ");
        $stmt->execute([
            ":section_name" => $section_name,
            ":teacher_id"   => $teacher_id ?: null
        ]);

        // ✅ Get inserted section ID
        $section_id = $pdo->lastInsertId();

        // ✅ Update teacher ONLY if teacher_id is valid
        if (!empty($teacher_id) && $teacher_id > 0) {
            $stmtTeacher = $pdo->prepare("
                UPDATE teachers
                SET section_id = :section_id
                WHERE id = :teacher_id
            ");
            $stmtTeacher->execute([
                ":section_id" => $section_id,
                ":teacher_id" => $teacher_id
            ]);
        }

        $pdo->commit();

        $_SESSION['message'] = "✅ Section created successfully!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "❌ Database Error: " . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
