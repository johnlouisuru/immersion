<?php
require("db-config/security.php");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST requests (Add and Edit)
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Add new allowed email
        $email = trim($_POST['email']);
        $is_allowed = isset($_POST['is_allowed']) ? (int)$_POST['is_allowed'] : 1;
        
        // Check if email already exists
        $check_query = "SELECT id FROM allowed_teachers WHERE allowed_email = :email";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([':email' => $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['allowed_message'] = "Email already exists in the allowed list!";
        } else {
            // Insert new record
            $insert_query = "INSERT INTO allowed_teachers (allowed_email, is_allowed, created_at) 
                            VALUES (:email, :is_allowed, NOW())";
            $insert_stmt = $pdo->prepare($insert_query);
            
            if ($insert_stmt->execute([':email' => $email, ':is_allowed' => $is_allowed])) {
                $_SESSION['allowed_message'] = "Email added successfully!";
            } else {
                $_SESSION['allowed_message'] = "Error adding email!";
            }
        }
        
        header('Location: register_teacher.php');
        exit;
        
    } elseif ($action === 'edit') {
        // Edit existing allowed email
        $id = (int)$_POST['id'];
        $email = trim($_POST['email']);
        $is_allowed = (int)$_POST['is_allowed'];
        
        // Check if email already exists for other records
        $check_query = "SELECT id FROM allowed_teachers WHERE allowed_email = :email AND id != :id";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([':email' => $email, ':id' => $id]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['allowed_message'] = "Email already exists in the allowed list!";
        } else {
            // Update record
            $update_query = "UPDATE allowed_teachers SET allowed_email = :email, is_allowed = :is_allowed WHERE id = :id";
            $update_stmt = $pdo->prepare($update_query);
            
            if ($update_stmt->execute([':email' => $email, ':is_allowed' => $is_allowed, ':id' => $id])) {
                $_SESSION['allowed_message'] = "Email updated successfully!";
            } else {
                $_SESSION['allowed_message'] = "Error updating email!";
            }
        }
        
        header('Location: register_teacher.php');
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests (Delete and Toggle Status)
    $action = $_GET['action'] ?? '';
    
    if ($action === 'delete') {
        // Delete record
        $id = (int)$_GET['id'];
        
        $delete_query = "DELETE FROM allowed_teachers WHERE id = :id";
        $delete_stmt = $pdo->prepare($delete_query);
        
        if ($delete_stmt->execute([':id' => $id])) {
            $_SESSION['allowed_message'] = "Email deleted successfully!";
        } else {
            $_SESSION['allowed_message'] = "Error deleting email!";
        }
        
        header('Location: register_teacher.php');
        exit;
    }
}

// If we get here, something went wrong
header('Location: register_teacher.php');
exit;
?>