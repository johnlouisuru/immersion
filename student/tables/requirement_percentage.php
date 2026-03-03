<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table me-1"></i>
        All Requirement Status
    </div>
    <div class="card-body">
        <?php 
        // Ensure $to_query is always set
        if (empty($to_query)) {
            $to_query = '';
        }

        // 1. Get all requirements once
        $query_requirements = "SELECT * FROM requirements WHERE is_active = 1";
        $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
        $requirements = $requirements_stmt ? $requirements_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 2. Get all students (updated to include company)
        $query_students = "SELECT * FROM students WHERE id = $_SESSION[user_id]";
        $students_stmt = secure_query_no_params($pdo, $query_students);
        $students = $students_stmt ? $students_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 3. Get all statuses in one query
        $query_statuses = "
            SELECT 
                rs.*,
                r.req_name
            FROM requirements_status rs
            INNER JOIN requirements r 
                ON rs.req_id = r.id
            WHERE r.is_active = 1
        ";

        $statuses_stmt = secure_query_no_params($pdo, $query_statuses);
        $statuses = $statuses_stmt ? $statuses_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 4. Build a quick lookup: [student_id][req_id] => is_checked
        $status_map = [];
        foreach ($statuses as $s) {
            $status_map[$s['student_id']][$s['req_id']] = (int)$s['is_checked'];
        }
        ?>

        <!-- Add responsive wrapper -->
        <div class="table-responsive">
            <table id="datatablesSimple" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Company</th>
                        <th>Section</th>
                        <th>Email</th>
                        <th>Teacher</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $loop = 1; ?>
                    <?php foreach ($students as $fetched): ?>
                        <?php $section_teacher_holder = get_section_name($pdo, $fetched['section_id']); ?>
                        <tr>
                            <td><?= $loop++ ?></td>
                            <td><?= htmlspecialchars($fetched['lastname']). ', '.htmlspecialchars($fetched['firstname'])  ?></td>
                            <td>
                                <?php 
                                // Display company with a default value if empty
                                $company = !empty($fetched['company']) ? htmlspecialchars($fetched['company']) : '<span class="text-muted fst-italic">Not set</span>';
                                echo $company;
                                ?>
                            </td>
                            <td><?= htmlspecialchars($section_teacher_holder['section_name'] ?? 'No Section') ?></td>
                            <td class="text-truncate" style="max-width: 200px;">
                                <span title="<?= htmlspecialchars($fetched['email']) ?>">
                                    <?= htmlspecialchars($fetched['email']) ?>
                                </span>
                            </td>
                            <?php 
                                if(empty($section_teacher_holder['teacher_id'])){
                                        echo "<td><span class='badge bg-warning text-dark'>No Teacher</span></td>";
                                }else {
                                    ?>
                                    <td><?= htmlspecialchars(get_teacher_name($pdo, $section_teacher_holder['teacher_id'])) ?></td>
                                <?php 
                            }
                            ?>
                            <td style="min-width: 150px;">
                                <?php
                                $percentage = get_requirement_percentage($pdo, $fetched['id']);
                                $barClass = ($percentage == 100) ? 'bg-success' : 'bg-danger';
                                
                                // Determine text color based on background
                                $textClass = ($percentage > 50) ? 'text-white' : 'text-dark';
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar <?= $barClass ?> <?= $textClass ?>"
                                        role="progressbar"
                                        style="width: <?= $percentage ?>%; font-size: 12px; line-height: 25px;"
                                        aria-valuenow="<?= $percentage ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?php if($percentage > 0): ?>
                                            <?= round($percentage, 1) ?>%
                                        <?php endif; ?>
                                    </div>
                                    <?php if($percentage == 0): ?>
                                        <span class="position-absolute w-100 text-center" style="font-size: 12px; line-height: 25px;">0%</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add custom CSS for better mobile responsiveness -->
<style>
@media screen and (max-width: 768px) {
    .table-responsive {
        border: 0;
        margin-bottom: 1rem;
    }
    
    .table-responsive table {
        font-size: 14px;
    }
    
    .table-responsive td,
    .table-responsive th {
        white-space: normal;
        word-wrap: break-word;
    }
    
    /* Adjust progress bar for mobile */
    .progress {
        min-width: 100px;
    }
    
    /* Make email addresses wrap properly */
    td .text-truncate {
        white-space: normal !important;
        overflow: visible !important;
    }
}

/* Improve table readability */
.table-striped tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Style for company column */
td:nth-child(3) {
    font-weight: 500;
}

.text-muted.fst-italic {
    color: #6c757d !important;
    font-style: italic;
}

/* Progress bar enhancements */
.progress {
    background-color: #e9ecef;
    border-radius: 0.5rem;
    position: relative;
}

.progress-bar {
    border-radius: 0.5rem;
    transition: width 0.6s ease;
    font-weight: 500;
}

/* Add tooltip for email on hover */
td[title] {
    cursor: help;
    border-bottom: 1px dotted #999;
}

/* Badge styling */
.badge {
    padding: 0.5em 0.8em;
    font-weight: 500;
}

/* Ensure table doesn't overflow */
.table-bordered {
    border: 1px solid #dee2e6;
}

.table-bordered th,
.table-bordered td {
    border: 1px solid #dee2e6;
    vertical-align: middle;
}

/* Responsive font sizes */
@media screen and (max-width: 576px) {
    .table-responsive table {
        font-size: 13px;
    }
    
    .progress {
        height: 20px !important;
    }
    
    .progress-bar {
        font-size: 11px !important;
        line-height: 20px !important;
    }
    
    td:nth-child(3) {
        max-width: 120px;
        word-break: break-word;
    }
}

/* Improve DataTables responsiveness */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    margin-bottom: 1rem;
}

@media screen and (max-width: 767px) {
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        text-align: left;
        margin-top: 0.5rem;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        width: 100% !important;
        margin-left: 0 !important;
    }
}
</style>

<!-- Optional: Add initialization for DataTables with responsive options -->
<script>
$(document).ready(function() {
    // If using DataTables, initialize with responsive options
    if ($.fn.DataTable) {
        $('#datatablesSimple').DataTable({
            responsive: true,
            scrollX: false,
            columnDefs: [
                { responsivePriority: 1, targets: 1 }, // Full name - highest priority
                { responsivePriority: 2, targets: 6 }, // Progress - second highest
                { responsivePriority: 3, targets: 2 }, // Company - third highest
                { responsivePriority: 10000, targets: [0,3,4,5] } // Others - lower priority
            ],
            language: {
                lengthMenu: "Show _MENU_ entries per page",
                zeroRecords: "No records found",
                info: "Showing page _PAGE_ of _PAGES_",
                infoEmpty: "No records available",
                infoFiltered: "(filtered from _MAX_ total records)"
            }
        });
    }
});
</script>