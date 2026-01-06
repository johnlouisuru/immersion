<?php 
    require("db-config/security.php");
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
        require __DIR__ . '/bars/sidebar.php'; //Topbar yung kasama Profile Icon
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
    <?php
        require __DIR__ . '/tables/cards.php'; //Topbar yung kasama Profile Icon
    ?> 
                        </div>
                        <div class="row">
    <?php
        require __DIR__ . '/charts/area.php'; //Topbar yung kasama Profile Icon
        require __DIR__ . '/charts/bar.php'; //Topbar yung kasama Profile Icon
    ?>  
                            
                        </div>
                        <!-- Table Template -->
    <?php
        require __DIR__ . '/tables/table-template.php'; //Topbar yung kasama Profile Icon
    ?> 
                        <!-- Table Template -->
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
     <?php
        require __DIR__ . '/footer/footer.php'; //Topbar yung kasama Profile Icon
    ?>
                </footer>
            </div>
        </div>
        <!-- Footer SCripts -->
    <?php
    require __DIR__ . '/footer/footer-scripts.php'; //Topbar yung kasama Profile Icon
    ?>
    </body>
</html>
