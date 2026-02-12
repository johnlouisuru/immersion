<?php
$to_query = '';
if (!empty($_GET['section_id']) && $_GET['section_id'] !== 'all') {
    $section_id = intval($_GET['section_id']); // sanitize
    $to_query = "WHERE section_id = $section_id";
} 
// Query to get all sections parin sa dropdown
$query_sections_to_dropdown = "
                        SELECT DISTINCT s.section_id, sec.section_name
                        FROM students AS s
                        JOIN sections AS sec ON s.section_id = sec.id
";
$sections_dropdown_stmt = secure_query_no_params($pdo, $query_sections_to_dropdown);
$sections_dropdown = $sections_dropdown_stmt ? $sections_dropdown_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
// 1. Requirements
$query_requirements = "SELECT * FROM requirements WHERE is_active = 1";
$requirements_stmt = secure_query_no_params($pdo, $query_requirements);
$requirements = $requirements_stmt ? $requirements_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// 2. Students
$query_students = "SELECT * FROM students WHERE id = $_SESSION[user_id]";
$students_stmt = secure_query_no_params($pdo, $query_students);
$students = $students_stmt ? $students_stmt->fetchAll(PDO::FETCH_ASSOC) : [];


// 3. Statuses
$query_statuses = "SELECT * FROM requirements_status";
$statuses_stmt = secure_query_no_params($pdo, $query_statuses);
$statuses = $statuses_stmt ? $statuses_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// 4. Lookup
$status_map = [];
foreach ($statuses as $s) {
    $status_map[$s['student_id']][$s['req_id']] = (int)$s['is_checked'];
}

$current_section = $_GET['section_id'] ?? 'all';
$button_label = 'Filter Section';
if ($current_section !== 'all') {
    $section_info = get_section_name($pdo, intval($current_section));
    $button_label = 'Section: ' . htmlspecialchars($section_info['section_name']);
}

?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-table me-1"></i>
            All Requirement Status
        </div>
        <!-- Section Filter Dropdown -->
        
    </div>

    <div class="card-body">
        <?php if (!empty($students)): ?>
            <?php $student = $students[0]; // Since only 1 student ?>
            <?php $section_teacher_holder = get_section_name($pdo, $student['section_id']); ?>
            
            <!-- Student Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Full Name:</span>
                            <span><?= htmlspecialchars($student['lastname'] . ', ' . $student['firstname']) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Section:</span>
                            <span><?= htmlspecialchars($section_teacher_holder['section_name']) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Email:</span>
                            <span><?= htmlspecialchars($student['email']) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Teacher Assigned:</span>
                            <span>
                                <?php if ($section_teacher_holder['teacher_id'] == 0): ?>
                                    No Assigned Teacher.
                                <?php else: ?>
                                    <?= htmlspecialchars(get_teacher_name($pdo, $section_teacher_holder['teacher_id'])) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Requirements Card -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Student Requirements</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($requirements as $req): ?>
                            <?php $is_checked = $status_map[$student['id']][$req['id']] ?? 0; ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><?= htmlspecialchars($req['req_name']) ?>:</span>
                                <span>
                                    <?php if ($is_checked): ?>
                                        <span class="badge bg-success">✓</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">○ Pending</span>
                                    <?php endif; ?>
                                    <input class="statusCheck form-check-input ms-2"
                                        type="checkbox"
                                        data-student="<?= $student['id'] ?>"
                                        data-id="<?= $req['id'] ?>"
                                        <?= $is_checked ? 'checked' : '' ?> 
                                        disabled
                                        style="opacity: 0.8;">
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No student found.</div>
        <?php endif; ?>
    </div>
</div>

<script>






</script>
