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
                 <?php
                   $query_students = "
    SELECT 
        st.id AS student_id,
        st.lrn,
        st.firstname AS student_firstname,
        st.lastname AS student_lastname,
        st.email AS student_email,
        s.id AS section_id,
        s.section_name,
        s.teacher_id AS stid,
        t.id AS teacher_id,
        CONCAT(t.firstname, ' ', t.lastname) AS teacher_fullname
    FROM students st
    INNER JOIN sections s ON st.section_id = s.id
    INNER JOIN teachers t ON s.teacher_id = t.id
    WHERE st.section_id = ".$_GET['sid']."
";



                    $students_stmt = secure_query_no_params($pdo, $query_students);
                    $loop = 1;
                    $rowSpan = $students_stmt->rowCount();
                    $assigned_teacher = '';
                ?>

                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-12">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h3 class="text-center font-weight-light my-4">Section: <?= $_GET['sn'] ?></h3></div>
                                    <div class="card-body">
                                        <table class="table table-striped">
                                        <?php if($rowSpan == 0 || $rowSpan == null): ?>
                                                <h5>No Students assigned in this Section</h5>
                                            <?php else: ?>
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>LRN#</th>
                                                    <th>Fullname</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            

                                            
                                            
                                            <?php foreach ($students_stmt as $section): 
                                            $assigned_teacher = get_teacher_name($pdo, $section['stid']);
                                            ?>
                                                <tr>
                                                    <td><?= $loop++ ?></td>
                                                    <td><?= htmlspecialchars($section['lrn']) ?></td>
                                                    <td><?= htmlspecialchars($section['student_lastname'] . ', ' . $section['student_firstname']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <th colspan="3" >Adviser: <?= $assigned_teacher ?></th>
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
