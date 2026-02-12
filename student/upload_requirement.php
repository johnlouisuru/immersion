<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Assuming student is logged in
$student_id = $_SESSION['user_id'];

// Get pending requirements (not completed)
$query = "
    SELECT r.id, r.req_name 
    FROM requirements r
    WHERE r.is_active = 1 
    AND r.id NOT IN (
        SELECT req_id 
        FROM requirements_status 
        WHERE student_id = ? AND is_checked = 1
    )
    ORDER BY r.req_name ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$student_id]);
$pending_requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get uploaded files with status
$files_query = "
    SELECT rs.*, r.req_name 
    FROM requirements_status rs
    JOIN requirements r ON rs.req_id = r.id
    WHERE rs.student_id = ? 
    AND rs.file_path IS NOT NULL
    ORDER BY rs.date_created DESC
";

$stmt = $pdo->prepare($files_query);
$stmt->execute([$student_id]);
$uploaded_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'upload' && isset($_FILES['requirement_file'])) {
        $req_id = $_POST['requirement_id'];
        $file = $_FILES['requirement_file'];
        
        // Validate file
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $response['message'] = 'Only PDF, JPEG, and PNG files are allowed.';
        } elseif ($file['size'] > $max_size) {
            $response['message'] = 'File size must be less than 5MB.';
        } else {
            // Create upload directory if not exists
            $upload_dir = 'uploads/requirements/' . $student_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . $req_id . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Check if entry exists
                $check_query = "SELECT id FROM requirements_status WHERE student_id = ? AND req_id = ?";
                $stmt = $pdo->prepare($check_query);
                $stmt->execute([$student_id, $req_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $is_checked = 1; // default para no need na icheck ni teacher
                    // Update existing record
                    $update_query = "UPDATE requirements_status SET file_path = ?, is_checked=?, date_created = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($update_query);
                    $stmt->execute([$file_path, $is_checked, $existing['id']]);
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO requirements_status (req_id, student_id, is_checked, file_path, date_created) 
                                    VALUES (?, ?, 1, ?, NOW())";
                    $stmt = $pdo->prepare($insert_query);
                    $stmt->execute([$req_id, $student_id, $file_path]);
                }
                
                $response['success'] = true;
                $response['message'] = 'File uploaded successfully!';
            } else {
                $response['message'] = 'Failed to upload file.';
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'delete' && isset($_POST['file_id'])) {
        $file_id = $_POST['file_id'];
        
        // Get file path
        $query = "SELECT file_path FROM requirements_status WHERE id = ? AND student_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$file_id, $student_id]);
        $file = $stmt->fetch();
        
        if ($file && file_exists($file['file_path'])) {
            unlink($file['file_path']); // Delete physical file
            
            // Update database
            $update_query = "UPDATE requirements_status SET file_path = NULL WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([$file_id]);
            
            $response['success'] = true;
            $response['message'] = 'File deleted successfully!';
        }
        
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requirements - Student Portal</title>
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
        
        .requirements-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .upload-area {
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px;
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
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .file-preview {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .requirement-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .file-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .file-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #667eea;
        }
        
        .file-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .file-icon.pdf { background: #fee2e2; color: #dc2626; }
        .file-icon.jpg, .file-icon.jpeg, .file-icon.png { background: #dbeafe; color: #2563eb; }
        
        .file-info h6 {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
        }
        
        .file-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div class="requirements-container">
        <!-- Main Card -->
        <div class="main-card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>
                    <i class="bi bi-file-earmark-text"></i>
                    My Files
                </h5>
                <span class="requirement-badge">
                    <i class="bi bi-clock-history"></i>
                    <?= count($pending_requirements) ?> Pending
                </span>
            </div>
            
            <div class="card-body p-4">
             <a href="dashboard" type="submit" class="btn btn-primary btn-lg w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                        <i class="bi bi-arrow-left-circle"></i>
                                        List of Requirements
                                    </a>
                                    <hr />
                <!-- Upload Section -->
                <div class="upload-section">
                    <h5 class="mb-4 d-flex align-items-center">
                        <i class="bi bi-cloud-upload fs-4 me-2" style="color: #667eea;"></i>
                        Upload New Requirement
                    </h5>
                    
                    <?php if (empty($pending_requirements)): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div>All requirements completed! Great job! 🎉</div>
                        </div>
                    <?php else: ?>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Requirement</label>
                                    <select class="form-select form-select-lg" name="requirement_id" id="requirementSelect" required>
                                        <option value="" selected disabled>Choose requirement</option>
                                        <?php foreach ($pending_requirements as $req): ?>
                                            <option value="<?= $req['id'] ?>"><?= htmlspecialchars($req['req_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Upload File</label>
                                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <h6 class="mb-2">Click or drag file to upload</h6>
                                        <p class="text-muted mb-0">PDF, JPEG, PNG (Max 5MB)</p>
                                        <input type="file" id="fileInput" name="requirement_file" style="display: none;" accept=".pdf,.jpg,.jpeg,.png" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                        <i class="bi bi-upload me-2"></i>
                                        Upload Requirement
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- File Preview Container -->
                        <div id="filePreview" class="file-preview" style="display: none;">
                            <div class="d-flex align-items-center">
                                <div class="file-icon pdf me-3">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1" id="previewFileName"></h6>
                                    <small class="text-muted" id="previewFileSize"></small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFileSelection()">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Alternative: Card Grid Layout for Mobile -->
<div class="mt-5">
    <h5 class="mb-4 d-flex align-items-center">
        <i class="bi bi-files fs-4 me-2" style="color: #667eea;"></i>
        My Uploaded Files
    </h5>
    
    <?php if (empty($uploaded_files)): ?>
        <div class="empty-state">
            <i class="bi bi-file-earmark-x"></i>
            <h6 class="mt-3">No files uploaded yet</h6>
            <p class="text-muted">Upload your requirements to get started</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($uploaded_files as $file): ?>
                <?php 
                    $file_extension = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                    $file_name = basename($file['file_path']);
                    $file_size = file_exists($file['file_path']) ? filesize($file['file_path']) : 0;
                    $file_size_formatted = $file_size ? round($file_size / 1024, 2) . ' KB' : 'Unknown';
                    $status_class = $file['is_checked'] == 1 ? 'status-approved' : ($file['is_checked'] == 2 ? 'status-rejected' : 'status-pending');
                    $status_text = $file['is_checked'] == 1 ? 'Approved' : ($file['is_checked'] == 2 ? 'Rejected' : 'Pending Review');
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="file-card h-100 d-flex flex-column">
                        <!-- Header with Icon and Status -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="file-icon <?= strtolower($file_extension) ?>">
                                <i class="bi bi-file-earmark-<?= $file_extension === 'pdf' ? 'pdf' : 'image' ?>"></i>
                            </div>
                            <span class="status-badge <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2"><?= htmlspecialchars($file['req_name']) ?></h6>
                            <div class="file-meta small">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-file-earmark me-2 text-muted"></i>
                                    <span class="text-truncate"><?= htmlspecialchars($file_name) ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-hdd me-2 text-muted"></i>
                                    <span><?= $file_size_formatted ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar me-2 text-muted"></i>
                                    <span><?= date('M d, Y', strtotime($file['date_created'])) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex gap-2 mt-3 pt-2 border-top">
                            <?php if (file_exists($file['file_path'])): ?>
                                <a href="<?= $file['file_path'] ?>" class="btn btn-outline-primary btn-action flex-fill" target="_blank">
                                    <i class="bi bi-eye"></i>
                                    <span class="d-none d-sm-inline ms-1">View</span>
                                </a>
                                <button type="button" class="btn btn-outline-warning btn-action flex-fill" onclick="editFile(<?= $file['id'] ?>, <?= $file['req_id'] ?>, '<?= htmlspecialchars($file['req_name'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil"></i>
                                    <span class="d-none d-sm-inline ms-1">Edit</span>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-action flex-fill" onclick="deleteFile(<?= $file['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                    <span class="d-none d-sm-inline ms-1">Delete</span>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($file['is_checked'] == 0): ?>
                                <button type="button" class="btn btn-outline-warning btn-action flex-fill" onclick="editFile(<?= $file['id'] ?>, <?= $file['req_id'] ?>, '<?= htmlspecialchars($file['req_name'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil"></i>
                                    <span class="d-none d-sm-inline ms-1">Edit</span>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-action flex-fill" onclick="deleteFile(<?= $file['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                    <span class="d-none d-sm-inline ms-1">Delete</span>
                                </button>
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
    
    <!-- Toast Notification -->
    <div class="toast-notification" id="toast"></div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit Requirement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" enctype="multipart/form-data">
                        <input type="hidden" name="file_id" id="editFileId">
                        <input type="hidden" name="requirement_id" id="editReqId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Requirement</label>
                            <input type="text" class="form-control" id="editReqName" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Upload New File</label>
                            <input type="file" class="form-control" name="requirement_file" id="editFileInput" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Upload a new file to replace the existing one.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitEdit()">Update File</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload preview
        document.getElementById('fileInput')?.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const file = this.files[0];
                document.getElementById('previewFileName').textContent = file.name;
                document.getElementById('previewFileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
                
                // Update file icon based on type
                const fileIcon = document.querySelector('#filePreview .file-icon');
                if (file.type.includes('pdf')) {
                    fileIcon.className = 'file-icon pdf me-3';
                    fileIcon.innerHTML = '<i class="bi bi-file-earmark-pdf"></i>';
                } else {
                    fileIcon.className = 'file-icon jpg me-3';
                    fileIcon.innerHTML = '<i class="bi bi-file-earmark-image"></i>';
                }
                
                document.getElementById('filePreview').style.display = 'block';
            }
        });
        
        function clearFileSelection() {
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').style.display = 'none';
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
            
            fetch('upload_requirement.php', {
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
        
        // Delete file
        function deleteFile(fileId) {
            if (confirm('Are you sure you want to delete this file?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('file_id', fileId);
                
                fetch('upload_requirement.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1000);
                    }
                });
            }
        }
        
        // Edit file
        function editFile(fileId, reqId, reqName) {
            document.getElementById('editFileId').value = fileId;
            document.getElementById('editReqId').value = reqId;
            document.getElementById('editReqName').value = reqName;
            document.getElementById('editFileInput').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }
        
        // Submit edit
        function submitEdit() {
            const formData = new FormData(document.getElementById('editForm'));
            formData.append('action', 'upload');
            
            if (!formData.get('requirement_file').size) {
                showToast('Please select a file to upload.', 'error');
                return;
            }
            
            fetch('upload_requirement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            });
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const bgColor = type === 'success' ? '#10b981' : '#ef4444';
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
            
            toast.innerHTML = `
                <div style="background: ${bgColor}; color: white; padding: 16px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease;">
                    <i class="bi ${icon}" style="font-size: 20px;"></i>
                    <span style="font-weight: 500;">${message}</span>
                </div>
            `;
            
            setTimeout(() => {
                toast.innerHTML = '';
            }, 3000);
        }
        
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
                document.getElementById('fileInput').files = dt.files;
                
                // Trigger change event
                const event = new Event('change', { bubbles: true });
                document.getElementById('fileInput').dispatchEvent(event);
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