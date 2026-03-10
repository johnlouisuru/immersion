<nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
    <div class="sb-sidenav-menu">
        <div class="nav">
            <div class="sb-sidenav-menu-heading">Core</div>
            <a class="nav-link" href="dashboard">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Dashboard
            </a>
            
            <?php 
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
            ?>
            
            <div class="sb-sidenav-menu-heading">Interface</div>
            
            <!-- Monitor Attendance (Time In) -->
            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                Monitor Attendance (Time In)
                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
            </a>
            <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                <nav class="sb-sidenav-menu-nested nav">
                    <?php
                    if ($teacher_section_id) {
                        // Query only the teacher's assigned section for Time In
                        $query_requirements = "
                            SELECT 
                                s.*,
                                COUNT(a.id) AS total_attendance
                            FROM sections s
                            LEFT JOIN attendance_logs a 
                                ON s.id = a.section_id
                                AND a.is_login = 1
                            WHERE s.is_active = 1 
                            AND s.id = :section_id
                            GROUP BY s.id
                        ";
                        
                        $requirements_stmt = $pdo->prepare($query_requirements);
                        $requirements_stmt->execute(['section_id' => $teacher_section_id]);
                    } else {
                        $requirements_stmt = null;
                    }
                    ?>
                    
                    <?php if ($requirements_stmt && $requirements_stmt->rowCount() > 0): ?>
                        <?php foreach ($requirements_stmt as $fetched): 
                            $total_attendance = $fetched['total_attendance'] ?? 0;
                        ?>
                            <a class="nav-link" href="attendance?section_name=<?= htmlspecialchars($fetched['section_name']) ?>&section_id=<?= htmlspecialchars($fetched['id']) ?>&time_in=1&total=<?= $total_attendance ?>">
                                <?= htmlspecialchars($fetched['section_name']) ?> [<?= $total_attendance ?>]
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a class="nav-link disabled" href="#">
                            <?= $teacher_section_id ? 'No attendance records' : 'No section assigned' ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Monitor Attendance (Time Out) -->
            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts2" aria-expanded="false" aria-controls="collapseLayouts2">
                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                Monitor Attendance (Time Out)
                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
            </a>
            <div class="collapse" id="collapseLayouts2" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                <nav class="sb-sidenav-menu-nested nav">
                    <?php
                    if ($teacher_section_id) {
                        // Query only the teacher's assigned section for Time Out
                        $query_requirements = "
                            SELECT 
                                s.*,
                                COUNT(a.id) AS total_attendance
                            FROM sections s
                            LEFT JOIN attendance_logs a 
                                ON s.id = a.section_id
                                AND a.is_login = 2
                            WHERE s.is_active = 1 
                            AND s.id = :section_id
                            GROUP BY s.id
                        ";
                        
                        $requirements_stmt = $pdo->prepare($query_requirements);
                        $requirements_stmt->execute(['section_id' => $teacher_section_id]);
                    } else {
                        $requirements_stmt = null;
                    }
                    ?>
                    
                    <?php if ($requirements_stmt && $requirements_stmt->rowCount() > 0): ?>
                        <?php foreach ($requirements_stmt as $fetched): 
                            $total_attendance = $fetched['total_attendance'] ?? 0;
                        ?>
                            <a class="nav-link" href="attendance?section_name=<?= htmlspecialchars($fetched['section_name']) ?>&section_id=<?= htmlspecialchars($fetched['id']) ?>&time_in=2&total=<?= $total_attendance ?>">
                                <?= htmlspecialchars($fetched['section_name']) ?> [<?= $total_attendance ?>]
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a class="nav-link disabled" href="#">
                            <?= $teacher_section_id ? 'No attendance records' : 'No section assigned' ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <a class="nav-link" href="requirement_page">
                <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                Update Requirements
            </a>
            
            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapsePages" aria-expanded="false" aria-controls="collapsePages">
                <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                Resources
                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
            </a>
            <div class="collapse" id="collapsePages" aria-labelledby="headingTwo" data-bs-parent="#sidenavAccordion">
                <nav class="sb-sidenav-menu-nested nav accordion" id="sidenavAccordionPages">
                    <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#pagesCollapseAuth" aria-expanded="false" aria-controls="pagesCollapseAuth">
                        Navigate
                        <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                    </a>
                    <div class="collapse" id="pagesCollapseAuth" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordionPages">
                        <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link" href="register_requirement">Add Requirement</a>
                            <a class="nav-link" href="register">Assign Student</a>
                            <a class="nav-link" href="assign_company">Assign company</a>
                            <a class="nav-link" href="register_section">New Section</a>
                            <a class="nav-link" href="register_teacher">Assign Teacher</a>
                        </nav>
                    </div>
                </nav>
            </div>
            
            <div class="sb-sidenav-menu-heading">Management</div>
            <a class="nav-link" href="manage_attendance">
                <div class="sb-nav-link-icon"><i class="fas fa-chart-area"></i></div>
                Manage Attendance
            </a>
            <a class="nav-link" href="manage_files">
                <div class="sb-nav-link-icon"><i class="fas fa-folder"></i></div>
                Manage Files
            </a>
            <a class="nav-link" href="learning_materials">
                <div class="sb-nav-link-icon"><i class="fas fa-folder"></i></div>
                Learning Materials
            </a>
        </div>
    </div>
    <div class="sb-sidenav-footer">
        <div class="small">Logged in as:</div>
        <?= isset($_SESSION['email']) ? $_SESSION['email'] : '-' ?>
        <?php if ($teacher_section_name): ?>
            <br><small>Section: <?= htmlspecialchars($teacher_section_name) ?></small>
        <?php endif; ?>
    </div>
</nav>