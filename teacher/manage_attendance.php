<?php
require("db-config/security.php");
ini_set('display_errors', 1);
error_reporting(E_ALL);
    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}
// ✅ Apply filter if section_id is passed
$section_id = $_GET['section_id'] ?? 'all';

if ($section_id != 'all') {
    $sql = "SELECT a.* 
            FROM attendance_logs AS a
            INNER JOIN students AS s ON a.student_id = s.id
            WHERE s.section_id = :section_id
            ORDER BY a.date_created DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":section_id" => (int)$section_id]);
} else {
    $sql = "SELECT a.* 
            FROM attendance_logs AS a
            INNER JOIN students AS s ON a.student_id = s.id
            ORDER BY a.date_created DESC";
    $stmt = $pdo->query($sql);
}

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Attendance Logs</title>
<link rel="icon" type="image/png" href="assets/img/sos.webp">
<style>
body {
  font-family: system-ui, Arial, sans-serif;
  background: #f7f7f7;
  margin: 20px;
}
h2 {
  text-align: center;
  color: #333;
}
table {
  border-collapse: collapse;
  width: 100%;
  background: #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
th, td {
  border: 1px solid #ddd;
  padding: 10px;
  font-size: 14px;
  text-align: left;
}
th {
  background-color: #007bff;
  color: white;
}
tr:nth-child(even) {
  background-color: #f9f9f9;
}
img {
  width: 80px;
  border-radius: 6px;
  cursor: pointer;
  transition: transform 0.2s ease;
}
img:hover {
  transform: scale(1.05);
}
.action-btn {
  padding: 6px 12px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 13px;
}
.edit-btn {
  background: #28a745;
  color: #fff;
}
.delete-btn {
  background: #dc3545;
  color: #fff;
}
.edit-btn:hover {
  background: #218838;
}
.delete-btn:hover {
  background: #c82333;
}
.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
a {
  text-decoration: none;
}

/* ✅ Fullscreen Overlay for Image Preview */
#imgModal {
  display: none;
  position: fixed;
  z-index: 9999;
  padding-top: 50px;
  left: 0; top: 0;
  width: 100%; height: 100%;
  background-color: rgba(0,0,0,0.9);
}
#imgModal img {
  display: block;
  margin: auto;
  max-width: 90%;
  max-height: 80vh;
  width: auto !important;
  height: auto !important;
  border-radius: 10px;
  box-shadow: 0 0 15px rgba(255,255,255,0.2);
}
#imgModal span {
  position: absolute;
  top: 15px; right: 35px;
  color: white;
  font-size: 35px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s;
}
#imgModal span:hover {
  color: #f1f1f1;
}
table img {
  width: 80px;
  border-radius: 6px;
  cursor: pointer;
  transition: transform 0.2s ease;
}

</style>
</head>
<body>
<div class="topbar">
  <h2>📋 Attendance Logs Management</h2>
  <a href="dashboard" style="background:#007bff;color:#fff;padding:8px 14px;border-radius:6px;">🏠 Back to Dashboard</a>
</div>

<!-- Section Filter -->
<div style="margin-bottom:15px;">
  <form method="get" action="">
    <label for="section_id">Filter by Section:</label>
    <select name="section_id" id="section_id" onchange="this.form.submit()">
      <option value="all" <?= $section_id === 'all' ? 'selected' : '' ?>>All Sections</option>
      <?php
      // Get all sections
      $sections_stmt = $pdo->query("SELECT a.*, s.section_id
                FROM attendance_logs AS a
                INNER JOIN students AS s ON a.student_id = s.id
                ");
      $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);

      $query_sections_to_dropdown = "
                        SELECT DISTINCT s.section_id, sec.section_name
                        FROM students AS s
                        JOIN sections AS sec ON s.section_id = sec.id 
      ";
      $sections_dropdown_stmt = secure_query_no_params($pdo, $query_sections_to_dropdown);
      $sections_dropdown = $sections_dropdown_stmt ? $sections_dropdown_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
      foreach ($sections_dropdown as $sec): ?>
        <option value="<?= $sec['section_id'] ?>" <?= $section_id == $sec['section_id'] ? 'selected' : '' ?>>
          <?= $sec['section_name'] ?>
        </option>
    <?php endforeach; ?>
    </select>
  </form>
</div>


<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Student ID</th>
      <th>Section ID</th>
      <th>Barangay</th>
      <th>Municipality</th>
      <th>Province</th>
      <th>Image</th>
      <th>Date Created</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php if (count($records)): ?>
      <?php foreach ($records as $row): 
        $section_name = get_section_name($pdo, $row['section_id'])?>
        <tr>
          <td><?= htmlspecialchars($row['id']) ?></td>
          <td><?= htmlspecialchars($row['student_id']) ?></td>
          <td><?= $section_name['section_name'] ?></td>
          <td><?= htmlspecialchars($row['barangay']) ?></td>
          <td><?= htmlspecialchars($row['municipality']) ?></td>
          <td><?= htmlspecialchars($row['province']) ?></td>
          <td>
            <?php if (!empty($row['video_path']) && file_exists('../student/'.$row['video_path'])): ?>
              <img src="../student/<?= htmlspecialchars($row['video_path']) ?>" alt="Image" onclick="openModal(this.src)">
            <?php else: ?>
              <em>No image</em>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['date_created']) ?> 
        <?php 
          if($row['is_login'] == 1){
            echo "<i class='text text-info'>Time In</i>";
          } else {
            echo "<i class='text text-warning'>Time Out</i>";
          }
        ?></td>
          <td>
            <a href="edit_attendance?id=<?= $row['id'] ?>">
              <button class="action-btn edit-btn">✏️ Edit</button>
            </a>
            <a href="for_delete?id=<?= $row['id'] ?>" 
               onclick="return confirm('Are you sure you want to delete this record?');">
              <button class="action-btn delete-btn">🗑️ Delete</button>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="11" style="text-align:center;">No records found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- ✅ Fullscreen Modal -->
<div id="imgModal" onclick="closeModal()">
  <span>&times;</span>
  <img id="modalImage" src="">
</div>

<script>
function openModal(src) {
  const modal = document.getElementById('imgModal');
  const modalImg = document.getElementById('modalImage');
  modalImg.src = src;
  modal.style.display = 'block';
}
function closeModal() {
  document.getElementById('imgModal').style.display = 'none';
}
</script>

</body>
</html>
