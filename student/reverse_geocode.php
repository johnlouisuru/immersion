<?php
header('Content-Type: application/json');

// Check if lat/lon exist
if (!isset($_GET['lat'], $_GET['lon'])) {
    echo json_encode(["success" => false, "message" => "Missing coordinates"]);
    exit;
}

$lat = floatval($_GET['lat']);
$lon = floatval($_GET['lon']);
$url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lon}&format=json&addressdetails=1";

// ✅ Use cURL with proper headers
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => "AEM-LocationFetcher/1.0 (jlouisuru@gmail.com)", // Required by Nominatim!
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch address",
        "httpCode" => $httpCode,
        "error" => $error
    ]);
    exit;
}

// Parse JSON
$data = json_decode($response, true);
$addr = $data['address'] ?? [];

// Extract address fields with fallbacks
$barangay = $addr['hamlet'] ?? $addr['quarter'] ?? $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['village'] ?? '';
$municipality = $addr['city'] ?? $addr['town'] ?? $addr['municipality'] ?? '';
$province = $addr['state'] ?? $addr['region'] ?? '';

echo json_encode([
    "success" => true,
    "barangay" => $barangay ?: 'N/A',
    "municipality" => $municipality ?: 'N/A',
    "province" => $province ?: 'N/A'
]);
