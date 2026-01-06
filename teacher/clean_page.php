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

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            
            const checkboxes = document.querySelectorAll(".statusCheck");

            checkboxes.forEach(cb => {
                cb.addEventListener("change", async (e) => {
                    //alert('test');
                    const userId = e.target.getAttribute("data-id");
                    const studentId = e.target.getAttribute("data-student");
                    const isChecked = e.target.checked ? 1 : 0;
                    console.log('data-id: '+userId+' / data-student: '+studentId+' / isChecked'+isChecked);
                    try {
                        const response = await fetch("tables/update_requirements.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ id: userId, student_id: studentId, status: isChecked })
                        });
                        // const text = await response.text();
                        // console.log("Raw response:", text);

                        // let results;
                        // try {
                        //     results = JSON.parse(text);
                        // } catch (err) {
                        //     console.error("Invalid JSON returned!", err);
                        //     return;
                        // }
                        const result = await response.json();

                        if (result.success) {
                            console.log(`User ${userId} updated ✅`);
                            alert('Requirement Update Successfully');
                        } else {
                            console.error("Update failed ❌", result.error);
                            alert('Error While Updating Requirements');
                        }
                    } catch (err) {
                        console.error("Error");
                        alert('Error While Updating Requirements');
                    }
                });
            });
        });
        </script>
    </body>
</html>
