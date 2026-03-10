<?php 
require("db-config/security.php");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Get teacher information including their assigned section
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    echo "<h1>Not a Teacher</h1>";
    exit;
}

// Get the teacher's section ID
$teacher_section_id = $teacher['section_id'];
$teacher_section_name = null;

// Get section name if teacher has assigned section
if ($teacher_section_id) {
    $section_info = get_section_name($pdo, $teacher_section_id);
    $teacher_section_name = $section_info['section_name'] ?? 'Unknown Section';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <?php
    require __DIR__ . '/headers/head.php'; //Included dito outside links and local styles
    ?>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <?php
        require __DIR__ . '/bars/topbar.php'; //Topbar yung kasama Profile Icon
    ?>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
    <?php
        require __DIR__ . '/bars/sidebar.php'; //Sidebar yung kasama Logged in Session
    ?>   
            </div>
            <div id="layoutSidenav_content">
                
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-12">
                               <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h5 class="text-center font-weight-light my-4">
                                            Assign Company to Students
                                            <?php if ($teacher_section_name): ?>
                                                <br><small class="text-muted">Section: <?= htmlspecialchars($teacher_section_name) ?></small>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                            if(@$_SESSION['section_message'] != ''){ ?>
                                                <div class="alert alert-info" role="alert">
                                                    <?=$_SESSION['section_message']?>
                                                </div>
                                            <?php 
                                            }
                                            
                                            // Display company update message if exists
                                            if(@$_SESSION['company_message'] != ''){ ?>
                                                <div class="alert alert-success" role="alert">
                                                    <?=$_SESSION['company_message']?>
                                                </div>
                                            <?php 
                                            }
                                        ?>
                                        
                                        <?php if (!$teacher_section_id): ?>
                                            <div class="alert alert-warning text-center">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                You are not assigned to any section yet. Please contact the administrator.
                                            </div>
                                        <?php else: ?>
                                        
                                        <table class='table table-striped'>
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Student Name</th>
                                                    <th>Section</th>
                                                    <th colspan="2">Company</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // Fetch only students from teacher's section
                                            $query_students = "
                                                SELECT * FROM students 
                                                WHERE section_id = :section_id 
                                                ORDER BY lastname, firstname
                                            ";
                                            $students_stmt = $pdo->prepare($query_students);
                                            $students_stmt->execute(['section_id' => $teacher_section_id]);
                                            $loop = 1;

                                            if ($students_stmt && $students_stmt->rowCount() > 0):
                                                foreach ($students_stmt as $fetched):
                                                    // Get current section name
                                                    $section_name_holder = get_section_name($pdo, $fetched['section_id']);
                                            ?>
                                                    <tr>
                                                        <td><?= $loop ?></td>
                                                        <td><?= htmlspecialchars($fetched['lastname']) ?>, <?= htmlspecialchars($fetched['firstname']) ?></td>
                                                        <td><?= htmlspecialchars($section_name_holder['section_name'] ?? 'N/A') ?></td>
                                                        <td colspan="2">
                                                            <!-- Company Text Field -->
                                                            <form method="POST" action="update_company" id="company_form_<?= $fetched['id'] ?>">
                                                                <input type="hidden" name="student_id" value="<?= $fetched['id'] ?>">
                                                                <textarea 
                                                                    name="company" 
                                                                    class="form-control form-control-sm" 
                                                                    placeholder="Enter company name"
                                                                    rows="2"><?= htmlspecialchars($fetched['company'] ?? '') ?></textarea>
                                                        </td>
                                                        <td>
                                                            <!-- Update Button -->
                                                            <button type="submit" class="btn btn-sm btn-success" title="Update company">
                                                                <i class="fas fa-save"></i> Update
                                                            </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                            <?php
                                                    $loop++;
                                                endforeach;
                                            else:
                                            ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        No students found in your section.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                        
                                        <!-- Summary Card -->
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Total Students in Your Section:</strong> 
                                                                <?= $students_stmt ? $students_stmt->rowCount() : 0 ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Section:</strong> 
                                                                <?= htmlspecialchars($teacher_section_name ?? 'N/A') ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
                <footer class="py-4 bg-light mt-auto">
                    <!-- Footer content here -->
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        
        <!-- Optional: Add auto-dismiss for alerts -->
        <script>
            $(document).ready(function() {
                // Auto-dismiss alerts after 5 seconds
                setTimeout(function() {
                    $('.alert').fadeOut('slow');
                }, 5000);
            });
        </script>
    </body>
</html>
<?php 
$_SESSION['message'] = '';
$_SESSION['section_message'] = '';
$_SESSION['company_message'] = '';
?>