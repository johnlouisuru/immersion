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
<meta charset="UTF-8">
<title>Location + Photo Map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>

body {
  font-family: system-ui, Arial, sans-serif;
  margin: 10px;
  background: #f7f7f7;
}

h2 {
  font-size: 1.3rem;
  margin-bottom: 10px;
}

/* Form container */
.form-card {
  background: #fff;
  padding: 15px;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  max-width: 100%;
  margin-top: 15px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Labels */
.form-label {
  font-weight: 600;
  margin-bottom: 4px;
  display: block;
  color: #333;
  font-size: 0.9rem;
}

/* Inputs & select */
.form-input {
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 1rem;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #007bff;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0,123,255,0.2);
}

/* Buttons */
.btn {
  background: #007bff;
  color: white;
  border: none;
  padding: 12px 16px;
  font-size: 1rem;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
  width: 100%; /* full width on mobile */
}

.btn:hover {
  background: #0056b3;
}

.btn-primary {
  background: #28a745;
}

.btn-primary:hover {
  background: #218838;
}

/* Output message */
#output {
  margin: 8px 0;
  font-size: 0.95rem;
  color: #444;
}

/* Preview image responsive */
#preview img {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin-top: 5px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Responsive tweaks */
@media (min-width: 600px) {
  .form-card {
    max-width: 400px;
  }
  .btn {
    width: auto; /* buttons shrink on larger screens */
  }
}

  #map { height: 500px; width: 100%; margin-top: 10px; }

</style>
</head>
<body>

<div id="loadingOverlay" style="
  position: fixed;
  top:0; left:0; width:100%; height:100%;
  background: rgba(0,0,0,0.6);
  color:#fff;
  font-size: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  display:none;
">
  ⏳ Uploading your Immersion Attendance... Please wait
</div>

<center>
<h2>Capture Location + Photo</h2>
<button id="getLocationBtn" class="btn">📍 Get My Location</button>
<div id="output"></div>

<form id="uploadForm" enctype="multipart/form-data" class="form-card">
  <input type="hidden" name="latitude" id="latitude">
  <input type="hidden" name="longitude" id="longitude">

  <!-- File input -->
  <label class="form-label">📷 Upload Photo</label>
  <input type="file" name="photo" accept="image/jpeg,image/png" capture="environment" class="form-input">

  <!-- Dropdown -->
  <label class="form-label">⏰ Log Type</label>
  <select id="is_login" name="is_login" class="form-input">
    <option value="1">Time In</option>
    <option value="2">Time Out</option>
  </select>

  <!-- Submit -->
  <button type="submit" class="btn btn-primary">💾 Save Location + Photo</button>
</form>

<div id="preview" style="margin-top:10px;"></div>
</center>

<h2>My Current Location</h2>
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let lat, lon;

// Initialize map
const map = L.map('map').setView([13.41, 122.56], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Auto-get user location on page load
window.addEventListener("load", () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            lat = position.coords.latitude;
            lon = position.coords.longitude;

            // Fill hidden fields
            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lon;
            document.getElementById("output").innerText = `📍 Auto-detected location: Lat ${lat}, Lon ${lon}`;

            // Center map on user location
            map.setView([lat, lon], 16);
            // Define a red icon
            const redIcon = new L.Icon({
                iconUrl: 'https://png.pngtree.com/png-vector/20230320/ourmid/pngtree-you-are-here-location-pointer-vector-png-image_6656543.png',
                shadowUrl: 'https://unpkg.com/leaflet/dist/images/marker-shadow.png',
                iconSize: [40, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            // Add marker for user
            L.marker([lat, lon], { icon: redIcon }).addTo(map)
                .bindPopup("You are here ✅")
                .openPopup();
        }, (err) => {
            document.getElementById("output").innerText = "⚠️ Location access denied or unavailable.";
        });
    } else {
        document.getElementById("output").innerText = "⚠️ Geolocation not supported.";
    }
});



// Upload form with loading overlay
const overlay = document.getElementById("loadingOverlay");

document.getElementById("uploadForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const fileInput = document.querySelector('input[name="photo"]');
    if (!fileInput.files.length) {
        alert("⚠️ Please upload a photo before submitting.");
        return;
    }

    // 🔹 Ask for confirmation
    const confirmSubmit = confirm("Are you sure you want to save your location and photo?");
    if (!confirmSubmit) {
        return; // stop if cancelled
    }

    const formData = new FormData(e.target);

    // Show loading
    overlay.style.display = "flex";

    try {
        const response = await fetch("save_location.php", { method: "POST", body: formData });
        const text = await response.text();

        let result;
        try {
            result = JSON.parse(text);
        } catch (err) {
            console.error("Server returned non-JSON:", text);
            alert("Server error (not JSON). See console for details.");
            return;
        }
        alert(result.message || "Saved!");
        loadLocations();
    } catch (err) {
        console.error(err);
        alert("Error saving location");
    } finally {
        // Hide loading
        overlay.style.display = "none";
    }
});




// Show file size + preview before upload
const fileInput = document.querySelector('input[name="photo"]');
const previewDiv = document.getElementById("preview");

fileInput.addEventListener("change", () => {
    previewDiv.innerHTML = ""; // clear old preview
    const file = fileInput.files[0];
    if (file) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const maxMB = 10; // must match your php.ini limit
        let msg = `Selected file size: ${sizeMB} MB`;

        if (sizeMB > maxMB) {
            msg += ` ❌ (Too large! Limit is ${maxMB} MB)`;
            alert(msg);
            fileInput.value = ""; // reset file
            previewDiv.innerHTML = `<p style="color:red;">${msg} </p>`;
            return;
        } else {
            msg += " ✅ Image Preview: ";
        }

        // Display message
        const info = document.createElement("p");
        info.textContent = msg;
        previewDiv.appendChild(info);

        // Display thumbnail preview
        const img = document.createElement("img");
        img.src = URL.createObjectURL(file);
        img.style.maxWidth = "200px";
        img.style.marginTop = "5px";
        img.onload = () => URL.revokeObjectURL(img.src); // free memory
        previewDiv.appendChild(img);
    }
});

// Load all locations and display markers
async function loadLocations() {
    try {
        const res = await fetch("get_locations.php");
        const data = await res.json();
        if (data.success && data.data.length) {
            // Clear existing markers
            map.eachLayer(layer => {
                if (layer instanceof L.Marker) map.removeLayer(layer);
            });

            const markers = [];
            data.data.forEach(loc => {
                const marker = L.marker([loc.latitude, loc.longitude])
                    .addTo(map)
                    .bindPopup(`Lat: ${loc.latitude}<br>Lon: ${loc.longitude}${loc.image_path ? `<br><img src="${loc.image_path}" width="100">` : ""}`);
                markers.push(marker);
            });

            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.5));
        }
    } catch (err) {
        console.error(err);
    }
}

// Initial load
loadLocations();
</script>

</body>
</html>
