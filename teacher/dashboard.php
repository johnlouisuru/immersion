<?php 
require("db-config/security.php");

if (!isGoogleAuthenticated() || !isLoggedIn()) {
    die('Unauthorized!');
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <?php
    require __DIR__ . '/headers/head.php';
    ?>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <?php
        require __DIR__ . '/bars/topbar.php';
    ?>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
    <?php
        require __DIR__ . '/bars/sidebar.php';
    ?>   
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4"><?=$_ENV['PAGE_SIDEBAR']?></h1>
                            <ol class="breadcrumb mb-4">
                                <li class="breadcrumb-item active"><?=ucwords($filename)?></li>
                            </ol>
                        <div class="row">
                                <div class="col-xl-3 col-md-6">
                                <div class="card bg-primary text-white mb-4">
                                    <div class="card-body">Students</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="register">View Details</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-warning text-white mb-4">
                                    <div class="card-body">Sections</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="register_section">View Details</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-success text-white mb-4">
                                    <div class="card-body"> Teachers</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="register_teacher">View Details</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-danger text-white mb-4">
                                    <div class="card-body">Requirements</div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <a class="small text-white stretched-link" href="register_requirement">View Details</a>
                                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                                    </div>
                                </div>
                            </div>
                            
                        
                        <!-- Table Template -->
    <div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table me-1"></i>
        Students and Requirements in Your Section
    </div>
    <div class="card-body">
        <?php 
        // Ensure $to_query is always set
        if (empty($to_query)) {
            $to_query = '';
        }

        // 1. Get all active requirements
        $query_requirements = "SELECT * FROM requirements WHERE is_active = 1";
        $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
        $requirements = $requirements_stmt ? $requirements_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 2. Get only students from the teacher's section
        if ($teacher_section_id) {
            // If teacher has an assigned section, get only those students
            $query_students = "SELECT * FROM students WHERE section_id = :section_id " . $to_query . " ORDER BY lastname ASC";
            $students_stmt = $pdo->prepare($query_students);
            $students_stmt->execute(['section_id' => $teacher_section_id]);
        } else {
            // If teacher has no assigned section, show no students
            $students_stmt = null;
            $students = [];
        }
        $students = $students_stmt ? $students_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 3. Get statuses only for students in this teacher's section
        if (!empty($students)) {
            // Create placeholders for student IDs
            $student_ids = array_column($students, 'id');
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            
            $query_statuses = "
                SELECT 
                    rs.*,
                    r.req_name
                FROM requirements_status rs
                INNER JOIN requirements r 
                    ON rs.req_id = r.id
                WHERE r.is_active = 1
                AND rs.student_id IN ($placeholders)
            ";
            
            $statuses_stmt = $pdo->prepare($query_statuses);
            $statuses_stmt->execute($student_ids);
            $statuses = $statuses_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $statuses = [];
        }

        // 4. Build a quick lookup: [student_id][req_id] => is_checked
        $status_map = [];
        foreach ($statuses as $s) {
            $status_map[$s['student_id']][$s['req_id']] = (int)$s['is_checked'];
        }
        ?>

        <table id="datatablesSimple">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Section</th>
                    <th>Email</th>
                    <th>Teacher Assigned</th>
                    <th>Requirement Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            <?php if (!$teacher_section_id): ?>
                                You are not assigned to any section yet.
                            <?php else: ?>
                                No students found in your section.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $loop = 1; ?>
                    <?php foreach ($students as $fetched): ?>
                        <?php $section_teacher_holder = get_section_name($pdo, $fetched['section_id']); ?>
                        <tr>
                            <td><?= $loop++ ?></td>
                            <td><?= htmlspecialchars($fetched['lastname']). ', '.htmlspecialchars($fetched['firstname'])  ?></td>
                            <td><?= htmlspecialchars($section_teacher_holder['section_name']) ?></td>
                            <td><?= htmlspecialchars($fetched['email']) ?></td>
                            <?php 
                                if($section_teacher_holder['teacher_id'] == NULL || $section_teacher_holder['teacher_id'] == 0 ){
                                        echo "<td>No Assigned Teacher.</td>";
                                }else {
                                    ?>
                                    <td><?= htmlspecialchars(get_teacher_name($pdo, $section_teacher_holder['teacher_id']) ?? '-') ?></td>
                                <?php 
                            }
                            ?>
                            <td>
                                <?php
                                $percentage = get_requirement_percentage($pdo, $fetched['id']);
                                $barClass = ($percentage == 100) ? 'bg-success' : 'bg-danger';
                                ?>
                                <div class="progress">
                                    <div class="progress-bar <?= $barClass ?>"
                                        role="progressbar"
                                        style="width: <?= $percentage ?>%;"
                                        aria-valuenow="<?= $percentage ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?= round($percentage, 2) ?>%
                                    </div>
                                </div>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

                        <!-- Table Template -->
                        </div>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
     <?php
        require __DIR__ . '/footer/footer.php';
    ?>
                </footer>
            </div>
        </div>
        <!-- Footer SCripts -->
    <?php
        require __DIR__ . '/footer/footer-scripts.php';
    ?>
    </body>
</html>