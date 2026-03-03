<?php
require("db-config/security.php");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $company = $_POST['company'] ?? '';
    
    if (!empty($student_id)) {
        // Update the company field for the student
        $query = "UPDATE students SET company = :company WHERE id = :student_id";
        $params = [
            ':company' => $company,
            ':student_id' => $student_id
        ];
        
        $result = secure_query($pdo, $query, $params);
        
        if ($result) {
            $_SESSION['company_message'] = "Company updated successfully!";
        } else {
            $_SESSION['company_message'] = "Error updating company.";
        }
    }
    
    // Redirect back to the students page
    header('Location: assign_company'); // Replace with your actual page name
    exit;
}
?>