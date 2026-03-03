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
                            
                        

                            <div class="col-lg-12">
                               <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h5 class="text-center font-weight-light my-4">All Students</h5></div>
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
                                            <table class='table table-striped'>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Student Name</th>
                                                    <th>Section</th>
                                                    <th>Company</th>
                                                    <th>Action</th>
                                                </tr>

                                            <?php
                                            // Fetch all students
                                            $query_requirements = "SELECT * FROM students ORDER BY lastname, firstname";
                                            $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
                                            $loop = 1;

                                            if ($requirements_stmt && $requirements_stmt->rowCount() > 0):
                                                foreach ($requirements_stmt as $fetched):
                                                    // Get current section name
                                                    $section_name_holder = get_section_name($pdo, $fetched['section_id']);
                                                    $current_section_id = $fetched['section_id'];

                                                    // Fetch all sections for dropdown
                                                    $query_all_sections = "SELECT * FROM sections";
                                                    $all_sections = secure_query_no_params($pdo, $query_all_sections);
                                            ?>
                                                    <tr>
                                                        <td><?= $loop ?></td>
                                                        <td><?= htmlspecialchars($fetched['lastname']) ?>, <?= htmlspecialchars($fetched['firstname']) ?></td>
                                                        <td>
                                                            <!-- Section Dropdown -->
                                                            <form method="POST" action="update_section" style="display:inline;">
                                                                <input type="hidden" name="student_id" value="<?= $fetched['id'] ?>">
                                                                <select name="section_id" class="form-select" onchange="this.form.submit()">
                                                                    <option value="">-- Select Section --</option>
                                                                    <?php foreach ($all_sections as $section): ?>
                                                                        <option 
                                                                            value="<?= $section['id'] ?>" 
                                                                            <?= ($section['id'] == $current_section_id) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($section['section_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </form>
                                                        </td>
                                                        <td>
                                                            <!-- Company Text Field -->
                                                            <form method="POST" action="update_company" id="company_form_<?= $fetched['id'] ?>">
                                                                <input type="hidden" name="student_id" value="<?= $fetched['id'] ?>">
                                                                <input type="text" 
                                                                       name="company" 
                                                                       class="form-control form-control-sm" 
                                                                       value="<?= htmlspecialchars($fetched['company'] ?? '') ?>" 
                                                                       placeholder="Enter company name">
                                                        </td>
                                                        <td>
                                                                <!-- Update Button -->
                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                    Update
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                            <?php
                                                    $loop++;
                                                endforeach;
                                            else:
                                            ?>
                                                <tr><td colspan="5" class="text-center">No students found</td></tr>
                                            <?php endif; ?>
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>
<?php 
$_SESSION['message'] = '';
$_SESSION['section_message'] = '';
$_SESSION['company_message'] = '';
?>