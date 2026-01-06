<?php
 require("db-config/security.php");
$_SESSION['section_message'] = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $section_id = intval($_POST['section_id']);

    if ($student_id && $section_id) {
        $stmt = $pdo->prepare("UPDATE students SET section_id = ? WHERE id = ?");
        if ($stmt->execute([$section_id, $student_id])) {
            $_SESSION['section_message'] = 'Section Sucessfully Updated.';
            header("Location: register");
            exit;
        } else {
            echo "Error updating section.";
        }
    } else {
        echo "Invalid data.";
    }
}
?>
