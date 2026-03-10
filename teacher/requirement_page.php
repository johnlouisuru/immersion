<?php 
$to_query = '';
require("db-config/security.php");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

// Get the logged-in teacher's section information
$teacher_section_id = null;
$teacher_section_name = null;

if (isset($_SESSION['user_id'])) {
    $teacher_stmt = $pdo->prepare("
        SELECT t.*, s.section_name 
        FROM teachers t 
        LEFT JOIN sections s ON t.section_id = s.id 
        WHERE t.id = :id
    ");
    $teacher_stmt->execute(['id' => $_SESSION['user_id']]);
    $teacher_info = $teacher_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher_info) {
        $teacher_section_id = $teacher_info['section_id'];
        $teacher_section_name = $teacher_info['section_name'];
    }
}

// Modify the section filter logic to respect teacher's assigned section
if(isset($_GET['section_id']) && $_GET['section_id'] !== 'all'){
    // Only allow filtering if the selected section matches teacher's section or if it's 'all'
    $sectionId = intval($_GET['section_id']);
    
    // If teacher has a section assigned, only allow viewing that section
    if ($teacher_section_id) {
        if ($sectionId == $teacher_section_id) {
            $to_query = "WHERE section_id = " . $sectionId;
        } else {
            // If trying to view another section, redirect to show only their section
            header('Location: requirement_page?section_id=' . $teacher_section_id);
            exit;
        }
    } else {
        // Teacher has no section assigned, show no students
        $to_query = "WHERE 1=0"; // This will return no results
    }
} else {
    // Default view - show teacher's section or no students if no section assigned
    if ($teacher_section_id) {
        $to_query = "WHERE section_id = " . $teacher_section_id;
        // Set the section_id in URL for consistency if not set
        if (!isset($_GET['section_id'])) {
            header('Location: requirement_page?section_id=' . $teacher_section_id);
            exit;
        }
    } else {
        $to_query = "WHERE 1=0"; // No section assigned, show no students
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                    <div class="container-fluid px-4">
                        <h1 class="mt-4"><?=$_ENV['PAGE_SIDEBAR']?></h1>
                            <ol class="breadcrumb mb-4">
                                <li class="breadcrumb-item active"><?=ucwords($filename)?></li>
                            </ol>
                        <div class="row">
                        
                        <!-- Table Template -->
<?php
// 1. Requirements - still get all active requirements
$query_requirements = "SELECT * FROM requirements WHERE is_active = 1";
$requirements_stmt = secure_query_no_params($pdo, $query_requirements);
$requirements = $requirements_stmt ? $requirements_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// 2. Students - now filtered by teacher's section via $to_query
$query_students = "SELECT * FROM students " . $to_query . " ORDER BY lastname, firstname";
$students_stmt = secure_query_no_params($pdo, $query_students);
$students = $students_stmt ? $students_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// 3. Statuses - only get statuses for students in this section
if (!empty($students)) {
    $student_ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    $query_statuses = "SELECT * FROM requirements_status WHERE student_id IN ($placeholders)";
    $statuses_stmt = $pdo->prepare($query_statuses);
    $statuses_stmt->execute($student_ids);
    $statuses = $statuses_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $statuses = [];
}

// 4. Lookup
$status_map = [];
foreach ($statuses as $s) {
    $status_map[$s['student_id']][$s['req_id']] = (int)$s['is_checked'];
}

$current_section = $_GET['section_id'] ?? ($teacher_section_id ?? 'all');
$button_label = $teacher_section_name ? 'Section: ' . htmlspecialchars($teacher_section_name) : 'No Section Assigned';

?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-table me-1"></i>
            Student Requirements - <?= htmlspecialchars($teacher_section_name ?? 'No Section') ?>
        </div>
        
        <?php if ($teacher_section_name): ?>
        <!-- Optional: Show section info instead of dropdown since teacher only has one section -->
        <div class="badge bg-primary p-2">
            <i class="fas fa-users me-1"></i>
            <?= htmlspecialchars($teacher_section_name) ?> (<?= count($students) ?> students)
        </div>
        <?php else: ?>
        <div class="badge bg-warning p-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            No Section Assigned
        </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <?php if (!$teacher_section_id): ?>
                    You are not assigned to any section. Please contact the administrator.
                <?php else: ?>
                    No students found in your section (<?= htmlspecialchars($teacher_section_name) ?>).
                <?php endif; ?>
            </div>
        <?php else: ?>

        <table id="datatablesSimple" class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Section</th>
                    <th>Email</th>
                    <th>Teacher Assigned</th>
                    <?php foreach ($requirements as $req): ?>
                        <th><?= htmlspecialchars($req['req_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $loop = 1; ?>
                <?php foreach ($students as $fetched): ?>
                    <?php $section_teacher_holder = get_section_name($pdo, $fetched['section_id']); ?>
                    <tr data-section="<?= $fetched['section_id'] ?>">
                        <td><?= $loop++ ?></td>
                        <td><?= htmlspecialchars($fetched['lastname']) . ', ' . htmlspecialchars($fetched['firstname'])  ?></td>
                        <td><?= htmlspecialchars($section_teacher_holder['section_name']) ?></td>
                        <td><?= htmlspecialchars($fetched['email']) ?></td>
                        <?php
                        if ($section_teacher_holder['teacher_id'] == 0) {
                            echo "<td>No Assigned Teacher.</td>";
                        } else {
                            echo "<td>" . htmlspecialchars(get_teacher_name($pdo, $section_teacher_holder['teacher_id'])) . "</td>";
                        }
                        ?>
                        <?php foreach ($requirements as $req):
                            $is_checked = $status_map[$fetched['id']][$req['id']] ?? 0;
                        ?>
                            <td>
                                <input class="statusCheck form-check-input"
                                    type="checkbox"
                                    data-student="<?= $fetched['id'] ?>"
                                    data-id="<?= $req['id'] ?>"
                                    <?= $is_checked ? 'checked' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Remove the old JavaScript filter since it's no longer needed -->
<script>
$(document).ready(function() {
    $(".statusCheck").on("change", function() {
        const userId    = $(this).data("id");        // requirement id
        const studentId = $(this).data("student");   // student id
        const isChecked = $(this).is(":checked") ? 1 : 0;

        $.ajax({
            url: "tables/update_requirements.php",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({ 
                id: userId, 
                student_id: studentId, 
                status: isChecked 
            }),
            success: function(response) {
                try {
                    let result = (typeof response === "string")
                        ? JSON.parse(response)
                        : response;

                    if (result.success) {
                        showToast('toastSuccess');
                    } else {
                        showToast('toastError');
                        console.error(result.error);
                    }

                } catch (err) {
                    console.error("Invalid JSON", err);
                    showToast('toastError');
                }
            },
            error: function() {
                console.error("AJAX Error");
                showToast('toastError');
            }
        });
    });

    function showToast(id) {
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 2500 });
        toast.show();
    }
});
</script>

                        <!-- Table Template -->
                        </div>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
     <?php
        require __DIR__ . '/footer/footer.php'; //Literal Footer with (c) and Year
    ?>
                </footer>
            </div>
        </div>
        <!-- Footer SCripts -->
    <?php
    require __DIR__ . '/footer/footer-scripts.php'; //Footer JavaSCripts 
    ?>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <!-- Success Toast -->
        <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Requirement updated successfully
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <!-- Error Toast -->
        <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ❌ Error while updating requirement
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    </body>
</html>