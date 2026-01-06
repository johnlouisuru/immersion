<?php
session_start();
require("db-config/security.php"); // Your PDO connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get input safely
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $_SESSION['message'] = "Please enter both email and password.";
        header("Location: signin");
        exit;
    }

    try {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, email, password FROM students WHERE email = :email LIMIT 1");
        $stmt->execute([":email" => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // ✅ Password correct → start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['firstname'] . " " . $user['lastname'];
            $_SESSION['user_email'] = $user['email'];

            // (Optional) regenerate session ID for security
            session_regenerate_id(true);

            header("Location: dashboard.php"); // Redirect after login
            exit;
        } else {
            // ❌ Invalid credentials
            $_SESSION['message'] = "Invalid email or password.";
            header("Location: signin.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['message'] = "Something went wrong. Please try again later.";
        header("Location: signin.php");
        exit;
    }
} else {
    // Direct access without POST
    header("Location: signin.php");
    exit;
}
