<?php //phpinfo(); 
date_default_timezone_set("Asia/Manila");
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Immersion Attendance</title>
<link rel="icon" type="image/png" href="assets/img/sos.webp">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { font-family: system-ui, Arial, sans-serif; margin:10px; background:#f7f7f7; }
h2 { font-size:1.3rem; margin-bottom:10px; text-align:center; }
.form-card { background:#fff; padding:15px; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1); max-width:100%; margin:15px auto; display:flex; flex-direction:column; gap:12px; }
.form-label { font-weight:600; margin-bottom:4px; display:block; color:#333; font-size:0.9rem; }
.form-input { padding:12px; border:1px solid #ccc; border-radius:8px; font-size:1rem; width:100%; box-sizing:border-box; }
.form-input:focus { border-color:#007bff; outline:none; box-shadow:0 0 0 2px rgba(0,123,255,0.2); }
.btn { background:#007bff; color:white; border:none; padding:12px 16px; font-size:1rem; font-weight:600; border-radius:8px; cursor:pointer; transition:0.2s; width:100%; }
.btn:hover { background:#0056b3; }
.btn-primary { background:#28a745; }
.btn-primary:hover { background:#218838; }
#output { margin:8px 0; font-size:0.95rem; color:#444; }
#preview img { max-width:100%; height:auto; border-radius:8px; margin-top:5px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
#map {
  height: 60vh;
  min-height: 400px;
  width: 100%;
}

#fullscreenOverlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); display:none; align-items:center; justify-content:center; z-index:9999; }
#fullscreenOverlay img, #fullscreenOverlay iframe { max-width:95%; max-height:95%; border-radius:10px; box-shadow:0 0 20px rgba(255,255,255,0.3); }
#loadingOverlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); color:#fff; font-size:24px; display:flex; align-items:center; justify-content:center; z-index:9999; display:none; }
@media (min-width:600px) { .form-card { max-width:400px; } .btn { width:auto; } }

.leaflet-marker-icon.thumb-marker {
  border-radius: 50%;
  border: 2px solid white;
  object-fit: cover;
  box-shadow: 0 0 6px rgba(0,0,0,0.4);
}
</style>
</head>
<body>

<div id="fullscreenOverlay" onclick="this.style.display='none'"></div>
<div id="loadingOverlay">⏳ Uploading your Current Situation... Please wait</div>

<div class="container py-3">

<div class="text-center mb-3">
  <h2 class="fw-bold">Immersion Attendance with Real-Time Selfie</h2>

  <!-- <button id="getLocationBtn"
          onclick="loadLocations()"
          class="btn btn-primary mt-2">
    📍 Get My Location
  </button> -->

  <div id="output" class="mt-3 small"></div>
</div>


<form id="uploadForm" enctype="multipart/form-data"
      class="card shadow-sm p-3 mx-auto"
      style="max-width:420px;">

  <input type="hidden" name="latitude" id="latitude">
  <input type="hidden" name="longitude" id="longitude">

    <label class="form-label">📷 Upload an Actual Photo</label>
    <input type="file" name="media"
        accept="image/jpeg,image/png"
        class="form-control">


  <!-- <label class="form-label">🎥 Or Enter a YouTube Video Link</label>
  <input type="url" name="youtube_link" id="youtube_link" placeholder="https://www.youtube.com/watch?v=..." class="form-input"> -->

  <label class="form-label">
    🏠 Additional Address Info
    <span class="text-warning small d-block">
        NOTE: Even if altered, exact coordinates are saved.
    </span>
  </label>
    <input type="text"
        class="form-control"
        id="addPreview"
        placeholder="Barangay / Municipality / Province" disabled>
    <hr/>
    <label class="text text-info text-strong" for="is_login">
        CHOOSE TIME STATUS:
        </label>

    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_login" id="is_login" value="1" checked>
        <label class="form-check-label" for="is_login">
        TIME IN
        </label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="is_login" id="is_login" value="2">
        <label class="form-check-label" for="exampleRadios3">
        TIME OUT
        </label>
    </div>
    <hr />


  <button id="submit" type="submit" class="btn btn-primary">💾 Save Location</button>
</form>

<div id="preview" class="mt-3 text-center"></div>

<hr />
<h4>Click each Marker to display its Details</h4>
<div id="legend" class="mt-3 text-center small">

  <b>📍 Legend:</b>
    <span class="badge bg-success">Near (&lt;1 km)</span>
    <span class="badge bg-warning text-dark">Medium (1–5 km)</span>
    <span class="badge bg-danger">Far (&gt;5 km)</span>
</div>
</div>

<h2>All Saved Locations [<span id="total_uploaded"></span>]</h2>
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    document.getElementById('submit').disabled = true;
let lat, lon;
const map = L.map('map').setView([13.41,122.56],6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'© OpenStreetMap contributors' }).addTo(map);
let userMarker=null;

window.addEventListener("load", ()=> {
  if (!navigator.geolocation) { document.getElementById("output").innerText="⚠️ Geolocation not supported."; return; }
  navigator.geolocation.getCurrentPosition(async (pos)=>{
    lat=pos.coords.latitude; lon=pos.coords.longitude;
    document.getElementById("latitude").value=lat;
    document.getElementById("longitude").value=lon;

    map.setView([lat,lon],14);
    if(userMarker) map.removeLayer(userMarker);
    const humanIcon = L.icon({ iconUrl:'assets/img/urhere.png', iconSize:[25,20], iconAnchor:[20,40], popupAnchor:[0,-50] });
    userMarker=L.marker([lat,lon],{icon:humanIcon}).addTo(map).bindPopup("📍 You are here ✅").openPopup();

    document.getElementById("output").innerText="📍 Location detected: fetching address...";
    try{
      const res = await fetch(`reverse_geocode.php?lat=${lat}&lon=${lon}`);
      const data = await res.json();
      if(data.success){
        const barangay=data.barangay||'N/A', municipality=data.municipality||'N/A', province=data.province||'N/A';
        document.getElementById("output").innerHTML=`📍 Barangay: <b>${barangay}</b><br>Municipality: <b>${municipality}</b><br>Province: <b>${province}</b>`;
        document.getElementById("addPreview").value=`${barangay}, ${municipality}, ${province}`;
      }else{ document.getElementById("output").innerText="⚠️ Failed to fetch address."; }
      document.getElementById('submit').disabled = false;
    }catch(err){ console.error(err); document.getElementById("output").innerText="⚠️ Error fetching address."; }

    loadLocations();
  }, err=>{ console.error(err); document.getElementById("output").innerText="⚠️ Location access denied."; }, {enableHighAccuracy:true, timeout:10000});
});

// Form validation & submit
const overlay=document.getElementById("loadingOverlay");
const fileInput = document.querySelector('input[name="media"]');
const previewDiv = document.getElementById("preview");

document.getElementById("uploadForm").addEventListener("submit", async (e)=>{
    console.log('test');
  e.preventDefault();
//   const youtubeLink=document.getElementById("youtube_link").value.trim();
  const latInput=document.getElementById("latitude");
  const lonInput=document.getElementById("longitude");
  const addressInput=document.getElementById("is_login");

  const hasFile=fileInput.files.length>0;
//   const hasYouTube=youtubeLink!=="";

  if (!hasFile) {
  alert("⚠️ Please upload an image");
  return;
}
  if(!latInput.value||!lonInput.value){ alert("⚠️ Location not detected yet."); return; }
  if(!addressInput.value.trim()){ alert("⚠️ Please enter additional address info."); return; }

  if(!confirm("Are you sure you want to save this report?")) return;
  const formData=new FormData(e.target);
  overlay.style.display="flex";
  try{
    const response=await fetch("save_location.php",{method:"POST",body:formData});
    const text=await response.text();
    const result=JSON.parse(text);
    alert(result.message||"Saved!");
    loadLocations();
  }catch(err){ console.error(err); alert("Error saving location"); }
  finally{ overlay.style.display="none"; }
});

// File preview with clickable fullscreen
fileInput.addEventListener("change", () => {
  previewDiv.innerHTML = "";
  const file = fileInput.files[0];
  if (!file) return;

  const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
  const maxMB = 10;
  let msg = `Selected file size: ${sizeMB} MB`;
  if (sizeMB > maxMB) {
    msg += ` ❌ (Too large! Limit ${maxMB} MB)`;
    alert(msg);
    fileInput.value = "";
    previewDiv.innerHTML = `<p style="color:red;">${msg}</p>`;
    return;
  } else { msg += " ✅ Preview:"; }

  const info = document.createElement("p");
  info.textContent = msg;
  previewDiv.appendChild(info);

  const mime = file.type;

  if (mime.startsWith("image/")) {
    const img = document.createElement("img");
    img.src = URL.createObjectURL(file);
    img.style.maxWidth = "200px";
    img.style.cursor = "pointer";
    img.onload = () => URL.revokeObjectURL(img.src);
    img.onclick = () => showFullscreen(img.src, "image");
    previewDiv.appendChild(img);
  } else if (mime.startsWith("video/")) {
    const video = document.createElement("video");
    video.src = URL.createObjectURL(file);
    video.controls = true;
    video.style.maxWidth = "250px";
    video.style.borderRadius = "8px";
    video.style.cursor = "pointer";
    video.onclick = () => showFullscreen(video.src, "video");
    video.onloadeddata = () => URL.revokeObjectURL(video.src);
    previewDiv.appendChild(video);
  } else {
    previewDiv.innerHTML += "<p style='color:red;'>Unsupported file type</p>";
  }
});

// Distance calculation
function calculateDistance(lat1,lon1,lat2,lon2){
  const R=6371;
  const dLat=(lat2-lat1)*Math.PI/180;
  const dLon=(lon2-lon1)*Math.PI/180;
  const a=Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  const c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
  return R*c;
}

// Fullscreen overlay
function showFullscreen(src,type='image'){
  const overlay=document.getElementById("fullscreenOverlay");
  overlay.innerHTML='';
  overlay.style.display='flex';
  if(type==='image'){
    overlay.innerHTML=`<img src="${src}">`;
  }else if(type==='video'){
    overlay.innerHTML=`<video src="${src}" controls autoplay style="max-width:95%;max-height:95%;border-radius:12px;"></video>`;
  }else if(type==='youtube'){
    overlay.innerHTML=`<iframe src="${src}?autoplay=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="width:90%;height:80%;border-radius:12px;"></iframe>`;
  }
  overlay.onclick = ()=> overlay.style.display='none';
}

// YouTube ID extraction
function extractYouTubeID(url){ const m=url.match(/(?:v=|\/)([0-9A-Za-z_-]{11})/); return m?m[1]:''; }

// Load markers & map popups
async function loadLocations() {
  try {
    const res = await fetch("get_locations.php");
    const data = await res.json();
    if (!data.success || !data.data.length) return;

    map.eachLayer(l => {
      if (l instanceof L.Marker && l !== userMarker) map.removeLayer(l);
    });

    const markers = [];
    const iconUrls = {
      green: "https://maps.google.com/mapfiles/ms/icons/green-dot.png",
      orange: "https://maps.google.com/mapfiles/ms/icons/orange-dot.png",
      red: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
      gray: "https://maps.google.com/mapfiles/ms/icons/ltblue-dot.png"
    };

    data.data.forEach(loc => {
      let distanceText="", iconColor="gray", tooltipText="📍 Unknown distance";
      
      if(lat!=null && lon!=null && loc.latitude && loc.longitude){
        const dist = calculateDistance(lat, lon, loc.latitude, loc.longitude);
        distanceText = `<br>📏 Distance: ${dist.toFixed(2)} km`;
        iconColor = dist<1 ? "green" : dist<5 ? "orange" : "red";
        tooltipText = `📏 ${dist.toFixed(2)} km away`;
      }

      let markerIcon;
if (loc.youtube_link) {
  const ytID = extractYouTubeID(loc.youtube_link);
  markerIcon = L.icon({
    iconUrl: `https://img.youtube.com/vi/${ytID}/0.jpg`, // YouTube thumbnail
    iconSize: [60,60],
    iconAnchor: [30,60],
    popupAnchor:[0,-60],
    className:"thumb-marker"
  });
} else if (loc.video_path && loc.video_path.match(/\.(mp4|webm|mov)$/i)) {
  // Use generic video icon instead of trying to use video directly
  markerIcon = L.icon({
    iconUrl: "assets/img/video_icon.png", // your video icon
    iconSize: [50,50],
    iconAnchor: [25,50],
    popupAnchor:[0,-50],
    className:"thumb-marker"
  });
} else if (loc.video_path) {
  // Image marker
  markerIcon = L.icon({
    iconUrl: loc.video_path,
    iconSize: [60,60],
    iconAnchor: [30,60],
    popupAnchor:[0,-60],
    className:"thumb-marker"
  });
} else {
  markerIcon = L.icon({ iconUrl: iconUrls[iconColor], iconSize:[32,32], iconAnchor:[16,32], popupAnchor:[0,-28] });
}

      let time_status = "";
      if(loc.is_login == 1){ time_status = "TIME IN"; } else { time_status = "TIME OUT"; }
      let mediaHtml="";
      if(loc.youtube_link){
        const ytID = extractYouTubeID(loc.youtube_link);
        mediaHtml = `<div style="width:100%; max-width:250px; cursor:pointer; border-radius:8px; overflow:hidden; text-align:center; box-shadow:0 2px 4px rgba(0,0,0,0.2);" onclick="showFullscreen('https://www.youtube.com/embed/${ytID}','youtube')">
          <img src="https://img.youtube.com/vi/${ytID}/0.jpg" style="width:100%; border-radius:8px;"></div>`;
      }else if(loc.video_path && loc.video_path.match(/\.(mp4|webm|mov)$/i)){
        mediaHtml = `<video controls width="250" style="border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.2);cursor:pointer" onclick="showFullscreen('${loc.video_path}','video')">
          <source src="${loc.video_path}" type="video/mp4">Your browser does not support video playback.</video>`;
      }else if(loc.video_path){
        mediaHtml = `<img src="${loc.video_path}" width="100" style="cursor:pointer;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.2);" onclick="showFullscreen('${loc.video_path}','image')">`;
      }
      const popupHtml = `<b>📍 Location</b><br>
        Barangay: ${loc.barangay||'N/A'}<br>
        Municipality: ${loc.municipality||'N/A'}<br>
        Province: ${loc.province||'N/A'}${mediaHtml}${distanceText}<br>
        ${time_status} : ${loc.date_formatted}`;

      document.getElementById("total_uploaded").innerHTML = data.data.length;
      const marker = L.marker([loc.latitude, loc.longitude],{icon:markerIcon}).addTo(map)
        .bindPopup(popupHtml)
        .bindTooltip(tooltipText, {direction:"top",offset:[0,-10],opacity:0.9});

      markers.push(marker);
    });

    const allMarkers = [userMarker, ...markers].filter(Boolean);
    if(allMarkers.length===1) map.setView(allMarkers[0].getLatLng(),14);
    else if(allMarkers.length>1) map.fitBounds(L.featureGroup(allMarkers).getBounds().pad(0.5));

  }catch(err){ console.error("Error loading markers:", err); }
}
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>
