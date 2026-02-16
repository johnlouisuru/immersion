<?php
require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

if(empty($_SESSION['lastname']) || empty($_SESSION['firstname'])){
    header('Location: complete-profile');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$section_id = '';

// Get teacher's section
$query = "SELECT section_id FROM teachers WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$teacher['section_id']) {
    $error_message = "You are not assigned to any section. Kindly assigned your account to any section since files are binded to section accordingly";
} else {
    $section_id = $teacher['section_id'];
    
}
// echo "<h1>xxxx $section_id</h1>";
// Handle Approve/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'approve' && isset($_POST['status_id'])) {
        $status_id = $_POST['status_id'];
        
        // Update requirement status to approved (is_checked = 1)
        $update_query = "UPDATE requirements_status SET is_checked = 1 WHERE id = ?";
        $stmt = $pdo->prepare($update_query);
        
        if ($stmt->execute([$status_id])) {
            $response['success'] = true;
            $response['message'] = 'Requirement approved successfully!';
        } else {
            $response['message'] = 'Failed to approve requirement.';
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'reject' && isset($_POST['status_id'])) {
        $status_id = $_POST['status_id'];
        
        // Get file path before deletion
        $select_query = "SELECT file_path FROM requirements_status WHERE id = ?";
        $stmt = $pdo->prepare($select_query);
        $stmt->execute([$status_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete physical file if exists
        if ($file && $file['file_path'] && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // Delete the record completely (permanent deletion)
        $delete_query = "DELETE FROM requirements_status WHERE id = ?";
        $stmt = $pdo->prepare($delete_query);
        
        if ($stmt->execute([$status_id])) {
            $response['success'] = true;
            $response['message'] = 'Requirement rejected and file deleted permanently.';
        } else {
            $response['message'] = 'Failed to reject requirement.';
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'bulk_approve' && isset($_POST['status_ids'])) {
        $status_ids = $_POST['status_ids'];
        $placeholders = implode(',', array_fill(0, count($status_ids), '?'));
        
        $update_query = "UPDATE requirements_status SET is_checked = 1 WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($update_query);
        
        if ($stmt->execute($status_ids)) {
            $response['success'] = true;
            $response['message'] = count($status_ids) . ' requirements approved successfully!';
        } else {
            $response['message'] = 'Failed to approve requirements.';
        }
        
        echo json_encode($response);
        exit;
    }
}

// Get all pending requirements from students in teacher's section with files
$query = "
    SELECT 
        rs.id as status_id,
        rs.req_id,
        rs.student_id,
        rs.is_checked,
        rs.file_path,
        rs.date_created,
        r.req_name,
        r.is_active as req_active,
        s.lastname,
        s.firstname,
        s.email,
        s.lrn,
        s.profile_picture,
        sec.section_name
    FROM requirements_status rs
    JOIN requirements r ON rs.req_id = r.id
    JOIN students s ON rs.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.section_id = ? 
    AND rs.file_path IS NOT NULL 
    AND rs.file_path != ''
    AND rs.is_checked = 0
    AND r.is_active = 1
    ORDER BY rs.date_created DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$section_id]);
$pending_requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved/rejected history
$history_query = "
    SELECT 
        rs.id as status_id,
        rs.req_id,
        rs.student_id,
        rs.is_checked,
        rs.file_path,
        rs.date_created,
        r.req_name,
        s.lastname,
        s.firstname,
        s.email,
        s.lrn,
        sec.section_name
    FROM requirements_status rs
    JOIN requirements r ON rs.req_id = r.id
    JOIN students s ON rs.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.section_id = ? 
    AND rs.file_path IS NOT NULL 
    AND rs.file_path != ''
    AND rs.is_checked IN (1, 2)
    ORDER BY rs.date_created DESC
    LIMIT 50
";

$stmt = $pdo->prepare($history_query);
$stmt->execute([$section_id]);
$history_requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
    COUNT(DISTINCT s.id) AS total_students,

    SUM(
        CASE 
            WHEN rs.is_checked = 0 
             AND rs.file_path IS NOT NULL 
            THEN 1 ELSE 0 
        END
    ) AS pending_count,

    SUM(
        CASE 
            WHEN rs.is_checked = 1 
            THEN 1 ELSE 0 
        END
    ) AS approved_count,

    COUNT(DISTINCT rs.student_id) AS students_with_uploads,

    -- 👇 NEW: count all uploads with a file
    SUM(
        CASE 
            WHEN rs.file_path IS NOT NULL 
            THEN 1 ELSE 0 
        END
    ) AS all_uploads

FROM students s
LEFT JOIN requirements_status rs 
    ON s.id = rs.student_id
WHERE s.section_id = ?
;";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$section_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="76x76" href="<?=$_ENV['PAGE_ICON']?>">
        <link rel="icon" type="image/png" href="<?=$_ENV['PAGE_ICON']?>">
        <title><?=$_ENV['PAGE_HEADER']?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
        }
        
        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }
        
        .stats-icon.pending { background: #fff3cd; color: #856404; }
        .stats-icon.approved { background: #d4edda; color: #155724; }
        .stats-icon.total { background: #cce5ff; color: #004085; }
        .stats-icon.uploaded { background: #e2d5f2; color: #6f42c1; }
        
        .stats-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stats-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .requirement-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .requirement-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: #667eea;
        }
        
        .student-avatar {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .student-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 16px;
            object-fit: cover;
        }
        
        .requirement-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .file-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
        }
        
        .file-icon-sm {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .file-icon-sm.pdf { background: #fee2e2; color: #dc2626; }
        .file-icon-sm.jpg, .file-icon-sm.jpeg, .file-icon-sm.png { background: #dbeafe; color: #2563eb; }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8fafc;
            border-radius: 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 320px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .nav-tabs-custom {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 12px 24px;
            margin-right: 8px;
            border-radius: 12px 12px 0 0;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: #667eea;
            background: linear-gradient(to top, rgba(102, 126, 234, 0.1), transparent);
            border-bottom: 3px solid #667eea;
        }
        
        .checkbox-select {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        @media (max-width: 767.98px) {
            body { padding: 20px 12px; }
            .card-header h2 { font-size: 22px; }
            .stats-card { padding: 18px; }
            .stats-number { font-size: 28px; }
            .requirement-card { padding: 16px; }
            .btn-action { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if (isset($error_message)): ?>
            <div class="main-card card">
                <div class="card-body p-5 text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 64px;"></i>
                    <h4 class="mt-4">Access Restricted</h4>
                    <p class="text-muted"><?= htmlspecialchars($error_message) ?></p>
                    <a href="dashboard" class="btn btn-primary mt-3">Back to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Main Card -->
            <div class="main-card card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h2>
                        <i class="bi bi-clipboard-check"></i>
                        Requirements Management
                    </h2>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-light text-dark p-3 rounded-pill">
                            <i class="bi bi-people-fill me-2"></i>
                            Section: <?= htmlspecialchars($pending_requirements[0]['section_name'] ?? $history_requirements[0]['section_name'] ?? 'Your Section') ?>
                        </span>
                        <a href="dashboard" class="btn btn-outline-light btn-sm rounded-pill px-4">
                            <i class="bi bi-speedometer me-2"></i>
                            Dashboard
                        </a>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-5">
                        <div class="col-sm-6 col-xl-4">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon total">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="stats-number"><?= $stats['total_students'] ?? 0 ?></div>
                                <div class="stats-label">Total Students</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon uploaded">
                                    <i class="bi bi-cloud-upload"></i>
                                </div>
                                <div class="stats-number"><?= $stats['all_uploads'] ?? 0 ?></div>
                                <div class="stats-label">Students with Uploads</div>
                            </div>
                        </div>
                        <!-- <div class="col-sm-6 col-xl-3">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon pending">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="stats-number"><?= count($pending_requirements) ?></div>
                                <div class="stats-label">Pending Review</div>
                            </div>
                        </div> -->
                        <div class="col-sm-6 col-xl-4">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon approved">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="stats-number"><?= $stats['approved_count'] ?? 0 ?></div>
                                <div class="stats-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Tabs -->
                    <ul class="nav nav-tabs-custom" id="dashboardTabs" role="tablist">
                        <!-- <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                <i class="bi bi-clock me-2"></i>
                                Pending Review
                                <?php if (count($pending_requirements) > 0): ?>
                                    <span class="badge bg-warning text-dark ms-2"><?= count($pending_requirements) ?></span>
                                <?php endif; ?>
                            </button>
                        </li> -->
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                                <i class="bi bi-archive me-2"></i>
                                History
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="dashboardTabContent">
                        
                        <!-- PENDING TAB -->
                        <div class="tab-pane fade" id="pending" role="tabpanel">
                            <!-- Bulk Actions -->
                            <?php if (!empty($pending_requirements)): ?>
                                <div class="filter-section d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input checkbox-select" type="checkbox" id="selectAll">
                                            <label class="form-check-label" for="selectAll">
                                                Select All
                                            </label>
                                        </div>
                                        <button class="btn btn-success btn-action" id="bulkApproveBtn" disabled>
                                            <i class="bi bi-check-all"></i>
                                            Approve Selected
                                        </button>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <input type="text" class="form-control" id="searchPending" placeholder="Search student or requirement..." style="width: 250px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (empty($pending_requirements)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-check2-circle text-success"></i>
                                    <h6 class="mt-3">All Caught Up!</h6>
                                    <p class="text-muted">No pending requirements to review.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4" id="pendingList">
                                    <?php foreach ($pending_requirements as $req): ?>
                                        <?php 
                                            $file_extension = pathinfo($req['file_path'], PATHINFO_EXTENSION);
                                            $file_name = basename($req['file_path']);
                                            $file_size = file_exists($req['file_path']) ? filesize($req['file_path']) : 0;
                                            $file_size_formatted = $file_size ? round($file_size / 1024, 2) . ' KB' : 'Unknown';
                                            $student_initials = strtoupper(substr($req['firstname'], 0, 1) . substr($req['lastname'], 0, 1));
                                        ?>
                                        <div class="col-12 requirement-item" data-student="<?= strtolower($req['lastname'] . ' ' . $req['firstname']) ?>" data-requirement="<?= strtolower($req['req_name']) ?>">
                                            <div class="requirement-card">
                                                <div class="d-flex flex-column flex-lg-row gap-4">
                                                    <!-- Left Section: Student Info & Checkbox -->
                                                    <div class="d-flex align-items-start gap-3 flex-lg-grow-1">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <input class="form-check-input checkbox-select req-checkbox" type="checkbox" value="<?= $req['status_id'] ?>" style="width: 20px; height: 20px;">
                                                            
                                                            <div class="student-avatar">
                                                                <?php if ($req['profile_picture']): ?>
                                                                    <img src="<?= htmlspecialchars($req['profile_picture']) ?>" alt="Profile">
                                                                <?php else: ?>
                                                                    <?= $student_initials ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                                <h5 class="fw-bold mb-0">
                                                                    <?= htmlspecialchars($req['lastname'] . ', ' . $req['firstname']) ?>
                                                                </h5>
                                                                <span class="badge bg-secondary">LRN: <?= htmlspecialchars($req['lrn']) ?></span>
                                                                <span class="badge bg-info"><?= htmlspecialchars($req['section_name']) ?></span>
                                                            </div>
                                                            
                                                            <div class="d-flex flex-wrap gap-3 mb-2">
                                                                <span class="d-flex align-items-center text-muted">
                                                                    <i class="bi bi-envelope me-2"></i>
                                                                    <?= htmlspecialchars($req['email']) ?>
                                                                </span>
                                                                <span class="d-flex align-items-center text-muted">
                                                                    <i class="bi bi-calendar me-2"></i>
                                                                    Uploaded: <?= date('M d, Y h:i A', strtotime($req['date_created'])) ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <!-- Requirement Info -->
                                                            <div class="file-info mt-2">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <div class="file-icon-sm <?= strtolower($file_extension) ?>">
                                                                        <i class="bi bi-file-earmark-<?= $file_extension === 'pdf' ? 'pdf' : 'image' ?>"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                                                            <span class="fw-semibold"><?= htmlspecialchars($req['req_name']) ?></span>
                                                                            <span class="badge badge-pending">
                                                                                <i class="bi bi-clock me-1"></i>
                                                                                Pending
                                                                            </span>
                                                                        </div>
                                                                        <div class="d-flex flex-wrap gap-3 small text-muted mt-1">
                                                                            <span>
                                                                                <i class="bi bi-file-earmark me-1"></i>
                                                                                <?= htmlspecialchars($file_name) ?>
                                                                            </span>
                                                                            <span>
                                                                                <i class="bi bi-hdd me-1"></i>
                                                                                <?= $file_size_formatted ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Right Section: Actions -->
                                                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-lg-center gap-2">
                                                        <?php if (file_exists($req['file_path'])): ?>
                                                            <a href="<?= $req['file_path'] ?>" class="btn btn-outline-primary btn-action" target="_blank">
                                                                <i class="bi bi-eye"></i>
                                                                <span>View File</span>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-success btn-action" onclick="approveRequirement(<?= $req['status_id'] ?>)">
                                                            <i class="bi bi-check-lg"></i>
                                                            <span>Approve</span>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-danger btn-action" onclick="rejectRequirement(<?= $req['status_id'] ?>)">
                                                            <i class="bi bi-x-lg"></i>
                                                            <span>Reject</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- HISTORY TAB -->
                        <div class="tab-pane fade  show active" id="history" role="tabpanel">
                            <div class="filter-section">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="searchHistory" placeholder="Search history...">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" id="filterStatus">
                                            <option value="all">All Status</option>
                                            <option value="1">Approved</option>
                                            <option value="2">Rejected</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($history_requirements)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-archive"></i>
                                    <h6 class="mt-3">No History Yet</h6>
                                    <p class="text-muted">Approved or rejected requirements will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4" id="historyList">
                                    <?php foreach ($history_requirements as $req): ?>
                                        <?php 
                                            $file_extension = pathinfo($req['file_path'], PATHINFO_EXTENSION);
                                            $file_name = basename($req['file_path']);
                                            $status_class = $req['is_checked'] == 1 ? 'badge-approved' : 'badge-rejected';
                                            $status_text = $req['is_checked'] == 1 ? 'Approved' : 'Rejected';
                                            $status_icon = $req['is_checked'] == 1 ? 'bi-check-circle' : 'bi-x-circle';
                                            $student_initials = strtoupper(substr($req['firstname'], 0, 1) . substr($req['lastname'], 0, 1));
                                        ?>
                                        <div class="col-12 history-item" data-status="<?= $req['is_checked'] ?>">
                                            <div class="requirement-card">
                                                <div class="d-flex flex-column flex-md-row gap-4">
                                                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                                                        <div class="student-avatar">
                                                            <?= $student_initials ?>
                                                        </div>
                                                        
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                                <h6 class="fw-bold mb-0">
                                                                    <?= htmlspecialchars($req['lastname'] . ', ' . $req['firstname']) ?>
                                                                </h6>
                                                                <span class="badge bg-secondary"><?= htmlspecialchars($req['lrn']) ?></span>
                                                                <span class="badge <?= $status_class ?>">
                                                                    <i class="bi <?= $status_icon ?> me-1"></i>
                                                                    <?= $status_text ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <div class="d-flex flex-wrap gap-3">
                                                                <span class="text-muted small">
                                                                    <i class="bi bi-file-earmark me-1"></i>
                                                                    <?= htmlspecialchars($req['req_name']) ?>
                                                                </span>
                                                                <span class="text-muted small">
                                                                    <i class="bi bi-calendar me-1"></i>
                                                                    <?= date('M d, Y', strtotime($req['date_created'])) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (file_exists('../student/'.$req['file_path'])): ?>
                                                        <div class="d-flex align-items-center">
                                                            <a href="../student/<?= $req['file_path'] ?>" class="btn btn-outline-secondary btn-action" target="_blank">
                                                                <i class="bi bi-eye"></i>
                                                                <span>View File</span>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast-notification" id="toast"></div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="confirmModalHeader">
                    <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const bgColor = type === 'success' ? '#10b981' : (type === 'warning' ? '#f59e0b' : '#ef4444');
            const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');
            
            toast.innerHTML = `
                <div style="background: ${bgColor}; color: white; padding: 16px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease;">
                    <i class="bi ${icon}" style="font-size: 20px;"></i>
                    <span style="font-weight: 500;">${message}</span>
                    <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: white; opacity: 0.8;">&times;</button>
                </div>
            `;
            
            setTimeout(() => {
                if (toast.children.length > 0) {
                    toast.innerHTML = '';
                }
            }, 5000);
        }
        
        // Approve requirement
        function approveRequirement(statusId) {
            const modalTitle = document.getElementById('confirmModalTitle');
            const modalBody = document.getElementById('confirmModalBody');
            const confirmBtn = document.getElementById('confirmActionBtn');
            
            modalTitle.textContent = 'Approve Requirement';
            modalBody.textContent = 'Are you sure you want to approve this requirement?';
            confirmBtn.className = 'btn btn-success';
            confirmBtn.textContent = 'Approve';
            
            confirmBtn.onclick = function() {
                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('status_id', statusId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
                
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            };
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }
        
        // Reject requirement (permanent deletion)
        function rejectRequirement(statusId) {
            const modalTitle = document.getElementById('confirmModalTitle');
            const modalBody = document.getElementById('confirmModalBody');
            const confirmBtn = document.getElementById('confirmActionBtn');
            const modalHeader = document.getElementById('confirmModalHeader');
            
            modalTitle.textContent = '⚠️ Reject & Delete Requirement';
            modalBody.innerHTML = `
                <div class="text-center mb-3">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 48px;"></i>
                </div>
                <p class="fw-bold text-danger">This action cannot be undone!</p>
                <p>The file will be permanently deleted from the server. Are you sure you want to reject this requirement?</p>
            `;
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.textContent = 'Yes, Reject & Delete';
            
            confirmBtn.onclick = function() {
                const formData = new FormData();
                formData.append('action', 'reject');
                formData.append('status_id', statusId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'warning' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
                
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            };
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }
        
        // Bulk approve
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const bulkApproveBtn = document.getElementById('bulkApproveBtn');
            const reqCheckboxes = document.querySelectorAll('.req-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    reqCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateBulkApproveButton();
                });
            }
            
            reqCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateBulkApproveButton);
            });
            
            function updateBulkApproveButton() {
                const checkedBoxes = document.querySelectorAll('.req-checkbox:checked');
                if (bulkApproveBtn) {
                    bulkApproveBtn.disabled = checkedBoxes.length === 0;
                    bulkApproveBtn.innerHTML = checkedBoxes.length > 0 
                        ? `<i class="bi bi-check-all"></i> Approve Selected (${checkedBoxes.length})` 
                        : '<i class="bi bi-check-all"></i> Approve Selected';
                }
            }
            
            if (bulkApproveBtn) {
                bulkApproveBtn.addEventListener('click', function() {
                    const checkedBoxes = document.querySelectorAll('.req-checkbox:checked');
                    const statusIds = Array.from(checkedBoxes).map(cb => cb.value);
                    
                    if (statusIds.length === 0) return;
                    
                    const modalTitle = document.getElementById('confirmModalTitle');
                    const modalBody = document.getElementById('confirmModalBody');
                    const confirmBtn = document.getElementById('confirmActionBtn');
                    
                    modalTitle.textContent = 'Bulk Approve';
                    modalBody.textContent = `Are you sure you want to approve ${statusIds.length} requirement(s)?`;
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = 'Approve All';
                    
                    confirmBtn.onclick = function() {
                        const formData = new FormData();
                        formData.append('action', 'bulk_approve');
                        formData.append('status_ids', JSON.stringify(statusIds));
                        
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            showToast(data.message, data.success ? 'success' : 'error');
                            if (data.success) {
                                setTimeout(() => location.reload(), 1500);
                            }
                        });
                        
                        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                    };
                    
                    new bootstrap.Modal(document.getElementById('confirmModal')).show();
                });
            }
            
            // Search functionality
            const searchPending = document.getElementById('searchPending');
            if (searchPending) {
                searchPending.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const items = document.querySelectorAll('#pendingList .requirement-item');
                    
                    items.forEach(item => {
                        const student = item.dataset.student || '';
                        const requirement = item.dataset.requirement || '';
                        
                        if (student.includes(searchTerm) || requirement.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // History filter
            const searchHistory = document.getElementById('searchHistory');
            const filterStatus = document.getElementById('filterStatus');
            
            function filterHistory() {
                const searchTerm = searchHistory?.value.toLowerCase() || '';
                const statusFilter = filterStatus?.value || 'all';
                const items = document.querySelectorAll('#historyList .history-item');
                
                items.forEach(item => {
                    let show = true;
                    
                    if (statusFilter !== 'all') {
                        if (item.dataset.status !== statusFilter) {
                            show = false;
                        }
                    }
                    
                    if (show && searchTerm) {
                        const text = item.textContent.toLowerCase();
                        if (!text.includes(searchTerm)) {
                            show = false;
                        }
                    }
                    
                    item.style.display = show ? 'block' : 'none';
                });
            }
            
            if (searchHistory) {
                searchHistory.addEventListener('keyup', filterHistory);
            }
            
            if (filterStatus) {
                filterStatus.addEventListener('change', filterHistory);
            }
        });
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
        // filterHistory();
    </script>
</body>
</html>