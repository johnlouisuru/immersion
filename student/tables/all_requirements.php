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


        <table id="table" class="table table-striped table-hover">
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
                    <tr data-section="<?= isset($fetched['section_id']) ? (string)$fetched['section_id'] : 'none' ?>">

                        <td><?= $loop++ ?></td>
                        <td><?= htmlspecialchars($fetched['lastname']) . ' ' . htmlspecialchars($fetched['firstname'])  ?></td>
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
                                    <?= $is_checked ? 'checked' : '' ?> disabled>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>






</script>
