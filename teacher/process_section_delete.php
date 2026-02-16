<?php
require "db-config/security.php";
header('Content-Type: application/json');

try {
    // Start transaction
    $pdo->beginTransaction();
    
    $id = (int) $_POST['id'];
    
    // First, update teachers that reference this section to set section_id to NULL
    $updateTeachersSql = "UPDATE teachers SET section_id = NULL WHERE section_id = ?";
    $updateTeachersStmt = $pdo->prepare($updateTeachersSql);
    $updateTeachersStmt->execute([$id]);
    
    // Second, update students that reference this section to set section_id to NULL
    $updateStudentsSql = "UPDATE students SET section_id = NULL WHERE section_id = ?";
    $updateStudentsStmt = $pdo->prepare($updateStudentsSql);
    $updateStudentsStmt->execute([$id]);
    
    // Then, permanently delete the section
    $deleteSql = "DELETE FROM sections WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteSuccess = $deleteStmt->execute([$id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => $deleteSuccess]);
    
} catch (Exception $e) {
    // Rollback transaction if something went wrong
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to delete section: ' . $e->getMessage()
    ]);
}
?>