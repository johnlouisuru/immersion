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
                            
                        <!-- Student Creation Form (Commented out in your code) -->
                        <!-- ... -->

                            <div class="col-lg-12">
                               <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <h5 class="text-center font-weight-light my-4">
                                            Students in Your Section
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
                                        ?>
                                        
                                        <?php if (!$teacher_section_id): ?>
                                            <div class="alert alert-warning text-center">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                You are not assigned to any section yet. Please contact the administrator.
                                            </div>
                                        <?php else: ?>
                                        
                                        <div class="table-responsive">
                                            <table class='table table-striped table-hover'>
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Student Name</th>
                                                        <th>Section</th>
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
                                                        $current_section_id = $fetched['section_id'];

                                                        // Fetch all sections for dropdown (but only show if admin or needed)
                                                        $query_all_sections = "SELECT * FROM sections";
                                                        $all_sections = secure_query_no_params($pdo, $query_all_sections);
                                                ?>
                                                        <tr>
                                                            <td><?= $loop ?></td>
                                                            <td>
                                                                <i class="fas fa-user-graduate me-2 text-primary"></i>
                                                                <?= htmlspecialchars($fetched['lastname']) ?>, <?= htmlspecialchars($fetched['firstname']) ?>
                                                                <?php if (!empty($fetched['mi'])): ?>
                                                                    <?= htmlspecialchars($fetched['mi']) ?>.
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <!-- Section Dropdown - Teachers can only assign students to their own section -->
                                                                <form method="POST" action="update_section" style="display:inline;" class="section-update-form">
                                                                    <input type="hidden" name="student_id" value="<?= $fetched['id'] ?>">
                                                                    <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 150px;">
                                                                        <option value="">-- Select Section --</option>
                                                                        <?php foreach ($all_sections as $section): ?>
                                                                            <option 
                                                                                value="<?= $section['id'] ?>" 
                                                                                <?= ($section['id'] == $current_section_id) ? 'selected' : '' ?>
                                                                                <?php //($section['id'] != $teacher_section_id) ? 'disabled style="color:#999;"' : '' ?>
                                                                                <?= ($section['id'] != $teacher_section_id) ? '' : '' ?>
                                                                                >
                                                                                <?= htmlspecialchars($section['section_name']) ?>
                                                                                <?= ($section['id'] == $teacher_section_id) ? ' (Your Section)' : ' (Other Section)' ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </form>
                                                                <small class="text-muted d-block mt-1">
                                                                    <i class="fas fa-info-circle"></i> Once assigned to other section, student will not be listed in your section anymore.
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <!-- <button class="btn btn-sm btn-outline-primary" onclick="alert('Edit functionality coming soon')">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button> -->
                                                                    <button class="btn btn-sm btn-outline-info" disabled>
                                                                        <i class="fas fa-sync-alt"></i> Auto
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                <?php
                                                        $loop++;
                                                    endforeach;
                                                else:
                                                ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-4">
                                                            <i class="fas fa-users fa-3x mb-3" style="color: #dee2e6;"></i>
                                                            <p class="mb-0">No students found in your section.</p>
                                                            <small class="text-muted">Students will appear here once they are assigned to <?= htmlspecialchars($teacher_section_name) ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Summary Card -->
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <strong>Total Students in Your Section:</strong> 
                                                                <span class="badge bg-primary"><?= $students_stmt ? $students_stmt->rowCount() : 0 ?></span>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <strong>Your Section:</strong> 
                                                                <span class="badge bg-info"><?= htmlspecialchars($teacher_section_name ?? 'N/A') ?></span>
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
                    <!-- Footer content -->
                </footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        
        <script>
            // Add confirmation for section changes
            document.querySelectorAll('.section-update-form select').forEach(select => {
                select.addEventListener('change', function(e) {
                    const selectedOption = this.options[this.selectedIndex];
                    const sectionName = selectedOption.text;
                    
                    if (this.value) {
                        const confirmChange = confirm(`Are you sure you want to move this student to ${sectionName}?`);
                        if (!confirmChange) {
                            // Reset to previous value
                            this.value = '<?= $current_section_id ?>';
                            e.preventDefault();
                        }
                    }
                });
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        </script>
    </body>
</html>
<?php 
$_SESSION['message'] = '';
$_SESSION['section_message'] = '';
?>