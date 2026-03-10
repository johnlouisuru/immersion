<?php
require("db-config/security.php");
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Get teacher information including their assigned section
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    echo "<h1>Not a Teacher</h1>";
    exit;
}

// Get the teacher's section ID
$teacher_section_id = $teacher['section_id'];
$teacher_section_name = null;

// Get section name if teacher has assigned section
if ($teacher_section_id) {
    $section_info = get_section_name($pdo, $teacher_section_id);
    $teacher_section_name = $section_info['section_name'] ?? 'Unknown Section';
}

// ✅ Apply filter if section_id is passed (but only allow teacher's section)
$section_id = $_GET['section_id'] ?? 'all';

// If teacher has no section, show no records
if (!$teacher_section_id) {
    $records = [];
} else {
    // Only allow filtering by teacher's section
    if ($section_id != 'all' && $section_id == $teacher_section_id) {
        $sql = "SELECT a.*, s.lastname, s.firstname, s.mi, s.section_id as student_section
                FROM attendance_logs AS a
                INNER JOIN students AS s ON a.student_id = s.id
                WHERE s.section_id = :section_id
                ORDER BY a.date_created DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":section_id" => (int)$section_id]);
    } else {
        // Default to teacher's section
        $sql = "SELECT a.*, s.lastname, s.firstname, s.mi, s.section_id as student_section
                FROM attendance_logs AS a
                INNER JOIN students AS s ON a.student_id = s.id
                WHERE s.section_id = :section_id
                ORDER BY a.date_created DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":section_id" => $teacher_section_id]);
        $section_id = $teacher_section_id; // Update current section for display
    }
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance Logs</title>
    <link rel="icon" type="image/png" href="assets/img/sos.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f7f7f7;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-wrapper {
            padding: 15px;
        }
        
        /* Mobile-optimized card layout */
        .attendance-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            padding: 15px;
            transition: transform 0.2s;
        }
        
        .attendance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .card-header-badge {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
        }
        
        .student-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .student-details {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 4px;
        }
        
        .attendance-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 12px 0;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .detail-label {
            color: #95a5a6;
            font-size: 0.8rem;
            margin-bottom: 2px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #34495e;
        }
        
        .image-preview {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.2s;
            max-height: 150px;
            object-fit: cover;
        }
        
        .image-preview:hover {
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            flex: 1;
            min-width: 80px;
            padding: 8px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
        }
        
        .btn-edit:hover {
            background: #218838;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            color: white;
        }
        
        .time-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 8px;
        }
        
        .time-in {
            background: #d4edda;
            color: #155724;
        }
        
        .time-out {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Desktop table */
        .desktop-table {
            display: none;
        }
        
        /* Filter section */
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .section-info {
            background: #e8f4fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Modal styles */
        #imgModal {
            display: none;
            position: fixed;
            z-index: 9999;
            padding: 20px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.95);
        }
        
        #imgModal img {
            display: block;
            margin: auto;
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        }
        
        #imgModal span {
            position: absolute;
            top: 15px;
            right: 25px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10000;
        }
        
        /* Tablet and Desktop styles */
        @media (min-width: 768px) {
            .main-wrapper {
                padding: 20px;
            }
            
            .mobile-cards {
                display: none;
            }
            
            .desktop-table {
                display: block;
                overflow-x: auto;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            }
            
            th {
                background: #007bff;
                color: white;
                padding: 12px;
                font-size: 0.9rem;
                white-space: nowrap;
            }
            
            td {
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
                font-size: 0.9rem;
            }
            
            tr:hover {
                background: #f8f9fa;
            }
            
            .desktop-img {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 6px;
                cursor: pointer;
            }
        }
        
        /* Loading state */
        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .no-records {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
            background: white;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i class="fas fa-clock me-2 text-primary"></i>
                Attendance Logs
            </h2>
            <a href="dashboard" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>
                <span class="d-none d-sm-inline">Back to Dashboard</span>
                <i class="fas fa-home d-sm-none"></i>
            </a>
        </div>
        
        <?php if (!$teacher_section_id): ?>
            <!-- Teacher has no section -->
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                You are not assigned to any section yet. Please contact the administrator.
            </div>
        <?php else: ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="section-info">
                <i class="fas fa-users text-primary"></i>
                <span>
                    <strong>Your Section:</strong> <?= htmlspecialchars($teacher_section_name) ?>
                    <span class="badge bg-secondary ms-2"><?= count($records) ?> records</span>
                </span>
            </div>
            
            <!-- Simple filter form (only shows teacher's section) -->
            <form method="get" action="" class="row g-2">
                <div class="col-12">
                    <label for="section_id" class="form-label fw-bold">Filter by Section:</label>
                    <select name="section_id" id="section_id" class="form-select" onchange="this.form.submit()" <?= !$teacher_section_id ? 'disabled' : '' ?>>
                        <option value="<?= $teacher_section_id ?>" selected>
                            <?= htmlspecialchars($teacher_section_name) ?> (Your Section)
                        </option>
                    </select>
                    <small class="text-muted">You can only view your assigned section</small>
                </div>
            </form>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-cards">
            <?php if (count($records)): ?>
                <?php foreach ($records as $row): 
                    $section_name = get_section_name($pdo, $row['section_id']);
                    $student_info = getStudentName($pdo, $row['student_id']);
                    $timeType = $row['is_login'] == 1 ? 'Time In' : 'Time Out';
                    $timeClass = $row['is_login'] == 1 ? 'time-in' : 'time-out';
                ?>
                    <div class="attendance-card">
                        <div class="card-header-badge">
                            <div class="student-info">
                                <span class="student-name">
                                    <?= htmlspecialchars($student_info['lastname'] ?? '') ?>, 
                                    <?= htmlspecialchars($student_info['firstname'] ?? '') ?>
                                    <?= !empty($student_info['mi']) ? htmlspecialchars($student_info['mi']) . '.' : '' ?>
                                </span>
                                <span class="student-details">
                                    <i class="fas fa-id-card me-1"></i> ID: <?= $row['student_id'] ?> • 
                                    <i class="fas fa-layer-group me-1"></i> <?= $section_name['section_name'] ?? 'N/A' ?>
                                </span>
                            </div>
                            <span class="time-badge <?= $timeClass ?>">
                                <?= $timeType ?>
                            </span>
                        </div>
                        
                        <div class="attendance-details">
                            <div class="detail-item">
                                <div class="detail-label">Barangay</div>
                                <div class="detail-value"><?= htmlspecialchars($row['barangay'] ?: '—') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Municipality</div>
                                <div class="detail-value"><?= htmlspecialchars($row['municipality'] ?: '—') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Province</div>
                                <div class="detail-value"><?= htmlspecialchars($row['province'] ?: '—') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Date/Time</div>
                                <div class="detail-value">
                                    <?= date('M d, Y h:i A', strtotime($row['date_created'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($row['video_path']) && file_exists('../student/'.$row['video_path'])): ?>
                            <div class="mb-2">
                                <img src="../student/<?= htmlspecialchars($row['video_path']) ?>" 
                                     alt="Attendance image" 
                                     class="image-preview"
                                     onclick="openModal(this.src)">
                            </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="edit_attendance?id=<?= $row['id'] ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="for_delete?id=<?= $row['id'] ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('Are you sure you want to delete this record?');">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-clock fa-3x mb-3" style="color: #dee2e6;"></i>
                    <p class="mb-0">No attendance records found for your section.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Desktop Table View -->
        <div class="desktop-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Location</th>
                        <th>Image</th>
                        <th>Date/Time</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records)): ?>
                        <?php foreach ($records as $row): 
                            $section_name = get_section_name($pdo, $row['section_id']);
                            $student_info = getStudentName($pdo, $row['student_id']);
                        ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($student_info['lastname'] ?? '') ?>, 
                                    <?= htmlspecialchars($student_info['firstname'] ?? '') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['barangay'] ?: '—') ?>, 
                                    <?= htmlspecialchars($row['municipality'] ?: '—') ?><br>
                                    <small><?= htmlspecialchars($row['province'] ?: '—') ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($row['video_path']) && file_exists('../student/'.$row['video_path'])): ?>
                                        <img src="../student/<?= htmlspecialchars($row['video_path']) ?>" 
                                             alt="Image" 
                                             class="desktop-img"
                                             onclick="openModal(this.src)">
                                    <?php else: ?>
                                        <em class="text-muted">No image</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($row['date_created'])) ?></td>
                                <td>
                                    <span class="badge <?= $row['is_login'] == 1 ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $row['is_login'] == 1 ? 'Time In' : 'Time Out' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="edit_attendance?id=<?= $row['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="for_delete?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-clock me-2"></i>No records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Image Modal -->
    <div id="imgModal" onclick="closeModal()">
        <span>&times;</span>
        <img id="modalImage" src="">
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imgModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('imgModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to get student name
function getStudentName($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT lastname, firstname, mi FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>