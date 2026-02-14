<?php 
    require("db-config/security.php");
    if (!isLoggedIn()) {
        header('Location: index');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php require __DIR__ . '/headers/head.php'; ?>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <?php require __DIR__ . '/bars/topbar.php'; ?>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <?php require __DIR__ . '/bars/sidebar.php'; ?>   
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <!-- Teachers Table Section -->
                            <div class="col-lg-12">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h5 class="text-center font-weight-light my-4">All Teachers</h5></div>
                                    <div class="card-body">
                                        <?php if(@$_SESSION['section_message'] != ''): ?>
                                            <div class="alert alert-info" role="alert">
                                                <?= $_SESSION['section_message'] ?>
                                            </div>
                                        <?php endif; ?>
                                        <table class='table table-striped'>
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Teacher Name</th>
                                                    <th>Section Assigned</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // Fetch all teachers
                                            $query_requirements = "SELECT * FROM teachers";
                                            $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
                                            $loop = 1;

                                            if ($requirements_stmt && $requirements_stmt->rowCount() > 0):
                                                foreach ($requirements_stmt as $fetched):
                                                    // Get teacher's current section assignment (returns section_id)
                                                    $current_assignment = get_teacher_section_assignment($pdo, $fetched['id']); 
                                                    $current_section_id = $current_assignment ? $current_assignment : 0;

                                                    // Fetch all sections for dropdown
                                                    $query_all_sections = "SELECT * FROM sections";
                                                    $all_sections = secure_query_no_params($pdo, $query_all_sections);
                                            ?>
                                                    <tr>
                                                        <td><?= $loop ?></td>
                                                        <td><?= htmlspecialchars($fetched['lastname']) ?>, <?= htmlspecialchars($fetched['firstname']) ?></td>
                                                        <td>
                                                            <!-- Dropdown Form -->
                                                            <form method="POST" action="update_teacher_section.php" style="display:inline;">
                                                                <input type="hidden" name="teacher_id" value="<?= $fetched['id'] ?>">
                                                                <select name="section_id" class="form-select" onchange="this.form.submit()">
                                                                    <option value="0" disabled selected>-- Not Yet Assigned --</option>
                                                                    <?php foreach ($all_sections as $section): ?>
                                                                        <?php
                                                                            // Conditions
                                                                            $is_same_teacher = ($section['teacher_id'] == $fetched['id']);
                                                                            $is_assigned_to_other = (!empty($section['teacher_id']) && !$is_same_teacher);
                                                                        ?>
                                                                        <option
                                                                            value="<?= $section['id'] ?>"
                                                                            <?= $is_same_teacher ? 'selected' : '' ?>
                                                                            <?= $is_assigned_to_other ? 'disabled' : '' ?>>
                                                                            <?= htmlspecialchars($section['section_name']) ?> 
                                                                            <?= $is_assigned_to_other ? ' (Assigned to '.get_teacher_name($pdo, $section['teacher_id']).')' : '' ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </form>
                                                        </td>
                                                    </tr>
                                            <?php
                                                    $loop++;
                                                endforeach;
                                            else:
                                            ?>
                                                <tr><td colspan="3" class="text-center">No teachers found</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Allowed Teachers CRUD Section -->
                            <div class="col-lg-12">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="font-weight-light my-4">Allowed Email(s) to register [Only Admin can Manage]</h5>
                                            <!-- Add New Button - triggers modal -->
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAllowedTeacherModal">
                                                <i class="fas fa-plus"></i> Add New Email
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if(@$_SESSION['allowed_message'] != ''): ?>
                                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                                <?= $_SESSION['allowed_message'] ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <table class='table table-striped'>
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                    <th>Created At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // Fetch all allowed teachers
                                            $query_allowed = "SELECT * FROM allowed_teachers ORDER BY created_at DESC";
                                            $allowed_stmt = secure_query_no_params($pdo, $query_allowed);
                                            $loop_allowed = 1;

                                            if ($allowed_stmt && $allowed_stmt->rowCount() > 0):
                                                foreach ($allowed_stmt as $allowed):
                                            ?>
                                                    <tr>
                                                        <td><?= $loop_allowed ?></td>
                                                        <td><?= htmlspecialchars($allowed['allowed_email']) ?></td>
                                                        <td>
                                                            <?php if($allowed['is_allowed'] == 1): ?>
                                                                <span class="badge bg-success">Allowed</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Blocked</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= date('M d, Y h:i A', strtotime($allowed['created_at'])) ?></td>
                                                        <td>
                                                            <!-- Edit Button -->
                                                            <button type="button" class="btn btn-sm btn-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editAllowedTeacherModal<?= $allowed['id'] ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <!-- Delete Button -->
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteAllowedTeacherModal<?= $allowed['id'] ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>

                                                            <!-- Toggle Status Button -->
                                                            <a href="toggle_allowed_teacher_status.php?id=<?= $allowed['id'] ?>" 
                                                               class="btn btn-sm <?= $allowed['is_allowed'] == 1 ? 'btn-secondary' : 'btn-success' ?>"
                                                               onclick="return confirm('Are you sure you want to <?= $allowed['is_allowed'] == 1 ? 'block' : 'allow' ?> this email?')">
                                                                <i class="fas <?= $allowed['is_allowed'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i>
                                                            </a>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit Modal for each record -->
                                                    <div class="modal fade" id="editAllowedTeacherModal<?= $allowed['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Edit Allowed Email</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form action="process_allowed_teacher.php" method="POST">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="edit">
                                                                        <input type="hidden" name="id" value="<?= $allowed['id'] ?>">
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="email<?= $allowed['id'] ?>" class="form-label">Email address</label>
                                                                            <input type="email" class="form-control" id="email<?= $allowed['id'] ?>" 
                                                                                   name="email" value="<?= htmlspecialchars($allowed['allowed_email']) ?>" required>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="status<?= $allowed['id'] ?>" class="form-label">Status</label>
                                                                            <select class="form-select" id="status<?= $allowed['id'] ?>" name="is_allowed">
                                                                                <option value="1" <?= $allowed['is_allowed'] == 1 ? 'selected' : '' ?>>Allowed</option>
                                                                                <option value="0" <?= $allowed['is_allowed'] == 0 ? 'selected' : '' ?>>Blocked</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Delete Modal for each record -->
                                                    <div class="modal fade" id="deleteAllowedTeacherModal<?= $allowed['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Delete Allowed Email</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete this email?</p>
                                                                    <p><strong><?= htmlspecialchars($allowed['allowed_email']) ?></strong></p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <a href="process_allowed_teacher.php?action=delete&id=<?= $allowed['id'] ?>" 
                                                                       class="btn btn-danger">Delete</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                            <?php
                                                    $loop_allowed++;
                                                endforeach;
                                            else:
                                            ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No allowed emails found</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
                <footer class="py-4 bg-light mt-auto"></footer>
            </div>
        </div>

        <!-- Add New Allowed Teacher Modal -->
        <div class="modal fade" id="addAllowedTeacherModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Allowed Email</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="process_allowed_teacher.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="new_email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="new_email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_status" class="form-label">Status</label>
                                <select class="form-select" id="new_status" name="is_allowed">
                                    <option value="1" selected>Allowed</option>
                                    <option value="0">Blocked</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
<?php 
$_SESSION['message'] = '';
$_SESSION['section_message'] = '';
$_SESSION['allowed_message'] = '';
?>