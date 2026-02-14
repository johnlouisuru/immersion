<?php
require("db-config/security.php");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

$id = (int)$_GET['id'];

// Get current status
$query = "SELECT is_allowed FROM allowed_teachers WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute([':id' => $id]);
$current = $stmt->fetch();

if ($current) {
    $new_status = $current['is_allowed'] == 1 ? 0 : 1;
    
    $update_query = "UPDATE allowed_teachers SET is_allowed = :is_allowed WHERE id = :id";
    $update_stmt = $pdo->prepare($update_query);
    
    if ($update_stmt->execute([':is_allowed' => $new_status, ':id' => $id])) {
        $_SESSION['allowed_message'] = "Status updated successfully!";
    } else {
        $_SESSION['allowed_message'] = "Error updating status!";
    }
}

header('Location: register_teacher.php');
exit;
?>