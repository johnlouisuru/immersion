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
                            <!-- <div class="col-lg-8">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h3 class="text-center font-weight-light my-4">Create Teacher Account</h3></div>
                                    <div class="card-body">
                                        <form action="process_teacher_register" method="POST">
                                            <div class="alert alert-primary" role="alert"><?=@$_SESSION['message']?></div>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-floating mb-3 mb-md-0">
                <input class="form-control" id="fname" name="fname" type="text" placeholder="Enter your first name" required />
                <label for="fname">First name</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating">
                <input class="form-control" id="lname" name="lname" type="text" placeholder="Enter your last name" required />
                <label for="lname">Last name</label>
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-floating mb-3">
                <input class="form-control" id="email" name="email" type="email" placeholder="YoungStunnah@example.com" required />
                <label for="email">Email address</label>
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-floating mb-3 mb-md-0">
                <input class="form-control" id="password" name="password" type="password" placeholder="Create a password" required />
                <label for="password">Password</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating mb-3 mb-md-0">
                <input class="form-control" id="repassword" name="repassword" type="password" placeholder="Confirm password" required />
                <label for="repassword">Confirm Password</label>
            </div>
        </div>
    </div>
    
    <div class="mt-4 mb-0">
        <div class="d-grid">
            <button class="btn btn-primary btn-block" type="submit">Create Account</button>
        </div>
    </div>
</form>

                                    </div>
                                    <div class="card-footer text-center py-3">
                                        <div class="small"><a href="login.html">Have an account? Go to login</a></div>
                                    </div>
                                </div>
                            </div> -->

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
                                            ?>
                                           <table class='table table-striped'>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Teacher Name</th>
                                                    <th>Section Assigned</th>
                                                </tr>

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
?>
