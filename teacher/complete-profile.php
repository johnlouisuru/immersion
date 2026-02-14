<?php
require_once 'db-config/security.php';
// Enable error logging
error_log("=== Complete Profile Access ===");
error_log("Session google_id: " . ($_SESSION['google_id'] ?? 'not set'));
error_log("Session email: " . ($_SESSION['email'] ?? 'not set'));
error_log("Session profile_complete: " . ($_SESSION['profile_complete'] ?? 'not set'));

// Set PDO error mode
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Redirect if not authenticated
if (!isGoogleAuthenticated()) {
    header('Location: index');
    exit;
}

if (isProfileComplete()) {
    header('Location: dashboard');
    exit;
}


$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastname = trim($_POST['lastname'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $mi = trim($_POST['mi'] ?? '');
    
    // Validation
    if (empty($lastname) || empty($firstname)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($mi) > 10) {
        $error = 'Middle initial is too long.';
    } else {
        // Replace this block in complete-profile.php:
try {
    $conn = $pdo;
    
    // First, check if user already exists
    $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE google_id = :google_id OR email = :email");
    $check_stmt->execute([
        'google_id' => $_SESSION['google_id'],
        'email' => $_SESSION['email']
    ]);
    $existing_user = $check_stmt->fetch();
    
    if ($existing_user) {
        // Update existing user
        $stmt = $conn->prepare("
            UPDATE teachers 
            SET lastname = :lastname, 
                firstname = :firstname, 
                mi = :mi, 
                profile_picture = :profile_picture,
                google_id = :google_id,
                email = :email
            WHERE id = :id
        ");
        
        $stmt->execute([
            'id' => $existing_user['id'],
            'google_id' => $_SESSION['google_id'],
            'email' => $_SESSION['email'],
            'lastname' => $lastname,
            'firstname' => $firstname,
            'mi' => $mi ?: null,
            'profile_picture' => $_SESSION['profile_picture'] ?? null
        ]);
        
        $user_id = $existing_user['id'];
    } else {
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO teachers (google_id, email, lastname, firstname, mi, profile_picture)
            VALUES (:google_id, :email, :lastname, :firstname, :mi, :profile_picture)
        ");
        
        $stmt->execute([
            'google_id' => $_SESSION['google_id'],
            'email' => $_SESSION['email'],
            'lastname' => $lastname,
            'firstname' => $firstname,
            'mi' => $mi ?: null,
            'profile_picture' => $_SESSION['profile_picture'] ?? null
        ]);
        
        $user_id = $conn->lastInsertId();
    }
    
    // Update session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['teacher_id'] = $user_id;
    $_SESSION['profile_complete'] = true;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    
    header('Location: dashboard');
    exit;
    
} catch (PDOException $e) {
    error_log("Complete profile error: " . $e->getMessage());
    $error = 'An error occurred. Please try again.';
}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        .profile-body {
            padding: 30px;
        }
        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="profile-card">
                    <div class="profile-header">
                        <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                        <h2 class="mt-3 mb-2">Complete Your Profile</h2>
                        <p class="mb-0">Please provide your student information</p>
                    </div>
                    <div class="profile-body">
                        <div class="user-info">
                            <?php if (isset($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" 
                                     alt="Profile" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                    <i class="bi bi-person-fill fs-3"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                                <small class="text-muted">Authenticated with Google</small>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="profileForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="lastname" class="form-label required">Last Name</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="firstname" class="form-label required">First Name</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="mi" class="form-label">Middle Initial</label>
                                    <input type="text" class="form-control" id="mi" name="mi" maxlength="10"
                                           value="<?php echo htmlspecialchars($_POST['mi'] ?? ''); ?>">
                                    <small class="text-muted">Optional</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-submit">
                                    <i class="bi bi-check-circle me-2"></i>Complete Registration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>