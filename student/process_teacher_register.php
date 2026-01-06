<?php
require("db-config/security.php"); // your PDO connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $fullname = $lname . ' ' . $fname;
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $repassword = $_POST['repassword'];
    $is_active = 1;

    // Basic validation
    if ($password !== $repassword) {
        $_SESSION['message'] = "❌ Passwords do not match!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if (strlen($password) <= 5) {
        $_SESSION['message'] = "❌ Password must have at least 6 characters";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if (empty($fname) || empty($lname) || empty($email) || empty($password)) {
        $_SESSION['message'] = "❌ Please fill in all required fields.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        // ✅ Check if email already exists
        $check = $pdo->prepare("SELECT id FROM teachers WHERE email = :email LIMIT 1");
        $check->execute([":email" => $email]);

        if ($check->rowCount() > 0) {
             $_SESSION['message'] = "❌ This email is already registered. Please use another one.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }

        // ✅ Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // ✅ Insert new student
        $stmt = $pdo->prepare("
            INSERT INTO teachers (fullname, email, password, is_active)
            VALUES (:fullname, :email, :password, :is_active)
        ");
        $stmt->execute([
            ":fullname" => $fullname,
            ":email" => $email,
            ":password" => $hashed_password,
            ":is_active" => $is_active
        ]);

        $_SESSION['message'] = "✅ Account created successfully!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;

    } catch (PDOException $e) {
         $_SESSION['message'] = "❌ Database Error: " . $e->getMessage();
         header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
