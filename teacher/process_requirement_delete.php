<?php
require "db-config/security.php";

$id = (int) $_POST['id'];

$sql = "UPDATE requirements SET is_active = 0 WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

$_SESSION['message'] = "Requirement deactivated successfully.";
header("Location: register_requirement");
exit;
