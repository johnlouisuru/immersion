<?php
// welding_result.php

// Database connection parameters
$host = "localhost"; // or your DB host
$db   = "welding";
$user = "root";      // your DB username
$pass = "";          // your DB password
$charset = "utf8mb4";

// Set up DSN and PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: ".$e->getMessage()]);
    exit;
}

// Validate POST input
if(!isset($_POST['student_id'], $_POST['time_used'], $_POST['welding_level'])){
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters."]);
    exit;
}

$student_id = intval($_POST['student_id']);
$time_used = floatval($_POST['time_used']);
$welding_level = intval($_POST['welding_level']);

// Insert into database using prepared statement
try {
    $stmt = $pdo->prepare("INSERT INTO student_result (student_id, time_used, welding_level) VALUES (?, ?, ?)");
    $stmt->execute([$student_id, $time_used, $welding_level]);

    echo json_encode(["success" => true, "message" => "Welding result saved successfully."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to save result: ".$e->getMessage()]);
}
?>
