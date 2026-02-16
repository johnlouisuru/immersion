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
                            <div class="col-lg-5">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h3 class="text-center font-weight-light my-4">Add New Section</h3></div>
                                    <div class="card-body">
                                        <form action="process_section_register" method="POST">
                                            <?=!empty($_SESSION['message']) ? '<div class="alert alert-primary" role="alert">'.$_SESSION['message'].'</div>' : '' ?> 
                                        <div class="row mb-12">
                                            <div class="col-md-12">
                                                <div class="form-floating mb-3 mb-md-0">
                                                    <input class="form-control" id="section_name" name="section_name" type="text" placeholder="Enter Section Name" required />
                                                    <label for="section_name">Section name</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-header"><h5 class="text-center font-weight-light my-4">* Optional: You can directly Assign Teacher to Section</h5></div>
                                        <div class="col-md-12">
                                            <div class="form-floating mb-3">
                                            <?php
                                                // Fetch all teachers
                                                $query_teachers = "SELECT * FROM teachers";
                                                $teachers_stmt = secure_query_no_params($pdo, $query_teachers);
                                                $teachers = $teachers_stmt ? $teachers_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

                                            ?>
                                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                                <option value="0" selected>Select Teacher *Optional</option>
                                                <?php if ($teachers_stmt && $teachers_stmt->rowCount() > 0): ?>
                                                    <?php foreach ($teachers as $teacher): ?>
                                                        <?php
                                                            // Check if teacher is already assigned to a section
                                                            $stmt_check = $pdo->prepare("SELECT id FROM sections WHERE teacher_id = ?");
                                                            $stmt_check->execute([$teacher['id']]);
                                                            $is_assigned = $stmt_check->fetch(PDO::FETCH_ASSOC);
                                                        ?>
                                                        <option 
                                                            value="<?= $teacher['id'] ?>"
                                                            <?= $is_assigned ? 'disabled' : '' ?>>
                                                            <?= htmlspecialchars($teacher['lastname']) ?>, <?= htmlspecialchars($teacher['firstname']) ?>
                                                            <?= $is_assigned ? ' (Already Assigned)' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="" disabled>No teachers available</option>
                                                <?php endif; ?>
                                            </select>
                                            <label for="teacher_id">Select Teacher</label>
                                        </div>

                                        </div>
                                        <div class="mt-4 mb-0">
                                            <div class="d-grid">
                                                <button class="btn btn-primary btn-block" type="submit">Create Section</button>
                                            </div>
                                        </div>
                                    </form>

                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                               <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h5 class="text-center font-weight-light my-4">All Sections</h5></div>
                                        <div class="card-body">
                                            <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Section Name</th>
                                                    <th>Assigned Teacher</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                                $query_sections = "
                                                    SELECT s.*, t.firstname, t.lastname
                                                    FROM sections s
                                                    LEFT JOIN teachers t ON s.teacher_id = t.id
                                                ";
                                                $sections_stmt = secure_query_no_params($pdo, $query_sections);
                                                $loop = 1;
                                            ?>

                                            <?php foreach ($sections_stmt as $section): ?>
                                                <tr>
                                                    <td><?= $loop++ ?></td>
                                                    <td><a href="section_class?sn=<?= htmlspecialchars($section['section_name']) ?>&sid=<?= htmlspecialchars($section['id']) ?>"><?= htmlspecialchars($section['section_name']) ?></a></td>
                                                    <td>
                                                        <?= $section['teacher_id']
                                                            ? htmlspecialchars($section['lastname'] . ', ' . $section['firstname'])
                                                            : 'No Assigned Teacher'; ?>
                                                    </td>
                                                    <td><?= $section['is_active'] == 1 ? 'Active' : 'Inactive' ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning btn-edit-section"
                                                            data-id="<?= $section['id'] ?>"
                                                            data-name="<?= htmlspecialchars($section['section_name']) ?>"
                                                            data-teacher="<?= (int)$section['teacher_id'] ?>"
                                                            data-active="<?= (int)$section['is_active'] ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editSectionModal">
                                                            Edit
                                                        </button>

                                                        <button class="btn btn-sm btn-danger btn-delete-section"
                                                            data-id="<?= $section['id'] ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteSectionModal">
                                                            Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
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
                <footer class="py-4 bg-light mt-auto">

                </footer>
            </div>
        </div>

        <!-- Edit Modal Section -->
         <div class="modal fade" id="editSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editSectionForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Section</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="edit_section_id">

        <div class="mb-3">
          <label class="form-label">Section Name</label>
          <input type="text" class="form-control" name="section_name" id="edit_section_name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Assigned Teacher</label>
          <select class="form-select" name="teacher_id" id="edit_teacher_id">
            <option value="0">No Teacher</option>
            <?php foreach ($teachers as $teacher): ?>
                <?php if($teacher['section_id'] == 0 || $teacher['section_id'] == null): ?> 
                    <option value="<?= $teacher['id'] ?>">
                        <?= htmlspecialchars($teacher['lastname'] . ', ' . $teacher['firstname']) ?>
                    </option>
                    <?php else: ?>
                        <option value="0" disabled>
                            <?= htmlspecialchars($teacher['lastname'] . ', ' . $teacher['firstname']) ?> [Already Assigned]
                        </option>
                <?php endif; ?> 
              
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="is_active" id="edit_is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="deleteSectionForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Confirm Delete</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="delete_section_id">
        <p>Are you sure you want to deactivate this section?</p>
      </div>

      <div class="modal-footer">
        <button class="btn btn-danger" type="submit">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>

        <script>
$(document).ready(function () {

    // Populate Edit Modal
    $('.btn-edit-section').on('click', function () {
        $('#edit_section_id').val($(this).data('id'));
        $('#edit_section_name').val($(this).data('name'));
        $('#edit_teacher_id').val($(this).data('teacher'));
        $('#edit_is_active').val($(this).data('active'));
    });

    // Update Section
    $('#editSectionForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'process_section_update.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message);
                }
            }
        });
    });

    // Delete Section
    $('.btn-delete-section').on('click', function () {
        $('#delete_section_id').val($(this).data('id'));
    });

    $('#deleteSectionForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'process_section_delete.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message);
                }
            }
        });
    });

});
</script>

    </body>
</html>
<?php 
$_SESSION['message'] = '';
?>
