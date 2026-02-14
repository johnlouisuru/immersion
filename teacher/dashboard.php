<?php 
    require("db-config/security.php");

    // Redirect if not logged in
// if (!isLoggedIn()) {
//     header('Location: index');
//     exit;
// }

// if(empty($_SESSION['lastname']) || empty($_SESSION['firstname'])){
//     header('Location: complete-profile');
//     exit;
// // }
// if (!isLoggedIn() && !isProfileComplete()) {
//     header('Location: index');
//     exit;
// }

if (!isGoogleAuthenticated() || !isLoggedIn()) {
    // header('Location: complete-profile');
    // exit;
    die('Unauthorized!');
}


// Get student information
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    //session_destroy();
    //header('Location: index');
    //exit;
    echo "<h1>Not Student</h1>";
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
    <?php
    
        require __DIR__ . '/tables/requirement_percentage.php'; //Table with Search
    ?> 
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

    
    </body>
</html>
