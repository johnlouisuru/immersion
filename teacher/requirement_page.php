<?php 
$to_query = '';
    require("db-config/security.php");
    if (!isLoggedIn()) {
    header('Location: index');
    exit;
}
    if(isset($_GET['section_id'])){
        $sectionId = $_GET['section_id'];
        $to_query = "WHERE section_id=".$sectionId;
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
        require __DIR__ . '/tables/all_requirements.php'; //Table with Search
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
    </body>
</html>
