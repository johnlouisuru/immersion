<nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="dashboard">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>
                            <div class="sb-sidenav-menu-heading">Interface</div>
                            <a class="nav-link" href="my-time-in">
                                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                My Time In
                            </a>
                            <a class="nav-link" href="my-time-out">
                                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                My Time Out
                            </a>

                            <div class="sb-sidenav-menu-heading">Attendance Today</div>
                            <a class="nav-link" href="send-location">
                                <div class="sb-nav-link-icon"><i class="fas fa-map-marker-alt"></i></div>
                                Take Attendance
                            </a>
                            <div class="sb-sidenav-menu-heading">Requirements Status</div>
                            <a class="nav-link" href="requirement_page">
                                        <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                                     My Requirements
                            </a>
                            <a class="nav-link" href="upload_requirement">
                                        <div class="sb-nav-link-icon"><i class="fas fa-upload"></i></div>
                                     Upload Requirement
                            </a>
                            <div class="sb-sidenav-menu-heading">LEARNING MATERIALS</div>
                            <a class="nav-link" href="learning_materials_view">
                                        <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
                                     Browse
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