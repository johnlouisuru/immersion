<?php
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Handle delete request
if (isset($_POST['delete_journal']) && isset($_POST['journal_id'])) {
    $journal_id = $_POST['journal_id'];
    $student_id = $_SESSION['user_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, get all images to delete files from server
        $images_query = "SELECT image_path FROM journal_images WHERE journal_id = ?";
        $stmt = $pdo->prepare($images_query);
        $stmt->execute([$journal_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete image files from server using multiple possible path variations
        foreach ($images as $image) {
            $deleted = false;
            $paths_to_try = [];
            
            // Get the relative path from database
            $db_path = $image['image_path'];
            
            // Try different path variations
            $paths_to_try[] = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($db_path, '/');
            $paths_to_try[] = $_SERVER['DOCUMENT_ROOT'] . '/student-journal/' . ltrim($db_path, '/');
            $paths_to_try[] = __DIR__ . '/../' . ltrim($db_path, '/');
            $paths_to_try[] = __DIR__ . '/' . ltrim($db_path, '/');
            
            // If the path already starts with http, try to extract the relative path
            if (strpos($db_path, 'http') === 0) {
                $parsed_url = parse_url($db_path);
                if (isset($parsed_url['path'])) {
                    $paths_to_try[] = $_SERVER['DOCUMENT_ROOT'] . $parsed_url['path'];
                }
            }
            
            // Try each path
            foreach ($paths_to_try as $full_path) {
                if (file_exists($full_path)) {
                    if (unlink($full_path)) {
                        $deleted = true;
                        error_log("Successfully deleted: " . $full_path);
                        break;
                    }
                }
            }
            
            if (!$deleted) {
                error_log("Could not delete file: " . $db_path);
            }
        }
        
        // After deleting all images, try to clean up the folder
        // Get the folder path from the first image
        if (!empty($images)) {
            $first_image = $images[0]['image_path'];
            $folder_relative = dirname(ltrim($first_image, '/'));
            
            // Try to find and delete the folder if empty
            $folder_paths_to_try = [
                $_SERVER['DOCUMENT_ROOT'] . '/' . $folder_relative,
                $_SERVER['DOCUMENT_ROOT'] . '/student-journal/' . $folder_relative,
                __DIR__ . '/../' . $folder_relative,
                __DIR__ . '/' . $folder_relative
            ];
            
            foreach ($folder_paths_to_try as $folder_path) {
                if (is_dir($folder_path)) {
                    $files = array_diff(scandir($folder_path), array('.', '..'));
                    if (empty($files)) {
                        rmdir($folder_path);
                        error_log("Removed empty folder: " . $folder_path);
                    }
                    break;
                }
            }
        }
        
        // Delete journal images from database
        $delete_images = "DELETE FROM journal_images WHERE journal_id = ?";
        $stmt = $pdo->prepare($delete_images);
        $stmt->execute([$journal_id]);
        
        // Delete the journal
        $delete_journal = "DELETE FROM student_journals WHERE id = ? AND student_id = ?";
        $stmt = $pdo->prepare($delete_journal);
        $stmt->execute([$journal_id, $student_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Journal entry and all associated images deleted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting journal: " . $e->getMessage();
        error_log("Delete error: " . $e->getMessage());
    }
    
    // Redirect to refresh the page
    header('Location: view_journals.php');
    exit;
}

// Rest of your code continues here...
// Rest of your code remains the same...

$student_id = $_SESSION['user_id'];

// Get student information with stats
$student_query = "
    SELECT 
        s.*,
        sec.section_name,
        (SELECT COUNT(*) FROM student_journals WHERE student_id = s.id) as total_journals,
        (SELECT COUNT(*) FROM journal_images ji 
         JOIN student_journals j ON ji.journal_id = j.id 
         WHERE j.student_id = s.id) as total_photos,
        (SELECT COUNT(*) FROM student_journals WHERE student_id = s.id AND mood = 'happy') as happy_days,
        (SELECT COUNT(*) FROM student_journals WHERE student_id = s.id AND mood = 'excited') as excited_days,
        (SELECT MAX(journal_date) FROM student_journals WHERE student_id = s.id) as latest_journal
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = ?
";

$stmt = $pdo->prepare($student_query);
$stmt->execute([$student_id]);
$student_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all journals - SIMPLE AND CLEAN
$journals_query = "
    SELECT 
        id,
        student_id,
        journal_date,
        title,
        reflection,
        mood,
        location
    FROM student_journals 
    WHERE student_id = ?
    ORDER BY journal_date DESC
";

$stmt = $pdo->prepare($journals_query);
$stmt->execute([$student_id]);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get images for each journal
foreach ($journals as $key => $journal) {
    $images_query = "
        SELECT 
            image_path
        FROM journal_images 
        WHERE journal_id = ?
        ORDER BY sort_order ASC
    ";
    
    $stmt = $pdo->prepare($images_query);
    $stmt->execute([$journal['id']]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Store images directly in the journal array
    $journals[$key]['images'] = $images;
    $journals[$key]['image_count'] = count($images);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Journal - <?= htmlspecialchars($student_profile['firstname'] ?? 'Student') ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Lightbox for image viewing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    
    <!-- SweetAlert2 for beautiful alerts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8fafc;
            font-family: system-ui, -apple-system, 'Inter', sans-serif;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 40px;
            color: white;
        }
        
        .profile-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
            margin: 20px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.9;
        }
        
        .btn-add {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        /* Alert Messages */
        .alert-message {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #10b981;
            color: white;
            border-left: 4px solid #059669;
        }
        
        .alert-error {
            background: #ef4444;
            color: white;
            border-left: 4px solid #dc2626;
        }
        
        /* Journal Grid */
        .journal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .journal-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .journal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        /* Image Gallery */
        .journal-gallery {
            display: grid;
            gap: 2px;
            background: #f1f5f9;
            aspect-ratio: 16/9;
        }
        
        .gallery-1 { grid-template-columns: 1fr; }
        .gallery-2 { grid-template-columns: repeat(2, 1fr); }
        .gallery-3 { grid-template-columns: repeat(3, 1fr); }
        .gallery-4 { grid-template-columns: repeat(2, 1fr); }
        .gallery-5 { grid-template-columns: repeat(3, 1fr); }
        
        .journal-gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .journal-gallery img:hover {
            transform: scale(1.05);
        }
        
        /* Content */
        .journal-content {
            padding: 20px;
        }
        
        .journal-date {
            background: #f1f5f9;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 13px;
            color: #64748b;
            display: inline-block;
            margin-bottom: 12px;
        }
        
        .journal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .journal-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .journal-mood {
            padding: 4px 12px;
            border-radius: 50px;
        }
        
        .mood-happy { background: #fef3c7; color: #92400e; }
        .mood-excited { background: #dbeafe; color: #1e40af; }
        .mood-neutral { background: #f1f5f9; color: #475569; }
        .mood-tired { background: #e0f2fe; color: #155e75; }
        
        .journal-reflection {
            color: #334155;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        
        .journal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, .btn-delete {
            border: 1px solid #e2e8f0;
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            cursor: pointer;
            background: white;
        }
        
        .btn-edit {
            color: #667eea;
        }
        
        .btn-edit:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .btn-delete {
            color: #ef4444;
        }
        
        .btn-delete:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 30px;
        }
        
        /* Delete Confirmation Modal (hidden by default) */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .delete-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 24px;
            max-width: 400px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .journal-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert-message alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert-message alert-error">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (empty($journals)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="bi bi-journal-album" style="font-size: 80px; color: #cbd5e1;"></i>
                <h3 style="font-size: 2rem; margin: 20px 0;">Start Your Journal</h3>
                <p class="text-muted mb-4">Your memories are waiting to be captured.</p>
                <a href="add_journal.php" class="btn-add">Write Your First Entry</a>
            </div>
        <?php else: ?>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 class="profile-name">
                            <?= htmlspecialchars($student_profile['firstname'] . ' ' . $student_profile['lastname']) ?>
                        </h1>
                        <div style="margin-bottom: 20px;">
                            <span><i class="bi bi-building me-1"></i> <?= htmlspecialchars($student_profile['section_name'] ?? 'Unassigned') ?></span>
                        </div>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['total_journals'] ?? 0 ?></div>
                                <div class="stat-label">Journals</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['total_photos'] ?? 0 ?></div>
                                <div class="stat-label">Photos</div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="add_journal.php" class="btn-add">+ New Entry</a>
                </div>
            </div>
            
            <!-- PDF Generate Button -->
            <div style="text-align: right; margin-bottom: 20px;">
                <a href="generate_pdf_journal.php" target="_blank" class="btn-add" style="background: #28a745; color: white;">
                    <i class="bi bi-file-pdf me-2"></i>
                    Generate E-Journal (PDF)
                </a>
            </div>
            
            <!-- Journals Grid -->
            <div class="journal-grid">
                <?php foreach ($journals as $journal): ?>
                    <div class="journal-card" id="journal-<?= $journal['id'] ?>">
                        
                        <!-- Images -->
                        <?php if (!empty($journal['images'])): ?>
                            <div class="journal-gallery gallery-<?= min(count($journal['images']), 5) ?>">
                                <?php foreach (array_slice($journal['images'], 0, 5) as $img): ?>
                                    <a href="<?= htmlspecialchars($img['image_path']) ?>" data-lightbox="journal-<?= $journal['id'] ?>">
                                        <img src="<?= htmlspecialchars($img['image_path']) ?>" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Content -->
                        <div class="journal-content">
                            <span class="journal-date">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('M d, Y', strtotime($journal['journal_date'])) ?>
                            </span>
                            
                            <h2 class="journal-title"><?= htmlspecialchars($journal['title']) ?></h2>
                            
                            <?php if ($journal['mood']): ?>
                                <div class="journal-meta">
                                    <span class="journal-mood mood-<?= $journal['mood'] ?>">
                                        <?= ucfirst($journal['mood']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="journal-reflection">
                                <?= nl2br(htmlspecialchars($journal['reflection'])) ?>
                            </div>
                            
                            <div class="journal-footer">
                                <span>
                                    <i class="bi bi-camera me-1"></i>
                                    <?= $journal['image_count'] ?> <?= $journal['image_count'] == 1 ? 'photo' : 'photos' ?>
                                </span>
                                
                                <div class="action-buttons">
                                    <!-- <a href="add_journal.php?edit=<?= $journal['id'] ?>" class="btn-edit">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a> -->
                                    
                                    <button type="button" class="btn-delete" onclick="confirmDelete(<?= $journal['id'] ?>, '<?= addslashes($journal['title']) ?>')">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_journal" value="1">
        <input type="hidden" name="journal_id" id="delete_journal_id" value="">
    </form>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Image %1 of %2'
        });
        
        // Auto-hide alert messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Delete confirmation function using SweetAlert2
        function confirmDelete(journalId, journalTitle) {
            Swal.fire({
                title: 'Delete Journal Entry?',
                html: `Are you sure you want to delete "<strong>${journalTitle}</strong>"?<br><br>
                       <span style="color: #ef4444; font-size: 0.9em;">
                       <i class="bi bi-exclamation-triangle"></i> 
                       This will also delete all associated photos permanently!
                       </span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    document.getElementById('delete_journal_id').value = journalId;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
    </script>
</body>
</html>