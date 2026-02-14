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
        COUNT(DISTINCT j.id) as total_journals,
        COUNT(DISTINCT ji.id) as total_photos,
        SUM(CASE WHEN j.mood = 'happy' THEN 1 ELSE 0 END) as happy_days,
        SUM(CASE WHEN j.mood = 'excited' THEN 1 ELSE 0 END) as excited_days,
        MAX(j.journal_date) as latest_journal
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN student_journals j ON s.id = j.student_id
    LEFT JOIN journal_images ji ON j.id = ji.journal_id
    WHERE s.id = ?
    GROUP BY s.id
";

$stmt = $pdo->prepare($student_query);
$stmt->execute([$student_id]);
$student_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all journals with images
$journals_query = "
    SELECT 
        j.*,
        COUNT(DISTINCT ji.id) as image_count,
        GROUP_CONCAT(
            CONCAT(ji.image_path, '|', ji.sort_order, '|', ji.id)
            ORDER BY ji.sort_order ASC
            SEPARATOR ';;;'
        ) as image_data
    FROM student_journals j
    LEFT JOIN journal_images ji ON j.id = ji.journal_id
    WHERE j.student_id = ?
    GROUP BY j.id
    ORDER BY j.journal_date DESC
";

$stmt = $pdo->prepare($journals_query);
$stmt->execute([$student_id]);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process image data and get image dimensions
foreach ($journals as &$journal) {
    $images = [];
    if ($journal['image_data']) {
        $image_parts = explode(';;;', $journal['image_data']);
        foreach ($image_parts as $part) {
            list($path, $sort_order, $id) = explode('|', $part);
            if (file_exists($path)) {
                list($width, $height) = @getimagesize($path);
                $orientation = 'square';
                if ($width && $height) {
                    if ($width > $height) $orientation = 'landscape';
                    else if ($height > $width) $orientation = 'portrait';
                }
                $images[] = [
                    'id' => $id,
                    'path' => $path,
                    'sort_order' => $sort_order,
                    'width' => $width,
                    'height' => $height,
                    'orientation' => $orientation
                ];
            }
        }
    }
    $journal['images'] = $images;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>My Journey - Student Biography</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Lightbox for image viewing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    
    <!-- Masonry Layout -->
    <script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>
    <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        
        .biography-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        @media (max-width: 767.98px) {
            .biography-container {
                padding: 10px;
            }
        }
        
       /* Cover Photo / Hero Section - COMPLETELY FIXED FOR MOBILE */
.profile-cover {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    height: 200px; /* Reduced height */
    border-radius: 20px 20px 20px 20px;
    position: relative;
    margin-bottom: 70px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    overflow: hidden;
    margin-top: 0; /* Ensure no top margin */
}

@media (min-width: 768px) {
    .profile-cover {
        height: 300px;
        border-radius: 30px;
        margin-bottom: 100px;
    }
}

.profile-header {
    position: absolute;
    bottom: 0px;
    left: 15px;
    right: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

@media (min-width: 768px) {
    .profile-header {
        bottom: -60px;
        left: 40px;
        right: 40px;
        flex-direction: row;
        align-items: flex-end;
        gap: 30px;
    }
}

.profile-avatar-wrapper {
    position: relative;
    width: 90px; /* Even smaller for mobile */
    height: 90px;
    border-radius: 18px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 4px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    margin-top: 0; /* No negative margin */
}

@media (min-width: 768px) {
    .profile-avatar-wrapper {
        width: 160px;
        height: 160px;
        border-radius: 30px;
        padding: 5px;
        margin-top: 0;
    }
}
.profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 16px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            color: #667eea;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
                .profile-avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        @media (min-width: 768px) {
            .profile-avatar-wrapper {
                width: 160px;
                height: 160px;
                border-radius: 30px;
                padding: 5px;
            }
        }
        
        @media (min-width: 768px) {
            .profile-avatar {
                border-radius: 26px;
                font-size: 64px;
            }
        }
/* Timeline - FIXED LEFT SPACE */
.timeline {
    position: relative;
    margin-top: 80px;
    padding: 20px 0;
    width: 100%;
}

@media (min-width: 768px) {
    .timeline {
        margin-top: 100px;
    }
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    left: 20px;
    width: 3px;
    height: 100%;
    background: linear-gradient(to bottom, #667eea, #764ba2);
    border-radius: 3px;
    opacity: 0.3;
}

@media (min-width: 768px) {
    .timeline::before {
        left: 50%;
        transform: translateX(-50%);
        width: 4px;
    }
}

.timeline-entry {
    position: relative;
    margin-bottom: 40px;
    clear: both;
    width: 100%;
}

@media (min-width: 768px) {
    .timeline-entry {
        margin-bottom: 80px;
    }
}

.timeline-marker {
    position: absolute;
    left: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: white;
    border: 3px solid #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
    z-index: 10;
}

@media (min-width: 768px) {
    .timeline-marker {
        left: 50%;
        transform: translateX(-50%);
        width: 40px;
        height: 40px;
        border-width: 4px;
    }
}

.timeline-content {
    position: relative;
    width: calc(100% - 45px); /* Reduced left margin */
    margin-left: 45px; /* Aligned with marker */
}

@media (min-width: 768px) {
    .timeline-content {
        width: calc(50% - 60px);
        margin-left: 0;
    }
}

/* Journal Card - NO LEFT SPACE */
.journal-album {
    background: white;
    border-radius: 16px; /* Slightly smaller for mobile */
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    width: 100%;
    margin-left: 0; /* No left margin */
    margin-right: 0; /* No right margin */
}

@media (min-width: 768px) {
    .journal-album {
        border-radius: 24px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    }
}

/* Image Collage - COMPLETELY EDGE TO EDGE */
.image-collage {
    display: grid;
    gap: 3px; /* Even smaller gap for mobile */
    padding: 0;
    background: #f8fafc;
    width: 100%;
    margin: 0; /* No margin */
}

@media (min-width: 768px) {
    .image-collage {
        gap: 6px;
    }
}

.image-collage a {
    display: block;
    width: 100%;
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
}

.image-collage img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
    display: block; /* Remove inline spacing */
    margin: 0;
    padding: 0;
}

/* 1 image - Full width */
.collage-1 {
    grid-template-columns: 1fr;
}
.collage-1 img {
    width: 100%;
    height: 220px; /* Slightly smaller for mobile */
    object-fit: cover;
}
@media (min-width: 768px) {
    .collage-1 img {
        height: 400px;
    }
}

/* 2 images - Full width */
.collage-2 {
    grid-template-columns: repeat(2, 1fr);
}
.collage-2 img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}
@media (min-width: 768px) {
    .collage-2 img {
        height: 250px;
    }
}

/* 3 images - Full width */
.collage-3 {
    grid-template-columns: repeat(3, 1fr);
}
.collage-3 img {
    width: 100%;
    height: 110px;
    object-fit: cover;
}
@media (min-width: 768px) {
    .collage-3 img {
        height: 200px;
    }
}
.collage-3.panorama {
    grid-template-columns: repeat(2, 1fr);
}
.collage-3.panorama img:first-child {
    grid-column: span 2;
    height: 150px;
}
@media (min-width: 768px) {
    .collage-3.panorama img:first-child {
        height: 250px;
    }
}

/* 4 images - Full width */
.collage-4 {
    grid-template-columns: repeat(2, 1fr);
}
.collage-4 img {
    width: 100%;
    height: 110px;
    object-fit: cover;
}
@media (min-width: 768px) {
    .collage-4 img {
        height: 180px;
    }
}
.collage-4.mosaic {
    grid-template-columns: repeat(3, 1fr);
}
.collage-4.mosaic img:first-child {
    grid-column: span 2;
    grid-row: span 2;
    height: 226px;
}
@media (min-width: 768px) {
    .collage-4.mosaic img:first-child {
        height: 366px;
    }
}

/* 5 images - Full width */
.collage-5 {
    grid-template-columns: repeat(3, 1fr);
}
.collage-5 img {
    width: 100%;
    height: 90px;
    object-fit: cover;
}
@media (min-width: 768px) {
    .collage-5 img {
        height: 150px;
    }
}
.collage-5 img:first-child {
    grid-column: span 2;
    grid-row: span 2;
    height: 184px;
}
@media (min-width: 768px) {
    .collage-5 img:first-child {
        height: 306px;
    }
}

/* Journal Header - Remove any left padding issues */
.journal-header {
    padding: 18px 18px; /* Slightly less padding on mobile */
    border-bottom: 1px solid #e2e8f0;
    width: 100%;
}

@media (min-width: 768px) {
    .journal-header {
        padding: 5px;
    }
}

/* Profile stats - FIXED for EC issue */
.profile-stats {
    display: flex;
    gap: 0px;
    margin-top: 80px;
    justify-content: center;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .profile-stats {
        gap: 0px;
        margin-top: 80px;
        justify-content: flex-start;
    }
}

.stat-item {
    text-align: center;
    min-width: 60px;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.8;
    white-space: nowrap;
}

/* Fix for EC label */
.stat-label:contains("EC") {
    font-size: 0; /* Hide text */
}
.stat-label:contains("EC")::before {
    content: "Excited";
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.8;
}

/* Profile badge - FIXED overflow */
.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    padding: 2px 5px;
    border-radius: 50px;
    margin-top: 5px;
    font-size: 12px;
    max-width: 100%;
    flex-wrap: wrap;
    justify-content: center;
    word-break: break-word;
}

@media (min-width: 768px) {
    .profile-badge {
        padding: 10px 20px;
        font-size: 14px;
        justify-content: flex-start;
        flex-wrap: nowrap;
    }
}
        
        @media (min-width: 768px) {
            .stat-label {
                font-size: 12px;
                letter-spacing: 2px;
            }
        }
        
        /* Timeline - FIXED FOR MOBILE */
        .timeline {
            position: relative;
            margin-top: 80px;
            padding: 20px 0;
        }
        
        @media (min-width: 768px) {
            .timeline {
                margin-top: 100px;
            }
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20px;
            width: 3px;
            height: 100%;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            border-radius: 3px;
            opacity: 0.3;
        }
        
        @media (min-width: 768px) {
            .timeline::before {
                left: 50%;
                transform: translateX(-50%);
                width: 4px;
            }
        }
        
        .timeline-entry {
            position: relative;
            margin-bottom: 50px;
            clear: both;
        }
        
        @media (min-width: 768px) {
            .timeline-entry {
                margin-bottom: 80px;
            }
        }
        
        .timeline-marker {
            position: absolute;
            left: 10px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            border: 3px solid #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
            z-index: 10;
        }
        
        @media (min-width: 768px) {
            .timeline-marker {
                left: 50%;
                transform: translateX(-50%);
                width: 40px;
                height: 40px;
                border-width: 4px;
            }
        }
        
        .timeline-content {
            position: relative;
            width: calc(100% - 50px);
            margin-left: 50px;
        }
        
        @media (min-width: 768px) {
            .timeline-content {
                width: calc(50% - 60px);
                margin-left: 0;
            }
            
            .timeline-entry:nth-child(even) .timeline-content {
                float: right;
            }
            
            .timeline-entry:nth-child(odd) .timeline-content {
                float: left;
            }
        }
        
        /* Journal Card - Album Style - FIXED FULL WIDTH */
        .journal-album {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .journal-album {
                border-radius: 24px;
                box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            }
        }
        
        /* Image Collage Layouts - FIXED FULL WIDTH */
        .image-collage {
            display: grid;
            gap: 4px;
            padding: 0;
            background: #f8fafc;
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .image-collage {
                gap: 6px;
                padding: 0;
            }
        }
        
        .image-collage a {
            display: block;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .image-collage img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .image-collage img:hover {
            transform: scale(1.05);
        }
        
        /* 1 image - Full width - NO PADDING */
        .collage-1 {
            grid-template-columns: 1fr;
        }
        .collage-1 img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        @media (min-width: 768px) {
            .collage-1 img {
                height: 400px;
            }
        }
        
        /* 2 images - Full width */
        .collage-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        .collage-2 img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        @media (min-width: 768px) {
            .collage-2 img {
                height: 250px;
            }
        }
        
        /* 3 images - Full width */
        .collage-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        .collage-3 img {
            width: 100%;
            height: 130px;
            object-fit: cover;
        }
        @media (min-width: 768px) {
            .collage-3 img {
                height: 200px;
            }
        }
        .collage-3.panorama {
            grid-template-columns: repeat(2, 1fr);
        }
        .collage-3.panorama img:first-child {
            grid-column: span 2;
            height: 180px;
        }
        @media (min-width: 768px) {
            .collage-3.panorama img:first-child {
                height: 250px;
            }
        }
        
        /* 4 images - Full width */
        .collage-4 {
            grid-template-columns: repeat(2, 1fr);
        }
        .collage-4 img {
            width: 100%;
            height: 130px;
            object-fit: cover;
        }
        @media (min-width: 768px) {
            .collage-4 img {
                height: 180px;
            }
        }
        .collage-4.mosaic {
            grid-template-columns: repeat(3, 1fr);
        }
        .collage-4.mosaic img:first-child {
            grid-column: span 2;
            grid-row: span 2;
            height: 266px;
        }
        @media (min-width: 768px) {
            .collage-4.mosaic img:first-child {
                height: 366px;
            }
        }
        
        /* 5 images - Full width */
        .collage-5 {
            grid-template-columns: repeat(3, 1fr);
        }
        .collage-5 img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        @media (min-width: 768px) {
            .collage-5 img {
                height: 150px;
            }
        }
        .collage-5 img:first-child {
            grid-column: span 2;
            grid-row: span 2;
            height: 204px;
        }
        @media (min-width: 768px) {
            .collage-5 img:first-child {
                height: 306px;
            }
        }
        
        /* Journal Header */
        .journal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        @media (min-width: 768px) {
            .journal-header {
                padding: 25px;
            }
        }
        
        .journal-date {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            background: linear-gradient(135deg, #667eea10, #764ba210);
            border-radius: 50px;
            color: #667eea;
            font-weight: 600;
            font-size: 13px;
        }
        
        @media (min-width: 768px) {
            .journal-date {
                padding: 8px 16px;
                font-size: 14px;
            }
        }
        
        .journal-title {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 700;
            margin-top: 12px;
            color: #1e293b;
            word-break: break-word;
        }
        
        @media (min-width: 768px) {
            .journal-title {
                font-size: 28px;
                margin-top: 15px;
            }
        }
        
        .journal-mood {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        
        @media (min-width: 768px) {
            .journal-mood {
                padding: 6px 16px;
                font-size: 14px;
            }
        }
        
        .mood-happy { background: #fef3c7; color: #92400e; }
        .mood-excited { background: #dbeafe; color: #1e40af; }
        .mood-neutral { background: #f1f5f9; color: #475569; }
        .mood-tired { background: #e0f2fe; color: #155e75; }
        .mood-stressed { background: #fee2e2; color: #991b1b; }
        
        .journal-reflection {
            padding: 20px;
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
            word-break: break-word;
        }
        
        @media (min-width: 768px) {
            .journal-reflection {
                padding: 25px;
                font-size: 16px;
                line-height: 1.8;
            }
        }
        
        .journal-reflection p {
            margin-bottom: 15px;
        }
        
        .journal-footer {
            padding: 15px 20px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e2e8f0;
        }
        
        @media (min-width: 768px) {
            .journal-footer {
                padding: 20px 25px;
            }
        }
        
        .photo-count {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }
        
        /* Memory Wall - FIXED FULL WIDTH */
        .memory-wall {
            margin-top: 60px;
            padding: 20px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .memory-wall {
                margin-top: 80px;
                padding: 40px;
                border-radius: 30px;
            }
        }
        
        .memory-wall-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1e293b;
            position: relative;
            display: inline-block;
        }
        
        @media (min-width: 768px) {
            .memory-wall-title {
                font-size: 32px;
                margin-bottom: 30px;
            }
        }
        
        .memory-wall-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 3px;
        }
        
        @media (min-width: 768px) {
            .memory-wall-title::after {
                bottom: -10px;
                width: 80px;
                height: 4px;
            }
        }
        
        .masonry-grid {
            column-count: 2;
            column-gap: 15px;
        }
        
        @media (min-width: 576px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 20px;
            }
        }
        
        @media (min-width: 768px) {
            .masonry-grid {
                column-count: 3;
            }
        }
        
        @media (min-width: 992px) {
            .masonry-grid {
                column-count: 4;
            }
        }
        
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 15px;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        @media (min-width: 768px) {
            .masonry-item {
                margin-bottom: 20px;
                border-radius: 16px;
            }
        }
        
        .masonry-item img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .masonry-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 15px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        @media (max-width: 767.98px) {
            .masonry-overlay {
                opacity: 1;
                background: linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0.3));
                padding: 12px;
            }
        }
        
        .masonry-item:hover .masonry-overlay {
            opacity: 1;
        }
        
        .masonry-date {
            font-size: 11px;
            opacity: 0.9;
        }
        
        @media (min-width: 768px) {
            .masonry-date {
                font-size: 12px;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        
        @media (min-width: 768px) {
            .empty-state {
                padding: 100px 20px;
            }
        }
        
        .empty-state i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .empty-state i {
                font-size: 80px;
                margin-bottom: 30px;
            }
        }
        
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .empty-state h3 {
                font-size: 32px;
            }
        }
        
        /* Mobile FAB */
        .mobile-fab {
            display: block;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @media (min-width: 768px) {
            .mobile-fab {
                display: none;
            }
        }
        
        .mobile-fab .btn {
            width: 60px;
            height: 60px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .mobile-fab .btn i {
            font-size: 24px;
        }
        
        /* Utility Classes */
        .text-break-all {
            word-break: break-word;
        }
        
        .w-100 {
            width: 100%;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .timeline-entry {
            animation: fadeInUp 0.6s ease backwards;
        }
        
        .timeline-entry:nth-child(1) { animation-delay: 0.1s; }
        .timeline-entry:nth-child(2) { animation-delay: 0.2s; }
        .timeline-entry:nth-child(3) { animation-delay: 0.3s; }
        .timeline-entry:nth-child(4) { animation-delay: 0.4s; }
        .timeline-entry:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="biography-container">
        
        <?php if (empty($journals)): ?>
            <!-- Empty State - First Journal -->
            <div class="empty-state">
                <i class="bi bi-journal-album"></i>
                <h3>Begin Your Journey</h3>
                <p class="text-muted mb-4 px-3">Your story is waiting to be written. Start your first journal entry today.</p>
                <a href="add_journal.php" class="btn btn-primary rounded-pill px-4 py-2" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                    <i class="bi bi-plus-circle me-2"></i>
                    Write Your First Story
                </a>
            </div>
        <?php else: ?>
            
            <!-- Profile Cover / Hero Section - FIXED FOR MOBILE -->
            <div class="profile-cover">
                <div class="profile-header">
                    <!-- <div class="profile-avatar-wrapper">
                        <div class="profile-avatar" style="<?= $student_profile['profile_picture'] ? 'background-image: url(' . htmlspecialchars($student_profile['profile_picture']) . ')' : '' ?>">
                            <?php if (!$student_profile['profile_picture']): ?>
                                <?= strtoupper(substr($student_profile['firstname'] ?? '', 0, 1) . substr($student_profile['lastname'] ?? '', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                    </div> -->
                    <div class="profile-info">
                        <h1 class="profile-name">
                            <?= htmlspecialchars($student_profile['firstname'] . ' ' . $student_profile['lastname']) ?>
                        </h1>
                        <div class="profile-badge">
                            <i class="bi bi-building"></i>
                            <span class="text-truncate"><?= htmlspecialchars($student_profile['section_name'] ?? 'Unassigned') ?></span>
                            <span class="d-none d-sm-inline mx-2">•</span>
                            <i class="bi bi-envelope d-none d-sm-inline"></i>
                            <span class="text-truncate d-none d-sm-inline"><?= htmlspecialchars($student_profile['email']) ?></span>
                        </div>
                        <!-- Replace the profile-stats div with this fixed version -->
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['total_journals'] ?? 0 ?></div>
                                <div class="stat-label">Journals</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['total_photos'] ?? 0 ?></div>
                                <div class="stat-label">Photos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['happy_days'] ?? 0 ?></div>
                                <div class="stat-label">Happy</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $student_profile['excited_days'] ?? 0 ?></div>
                                <div class="stat-label">Excited</div> <!-- Changed from EC to Excited -->
                            </div>
                            <a href="add_journal.php" class="btn btn-primary rounded-pill px-4 py-2" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                                <i class="bi bi-plus-circle me-2"></i>
                                Add New
                            </a>
                        </div>
                    </div>
                    <div class="d-none d-md-block ms-auto">
                        <a href="add_journal.php" class="btn btn-light btn-lg rounded-pill px-4">
                            <i class="bi bi-plus-circle me-2"></i>
                            New Entry
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Timeline of Journals - FIXED FULL WIDTH -->
            <div class="timeline">
                <?php foreach ($journals as $index => $journal): ?>
                    <div class="timeline-entry">
                        <div class="timeline-marker"></div>
                        
                        <div class="timeline-content">
                            <div class="journal-album">
                                
                                <!-- Dynamic Image Collage - FULL WIDTH NO PADDING -->
                                <?php if (!empty($journal['images'])): ?>
                                    <?php 
                                    $image_count = count($journal['images']);
                                    $collage_class = 'collage-' . min($image_count, 5);
                                    
                                    // Determine layout based on image orientations
                                    $landscape_count = 0;
                                    $portrait_count = 0;
                                    foreach ($journal['images'] as $img) {
                                        if ($img['orientation'] == 'landscape') $landscape_count++;
                                        if ($img['orientation'] == 'portrait') $portrait_count++;
                                    }
                                    
                                    // Smart layout selection
                                    if ($image_count == 3) {
                                        if ($landscape_count >= 2) $collage_class .= ' panorama';
                                    }
                                    elseif ($image_count == 4) {
                                        if ($landscape_count >= 3) $collage_class .= ' mosaic';
                                    }
                                    ?>
                                    
                                    <div class="image-collage <?= $collage_class ?>">
                                        <?php foreach ($journal['images'] as $img): ?>
                                            <a href="<?= htmlspecialchars($img['path']) ?>" data-lightbox="journal-<?= $journal['id'] ?>" data-title="<?= htmlspecialchars($journal['title']) ?>">
                                                <img src="<?= htmlspecialchars($img['path']) ?>" 
                                                     alt="Journal image"
                                                     loading="lazy">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Journal Header -->
                                <div class="journal-header">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                        <span class="journal-date">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= date('M d, Y', strtotime($journal['journal_date'])) ?>
                                        </span>
                                        
                                        <?php if ($journal['mood']): ?>
                                            <span class="journal-mood mood-<?= $journal['mood'] ?>">
                                                <?php
                                                $mood_icons = [
                                                    'happy' => 'bi-emoji-smile',
                                                    'excited' => 'bi-emoji-sunglasses',
                                                    'neutral' => 'bi-emoji-neutral',
                                                    'tired' => 'bi-emoji-tired',
                                                    'stressed' => 'bi-emoji-dizzy'
                                                ];
                                                $icon = $mood_icons[$journal['mood']] ?? 'bi-emoji-smile';
                                                ?>
                                                <i class="bi <?= $icon ?>"></i>
                                                <span class="d-none d-sm-inline"><?= ucfirst($journal['mood']) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h2 class="journal-title"><?= htmlspecialchars($journal['title']) ?></h2>
                                    
                                    <?php if ($journal['location']): ?>
                                        <div class="mt-2 text-muted small">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            <?= htmlspecialchars($journal['location']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Journal Reflection -->
                                <div class="journal-reflection">
                                    <?= nl2br(htmlspecialchars($journal['reflection'])) ?>
                                </div>
                                
                                <!-- Journal Footer -->
                                <div class="journal-footer">
                                    <div class="photo-count">
                                        <i class="bi bi-camera"></i>
                                        <span><?= $journal['image_count'] ?> <?= $journal['image_count'] == 1 ? 'photo' : 'photos' ?></span>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="add_journal.php?edit=<?= $journal['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="bi bi-pencil"></i>
                                            <span class="d-none d-sm-inline ms-1">Edit</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Memory Wall - All Photos Collage -->
            <?php
            // Collect all images for memory wall
            $all_images = [];
            foreach ($journals as $journal) {
                foreach ($journal['images'] as $img) {
                    $all_images[] = [
                        'path' => $img['path'],
                        'title' => $journal['title'],
                        'date' => $journal['journal_date']
                    ];
                }
            }
            
            if (!empty($all_images)):
            ?>
            <div class="memory-wall">
                <h3 class="memory-wall-title">
                    <i class="bi bi-images me-2"></i>
                    Memory Wall
                </h3>
                <p class="text-muted mb-3"><?= count($all_images) ?> captured moments from your journey</p>
                
                <div class="masonry-grid" id="masonryGrid">
                    <?php foreach ($all_images as $image): ?>
                        <div class="masonry-item">
                            <a href="<?= htmlspecialchars($image['path']) ?>" data-lightbox="memory-wall" data-title="<?= htmlspecialchars($image['title']) ?>">
                                <img src="<?= htmlspecialchars($image['path']) ?>" alt="Memory" loading="lazy">
                            </a>
                            <div class="masonry-overlay">
                                <div class="masonry-date">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= date('M d, Y', strtotime($image['date'])) ?>
                                </div>
                                <small class="d-none d-sm-block"><?= htmlspecialchars(substr($image['title'], 0, 30)) ?><?= strlen($image['title']) > 30 ? '...' : '' ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Mobile FAB for new entry -->
            <div class="mobile-fab">
                <a href="add_journal.php" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    
    <script>
        // Initialize Masonry for Memory Wall
        document.addEventListener('DOMContentLoaded', function() {
            const grid = document.getElementById('masonryGrid');
            if (grid) {
                imagesLoaded(grid, function() {
                    new Masonry(grid, {
                        itemSelector: '.masonry-item',
                        columnWidth: '.masonry-item',
                        percentPosition: true,
                        gutter: 15
                    });
                });
            }
            const statLabels = document.querySelectorAll('.stat-label');
            statLabels.forEach(label => {
                if (label.textContent.trim() === 'EC') {
                    label.textContent = 'Excited';
                }
            });
        });
        
        // Lightbox configuration
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Image %1 of %2',
            'fadeDuration': 300,
            'imageFadeDuration': 300,
            'maxWidth': 1000,
            'maxHeight': 800
        });
        
        // Fix for mobile viewport height
        function fixMobileHeight() {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        window.addEventListener('resize', fixMobileHeight);
        fixMobileHeight();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>