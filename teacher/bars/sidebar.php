<nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="dashboard">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>
                            <div class="sb-sidenav-menu-heading">Interface</div>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                Monitor Attendance (Time In)
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <?php
                                                        $query_requirements = "
                                                            SELECT 
                                                                s.*,
                                                                COUNT(a.id) AS total_attendance
                                                            FROM sections s
                                                            LEFT JOIN attendance_logs a 
                                                                ON s.id = a.section_id
                                                                AND a.is_login = 1
                                                            WHERE s.is_active = 1
                                                            GROUP BY s.id
                                                        ";

                                                        $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
                                                        $loop = 1;
                                                        
                                                    ?>
                                                    <?php if ($requirements_stmt && $requirements_stmt->rowCount() > 0): ?>
                                                        <?php foreach ($requirements_stmt as $fetched): 
                                                                $total_attendance = 0;
                                                                if(isset($fetched['total_attendance'])){
                                                                    $total_attendance = $fetched['total_attendance'];
                                                                }
                                                            ?>
                                                           <a class="nav-link" href="attendance?section_name=<?= htmlspecialchars($fetched['section_name']) ?>&section_id=<?= htmlspecialchars($fetched['id']) ?>&time_in=1&total=<?= $total_attendance ?>"><?= htmlspecialchars($fetched['section_name']) ?>[<?= $total_attendance ?>]</a>
                                                        <?php $loop++;
                                                              endforeach; ?>
                                                    <?php else: ?>
                                                       
                                                    <?php endif; ?>
                                    
                                </nav>
                            </div>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLayouts2" aria-expanded="false" aria-controls="collapseLayouts2">
                                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                Monitor Attendance (Time Out)
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseLayouts2" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <?php
                                                        $query_requirements = "
                                                            SELECT 
                                                                s.*,
                                                                COUNT(a.id) AS total_attendance
                                                            FROM sections s
                                                            LEFT JOIN attendance_logs a 
                                                                ON s.id = a.section_id
                                                                AND a.is_login = 2
                                                            WHERE s.is_active = 1
                                                            GROUP BY s.id
                                                        ";

                                                        $requirements_stmt = secure_query_no_params($pdo, $query_requirements);
                                                        $loop = 1;
                                                        
                                                    ?>
                                                    <?php if ($requirements_stmt && $requirements_stmt->rowCount() > 0): ?>
                                                        <?php foreach ($requirements_stmt as $fetched): 
                                                            $total_attendance = 0;
                                                            if(isset($fetched['total_attendance'])){
                                                                $total_attendance = $fetched['total_attendance'];
                                                            }
                                                            ?>
                                                           <a class="nav-link" href="attendance?section_name=<?= htmlspecialchars($fetched['section_name']) ?>&section_id=<?= htmlspecialchars($fetched['id']) ?>&time_in=2&total=<?= $total_attendance ?>"><?= htmlspecialchars($fetched['section_name']) ?>[<?= $total_attendance ?>]</a>
                                                        <?php $loop++;
                                                              endforeach; ?>
                                                    <?php else: ?>
                                                       
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
                                            <a class="nav-link" href="register_section">New Section</a>
                                            <a class="nav-link" href="register_teacher">Assign Teacher</a>
                                        </nav>
                                </nav>
                            </div>
                            
                            <div class="sb-sidenav-menu-heading">Management</div>
                            <a class="nav-link" href="manage_attendance">
                                <div class="sb-nav-link-icon"><i class="fas fa-chart-area"></i></div>
                                Manage Attendance
                            </a>
                            <!-- <a class="nav-link" href="#">
                                <div class="sb-nav-link-icon"><i class="fas fa-table"></i></div>
                                Tables
                            </a> -->
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Logged in as:</div>
                        <?= isset($_SESSION['email']) ? $_SESSION['email'] : '-' ?>
                    </div>
                </nav>