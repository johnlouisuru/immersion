<?php
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student's section
$query = "SELECT section_id, lastname, firstname FROM students WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || !$student['section_id']) {
    $error_message = "You are not assigned to any section.";
} else {
    $section_id = $student['section_id'];
    
    // Get section name
    $section_query = "SELECT section_name FROM sections WHERE id = ?";
    $stmt = $pdo->prepare($section_query);
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    $section_name = $section['section_name'] ?? 'Unknown Section';
}

// Get learning materials for this section
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

// Update download count when accessed
if (isset($_GET['download']) && isset($_GET['id'])) {
    $material_id = $_GET['id'];
    $update_query = "UPDATE learning_materials SET download_count = download_count + 1 WHERE id = ?";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([$material_id]);
    
    // Redirect to actual file
    $file_query = "SELECT file_path FROM learning_materials WHERE id = ?";
    $stmt = $pdo->prepare($file_query);
    $stmt->execute([$material_id]);
    $file = $stmt->fetch();
    
    if ($file && file_exists($file['file_path'])) {
        header('Location: ' . $file['file_path']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title>Learning Materials - Student Portal</title>
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
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2C3E50, #3498DB);
            color: white;
            padding: 20px 25px;
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
            body { padding: 40px 20px; }
            .card-header h2 { font-size: 28px; }
        }
        
        .material-card {
            background: white;
            border-radius: 16px;
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
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
        }
        
        @media (min-width: 576px) {
            .btn-action {
                width: auto;
            }
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        /* PDF Modal Styles - Full featured */
        .pdf-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 99999;
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
            max-width: 1000px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            margin: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
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
            min-height: 500px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .pdf-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: white;
            padding: 12px 20px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 100%;
        }
        
        .pdf-page-info {
            background: #f8fafc;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }
        
        .pdf-canvas {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            background: white;
        }
        
        .btn-pdf-control {
            padding: 8px 14px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        @media (max-width: 767.98px) {
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
                padding: 10px 15px;
            }
            
            .btn-pdf-control {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .pdf-page-info {
                padding: 4px 12px;
                font-size: 12px;
            }
        }
        
        .badge-section {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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
            color: #cbd5e1;
        }
        
        .search-box {
            position: relative;
            max-width: 300px;
            width: 100%;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <h2 class="mb-0">
                    <i class="bi bi-journal-bookmark-fill"></i>
                    Learning Materials
                </h2>
                <div class="d-flex flex-wrap align-items-center gap-3 w-100 w-md-auto">
                    <span class="badge-section w-100 w-md-auto justify-content-center justify-content-md-start">
                        <i class="bi bi-people-fill"></i>
                        Section: <?= htmlspecialchars($section_name) ?>
                    </span>
                    <span class="badge-section w-100 w-md-auto justify-content-center justify-content-md-start">
                        <i class="bi bi-files"></i>
                        <?= count($learning_materials) ?> Materials
                    </span>
                </div>
                
            </div>
            
            
            <div class="card-body p-4">
            <a href="dashboard" type="submit" class="btn btn-primary btn-lg w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                        <i class="bi bi-arrow-left-circle"></i>
                                        Back to Dashboard
                                    </a>
                                    <hr />
                <!-- Search Bar -->
                <div class="d-flex justify-content-end mb-4">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="searchMaterials" placeholder="Search materials...">
                    </div>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($error_message) ?></div>
                <?php elseif (empty($learning_materials)): ?>
                    <div class="empty-state">
                        <i class="bi bi-files"></i>
                        <h5 class="mt-4">No learning materials available</h5>
                        <p class="text-muted">Your teacher hasn't uploaded any materials yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3" id="materialsList">
                        <?php foreach ($learning_materials as $material): ?>
                            <?php 
                                $file_name_without_ext = pathinfo($material['file_name'], PATHINFO_FILENAME);
                                $upload_date = date('M d, Y', strtotime($material['uploaded_at']));
                                $teacher_name = $material['teacher_lastname'] . ', ' . $material['teacher_firstname'];
                                $file_size = $material['file_size'] ? round($material['file_size'] / 1024 / 1024, 2) . ' MB' : 'Unknown';
                            ?>
                            <div class="col-12 material-item" data-name="<?= strtolower($file_name_without_ext) ?>">
                                <div class="material-card">
                                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                        <div class="pdf-icon">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h6 class="fw-bold mb-0"><?= htmlspecialchars($file_name_without_ext) ?>.pdf</h6>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                                                    <i class="bi bi-file-pdf me-1"></i>
                                                    PDF
                                                </span>
                                            </div>
                                            
                                            <?php if (!empty($material['description'])): ?>
                                                <p class="text-muted small mb-2">
                                                    <i class="bi bi-quote me-1"></i>
                                                    <?= htmlspecialchars($material['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                                <span class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-person"></i>
                                                    <?= htmlspecialchars($teacher_name) ?>
                                                </span>
                                                <span class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-calendar"></i>
                                                    <?= $upload_date ?>
                                                </span>
                                                <span class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-hdd"></i>
                                                    <?= $file_size ?>
                                                </span>
                                                <span class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-cloud-download"></i>
                                                    <?= $material['download_count'] ?? 0 ?> downloads
                                                </span>
                                            </div>
                                        </div>
                                        <div class="action-group">
                                            <button class="btn btn-outline-primary btn-action" onclick="viewPDF('../teacher/<?= $material['file_path'] ?>', '<?= htmlspecialchars($file_name_without_ext) ?>.pdf')">
                                                <i class="bi bi-eye"></i>
                                                <span class="d-inline d-sm-none">View</span>
                                            </button>
                                            <a href="?download=1&id=<?= $material['id'] ?>" class="btn btn-outline-success btn-action">
                                                <i class="bi bi-download"></i>
                                                <span class="d-inline d-sm-none">Download</span>
                                            </a>
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
    
    <!-- Full Featured PDF Viewer Modal - Same as Teacher Version -->
    <div id="pdfViewerModal" class="pdf-modal">
        <div class="pdf-modal-content">
            <div class="pdf-modal-header">
                <h5 id="pdfModalTitle">PDF Viewer</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closePDFViewer()"></button>
            </div>
            <div class="pdf-viewer-container">
                <!-- Full PDF Controls -->
                <div class="pdf-controls">
                    <button class="btn btn-primary btn-pdf-control" onclick="prevPage()" title="Previous Page">
                        <i class="bi bi-chevron-left"></i>
                        <span class="d-none d-sm-inline">Prev</span>
                    </button>
                    
                    <span class="pdf-page-info">
                        Page <span id="currentPage">1</span> of <span id="totalPages">1</span>
                    </span>
                    
                    <button class="btn btn-primary btn-pdf-control" onclick="nextPage()" title="Next Page">
                        <span class="d-none d-sm-inline">Next</span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    
                    <div class="vr d-none d-sm-block" style="height: 30px;"></div>
                    
                    <button class="btn btn-outline-secondary btn-pdf-control" onclick="zoomIn()" title="Zoom In">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    
                    <button class="btn btn-outline-secondary btn-pdf-control" onclick="zoomOut()" title="Zoom Out">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    
                    <button class="btn btn-outline-secondary btn-pdf-control" onclick="resetZoom()" title="Reset Zoom">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    
                    <div class="vr d-none d-sm-block" style="height: 30px;"></div>
                    
                    <button class="btn btn-outline-success btn-pdf-control" onclick="downloadCurrentPDF()" title="Download">
                        <i class="bi bi-download"></i>
                        <span class="d-none d-sm-inline">Download</span>
                    </button>
                    
                    <button class="btn btn-outline-danger btn-pdf-control" onclick="closePDFViewer()" title="Close">
                        <i class="bi bi-x-lg"></i>
                        <span class="d-none d-sm-inline">Close</span>
                    </button>
                </div>
                
                <!-- PDF Canvas -->
                <canvas id="pdfCanvas" class="pdf-canvas"></canvas>
                
                <!-- Loading Indicator -->
                <div id="pdfLoader" style="display: none;" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // PDF.js Configuration - Full Featured
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        
        // PDF Viewer Variables
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.2;
        let canvas = document.getElementById('pdfCanvas');
        let ctx = canvas.getContext('2d');
        let currentPDFPath = '';
        let currentPDFTitle = '';
        
        /**
         * View PDF Function - Full Featured
         */
        function viewPDF(path, filename) {
            // Show modal and loader
            currentPDFPath = path;
            currentPDFTitle = filename;
            document.getElementById('pdfModalTitle').textContent = filename;
            document.getElementById('pdfViewerModal').style.display = 'flex';
            document.getElementById('pdfLoader').style.display = 'block';
            
            // Reset state
            pageNum = 1;
            scale = 1.2;
            
            // Load PDF document
            pdfjsLib.getDocument(path).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('totalPages').textContent = pdfDoc.numPages;
                document.getElementById('pdfLoader').style.display = 'none';
                
                // Render first page
                renderPage(pageNum);
            }).catch(function(error) {
                document.getElementById('pdfLoader').style.display = 'none';
                alert('Error loading PDF: ' + error.message);
            });
        }
        
        /**
         * Render PDF Page
         */
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
        
        /**
         * Queue Page Render
         */
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }
        
        /**
         * Previous Page
         */
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }
        
        /**
         * Next Page
         */
        function nextPage() {
            if (pdfDoc && pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }
        
        /**
         * Zoom In
         */
        function zoomIn() {
            scale += 0.25;
            if (pdfDoc) {
                renderPage(pageNum);
            }
        }
        
        /**
         * Zoom Out
         */
        function zoomOut() {
            if (scale > 0.5) {
                scale -= 0.25;
                if (pdfDoc) {
                    renderPage(pageNum);
                }
            }
        }
        
        /**
         * Reset Zoom
         */
        function resetZoom() {
            scale = 1.2;
            if (pdfDoc) {
                renderPage(pageNum);
            }
        }
        
        /**
         * Download Current PDF
         */
        function downloadCurrentPDF() {
            if (currentPDFPath) {
                const a = document.createElement('a');
                a.href = currentPDFPath;
                a.download = currentPDFTitle;
                a.click();
                
                // Increment download count via AJAX
                const urlParams = new URLSearchParams(window.location.search);
                const materialId = urlParams.get('id');
                if (materialId) {
                    fetch('?download=1&id=' + materialId);
                }
            }
        }
        
        /**
         * Close PDF Viewer
         */
        function closePDFViewer() {
            document.getElementById('pdfViewerModal').style.display = 'none';
            pdfDoc = null;
            pageNum = 1;
            scale = 1.2;
        }
        
        /**
         * Search Materials
         */
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
        
        /**
         * Keyboard Shortcuts for PDF Viewer
         */
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('pdfViewerModal');
            if (modal.style.display === 'flex') {
                if (e.key === 'Escape') {
                    closePDFViewer();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    prevPage();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    nextPage();
                } else if (e.key === '+' || e.key === '=') {
                    e.preventDefault();
                    zoomIn();
                } else if (e.key === '-') {
                    e.preventDefault();
                    zoomOut();
                } else if (e.key === '0') {
                    e.preventDefault();
                    resetZoom();
                }
            }
        });
        
        /**
         * Close modal when clicking outside
         */
        document.getElementById('pdfViewerModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePDFViewer();
            }
        });
        
        /**
         * Prevent body scroll when modal is open
         */
        function preventBodyScroll(prevent) {
            document.body.style.overflow = prevent ? 'hidden' : 'auto';
        }
        
        // Override open/close to handle body scroll
        const originalViewPDF = viewPDF;
        viewPDF = function(path, filename) {
            preventBodyScroll(true);
            originalViewPDF(path, filename);
        };
        
        const originalClosePDFViewer = closePDFViewer;
        closePDFViewer = function() {
            preventBodyScroll(false);
            originalClosePDFViewer();
        };
    </script>
</body>
</html>