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
                                    <div class="card-header"><h3 class="text-center font-weight-light my-4">Add New Requirement</h3></div>
                                        <div class="card-body">
                                            <form action="process_requirement_register" method="POST">
                                                <div class="alert alert-primary" role="alert"><?=@$_SESSION['message']?></div>
                                                    <div class="row mb-12">
                                                        <div class="col-md-12">
                                                            <div class="form-floating mb-3 mb-md-0">
                                                                <input class="form-control" id="requirement_name" name="requirement_name" type="text" placeholder="Enter Requirement Name" required />
                                                                <label for="requirement_name">Requirement name</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="form-floating mb-3 mb-md-0">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" name="is_active" id="is_active" type="checkbox" role="switch" id="flexSwitchCheckChecked" checked>
                                                                    <label class="form-check-label" for="flexSwitchCheckChecked">Active / Inactive</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 mb-0">
                                                        <div class="d-grid">
                                                            <button class="btn btn-primary btn-block" type="submit">Add Requirement</button>
                                                        </div>
                                                    </div>
                                            </form>

                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                               <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h5 class="text-center font-weight-light my-4">All Requirement</h5></div>
                                        <div class="card-body">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Requirement Name</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                    $query_requirements = "SELECT * FROM requirements";
                                                    $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
                                                    $loop = 1;
                                                ?>
                                                <?php foreach ($requirements_stmt as $fetched): ?>
                                                    <tr>
                                                        <td><?= $loop ?></td>
                                                        <td><?= htmlspecialchars($fetched['req_name']) ?></td>
                                                        <td>
                                                            <?= $fetched['is_active'] == 1 ? 'Active' : 'Inactive' ?>
                                                        </td>
                                                        <td>
                                                            <button 
                                                                class="btn btn-sm btn-warning btn-edit"
                                                                data-id="<?= $fetched['id'] ?>"
                                                                data-name="<?= htmlspecialchars($fetched['req_name']) ?>"
                                                                data-active="<?= (int)$fetched['is_active'] ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editModal">
                                                                Edit
                                                            </button>

                                                            <button 
                                                                class="btn btn-sm btn-danger btn-delete"
                                                                data-id="<?= $fetched['id'] ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteModal">
                                                                Delete
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php $loop++; endforeach; ?>
                                                </tbody>
                                            </table>

                                        </div>
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

<!-- EDIT REQUIREMENT MODAL -->
 <div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="process_requirement_update.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Requirement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="edit_id">

        <div class="mb-3">
          <label class="form-label">Requirement Name</label>
          <input type="text" class="form-control" name="req_name" id="edit_name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="is_active" id="edit_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="process_requirement_delete.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="delete_id">
        <p>Are you sure you want to deactivate this requirement?</p>
      </div>

      <div class="modal-footer">
        <button class="btn btn-danger" type="submit">Yes, Delete</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
      </div>
    </form>
  </div>
</div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {

                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('edit_id').value = this.dataset.id;
                        document.getElementById('edit_name').value = this.dataset.name;
                        document.getElementById('edit_active').value = this.dataset.active ?? 0;
                    });
                });

                document.querySelectorAll('.btn-delete').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('delete_id').value = this.dataset.id;
                    });
                });

            });
            </script>

    </body>
</html>
<?php 
$_SESSION['message'] = '';
?>
