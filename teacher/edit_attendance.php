<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isGoogleAuthenticated() || !isLoggedIn()) {
            // header('Location: complete-profile');
            // exit;
            die('Unauthorized!');
        }
ini_set('display_errors', 1);
error_reporting(E_ALL);

// if($_SESSION['admin-email'] != 'jlouisuru@gmail.com'){
//     header('Location: all_help');
//     die();
// }
// ✅ Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid ID.");
}

$id = (int) $_GET['id'];

// ✅ Fetch the record
$stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE id = :id");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("Record not found.");
}

// ✅ Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangay = trim($_POST['barangay']);
    $municipality = trim($_POST['municipality']);
    $province = trim($_POST['province']);
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);
    $is_login = trim($_POST['is_login']);

    $imagePath = $record['image_path']; // default existing image

    // ✅ Handle new image upload
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg', 'image/png'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);

        if (!in_array($fileType, $allowed)) {
            die("Invalid file type. Only JPG and PNG are allowed.");
        }

        // Remove old image
        if (!empty($record['image_path']) && file_exists($record['image_path'])) {
            unlink($record['image_path']);
        }

        // Save new image
        $folder = "uploads/";
        if (!file_exists($folder)) mkdir($folder, 0777, true);
        $newName = $folder . "updated_" . time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $newName);
        $imagePath = $newName;
    }

    // ✅ Update database record
    $update = $pdo->prepare("
        UPDATE attendance_logs 
        SET barangay = :barangay,
            municipality = :municipality,
            province = :province,
            latitude = :latitude,
            longitude = :longitude,
            is_login = :is_login,
            image_path = :image_path
        WHERE id = :id
    ");
    $update->execute([
        ':barangay' => $barangay,
        ':municipality' => $municipality,
        ':province' => $province,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':is_login' => $is_login,
        ':image_path' => $imagePath,
        ':id' => $id
    ]);

    echo "<script>alert('✅ Record updated successfully!');window.location='manage_attendance';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Attendance Record</title>
<link rel="icon" type="image/png" href="assets/img/sos.webp">
<style>
body {
  font-family: system-ui, Arial, sans-serif;
  background: #f9fafb;
  margin: 0;
  padding: 40px 20px;
}
.container {
  max-width: 600px;
  margin: auto;
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
h2 {
  text-align: center;
  color: #333;
  margin-bottom: 20px;
}
label {
  font-weight: 600;
  display: block;
  margin-top: 10px;
  color: #444;
}
input[type=text], input[type=file] {
  width: 100%;
  padding: 10px;
  margin-top: 6px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
}
img {
  display: block;
  margin-top: 10px;
  max-width: 100%;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
button {
  width: 100%;
  background: #28a745;
  color: white;
  padding: 12px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  margin-top: 20px;
}
button:hover {
  background: #218838;
}
.back-btn {
  background: #007bff;
  color: white;
  text-decoration: none;
  padding: 10px 14px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 15px;
}
.back-btn:hover {
  background: #0056b3;
}
.preview {
  margin-top: 15px;
  text-align: center;
}
.preview img {
  width: 100%;
  max-width: 280px;
  border-radius: 8px;
}
/* ✅ Image Zoom Modal */
#imgModal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.8);
  justify-content: center;
  align-items: center;
}
#imgModal img {
  display: block;
  max-width: 90%;
  max-height: 85vh;
  width: auto !important;
  height: auto !important;
  border-radius: 10px;
  box-shadow: 0 0 15px rgba(255,255,255,0.2);
  cursor: zoom-out;
}
#imgModal .close {
  position: absolute;
  top: 20px;
  right: 30px;
  font-size: 40px;
  color: #fff;
  cursor: pointer;
  font-weight: bold;
}

</style>
</head>
<body>

<div class="container">
  <a href="manage_attendance" class="back-btn">← Back</a>
  <h2>✏️ Edit Attendance Record</h2>

  <form method="POST" enctype="multipart/form-data">
    <label>Barangay</label>
    <input type="text" name="barangay" value="<?= htmlspecialchars($record['barangay']) ?>" required>

    <label>Municipality</label>
    <input type="text" name="municipality" value="<?= htmlspecialchars($record['municipality']) ?>" required>

    <label>Province</label>
    <input type="text" name="province" value="<?= htmlspecialchars($record['province']) ?>" required>

    <label>Latitude</label>
    <input type="text" name="latitude" value="<?= htmlspecialchars($record['latitude']) ?>" required>

    <label>Longitude</label>
    <input type="text" name="longitude" value="<?= htmlspecialchars($record['longitude']) ?>" required>

    <label>Remarks / Status [1 = Time In / 2 = Time Out]</label>
    <input type="text" name="is_login" value="<?= htmlspecialchars($record['is_login']) ?>">

    <label>Current Image:</label>
    <?php if (!empty($record['video_path']) && file_exists($record['video_path'])): ?>
      <img src="<?= htmlspecialchars($record['video_path']) ?>" alt="Current Image">
    <?php else: ?>
      <p><em>No image available</em></p>
    <?php endif; ?>

    <label>Upload New Image (optional)</label>
    <input type="file" name="photo" accept="image/jpeg,image/png" id="photoInput">

    <div class="preview" id="preview"></div>

    <button type="submit">💾 Update Record</button>
  </form>
</div>

<!-- ✅ Image Modal HTML -->
<div id="imgModal">
  <span class="close">&times;</span>
  <img id="modalImage" src="" alt="Zoomed Image">
</div>

<script>
// ✅ Live preview for new uploads
document.getElementById('photoInput').addEventListener('change', function(event) {
  const preview = document.getElementById('preview');
  preview.innerHTML = '';

  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = e => {
    const img = document.createElement('img');
    img.src = e.target.result;
    img.classList.add('zoomable'); // enable zoom for preview
    preview.appendChild(img);
    enableZoom(img);
  };
  reader.readAsDataURL(file);
});

// ✅ Apply zoom to all images on page (existing and preview)
document.querySelectorAll('img').forEach(enableZoom);

function enableZoom(img) {
  img.addEventListener('click', function() {
    const modal = document.getElementById('imgModal');
    const modalImg = document.getElementById('modalImage');
    modalImg.src = this.src;
    modal.style.display = 'flex';
  });
}

// ✅ Close modal
document.querySelector('#imgModal .close').addEventListener('click', () => {
  document.getElementById('imgModal').style.display = 'none';
});

// ✅ Close when clicking outside image
document.getElementById('imgModal').addEventListener('click', e => {
  if (e.target.id === 'imgModal') {
    document.getElementById('imgModal').style.display = 'none';
  }
});
</script>

</body>
</html>
