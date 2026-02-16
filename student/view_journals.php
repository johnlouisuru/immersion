<?php
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

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
        
        .btn-edit {
            border: 1px solid #e2e8f0;
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            color: #64748b;
        }
        
        .btn-edit:hover {
            background: #667eea;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 30px;
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
            <!-- Add this after the profile header div, before the journals grid -->
<div style="text-align: right; margin-bottom: 20px;">
    <a href="generate_pdf_journal.php" target="_blank" class="btn btn-success" style="background: #28a745; color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; display: inline-block;">
        <i class="bi bi-file-pdf me-2"></i>
        Generate E-Journal (PDF)
    </a>
</div>
            <!-- Journals Grid -->
            <div class="journal-grid">
                <?php foreach ($journals as $journal): ?>
                    <div class="journal-card">
                        
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
                                    <?= $journal['image_count'] ?> photos
                                </span>
                                
                                <a href="add_journal.php?edit=<?= $journal['id'] ?>" class="btn-edit">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    
    <script>
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Image %1 of %2'
        });
    </script>
</body>
</html>