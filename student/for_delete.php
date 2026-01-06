<?php
require("db-config/security.php");
ini_set('display_errors', 1);
error_reporting(E_ALL);

if($_SESSION['admin-email'] != 'jlouisuru@gmail.com'){
    header('Location: all_help');
    die();
}

// ✅ Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('Invalid ID.');window.location='manage_attendance';</script>";
    exit;
}

$id = (int) $_GET['id'];

// ✅ Fetch record to get image path
$stmt = $pdo->prepare("SELECT image_path FROM attendance_logs WHERE id = :id");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    echo "<script>alert('Record not found.');window.location='manage_attendance';</script>";
    exit;
}

// ✅ Delete image file if exists
if (!empty($record['image_path']) && file_exists($record['image_path'])) {
    unlink($record['image_path']);
}

// ✅ Delete from database
$delete = $pdo->prepare("DELETE FROM attendance_logs WHERE id = :id");
$delete->execute([':id' => $id]);

// ✅ Redirect with success message
echo "<script>
    alert('✅ Record and its image were successfully deleted!');
    window.location = 'manage_attendance';
</script>";
exit;
?>
