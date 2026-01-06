<?php
require "db-config/security.php";

$id = (int) $_POST['id'];
$req_name = trim($_POST['req_name']);
$is_active = (int) $_POST['is_active'];

$sql = "UPDATE requirements 
        SET req_name = ?, is_active = ?
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$req_name, $is_active, $id]);

$_SESSION['message'] = "Requirement updated successfully.";
header("Location: register_requirement");
exit;
