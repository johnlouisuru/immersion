<?php 
    require("db-config/security.php");

    // Redirect if not logged in
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
                    <div class="container-fluid px-4">
                        <h1 class="mt-4"><?=$_ENV['PAGE_SIDEBAR']?></h1>
                            <ol class="breadcrumb mb-4">
                                <li class="breadcrumb-item active"><?=ucwords($filename)?></li>
                            </ol>
                        <div class="row">
                                
                            
                        
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
