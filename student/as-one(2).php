<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Location + Photo Map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
  #map { height: 500px; width: 100%; margin-top: 10px; }
</style>
</head>
<body>

<h2>Capture Location + Photo</h2>
<button id="getLocationBtn">Get My Location</button>
<div id="output"></div>

<form id="uploadForm" enctype="multipart/form-data">
  <input type="hidden" name="latitude" id="latitude">
  <input type="hidden" name="longitude" id="longitude">
  <input type="file" name="photo" accept="image/*" capture="environment">
  <select id="is_login" name="is_login">
    <option value="1">Time In</option>
    <option value="2">Time Out</option>
  </select>
  <button type="submit">Save Location + Photo</button>
</form>

<h2>All Saved Locations</h2>
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let lat, lon;

const map = L.map('map').setView([13.41, 122.56], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Get current location
document.getElementById("getLocationBtn").addEventListener("click", () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            lat = position.coords.latitude;
            lon = position.coords.longitude;

            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lon;
            document.getElementById("output").innerText = `Lat: ${lat}, Lon: ${lon}`;
        }, (err) => {
            alert("Error getting location: " + err.message);
        });
    } else {
        alert("Geolocation not supported");
    }
});

// Upload form
document.getElementById("uploadForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const response = await fetch("save_location.php", { method: "POST", body: formData });
        const result = await response.json();
        alert(result.message || "Saved!");
        loadLocations(); // Refresh map after saving
    } catch (err) {
        console.error(err);
        alert("Error saving location");
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
