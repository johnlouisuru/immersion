<?php
require("db-config/security.php");
$_SESSION['section_message'] = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $teacher_id = (int) $_POST['teacher_id'];
    $new_section_id = (int) $_POST['section_id'];

    if ($teacher_id && $new_section_id) {

        try {
            $pdo->beginTransaction();

            // Step 1: Remove teacher from previous section (if any)
            $stmt_prev = $pdo->prepare(
                "SELECT id FROM sections WHERE teacher_id = ?"
            );
            $stmt_prev->execute([$teacher_id]);
            $previous_section = $stmt_prev->fetch(PDO::FETCH_ASSOC);

            if ($previous_section) {
                $pdo->prepare(
                    "UPDATE sections SET teacher_id = NULL WHERE id = ?"
                )->execute([$previous_section['id']]);
            }

            // Step 2: Assign new section to teacher
            $pdo->prepare(
                "UPDATE sections SET teacher_id = ? WHERE id = ?"
            )->execute([$teacher_id, $new_section_id]);

            // Step 3: Update teacher record with new section
            $pdo->prepare(
                "UPDATE teachers SET section_id = ? WHERE id = ?"
                // ⬆️ change section_id to teacher_id if that is REALLY your column
            )->execute([$new_section_id, $teacher_id]);

            $pdo->commit();

            $_SESSION['section_message'] = 'Section successfully updated.';
            header("Location: register_teacher.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error updating section assignment.";
        }

    } else {
        echo "Invalid data.";
    }
}
?>
