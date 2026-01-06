<?php
require("db-config/security.php");
$_SESSION['section_message'] = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = intval($_POST['teacher_id']);
    $new_section_id = intval($_POST['section_id']);

    if ($teacher_id && $new_section_id) {

        // Step 1: Find the teacher's previous section (if any)
        $stmt_prev = $pdo->prepare("SELECT id FROM sections WHERE teacher_id = ?");
        $stmt_prev->execute([$teacher_id]);
        $previous_section = $stmt_prev->fetch(PDO::FETCH_ASSOC);

        // Step 2: If found, set previous section's teacher_id to NULL
        if ($previous_section) {
            $pdo->prepare("UPDATE sections SET teacher_id = NULL WHERE id = ?")
                ->execute([$previous_section['id']]);
        }

        // Step 3: Assign the new section to this teacher
        $stmt = $pdo->prepare("UPDATE sections SET teacher_id = ? WHERE id = ?");
        if ($stmt->execute([$teacher_id, $new_section_id])) {
            $_SESSION['section_message'] = 'Section successfully updated.';
            header("Location: register_teacher");
            exit;
        } else {
            echo "Error updating section assignment.";
        }

    } else {
        echo "Invalid data.";
    }
}
?>
