<?php 
    require("db-config/security.php");
    $time_status = '';
    $total = 0;
    if(isset($_GET['section_id']) && isset($_GET['time_in']) && isset($_GET['total'])){
        $sectionId = $_GET['section_id'];
        $isLogin = $_GET['time_in'];
        $total = $_GET['total'];
        if($isLogin == 1){
            $time_status = "TIME IN";
        } else {
            $time_status = "TIME OUT";
        }
    }

        // Redirect if not logged in
        if (!isLoggedIn()) {
            header('Location: index');
            exit;
        }

        // Get student information
        $conn = $pdo;
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $student = $stmt->fetch();

        if (!$student) {
            session_destroy();
            header('Location: index');
            exit;
        }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php
    require __DIR__ . '/headers/head.php'; //Included dito outside links and local styles
    ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@panzoom/panzoom/dist/panzoom.min.js"></script>

<style>
  body {
    background: #f8f9fa;
    font-family: 'Segoe UI', Roboto, sans-serif;
  }

  header {
    background: linear-gradient(135deg, #0d6efd, #6610f2);
    color: white;
    padding: 1rem;
    text-align: center;
    border-radius: 0 0 1rem 1rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  }

  #map {
    height: 60vh;
    width: 100%;
    margin-top: 1rem;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }

  .card-footer {
    background: transparent;
    margin-top: 1rem;
  }

  .card-footer a {
    text-decoration: none;
    font-weight: 500;
    color: #0d6efd;
  }

  /* Modal styling */
  .modal-content {
    background: #212529;
    border-radius: 0.75rem;
    overflow: hidden;
    animation: fadeIn 0.3s ease-in-out;
  }

  .modal-body {
    padding: 0;
    background: #000;
  }

  #modalImage {
    max-height: 80vh;
    object-fit: contain;
    transition: transform 0.3s ease;
  }

  @keyframes fadeIn {
    from {opacity: 0; transform: scale(0.95);}
    to {opacity: 1; transform: scale(1);}
  }

  /* Responsive tweaks */
  @media (max-width: 768px) {
    header h2 {
      font-size: 1.25rem;
    }
    #map {
      height: 50vh;
    }
  }

  /* Floating Action Button */
.fab {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0d6efd, #6610f2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  z-index: 9999;
}

.fab:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0,0,0,0.35);
}
/* Fixed bottom center button */
.home-btn {
  position: fixed;
  bottom: 1rem;
  left: 50%;
  transform: translateX(-50%);
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0d6efd, #6610f2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  z-index: 10000;
}

.home-btn:hover {
  transform: translateX(-50%) scale(1.1);
  box-shadow: 0 6px 16px rgba(0,0,0,0.35);
}

.home-btn i {
  font-size: 1.75rem;
}

</style>
<script src="js/time-format.js"></script>
</head>
<body>

<header>
  <h5>
    All Location of Section 
    <u><?=@$_GET['section_name']?></u> 
    [<?= $time_status ?>] (<?= $total ?>) Today.
  </h5>
</header>

<div class="container my-3">
  <div id="map"></div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([13.41, 122.56], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

async function loadLocations() {
  const sectionId = <?= json_encode($sectionId) ?>;
  const isLogin = <?= json_encode($isLogin) ?>;

  try {
    const res = await fetch(`get_by_section?section_id=${sectionId}&isLogin=${isLogin}`);
    const data = await res.json();
    if (data.success && data.data.length) {
      map.eachLayer(layer => {
        if (layer instanceof L.Marker) map.removeLayer(layer);
      });

      const markers = [];
      data.data.forEach(loc => {
        const marker = L.marker([loc.latitude, loc.longitude])
          .addTo(map)
          .bindPopup(`
            <div class="p-1">
              <strong>${loc.lastname}, ${loc.firstname}</strong><br>
              ${formatDateTime(loc.date_created)}<br>
              ${loc.video_path ? `<img src="${loc.video_path}" class="img-thumbnail mt-2" style="cursor:pointer;max-width:120px;" onclick="showImageModal('${loc.video_path}')">` : ""}
            </div>
          `);
        markers.push(marker);
      });

      const group = new L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.5));
    }
  } catch (err) {
    console.error(err);
  }
}

function showImageModal(src) {
  const modalImage = document.getElementById('modalImage');
  modalImage.src = src;
  const modal = new bootstrap.Modal(document.getElementById('imageModal'));
  modal.show();
  Panzoom(modalImage, { maxScale: 5, contain: 'outside' });
}

function recenterMap() {
  if (map && map._layers) {
    const markers = [];
    map.eachLayer(layer => {
      if (layer instanceof L.Marker) markers.push(layer);
    });
    if (markers.length) {
      const group = new L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.5));
    }
  }
}


loadLocations();
</script>
<!-- 
<div class="card-footer text-center py-3">
  <div class="small">
    <a href="dashboard">🏠︎ Go back to Homepage</a>
  </div>
</div> -->

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body text-center">
        <img id="modalImage" src="" class="img-fluid" alt="Zoomed Image">
      </div>
    </div>
  </div>
</div>


<!-- Floating Action Button -->
<div class="fab" onclick="recenterMap()" title="Recenter Map">
  <i class="bi bi-crosshair"></i>
</div>

<!-- Fixed Go Home Button -->
<a href="dashboard" class="home-btn" title="Go Home">
  <i class="bi bi-house-door-fill"></i>
</a>


</body>
</html>

