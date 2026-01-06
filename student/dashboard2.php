<?php
require_once 'db-config/security.php';

// Redirect if not logged in
if (!isLoggedIn() || !isProfileComplete()) {
    header('Location: index');
    exit;
}

// Get student information
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: index');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }
        .info-row {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 180px;
        }
        .info-value {
            color: #495057;
        }
        .badge-lrn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-size: 1rem;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-mortarboard-fill me-2"></i>Student Portal
            </span>
            <a href="logout/" class="btn btn-light btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="profile-card">
                    <div class="profile-header flex-column flex-md-row text-center text-md-start">
                        <?php if ($student['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                 alt="Profile" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar bg-secondary d-flex align-items-center justify-content-center text-white fs-1">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h2 class="mb-2">
                                <?php echo htmlspecialchars($student['firstname'] . ' ' . 
                                    ($student['mi'] ? $student['mi'] . '. ' : '') . 
                                    $student['lastname']); ?>
                            </h2>
                            <p class="text-muted mb-2">
                                <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($student['email']); ?>
                            </p>
                            <span class="badge badge-lrn">
                                <i class="bi bi-card-text me-1"></i>LRN: <?php echo htmlspecialchars($student['lrn']); ?>
                            </span>
                        </div>
                    </div>

                    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Student Information</h5>
                    
                    <div class="info-row">
                        <div class="info-label">Last Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['lastname']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">First Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['firstname']); ?></div>
                    </div>
                    
                    <?php if ($student['mi']): ?>
                    <div class="info-row">
                        <div class="info-label">Middle Initial:</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['mi']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <div class="info-label">LRN:</div>
                        <div class="info-value"><strong><?php echo htmlspecialchars($student['lrn']); ?></strong></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Registration Date:</div>
                        <div class="info-value">
                            <?php echo date('F j, Y, g:i a', strtotime($student['created_at'])); ?>
                        </div>
                    </div>

                    <div class="alert alert-success mt-4 mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Your registration is complete! Welcome to the student portal.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>