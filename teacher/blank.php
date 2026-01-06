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
                                
                            
                            <button id="getLocationBtn">Get My Location</button>
                                <div id="output"></div>
                                <div id="map"></div>

                                <form id="uploadForm" enctype="multipart/form-data">
                                <input type="hidden" name="latitude" id="latitude">
                                <input type="hidden" name="longitude" id="longitude">
                                
                                <!-- Camera input -->
                                <input type="file" name="photo" accept="image/*" capture="environment">
                                
                                <button type="submit">Save Location + Photo</button>
                                </form>

                                <!-- Leaflet -->
                                <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
                                <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
                                <script>
                                let lat, lon;

                                document.getElementById("getLocationBtn").addEventListener("click", () => {
                                    if (navigator.geolocation) {
                                        navigator.geolocation.getCurrentPosition((position) => {
                                            lat = position.coords.latitude;
                                            lon = position.coords.longitude;

                                            document.getElementById("latitude").value = lat;
                                            document.getElementById("longitude").value = lon;
                                            document.getElementById("output").innerText = `Lat: ${lat}, Lon: ${lon}`;

                                            const map = L.map('map').setView([lat, lon], 15);
                                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                                attribution: '© OpenStreetMap contributors'
                                            }).addTo(map);
                                            L.marker([lat, lon]).addTo(map).bindPopup("📍 You are here").openPopup();
                                        });
                                    }
                                });

                                // Handle form submit
                                document.getElementById("uploadForm").addEventListener("submit", async (e) => {
                                    e.preventDefault();
                                    const formData = new FormData(e.target);

                                    const response = await fetch("save_location.php", {
                                        method: "POST",
                                        body: formData
                                    });

                                    // const text = await response.text();
                                    // console.log("Raw response:", text);

                                    // let results;
                                    // try {
                                    //     results = JSON.parse(text);
                                    //     console.log("Parsed JSON:", results);
                                    // } catch (err) {
                                    //     console.error("Invalid JSON:", err);
                                    // }

                                    const result = await response.json();
                                    console.log(result);
                                    alert(result.message || "Saved!");
                                });
                                </script>


                            </div>
                        <div class="row"> 
    <?php
        //require __DIR__ . '/tables/cards.php'; //4 Cards with different colors
    ?> 
                        </div>
                        <div class="row">
    <?php
        // require __DIR__ . '/charts/area.php'; //Line Charts
        // require __DIR__ . '/charts/bar.php'; //Bar Charts
    ?>  
                            
                        </div>
                        <!-- Table Template -->
    <?php
        // require __DIR__ . '/tables/table-template.php'; //Table with Search
    ?> 
                        <!-- Table Template -->
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
