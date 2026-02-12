<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}


$student_id = $_SESSION['user_id'];

// Get student information
$student_query = "SELECT id, lastname, firstname, email, section_id FROM students WHERE id = ?";
$stmt = $pdo->prepare($student_query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Create upload directory for journals
$upload_dir = 'uploads/journals/' . $student_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Check if date already has journal
    if ($_POST['action'] === 'check_date') {
        $check_date = $_POST['journal_date'];
        $check_query = "SELECT id FROM student_journals WHERE student_id = ? AND journal_date = ?";
        $stmt = $pdo->prepare($check_query);
        $stmt->execute([$student_id, $check_date]);
        
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['exists'] = true;
            $response['message'] = 'You already have a journal entry for this date.';
        } else {
            $response['success'] = true;
            $response['exists'] = false;
        }
        echo json_encode($response);
        exit;
    }
    
    // Save journal
    if ($_POST['action'] === 'save_journal') {
        $journal_date = $_POST['journal_date'];
        $title = trim($_POST['title']);
        $reflection = $_POST['reflection'];
        $mood = $_POST['mood'] ?? null;
        $location = $_POST['location'] ?? null;
        
        // Validate
        if (empty($title)) {
            $response['message'] = 'Please enter a journal title.';
            echo json_encode($response);
            exit;
        }
        
        if (empty($reflection)) {
            $response['message'] = 'Please write your reflection.';
            echo json_encode($response);
            exit;
        }
        
        // Check if journal already exists for this date
        $check_query = "SELECT id FROM student_journals WHERE student_id = ? AND journal_date = ?";
        $stmt = $pdo->prepare($check_query);
        $stmt->execute([$student_id, $journal_date]);
        
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'You already have a journal entry for this date.';
            echo json_encode($response);
            exit;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert journal
            $insert_query = "
                INSERT INTO student_journals 
                (student_id, journal_date, title, reflection, mood, location, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([$student_id, $journal_date, $title, $reflection, $mood, $location]);
            
            $journal_id = $pdo->lastInsertId();
            
            // Process images
            if (!empty($_POST['images'])) {
                $images = json_decode($_POST['images'], true);
                
                $image_query = "
                    INSERT INTO journal_images 
                    (journal_id, image_path, image_name, file_size, sort_order) 
                    VALUES (?, ?, ?, ?, ?)
                ";
                $img_stmt = $pdo->prepare($image_query);
                
                foreach ($images as $index => $image) {
                    $img_stmt->execute([
                        $journal_id,
                        $image['path'],
                        $image['name'],
                        $image['size'],
                        $index
                    ]);
                }
            }
            
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Journal saved successfully!';
            $response['journal_id'] = $journal_id;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['message'] = 'Failed to save journal: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Update journal
    if ($_POST['action'] === 'update_journal' && isset($_POST['journal_id'])) {
        $journal_id = $_POST['journal_id'];
        $title = trim($_POST['title']);
        $reflection = $_POST['reflection'];
        $mood = $_POST['mood'] ?? null;
        $location = $_POST['location'] ?? null;
        
        // Verify ownership
        $check_query = "SELECT id FROM student_journals WHERE id = ? AND student_id = ?";
        $stmt = $pdo->prepare($check_query);
        $stmt->execute([$journal_id, $student_id]);
        
        if ($stmt->rowCount() === 0) {
            $response['message'] = 'Journal not found or access denied.';
            echo json_encode($response);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Update journal
            $update_query = "
                UPDATE student_journals 
                SET title = ?, reflection = ?, mood = ?, location = ?, updated_at = NOW() 
                WHERE id = ? AND student_id = ?
            ";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([$title, $reflection, $mood, $location, $journal_id, $student_id]);
            
            // Handle images
            if (isset($_POST['images'])) {
                // Delete old images
                $delete_query = "DELETE FROM journal_images WHERE journal_id = ?";
                $stmt = $pdo->prepare($delete_query);
                $stmt->execute([$journal_id]);
                
                // Insert new images
                $images = json_decode($_POST['images'], true);
                
                if (!empty($images)) {
                    $image_query = "
                        INSERT INTO journal_images 
                        (journal_id, image_path, image_name, file_size, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ";
                    $img_stmt = $pdo->prepare($image_query);
                    
                    foreach ($images as $index => $image) {
                        $img_stmt->execute([
                            $journal_id,
                            $image['path'],
                            $image['name'],
                            $image['size'],
                            $index
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Journal updated successfully!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['message'] = 'Failed to update journal: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Upload image
    if ($_POST['action'] === 'upload_image' && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        
        // Validate image
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $response['message'] = 'Only image files (JPEG, PNG, GIF, WEBP) are allowed.';
        } elseif ($file['size'] > $max_size) {
            $response['message'] = 'Image size must be less than 10MB.';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $response['success'] = true;
                $response['path'] = $filepath;
                $response['name'] = $file['name'];
                $response['size'] = $file['size'];
                $response['url'] = $filepath;
            } else {
                $response['message'] = 'Failed to upload image.';
            }
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Delete image
    if ($_POST['action'] === 'delete_image' && isset($_POST['image_path'])) {
        $image_path = $_POST['image_path'];
        
        // Security: Only delete if file is in student's directory
        if (strpos($image_path, $upload_dir) === 0 && file_exists($image_path)) {
            unlink($image_path);
            $response['success'] = true;
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Get journal for editing
    if ($_POST['action'] === 'get_journal' && isset($_POST['journal_id'])) {
        $journal_id = $_POST['journal_id'];
        
        $query = "
            SELECT j.*, 
                   GROUP_CONCAT(ji.id, '|', ji.image_path, '|', ji.image_name, '|', ji.file_size) as images
            FROM student_journals j
            LEFT JOIN journal_images ji ON j.id = ji.journal_id
            WHERE j.id = ? AND j.student_id = ?
            GROUP BY j.id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$journal_id, $student_id]);
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($journal) {
            // Parse images
            $images = [];
            if ($journal['images']) {
                $image_parts = explode(',', $journal['images']);
                foreach ($image_parts as $part) {
                    $img = explode('|', $part);
                    if (count($img) >= 4) {
                        $images[] = [
                            'id' => $img[0],
                            'path' => $img[1],
                            'name' => $img[2],
                            'size' => $img[3],
                            'url' => $img[1]
                        ];
                    }
                }
            }
            
            $journal['images'] = $images;
            $response['success'] = true;
            $response['journal'] = $journal;
        } else {
            $response['message'] = 'Journal not found.';
        }
        
        echo json_encode($response);
        exit;
    }
}

// Get existing journals for this student
$journals_query = "
    SELECT j.*, COUNT(ji.id) as image_count
    FROM student_journals j
    LEFT JOIN journal_images ji ON j.id = ji.journal_id
    WHERE j.student_id = ?
    GROUP BY j.id
    ORDER BY j.journal_date DESC
    LIMIT 10
";
$stmt = $pdo->prepare($journals_query);
$stmt->execute([$student_id]);
$existing_journals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title>Daily Journal - Student Portal</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Cropper.js for Image Cropping -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    
    <!-- SortableJS for Drag & Drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
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
        
        .journal-container {
            max-width: 1200px;
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
            body { padding: 30px; }
            .card-header h2 { font-size: 28px; }
            .card-header { padding: 25px 30px; }
        }
        
        /* Date Picker */
        .date-picker-container {
            position: relative;
        }
        
        .date-picker-container i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            z-index: 10;
        }
        
        .date-picker-container input {
            padding-left: 48px;
            height: 54px;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .date-picker-container input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Mood Selector */
        .mood-selector {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .mood-btn {
            flex: 1 0 calc(20% - 10px);
            min-width: 80px;
            padding: 12px 8px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            background: white;
            color: #64748b;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        
        .mood-btn i {
            font-size: 24px;
        }
        
        .mood-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea10, #764ba210);
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .mood-btn span {
            font-size: 12px;
            font-weight: 600;
        }
        
        /* MODERN TEXT EDITOR - CUSTOM STYLED TEXTAREA */
        .text-editor-wrapper {
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .text-editor-wrapper:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .editor-toolbar {
            background: #f8fafc;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .toolbar-group {
            display: flex;
            gap: 4px;
            padding: 0 8px;
            border-right: 2px solid #e2e8f0;
        }
        
        .toolbar-group:last-child {
            border-right: none;
        }
        
        .toolbar-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .toolbar-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .toolbar-btn.active {
            background: #667eea;
            color: white;
        }
        
        .toolbar-btn i {
            font-size: 18px;
        }
        
        .editor-textarea {
            width: 100%;
            min-height: 300px;
            padding: 20px;
            border: none;
            outline: none;
            resize: vertical;
            font-size: 16px;
            line-height: 1.7;
            color: #1e293b;
            background: white;
            font-family: 'Inter', sans-serif;
        }
        
        .editor-textarea::placeholder {
            color: #94a3b8;
            font-style: italic;
        }
        
        .editor-statusbar {
            background: #f8fafc;
            padding: 10px 16px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #64748b;
        }
        
        .word-count {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .word-count span {
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Image Upload Area */
        .image-upload-area {
            border: 2px dashed #cbd5e0;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-upload-area:hover {
            border-color: #667eea;
            background: #f5f3ff;
        }
        
        .image-upload-area i {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 12px;
        }
        
        .image-upload-area h6 {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        /* Image Preview Grid */
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        .image-preview-item {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: move;
        }
        
        .image-preview-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .image-preview-item:hover .image-preview-overlay {
            opacity: 1;
        }
        
        .image-preview-overlay button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-crop {
            background: #3498db;
        }
        
        .btn-delete {
            background: #e74c3c;
        }
        
        .image-counter {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-top: 8px;
        }
        
        /* Cropper Modal */
        .cropper-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 99999;
            padding: 20px;
        }
        
        .cropper-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cropper-content {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }
        
        .cropper-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cropper-body {
            padding: 20px;
        }
        
        .cropper-image-container {
            max-height: 400px;
            overflow: hidden;
        }
        
        .cropper-image-container img {
            max-width: 100%;
        }
        
        /* Journal Cards */
        .journal-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .journal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .journal-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        /* Responsive */
        @media (max-width: 767.98px) {
            .mood-btn {
                flex: 1 0 calc(33.333% - 10px);
            }
            
            .toolbar-group {
                border-right: none;
                padding: 0 4px;
            }
            
            .toolbar-btn {
                width: 32px;
                height: 32px;
            }
            
            .image-preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 12px;
            }
        }
        
        @media (max-width: 575.98px) {
            .mood-btn {
                flex: 1 0 calc(50% - 10px);
            }
            
            .card-header h2 {
                font-size: 20px;
            }
            
            .editor-toolbar {
                padding: 8px;
                justify-content: center;
            }
            
            .toolbar-group {
                padding: 0 2px;
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
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 999999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="journal-container">
        <div class="main-card card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <h2>
                    <i class="bi bi-journal-bookmark-fill"></i>
                    Daily Journal
                </h2>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="badge bg-light text-dark p-3 rounded-pill">
                        <i class="bi bi-person-circle me-2"></i>
                        <?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?>
                    </span>
                    <a href="student_dashboard" class="btn btn-outline-light btn-sm rounded-pill px-4">
                        <i class="bi bi-arrow-left me-2"></i>
                        Back
                    </a>
                    <a href="view_journals" class="btn btn-outline-light btn-sm rounded-pill px-4">
                        <i class="bi bi-eye me-2"></i>
                        View
                    </a>
                </div>
            </div>
            
            <div class="card-body p-4">
                <!-- Journal Form -->
                <div class="journal-form-section">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                            <i class="bi bi-pencil-square fs-4" style="color: #667eea;"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">Create New Journal Entry</h5>
                            <p class="text-muted mb-0">Share your thoughts, experiences, and reflections for today</p>
                        </div>
                    </div>
                    
                    <form id="journalForm">
                        <input type="hidden" id="journalId" name="journal_id" value="">
                        
                        <div class="row g-4">
                            <!-- Date and Title -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar3 me-2" style="color: #667eea;"></i>
                                    Journal Date <span class="text-danger">*</span>
                                </label>
                                <div class="date-picker-container">
                                    <i class="bi bi-calendar"></i>
                                    <input type="text" 
                                           class="form-control" 
                                           id="journalDate" 
                                           name="journal_date" 
                                           placeholder="Select date"
                                           required>
                                </div>
                                <div id="dateWarning" class="form-text text-warning mt-2" style="display: none;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    <span></span>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-type me-2" style="color: #667eea;"></i>
                                    Journal Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="journalTitle" 
                                       name="title" 
                                       placeholder="e.g., My First Day of Immersion"
                                       required>
                            </div>
                            
                            <!-- Mood Selector -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-emoji-smile me-2" style="color: #667eea;"></i>
                                    How are you feeling today?
                                </label>
                                <div class="mood-selector">
                                    <button type="button" class="mood-btn" data-mood="happy">
                                        <i class="bi bi-emoji-smile"></i>
                                        <span>Happy</span>
                                    </button>
                                    <button type="button" class="mood-btn" data-mood="excited">
                                        <i class="bi bi-emoji-sunglasses"></i>
                                        <span>Excited</span>
                                    </button>
                                    <button type="button" class="mood-btn" data-mood="neutral">
                                        <i class="bi bi-emoji-neutral"></i>
                                        <span>Neutral</span>
                                    </button>
                                    <button type="button" class="mood-btn" data-mood="tired">
                                        <i class="bi bi-emoji-tired"></i>🫩
                                        <span>Tired</span>
                                    </button>
                                    <button type="button" class="mood-btn" data-mood="stressed">
                                        <i class="bi bi-emoji-dizzy"></i>
                                        <span>Stressed</span>
                                    </button>
                                    <input type="hidden" id="mood" name="mood">
                                </div>
                            </div>
                            
                            <!-- Location -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-geo-alt me-2" style="color: #667eea;"></i>
                                    Location (Optional)
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       placeholder="e.g., School, Office, Home">
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-images me-2" style="color: #667eea;"></i>
                                    Images (Max 5)
                                </label>
                                <div id="imageUploadArea" class="image-upload-area">
                                    <i class="bi bi-cloud-upload"></i>
                                    <h6 class="mb-1">Click or drag images to upload</h6>
                                    <p class="text-muted small mb-2">JPEG, PNG, GIF, WEBP (Max 10MB each)</p>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                        <span id="imageCount">0</span>/5 images
                                    </span>
                                    <input type="file" 
                                           id="imageInput" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                                           multiple
                                           style="display: none;">
                                </div>
                                
                                <!-- Image Preview Grid -->
                                <div id="imagePreviewGrid" class="image-preview-grid"></div>
                                <input type="hidden" id="imageData" name="images">
                            </div>
                            
                            <!-- MODERN TEXT EDITOR - Reflection -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-pencil me-2" style="color: #667eea;"></i>
                                    Reflection <span class="text-danger">*</span>
                                </label>
                                <div class="text-editor-wrapper">
                                    <!-- Editor Toolbar -->
                                    <div class="editor-toolbar">
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="formatText('bold')" title="Bold">
                                                <i class="bi bi-type-bold"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('italic')" title="Italic">
                                                <i class="bi bi-type-italic"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('underline')" title="Underline">
                                                <i class="bi bi-type-underline"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('strikethrough')" title="Strikethrough">
                                                <i class="bi bi-type-strikethrough"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="formatText('justifyLeft')" title="Align Left">
                                                <i class="bi bi-text-left"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('justifyCenter')" title="Align Center">
                                                <i class="bi bi-text-center"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('justifyRight')" title="Align Right">
                                                <i class="bi bi-text-right"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="formatText('insertUnorderedList')" title="Bullet List">
                                                <i class="bi bi-list-ul"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('insertOrderedList')" title="Numbered List">
                                                <i class="bi bi-list-ol"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="formatText('h1')" title="Heading 1">
                                                H1
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('h2')" title="Heading 2">
                                                H2
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('h3')" title="Heading 3">
                                                H3
                                            </button>
                                        </div>
                                        
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="formatText('undo')" title="Undo">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                            <button type="button" class="toolbar-btn" onclick="formatText('redo')" title="Redo">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="toolbar-group">
                                            <button type="button" class="toolbar-btn" onclick="insertEmoji()" title="Insert Emoji">
                                                <i class="bi bi-emoji-smile"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Textarea -->
                                    <textarea id="reflectionText" class="editor-textarea" placeholder="Write your reflection here... What did you learn today? What challenges did you face? How did you feel?" required></textarea>
                                    
                                    <!-- Status Bar -->
                                    <div class="editor-statusbar">
                                        <span>Ready</span>
                                        <div class="word-count">
                                            <i class="bi bi-fonts"></i>
                                            Words: <span id="wordCount">0</span>
                                            <span class="mx-2">|</span>
                                            <i class="bi bi-clock"></i>
                                            Characters: <span id="charCount">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="col-12 mt-4">
                                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary px-5 py-3 rounded-pill" onclick="resetForm()">
                                        <i class="bi bi-x-circle me-2"></i>
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                                        <i class="bi bi-check-circle me-2"></i>
                                        Save Journal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Journals -->
                <?php if (!empty($existing_journals)): ?>
                <hr class="my-5">
                
                <div class="recent-journals-section">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-info bg-opacity-10 p-3 rounded-3">
                            <i class="bi bi-clock-history fs-4" style="color: #3498db;"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">Recent Journal Entries</h5>
                            <p class="text-muted mb-0">Your latest reflections and experiences</p>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <?php foreach ($existing_journals as $journal): ?>
                            <?php
                            // Get first image for thumbnail
                            $thumb_query = "SELECT image_path FROM journal_images WHERE journal_id = ? LIMIT 1";
                            $stmt = $pdo->prepare($thumb_query);
                            $stmt->execute([$journal['id']]);
                            $thumb = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="journal-card h-100">
                                    <div class="d-flex gap-3">
                                        <?php if ($thumb): ?>
                                            <img src="<?= htmlspecialchars($thumb['image_path']) ?>" 
                                                 class="journal-thumbnail" 
                                                 alt="Journal thumbnail">
                                        <?php else: ?>
                                            <div class="journal-thumbnail bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-journal-text fs-2" style="color: #94a3b8;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="fw-bold mb-1 text-truncate" style="max-width: 150px;">
                                                        <?= htmlspecialchars($journal['title']) ?>
                                                    </h6>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?= date('M d, Y', strtotime($journal['journal_date'])) ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="bi bi-images me-1"></i>
                                                        <?= $journal['image_count'] ?> images
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary rounded-circle p-2" 
                                                        onclick="editJournal(<?= $journal['id'] ?>)"
                                                        title="Edit journal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Cropper Modal -->
    <div id="cropperModal" class="cropper-modal">
        <div class="cropper-content">
            <div class="cropper-header">
                <h5 class="mb-0"><i class="bi bi-crop me-2"></i>Crop Image</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeCropper()"></button>
            </div>
            <div class="cropper-body">
                <div class="cropper-image-container">
                    <img id="cropperImage" src="" alt="Crop image">
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-outline-secondary rounded-pill px-4" onclick="closeCropper()">Cancel</button>
                    <button class="btn btn-primary rounded-pill px-4" onclick="applyCrop()" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                        <i class="bi bi-check-lg me-2"></i>Apply Crop
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <div class="text-muted fw-semibold">Saving your journal...</div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toastNotification" class="toast-notification"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ============================================
        // GLOBAL VARIABLES
        // ============================================
        let uploadedImages = [];
        let cropper = null;
        let currentCroppingImage = null;
        const reflectionText = document.getElementById('reflectionText');
        
        // ============================================
        // DATE PICKER INITIALIZATION
        // ============================================
        flatpickr("#journalDate", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            defaultDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                checkExistingJournal(dateStr);
            }
        });
        
        // ============================================
        // MODERN TEXT EDITOR FUNCTIONS
        // ============================================
        function formatText(command) {
            const textarea = reflectionText;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let formattedText = '';
            
            switch(command) {
                case 'bold':
                    formattedText = `**${selectedText}**`;
                    break;
                case 'italic':
                    formattedText = `*${selectedText}*`;
                    break;
                case 'underline':
                    formattedText = `<u>${selectedText}</u>`;
                    break;
                case 'strikethrough':
                    formattedText = `~~${selectedText}~~`;
                    break;
                case 'h1':
                    formattedText = `# ${selectedText}`;
                    break;
                case 'h2':
                    formattedText = `## ${selectedText}`;
                    break;
                case 'h3':
                    formattedText = `### ${selectedText}`;
                    break;
                case 'justifyLeft':
                case 'justifyCenter':
                case 'justifyRight':
                    // For simplicity, we'll just add alignment markers
                    formattedText = selectedText;
                    break;
                case 'insertUnorderedList':
                    formattedText = selectedText.split('\n').map(line => `• ${line}`).join('\n');
                    break;
                case 'insertOrderedList':
                    formattedText = selectedText.split('\n').map((line, i) => `${i + 1}. ${line}`).join('\n');
                    break;
                case 'undo':
                    document.execCommand('undo', false, null);
                    return;
                case 'redo':
                    document.execCommand('redo', false, null);
                    return;
                default:
                    return;
            }
            
            // Insert formatted text
            textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
            
            // Update word count
            updateWordCount();
            
            // Focus back to textarea
            textarea.focus();
        }
        
        function insertEmoji() {
            const emoji = prompt('Enter emoji name (e.g., smile, heart, thumbsup) or paste emoji directly:', '😊');
            if (emoji) {
                const textarea = reflectionText;
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                
                textarea.value = textarea.value.substring(0, start) + emoji + textarea.value.substring(end);
                updateWordCount();
                textarea.focus();
            }
        }
        
        function updateWordCount() {
            const text = reflectionText.value;
            const words = text.trim() ? text.trim().split(/\s+/).length : 0;
            const chars = text.length;
            
            document.getElementById('wordCount').textContent = words;
            document.getElementById('charCount').textContent = chars;
        }
        
        // Update word count on input
        reflectionText.addEventListener('input', updateWordCount);
        reflectionText.addEventListener('keyup', updateWordCount);
        
        // ============================================
        // CHECK EXISTING JOURNAL
        // ============================================
        function checkExistingJournal(date) {
            if (!date) return;
            
            const formData = new FormData();
            formData.append('action', 'check_date');
            formData.append('journal_date', date);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const warningDiv = document.getElementById('dateWarning');
                if (data.exists) {
                    warningDiv.style.display = 'block';
                    warningDiv.querySelector('span').textContent = data.message;
                    document.querySelector('button[type="submit"]').disabled = true;
                } else {
                    warningDiv.style.display = 'none';
                    document.querySelector('button[type="submit"]').disabled = false;
                }
            });
        }
        
        // ============================================
        // MOOD SELECTOR
        // ============================================
        document.querySelectorAll('.mood-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('mood').value = this.dataset.mood;
            });
        });
        
        // ============================================
        // IMAGE UPLOAD HANDLER
        // ============================================
        const uploadArea = document.getElementById('imageUploadArea');
        const imageInput = document.getElementById('imageInput');
        
        uploadArea.addEventListener('click', function() {
            if (uploadedImages.length < 5) {
                imageInput.click();
            } else {
                showToast('Maximum 5 images allowed', 'warning');
            }
        });
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                this.classList.add('border-primary', 'bg-light');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                this.classList.remove('border-primary', 'bg-light');
            }, false);
        });
        
        uploadArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleImageUpload(files);
        }, false);
        
        imageInput.addEventListener('change', function(e) {
            handleImageUpload(this.files);
        });
        
        // ============================================
        // HANDLE IMAGE UPLOAD
        // ============================================
        function handleImageUpload(files) {
            const remainingSlots = 5 - uploadedImages.length;
            const filesToUpload = Array.from(files).slice(0, remainingSlots);
            
            filesToUpload.forEach(file => {
                if (!file.type.match('image.*')) {
                    showToast(`${file.name} is not an image`, 'error');
                    return;
                }
                
                if (file.size > 10 * 1024 * 1024) {
                    showToast(`${file.name} exceeds 10MB limit`, 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'upload_image');
                formData.append('image', file);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadedImages.push(data);
                        renderImagePreviews();
                        
                        // Open cropper for the new image
                        setTimeout(() => {
                            openCropper(data.url, uploadedImages.length - 1);
                        }, 100);
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            });
            
            // Clear input
            imageInput.value = '';
        }
        
        // ============================================
        // RENDER IMAGE PREVIEWS
        // ============================================
        function renderImagePreviews() {
            const grid = document.getElementById('imagePreviewGrid');
            grid.innerHTML = '';
            
            uploadedImages.forEach((img, index) => {
                const item = document.createElement('div');
                item.className = 'image-preview-item';
                item.dataset.index = index;
                
                item.innerHTML = `
                    <img src="${img.url}" alt="Preview ${index + 1}">
                    <div class="image-preview-overlay">
                        <button type="button" class="btn-crop" onclick="openCropper('${img.url}', ${index})">
                            <i class="bi bi-crop"></i>
                        </button>
                        <button type="button" class="btn-delete" onclick="deleteImage(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                
                grid.appendChild(item);
            });
            
            document.getElementById('imageCount').textContent = uploadedImages.length;
            
            // Update hidden input
            document.getElementById('imageData').value = JSON.stringify(uploadedImages);
        }
        
        // ============================================
        // IMAGE CROPPER
        // ============================================
        function openCropper(imageUrl, index) {
            currentCroppingImage = { url: imageUrl, index: index };
            
            const modal = document.getElementById('cropperModal');
            const img = document.getElementById('cropperImage');
            
            modal.classList.add('active');
            img.src = imageUrl;
            
            setTimeout(() => {
                if (cropper) {
                    cropper.destroy();
                }
                
                cropper = new Cropper(img, {
                    aspectRatio: NaN,
                    viewMode: 1,
                    dragMode: 'move',
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
            }, 100);
        }
        
        function applyCrop() {
            if (cropper && currentCroppingImage) {
                const canvas = cropper.getCroppedCanvas({
                    width: 800,
                    height: 600
                });
                
                canvas.toBlob(function(blob) {
                    // Create file from blob
                    const file = new File([blob], 'cropped_image.jpg', { type: 'image/jpeg' });
                    
                    // Upload cropped image
                    const formData = new FormData();
                    formData.append('action', 'upload_image');
                    formData.append('image', file);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Delete old image
                            if (uploadedImages[currentCroppingImage.index]) {
                                deleteImage(currentCroppingImage.index, false);
                            }
                            
                            // Add new cropped image
                            uploadedImages.push(data);
                            renderImagePreviews();
                            
                            showToast('Image cropped successfully', 'success');
                            closeCropper();
                        }
                    });
                }, 'image/jpeg', 0.9);
            }
        }
        
        function closeCropper() {
            document.getElementById('cropperModal').classList.remove('active');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            currentCroppingImage = null;
        }
        
        // ============================================
        // DELETE IMAGE
        // ============================================
        function deleteImage(index, showToastMessage = true) {
            const image = uploadedImages[index];
            
            // Delete from server
            const formData = new FormData();
            formData.append('action', 'delete_image');
            formData.append('image_path', image.path);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    uploadedImages.splice(index, 1);
                    renderImagePreviews();
                    if (showToastMessage) {
                        showToast('Image deleted successfully', 'success');
                    }
                }
            });
        }
        
        // ============================================
        // SORTABLE DRAG AND DROP
        // ============================================
        new Sortable(document.getElementById('imagePreviewGrid'), {
            animation: 150,
            onEnd: function(evt) {
                const movedImage = uploadedImages.splice(evt.oldIndex, 1)[0];
                uploadedImages.splice(evt.newIndex, 0, movedImage);
                renderImagePreviews();
            }
        });
        
        // ============================================
        // FORM SUBMISSION
        // ============================================
        document.getElementById('journalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get reflection text
            const reflection = reflectionText.value;
            
            if (!reflection.trim()) {
                showToast('Please write your reflection', 'error');
                return;
            }
            
            const formData = new FormData();
            const journalId = document.getElementById('journalId').value;
            
            formData.append('action', journalId ? 'update_journal' : 'save_journal');
            formData.append('journal_date', document.getElementById('journalDate').value);
            formData.append('title', document.getElementById('journalTitle').value);
            formData.append('reflection', reflection);
            formData.append('mood', document.getElementById('mood').value || '');
            formData.append('location', document.getElementById('location').value || '');
            formData.append('images', document.getElementById('imageData').value);
            
            if (journalId) {
                formData.append('journal_id', journalId);
            }
            
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.remove('active');
                showToast('An error occurred. Please try again.', 'error');
            });
        });
        
        // ============================================
        // EDIT JOURNAL
        // ============================================
        function editJournal(journalId) {
            document.getElementById('loadingOverlay').classList.add('active');
            
            const formData = new FormData();
            formData.append('action', 'get_journal');
            formData.append('journal_id', journalId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                
                if (data.success) {
                    const journal = data.journal;
                    
                    // Fill form
                    document.getElementById('journalId').value = journal.id;
                    document.getElementById('journalDate').value = journal.journal_date;
                    document.getElementById('journalTitle').value = journal.title;
                    document.getElementById('location').value = journal.location || '';
                    
                    // Set mood
                    if (journal.mood) {
                        document.querySelectorAll('.mood-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.mood === journal.mood) {
                                btn.classList.add('active');
                                document.getElementById('mood').value = journal.mood;
                            }
                        });
                    }
                    
                    // Set reflection
                    reflectionText.value = journal.reflection;
                    updateWordCount();
                    
                    // Set images
                    uploadedImages = journal.images || [];
                    renderImagePreviews();
                    
                    // Disable date editing for existing journal
                    document.getElementById('journalDate').disabled = true;
                    
                    // Scroll to form
                    document.querySelector('.journal-form-section').scrollIntoView({ behavior: 'smooth' });
                    
                    showToast('Journal loaded for editing', 'success');
                }
            });
        }
        
        // ============================================
        // RESET FORM
        // ============================================
        function resetForm() {
            document.getElementById('journalId').value = '';
            document.getElementById('journalDate').disabled = false;
            document.getElementById('journalDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('journalTitle').value = '';
            document.getElementById('location').value = '';
            document.getElementById('mood').value = '';
            
            document.querySelectorAll('.mood-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            reflectionText.value = '';
            updateWordCount();
            
            // Delete all uploaded images
            uploadedImages.forEach((img, index) => {
                deleteImage(index, false);
            });
            uploadedImages = [];
            renderImagePreviews();
            
            document.getElementById('dateWarning').style.display = 'none';
            document.querySelector('button[type="submit"]').disabled = false;
        }
        
        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
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
                toast.innerHTML = '';
            }, 5000);
        }
        
        // Initialize word count
        updateWordCount();
        
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