<?php
require "db-config/security.php";
header('Content-Type: application/json');

$id = (int) $_POST['id'];

$sql = "UPDATE sections SET is_active = 0 WHERE id = ?";
$stmt = $pdo->prepare($sql);

echo json_encode(['success' => $stmt->execute([$id])]);
