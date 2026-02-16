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

// Get teacher's section
$query = "SELECT id, section_id, lastname, firstname FROM teachers WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$teacher['section_id']) {
    $error_message = "You are not assigned to any section. Please contact the administrator.";
} else {
    $section_id = $teacher['section_id'];
    
    // Get section name
    $section_query = "SELECT section_name FROM sections WHERE id = ?";
    $stmt = $pdo->prepare($section_query);
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    $section_name = $section['section_name'] ?? 'Unknown Section';
}

// Create upload directory if not exists
$upload_dir = 'uploads/learning_materials/section_' . $section_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'upload' && isset($_FILES['learning_file'])) {
        $file = $_FILES['learning_file'];
        $description = $_POST['description'] ?? '';
        $custom_filename = $_POST['custom_filename'] ?? '';
        
        // Validate file - PDF only
        $allowed_types = ['application/pdf'];
        $max_size = 20 * 1024 * 1024; // 20MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $response['message'] = 'Only PDF files are allowed.';
        } elseif ($file['size'] > $max_size) {
            $response['message'] = 'File size must be less than 20MB.';
        } else {
            // Generate filename
            if (!empty($custom_filename)) {
                // Sanitize custom filename
                $custom_filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $custom_filename);
                $file_name = $custom_filename . '.pdf';
            } else {
                // Generate unique filename with timestamp
                $file_name = time() . '_' . uniqid() . '.pdf';
            }
            
            $file_path = $upload_dir . $file_name;
            
            // Check if filename already exists
            if (file_exists($file_path)) {
                $file_name = time() . '_' . $file_name;
                $file_path = $upload_dir . $file_name;
            }
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Insert into database
                $insert_query = "
                    INSERT INTO learning_materials 
                    (teacher_id, section_id, file_name, original_name, file_path, file_size, description, uploaded_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ";
                
                $stmt = $pdo->prepare($insert_query);
                $result = $stmt->execute([
                    $teacher_id,
                    $section_id,
                    $file_name,
                    $file['name'],
                    $file_path,
                    $file['size'],
                    $description
                ]);
                
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Learning material uploaded successfully!';
                    $response['file_id'] = $pdo->lastInsertId();
                } else {
                    $response['message'] = 'Failed to save to database.';
                    // Delete uploaded file if database insert fails
                    unlink($file_path);
                }
            } else {
                $response['message'] = 'Failed to upload file.';
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle rename
    if ($_POST['action'] === 'rename' && isset($_POST['material_id']) && isset($_POST['new_filename'])) {
        $material_id = $_POST['material_id'];
        $new_filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $_POST['new_filename']);
        $new_filename = $new_filename . '.pdf';
        
        // Get current file info
        $query = "SELECT file_path, file_name FROM learning_materials WHERE id = ? AND teacher_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$material_id, $teacher_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            $old_path = $material['file_path'];
            $dir = dirname($old_path);
            $new_path = $dir . '/' . $new_filename;
            
            // Check if new filename already exists
            if (file_exists($new_path) && $old_path !== $new_path) {
                $response['message'] = 'Filename already exists.';
            } else {
                // Rename physical file
                if (rename($old_path, $new_path)) {
                    // Update database
                    $update_query = "UPDATE learning_materials SET file_name = ?, file_path = ? WHERE id = ?";
                    $stmt = $pdo->prepare($update_query);
                    
                    if ($stmt->execute([$new_filename, $new_path, $material_id])) {
                        $response['success'] = true;
                        $response['message'] = 'File renamed successfully!';
                    } else {
                        // Rollback file rename if database update fails
                        rename($new_path, $old_path);
                        $response['message'] = 'Failed to update database.';
                    }
                } else {
                    $response['message'] = 'Failed to rename file.';
                }
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle delete
    if ($_POST['action'] === 'delete' && isset($_POST['material_id'])) {
        $material_id = $_POST['material_id'];
        
        // Get file info
        $query = "SELECT file_path FROM learning_materials WHERE id = ? AND teacher_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$material_id, $teacher_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material) {
            // Delete physical file
            if (file_exists($material['file_path'])) {
                unlink($material['file_path']);
            }
            
            // Delete from database
            $delete_query = "DELETE FROM learning_materials WHERE id = ?";
            $stmt = $pdo->prepare($delete_query);
            
            if ($stmt->execute([$material_id])) {
                $response['success'] = true;
                $response['message'] = 'Learning material deleted successfully!';
            } else {
                $response['message'] = 'Failed to delete from database.';
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle update description
    if ($_POST['action'] === 'update_description' && isset($_POST['material_id']) && isset($_POST['description'])) {
        $material_id = $_POST['material_id'];
        $description = $_POST['description'];
        
        $update_query = "UPDATE learning_materials SET description = ? WHERE id = ? AND teacher_id = ?";
        $stmt = $pdo->prepare($update_query);
        
        if ($stmt->execute([$description, $material_id, $teacher_id])) {
            $response['success'] = true;
            $response['message'] = 'Description updated successfully!';
        } else {
            $response['message'] = 'Failed to update description.';
        }
        
        echo json_encode($response);
        exit;
    }
}

// Get all learning materials for this section
$materials_query = "
    SELECT 
        lm.*,
        t.lastname as teacher_lastname,
        t.firstname as teacher_firstname
    FROM learning_materials lm
    JOIN teachers t ON lm.teacher_id = t.id
    WHERE lm.section_id = ? AND lm.is_active = 1
    ORDER BY lm.uploaded_at DESC
";

$stmt = $pdo->prepare($materials_query);
$stmt->execute([$section_id]);
$learning_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_materials,
        SUM(file_size) as total_size,
        SUM(download_count) as total_downloads,
        MAX(uploaded_at) as latest_upload
    FROM learning_materials
    WHERE section_id = ? AND is_active = 1
";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$section_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Format total size
$total_size_formatted = '';
if ($stats['total_size']) {
    $size = $stats['total_size'];
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }
    $total_size_formatted = round($size, 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="<?=$_ENV['PAGE_ICON']?>">
        <link rel="icon" type="image/png" href="<?=$_ENV['PAGE_ICON']?>">
        <title><?=$_ENV['PAGE_HEADER']?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- PDF.js Viewer -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
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
            padding: 20px 25px;
            border-bottom: none;
        }
        
        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        @media (min-width: 768px) {
            .card-header h2 {
                font-size: 28px;
            }
            body {
                padding: 40px 20px;
            }
            .card-header {
                padding: 25px 30px;
            }
        }
        
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }
        
        .stats-icon.materials { background: #dbeafe; color: #2563eb; }
        .stats-icon.size { background: #e0f2fe; color: #0891b2; }
        .stats-icon.downloads { background: #dcfce7; color: #16a34a; }
        .stats-icon.latest { background: #fef9c3; color: #ca8a04; }
        
        .stats-number {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stats-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }
        
        @media (min-width: 768px) {
            .stats-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            .stats-number {
                font-size: 32px;
            }
            .stats-label {
                font-size: 14px;
            }
            .stats-card {
                padding: 24px;
            }
        }
        
        .upload-area {
            border: 2px dashed #cbd5e0;
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: #f5f3ff;
        }
        
        .upload-area i {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 12px;
        }
        
        .upload-area h6 {
            font-size: 16px;
            font-weight: 600;
        }
        
        .upload-area p {
            font-size: 13px;
        }
        
        @media (min-width: 768px) {
            .upload-area {
                padding: 40px;
            }
            .upload-area i {
                font-size: 48px;
            }
        }
        
        .material-card {
            background: white;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .material-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .pdf-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        @media (min-width: 768px) {
            .pdf-icon {
                width: 56px;
                height: 56px;
                font-size: 28px;
            }
        }
        
        .material-info {
            flex: 1;
            min-width: 0; /* Enable text truncation */
        }
        
        .material-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .material-title {
                font-size: 18px;
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }
        
        .material-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .material-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .badge-pdf {
            background: #fee2e2;
            color: #dc2626;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            justify-content: center;
        }
        
        @media (min-width: 576px) {
            .btn-action {
                width: auto;
            }
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .action-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }
        
        @media (min-width: 576px) {
            .action-group {
                flex-direction: row;
                width: auto;
            }
        }
        
        /* PDF Modal */
        .pdf-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            overflow-y: auto;
            padding: 16px;
        }
        
        .pdf-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pdf-modal-content {
            background: white;
            width: 100%;
            max-width: 900px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            margin: auto;
        }
        
        .pdf-modal-header {
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pdf-modal-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 10px;
        }
        
        @media (min-width: 768px) {
            .pdf-modal-header h5 {
                font-size: 18px;
            }
        }
        
        .pdf-viewer-container {
            padding: 20px;
            background: #f1f5f9;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .pdf-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .pdf-page-info {
            background: white;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .pdf-canvas {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8fafc;
            border-radius: 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        @media (min-width: 768px) {
            .empty-state {
                padding: 60px 20px;
            }
            .empty-state i {
                font-size: 64px;
            }
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            left: 20px;
            z-index: 99999;
            max-width: 400px;
            margin: 0 auto;
        }
        
        @media (min-width: 768px) {
            .toast-notification {
                left: auto;
                margin: 0;
            }
        }
        
        .section-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filename-edit {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .filename-text {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
        }
        
        @media (max-width: 575.98px) {
            .material-card .row {
                flex-direction: column;
            }
            
            .action-group {
                margin-top: 12px;
            }
            
            .pdf-modal-content {
                margin: 10px;
                max-height: 90vh;
            }
            
            .pdf-viewer-container {
                padding: 12px;
                min-height: 300px;
            }
            
            .pdf-controls {
                gap: 8px;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if (isset($error_message)): ?>
            <div class="main-card card">
                <div class="card-body p-4 p-md-5 text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                    <h4 class="mt-4">Access Restricted</h4>
                    <p class="text-muted"><?= htmlspecialchars($error_message) ?></p>
                    <a href="logout.php" class="btn btn-primary mt-3">Logout</a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Main Card -->
            <div class="main-card card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <h2>
                        <i class="bi bi-journal-bookmark-fill"></i>
                        Learning Materials
                    </h2>
                    <div class="d-flex flex-wrap align-items-center gap-3 w-100 w-md-auto">
                        <span class="section-badge w-100 w-md-auto justify-content-center justify-content-md-start">
                            <i class="bi bi-people-fill"></i>
                            Section: <?= htmlspecialchars($section_name) ?>
                        </span>
                        <a href="teacher_dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-4 w-100 w-md-auto">
                            <i class="bi bi-arrow-left me-2"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <div class="card-body p-3 p-md-4">
                    <!-- Statistics Cards -->
                    <div class="row g-3 g-md-4 mb-4 mb-md-5">
                        <div class="col-6 col-md-3">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon materials">
                                    <i class="bi bi-files"></i>
                                </div>
                                <div class="stats-number"><?= $stats['total_materials'] ?? 0 ?></div>
                                <div class="stats-label">Total Materials</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon size">
                                    <i class="bi bi-hdd-stack"></i>
                                </div>
                                <div class="stats-number"><?= $total_size_formatted ?: '0 B' ?></div>
                                <div class="stats-label">Total Size</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon downloads">
                                    <i class="bi bi-cloud-download"></i>
                                </div>
                                <div class="stats-number"><?= $stats['total_downloads'] ?? 0 ?></div>
                                <div class="stats-label">Downloads</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stats-card d-flex flex-column">
                                <div class="stats-icon latest">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="stats-number"><?= $stats['latest_upload'] ? date('M d', strtotime($stats['latest_upload'])) : 'N/A' ?></div>
                                <div class="stats-label">Latest Upload</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upload Section -->
                    <div class="mb-4 mb-md-5">
                        <h5 class="mb-3 mb-md-4 d-flex align-items-center">
                            <i class="bi bi-cloud-upload fs-4 me-2" style="color: #667eea;"></i>
                            Upload New Material
                        </h5>
                        
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row g-3 g-md-4">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">PDF File <span class="text-danger">*</span></label>
                                    <div class="upload-area" onclick="document.getElementById('pdfFileInput').click()">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <h6 class="mb-2">Click or drag PDF to upload</h6>
                                        <p class="text-muted mb-0">PDF only (Max 20MB)</p>
                                        <input type="file" id="pdfFileInput" name="learning_file" style="display: none;" accept=".pdf" required>
                                    </div>
                                    <div id="filePreview" class="mt-3 p-3 bg-light rounded-3" style="display: none;">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-pdf text-danger fs-3 me-3"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold" id="previewFileName"></div>
                                                <small class="text-muted" id="previewFileSize"></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFileSelection()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Custom Filename (Optional)</label>
                                        <input type="text" class="form-control" name="custom_filename" id="customFilename" placeholder="e.g., Module-1-Introduction">
                                        <div class="form-text">Leave empty to use auto-generated filename. .pdf will be added automatically.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Description (Optional)</label>
                                        <textarea class="form-control" name="description" id="materialDescription" rows="3" placeholder="Brief description of this learning material..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100 py-2 py-md-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                        <i class="bi bi-upload me-2"></i>
                                        Upload Learning Material
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Learning Materials List -->
                    <div class="mt-4 mt-md-5">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="bi bi-files fs-4 me-2" style="color: #667eea;"></i>
                                Materials Library
                                <span class="badge bg-secondary ms-3 rounded-pill"><?= count($learning_materials) ?></span>
                            </h5>
                            
                            <div class="d-flex gap-2 w-100 w-md-auto">
                                <input type="text" class="form-control" id="searchMaterials" placeholder="Search materials..." style="max-width: 300px;">
                                <select class="form-select" id="sortMaterials" style="width: auto;">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="name">Name A-Z</option>
                                    <option value="size">Size</option>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (empty($learning_materials)): ?>
                            <div class="empty-state">
                                <i class="bi bi-files"></i>
                                <h6 class="mt-3">No learning materials yet</h6>
                                <p class="text-muted">Upload your first PDF learning material to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3" id="materialsList">
                                <?php foreach ($learning_materials as $material): ?>
                                    <?php 
                                        $file_size = $material['file_size'] ?? filesize($material['file_path']);
                                        $size_formatted = '';
                                        if ($file_size) {
                                            $size = $file_size;
                                            $units = ['B', 'KB', 'MB'];
                                            $i = 0;
                                            while ($size >= 1024 && $i < 2) {
                                                $size /= 1024;
                                                $i++;
                                            }
                                            $size_formatted = round($size, 2) . ' ' . $units[$i];
                                        }
                                        
                                        $file_name_without_ext = pathinfo($material['file_name'], PATHINFO_FILENAME);
                                        $upload_date = date('M d, Y', strtotime($material['uploaded_at']));
                                        $teacher_name = $material['teacher_lastname'] . ', ' . $material['teacher_firstname'];
                                    ?>
                                    <div class="col-12 material-item" 
                                         data-id="<?= $material['id'] ?>"
                                         data-name="<?= strtolower($file_name_without_ext) ?>"
                                         data-date="<?= $material['uploaded_at'] ?>"
                                         data-size="<?= $file_size ?>">
                                        <div class="material-card">
                                            <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                                <!-- PDF Icon -->
                                                <div class="pdf-icon">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </div>
                                                
                                                <!-- Material Info -->
                                                <div class="material-info">
                                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                        <span class="badge-pdf">
                                                            <i class="bi bi-file-pdf me-1"></i>
                                                            PDF
                                                        </span>
                                                        <span class="text-muted small">
                                                            <i class="bi bi-person"></i>
                                                            <?= htmlspecialchars($teacher_name) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="filename-edit mb-2" id="filename-<?= $material['id'] ?>">
                                                        <span class="filename-text"><?= htmlspecialchars($file_name_without_ext) ?>.pdf</span>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="showRenameModal(<?= $material['id'] ?>, '<?= htmlspecialchars($file_name_without_ext) ?>')">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <?php if (!empty($material['description'])): ?>
                                                        <div class="material-description mb-2 text-muted" id="desc-<?= $material['id'] ?>">
                                                            <i class="bi bi-quote me-1"></i>
                                                            <?= htmlspecialchars($material['description']) ?>
                                                            <button class="btn btn-sm btn-link p-0 ms-2" onclick="editDescription(<?= $material['id'] ?>)">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="material-description mb-2 text-muted fst-italic" id="desc-<?= $material['id'] ?>">
                                                            <span class="text-muted">No description</span>
                                                            <button class="btn btn-sm btn-link p-0 ms-2" onclick="editDescription(<?= $material['id'] ?>)">
                                                                <i class="bi bi-plus-circle"></i> Add
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="material-meta">
                                                        <span>
                                                            <i class="bi bi-calendar3"></i>
                                                            <?= $upload_date ?>
                                                        </span>
                                                        <span>
                                                            <i class="bi bi-hdd"></i>
                                                            <?= $size_formatted ?>
                                                        </span>
                                                        <span>
                                                            <i class="bi bi-cloud-download"></i>
                                                            <?= $material['download_count'] ?? 0 ?> downloads
                                                        </span>
                                                        <span>
                                                            <i class="bi bi-clock"></i>
                                                            <?= time_elapsed_string($material['uploaded_at']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Actions -->
                                                <div class="action-group ms-sm-auto">
                                                    <button type="button" class="btn btn-outline-primary btn-action" onclick="viewPDF(<?= $material['id'] ?>, '<?= htmlspecialchars($file_name_without_ext . '.pdf') ?>', '<?= $material['file_path'] ?>')">
                                                        <i class="bi bi-eye"></i>
                                                        <span class="d-inline d-sm-none">View</span>
                                                    </button>
                                                    
                                                    <a href="<?= $material['file_path'] ?>" download="<?= htmlspecialchars($file_name_without_ext) ?>.pdf" class="btn btn-outline-success btn-action" onclick="incrementDownload(<?= $material['id'] ?>)">
                                                        <i class="bi bi-download"></i>
                                                        <span class="d-inline d-sm-none">Download</span>
                                                    </a>
                                                    
                                                    <button type="button" class="btn btn-outline-danger btn-action" onclick="deleteMaterial(<?= $material['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                        <span class="d-inline d-sm-none">Delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- PDF Viewer Modal -->
    <div id="pdfViewerModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h5 id="pdfModalTitle">PDF Viewer</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closePDFViewer()"></button>
            </div>
            <div class="pdf-viewer-container">
                <div class="pdf-controls">
                    <button class="btn btn-primary btn-sm" onclick="prevPage()">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="pdf-page-info" id="pageInfo">Page: <span id="currentPage">1</span> / <span id="totalPages">1</span></span>
                    <button class="btn btn-primary btn-sm" onclick="nextPage()">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomIn()">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomOut()">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="downloadCurrentPDF()">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <canvas id="pdfCanvas" class="pdf-canvas"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Rename Modal -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>
                        Rename File
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="renameMaterialId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Filename</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="newFilename" placeholder="Enter new filename">
                            <span class="input-group-text">.pdf</span>
                        </div>
                        <div class="form-text">Use only letters, numbers, hyphens, and underscores.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitRename()">Rename File</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Description Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-card-text me-2"></i>
                        Edit Description
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="descMaterialId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="materialDescText" rows="4" placeholder="Enter description for this learning material..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="submitDescription()">Save Description</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast-notification" id="toast"></div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold">Are you sure you want to delete this learning material?</p>
                    <p class="text-muted mb-0">This action cannot be undone. The PDF file will be permanently removed from the server.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // PDF.js variables
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;
        let canvas = document.getElementById('pdfCanvas');
        let ctx = canvas.getContext('2d');
        let currentPDFPath = '';
        
        // Initialize PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        
        // Time elapsed function
        function timeElapsed(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + ' years ago';
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + ' months ago';
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + ' days ago';
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + ' hours ago';
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + ' minutes ago';
            return Math.floor(seconds) + ' seconds ago';
        }
        
        // File preview
        document.getElementById('pdfFileInput')?.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const file = this.files[0];
                document.getElementById('previewFileName').textContent = file.name;
                document.getElementById('previewFileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                document.getElementById('filePreview').style.display = 'block';
                
                // Auto-fill custom filename from original filename (without extension)
                const fileNameWithoutExt = file.name.replace('.pdf', '');
                document.getElementById('customFilename').value = fileNameWithoutExt;
            }
        });
        
        function clearFileSelection() {
            document.getElementById('pdfFileInput').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('customFilename').value = '';
        }
        
        // Upload form submission
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'upload');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
            submitBtn.disabled = true;
            
            fetch('learning_materials.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // View PDF
        function viewPDF(id, filename, path) {
            currentPDFPath = path;
            document.getElementById('pdfModalTitle').textContent = filename;
            document.getElementById('pdfViewerModal').classList.add('active');
            
            // Load PDF
            pdfjsLib.getDocument(path).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('totalPages').textContent = pdfDoc.numPages;
                
                // Reset page and scale
                pageNum = 1;
                scale = 1.5;
                renderPage(pageNum);
            }).catch(function(error) {
                showToast('Error loading PDF: ' + error.message, 'error');
            });
        }
        
        function renderPage(num) {
            pageRendering = true;
            
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                
                const renderTask = page.render(renderContext);
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
            
            document.getElementById('currentPage').textContent = num;
        }
        
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }
        
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }
        
        function nextPage() {
            if (pdfDoc && pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }
        
        function zoomIn() {
            scale += 0.25;
            if (pdfDoc) {
                renderPage(pageNum);
            }
        }
        
        function zoomOut() {
            if (scale > 0.5) {
                scale -= 0.25;
                if (pdfDoc) {
                    renderPage(pageNum);
                }
            }
        }
        
        function downloadCurrentPDF() {
            if (currentPDFPath) {
                const a = document.createElement('a');
                a.href = currentPDFPath;
                a.download = document.getElementById('pdfModalTitle').textContent;
                a.click();
            }
        }
        
        function closePDFViewer() {
            document.getElementById('pdfViewerModal').classList.remove('active');
            pdfDoc = null;
        }
        
        // Rename functions
        function showRenameModal(id, currentName) {
            document.getElementById('renameMaterialId').value = id;
            document.getElementById('newFilename').value = currentName;
            new bootstrap.Modal(document.getElementById('renameModal')).show();
        }
        
        function submitRename() {
            const materialId = document.getElementById('renameMaterialId').value;
            const newFilename = document.getElementById('newFilename').value.trim();
            
            if (!newFilename) {
                showToast('Please enter a filename.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'rename');
            formData.append('material_id', materialId);
            formData.append('new_filename', newFilename);
            
            fetch('learning_materials.php', {
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
            
            bootstrap.Modal.getInstance(document.getElementById('renameModal')).hide();
        }
        
        // Edit description
        function editDescription(materialId) {
            const descElement = document.getElementById('desc-' + materialId);
            let currentDesc = '';
            
            if (descElement) {
                const textNode = descElement.childNodes[0];
                if (textNode && textNode.nodeType === 3) {
                    currentDesc = textNode.textContent.trim();
                } else {
                    currentDesc = descElement.querySelector('span')?.textContent || '';
                }
            }
            
            document.getElementById('descMaterialId').value = materialId;
            document.getElementById('materialDescText').value = currentDesc;
            new bootstrap.Modal(document.getElementById('descriptionModal')).show();
        }
        
        function submitDescription() {
            const materialId = document.getElementById('descMaterialId').value;
            const description = document.getElementById('materialDescText').value.trim();
            
            const formData = new FormData();
            formData.append('action', 'update_description');
            formData.append('material_id', materialId);
            formData.append('description', description);
            
            fetch('learning_materials.php', {
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
            
            bootstrap.Modal.getInstance(document.getElementById('descriptionModal')).hide();
        }
        
        // Delete material
        function deleteMaterial(materialId) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            confirmBtn.onclick = function() {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('material_id', materialId);
                
                fetch('learning_materials.php', {
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
        
        // Increment download count
        function incrementDownload(materialId) {
            // You can implement this with AJAX if needed
            console.log('Downloaded: ' + materialId);
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const bgColor = type === 'success' ? '#10b981' : (type === 'warning' ? '#f59e0b' : '#ef4444');
            const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');
            
            toast.innerHTML = `
                <div style="background: ${bgColor}; color: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease;">
                    <i class="bi ${icon}" style="font-size: 20px;"></i>
                    <span style="font-weight: 500; flex: 1;">${message}</span>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; opacity: 0.8; font-size: 20px;">&times;</button>
                </div>
            `;
            
            setTimeout(() => {
                if (toast.children.length > 0) {
                    toast.innerHTML = '';
                }
            }, 5000);
        }
        
        // Search functionality
        document.getElementById('searchMaterials')?.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('#materialsList .material-item');
            
            items.forEach(item => {
                const name = item.dataset.name || '';
                if (name.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Sort functionality
        document.getElementById('sortMaterials')?.addEventListener('change', function() {
            const sortBy = this.value;
            const container = document.getElementById('materialsList');
            const items = Array.from(document.querySelectorAll('#materialsList .material-item'));
            
            items.sort((a, b) => {
                switch(sortBy) {
                    case 'newest':
                        return new Date(b.dataset.date) - new Date(a.dataset.date);
                    case 'oldest':
                        return new Date(a.dataset.date) - new Date(b.dataset.date);
                    case 'name':
                        return a.dataset.name.localeCompare(b.dataset.name);
                    case 'size':
                        return parseInt(b.dataset.size) - parseInt(a.dataset.size);
                    default:
                        return 0;
                }
            });
            
            container.innerHTML = '';
            items.forEach(item => container.appendChild(item));
        });
        
        // Close modal when clicking outside
        document.getElementById('pdfViewerModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePDFViewer();
            }
        });
        
        // Keyboard shortcuts for PDF viewer
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('pdfViewerModal').classList.contains('active')) {
                if (e.key === 'Escape') {
                    closePDFViewer();
                } else if (e.key === 'ArrowLeft') {
                    prevPage();
                } else if (e.key === 'ArrowRight') {
                    nextPage();
                } else if (e.key === '+' || e.key === '=') {
                    zoomIn();
                } else if (e.key === '-') {
                    zoomOut();
                }
            }
        });
        
        // Drag and drop
        const uploadArea = document.querySelector('.upload-area');
        if (uploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => {
                    uploadArea.classList.add('border-primary', 'bg-light');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => {
                    uploadArea.classList.remove('border-primary', 'bg-light');
                }, false);
            });
            
            uploadArea.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const file = dt.files[0];
                
                if (file.type === 'application/pdf') {
                    document.getElementById('pdfFileInput').files = dt.files;
                    
                    const event = new Event('change', { bubbles: true });
                    document.getElementById('pdfFileInput').dispatchEvent(event);
                } else {
                    showToast('Only PDF files are allowed.', 'error');
                }
            }, false);
        }
        
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
    </script>
</body>
</html>

<?php
// Helper function for time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>