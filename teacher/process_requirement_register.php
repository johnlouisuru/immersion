<?php
require("db-config/security.php"); // your PDO connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
$is_active = 1;

if(isset($_POST['is_active'])){
    $is_active = 1;
} else {
    $is_active = 0;
}
    if(!isset($_POST['requirement_name']) || empty($_POST['requirement_name'])){
        $_SESSION['message'] = "❌ Section Name must not be Empty.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    $requirement_name = trim($_POST['requirement_name']);

    // Basic validation

    if (empty($requirement_name)) {
        $_SESSION['message'] = "❌ Section Name must not be Empty.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {

        // ✅ Insert new student
        $stmt = $pdo->prepare("
            INSERT INTO requirements (req_name, is_active)
            VALUES (:req_name, :is_active)
        ");
        $stmt->execute([
            ":req_name" => $requirement_name,
            ":is_active" => $is_active
        ]);

        $_SESSION['message'] = "✅ Requirement created successfully!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;

    } catch (PDOException $e) {
         $_SESSION['message'] = "❌ Database Error: " . $e->getMessage();
         header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
