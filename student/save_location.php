<?php
date_default_timezone_set("Asia/Manila");
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ============================
   TEMP DEBUG — REMOVE AFTER
   ============================ */
// echo json_encode([
//     'upload_max_filesize' => ini_get('upload_max_filesize'),
//     'post_max_size' => ini_get('post_max_size'),
//     'max_execution_time' => ini_get('max_execution_time'),
//     'php_version' => PHP_VERSION
// ]);
// exit;

$sectionId = $_SESSION['section_id'] ?? 0;
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// echo $_POST['province'];
// exit;
// Compress image function
function compressImageToLimit($source, $destination, $maxWidth = 1280, $maxSizeBytes = 10485760) {
    $info = getimagesize($source);
    if ($info === false) return ["ok" => false, "error" => "Not a valid image file"];
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = @imagecreatefromjpeg($source); break;
        case 'image/png': $image = @imagecreatefrompng($source); break;
        default: return ["ok" => false, "error" => "Unsupported format ($mime). Only JPEG/PNG allowed."];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($newWidth / $width));
        $tmp = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime === 'image/png') { imagealphablending($tmp, false); imagesavealpha($tmp, true); }
        imagecopyresampled($tmp, $image, 0,0,0,0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $tmp;
    }

    $quality = 80;
    do {
        if ($mime === 'image/jpeg') imagejpeg($image, $destination, $quality);
        elseif ($mime === 'image/png') imagepng($image, $destination, 9 - floor($quality / 10));
        clearstatcache();
        if (filesize($destination) <= $maxSizeBytes) { imagedestroy($image); return ["ok"=>true]; }
        $quality -= 5;
    } while ($quality >= 30);

    imagedestroy($image);
    return ["ok"=>true];
}

$youtube_link = trim($_POST['youtube_link'] ?? '');
$hasFile = isset($_FILES['media']) && !empty($_FILES['media']['name']);

if (!$hasFile && !$youtube_link) {
    echo json_encode(["success"=>false,"message"=>"⚠️ Please provide an image, video, or YouTube link."]);
    exit;
}
if ($hasFile && $youtube_link) {
    echo json_encode(["success"=>false,"message"=>"⚠️ Please provide only one upload type."]);
    exit;
}

// YouTube embed conversion
if ($youtube_link) {
    if (preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_link, $m)) {
        $youtube_link = "https://www.youtube.com/embed/" . $m[1];
    } else {
        echo json_encode(["success"=>false,"message"=>"⚠️ Invalid YouTube link."]);
        exit;
    }
}

if (!isset($_POST['latitude'], $_POST['longitude'])) {
    echo json_encode(["success"=>false,"message"=>"Missing coordinates"]);
    exit;
}
$lat = $_POST['latitude'];
$lon = $_POST['longitude'];
$is_login = $_POST['is_login'] ?? '';
$mediaPath = null;

// --- Reverse geocoding to get barangay, municipality, province ---
$addr = ['suburb'=>'', 'neighbourhood'=>'', 'city'=>'', 'town'=>'', 'municipality'=>'', 'state'=>''];

try {
    $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lon}&format=json&addressdetails=1";
    $opts = ['http'=>['header'=>'User-Agent: THESIS-IMMERSION/1.0 (contact: jlouisuru@gmail.com)']];
    $context = stream_context_create($opts);
    $res = file_get_contents($url,false,$context);
    $data = json_decode($res,true);
    if(isset($data['address'])) $addr = $data['address'];
} catch(Exception $e) {
    // fallback if geocoding fails
}



$barangay = $addr['hamlet'] ?? $addr['quarter'] ?? $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['village'] ?? '';
$municipality = $addr['city'] ?? $addr['town'] ?? $addr['municipality'] ?? '';
$province = $addr['state'] ?? $addr['region'] ?? '';

if(isset($_POST['barangay'])){
    $barangay = $_POST['barangay'];
}

if(isset($_POST['city'])){
    $municipality = $_POST['city'];
}

if(isset($_POST['province'])){
    $province = $_POST['province'];
}

try {
    // $check_if_already_timed_in_today = $pdo->prepare("SELECT id FROM attendance_logs WHERE is_login=:login AND student_id=:student_id AND DATE(date_created)=TODAY");
    // $stmt = $pdo->prepare("SELECT date_created FROM attendance_logs WHERE user_ip=:ip ORDER BY date_created DESC LIMIT 1");
    // $stmt->execute([':ip'=>$user_ip]);
    // if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    //     $diff = time() - strtotime($row['date_created']);
    //     if ($diff < 300) {
    //         echo json_encode(["success"=>false,"message"=>"⏳ Please wait a few minutes before uploading again."]);
    //         exit;
    //     }
    // }
    if($is_login == 1){
        $check_if_already_timed_in_today = $pdo->prepare("
            SELECT id
            FROM attendance_logs
            WHERE student_id = :student_id
            AND is_login = 1
            AND date_created >= CURDATE()
            AND date_created < CURDATE() + INTERVAL 1 DAY
        ");

        $check_if_already_timed_in_today->execute([
            ':student_id' => $_SESSION['user_id']
        ]);

        $alreadyTimedIn = $check_if_already_timed_in_today->fetch(PDO::FETCH_ASSOC);
        if($alreadyTimedIn){
            echo json_encode(["success"=>false,"message"=>"You have been Timed In Today."]);
            exit;
        }
    }else if($is_login == 2){
        $check_if_already_timed_in_today = $pdo->prepare("
            SELECT id
            FROM attendance_logs
            WHERE student_id = :student_id
            AND is_login = 1
            AND date_created >= CURDATE()
            AND date_created < CURDATE() + INTERVAL 1 DAY
        ");

        $check_if_already_timed_in_today->execute([
            ':student_id' => $_SESSION['user_id']
        ]);

        $alreadyTimedIn = $check_if_already_timed_in_today->fetch(PDO::FETCH_ASSOC);
        if(!$alreadyTimedIn){
            echo json_encode(["success"=>false,"message"=>"You have not been Timed In today, you are not allowed to Time Out. You must Time In first."]);
            exit;
        }

        $check_if_already_timed_out_today = $pdo->prepare("
            SELECT id
            FROM attendance_logs
            WHERE student_id = :student_id
            AND is_login = 2
            AND date_created >= CURDATE()
            AND date_created < CURDATE() + INTERVAL 1 DAY
        ");

        $check_if_already_timed_out_today->execute([
            ':student_id' => $_SESSION['user_id']
        ]);

        $alreadyTimedOut = $check_if_already_timed_out_today->fetch(PDO::FETCH_ASSOC);
        if($alreadyTimedOut){
            echo json_encode(["success"=>false,"message"=>"You already Timed Out Today."]);
            exit;
        }
    }
    
    if ($hasFile) {

        // ✅ Validate upload success
        if (!isset($_FILES['media']['tmp_name']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK || empty($_FILES['media']['tmp_name'])) {
            $errorMap = [
                UPLOAD_ERR_INI_SIZE   => "File exceeds server upload limit.",
                UPLOAD_ERR_FORM_SIZE  => "File exceeds form upload limit.",
                UPLOAD_ERR_PARTIAL    => "File was only partially uploaded.",
                UPLOAD_ERR_NO_FILE    => "No file uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION  => "Upload blocked by PHP extension."
            ];
    
        $errCode = $_FILES['media']['error'];
        $msg = $errorMap[$errCode] ?? "Unknown upload error.";
        
            // echo json_encode(["success"=>false,"message"=>$msg]);
            // echo json_encode([
            //     "success" => false,
            //     "error_code" => $_FILES['media']['error'] ?? 'no_file',
            //     "tmp_name" => $_FILES['media']['tmp_name'] ?? null,
            //     "name" => $_FILES['media']['name'] ?? null,
            //     "size" => $_FILES['media']['size'] ?? null
            // ]);
                echo json_encode([
                    "success" => false,
                    "message" => "⚠️ File upload failed. Please try again."
                ]);
                exit;
        }

    $tmp = $_FILES['media']['tmp_name'];

    // ✅ Now it is safe
    $mime = mime_content_type($tmp);
        $allowedImages = ['image/jpeg','image/png'];
        $allowedVideos = ['video/mp4','video/webm','video/quicktime'];

        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $fileName = time() . "_" . basename($_FILES['media']['name']);
        $target = $uploadDir . $fileName;

        if (in_array($mime, $allowedImages)) {
            $result = compressImageToLimit($tmp, $target);
            if (!$result['ok']) {
                echo json_encode(["success"=>false,"message"=>$result['error']]);
                exit;
            }
        } elseif (in_array($mime, $allowedVideos)) {
            if ($_FILES['media']['size'] > 30 * 1024 * 1024) {
                echo json_encode(["success"=>false,"message"=>"⚠️ Video too large (max 30MB)."]);
                exit;
            }
            move_uploaded_file($tmp, $target);
        } else {
            echo json_encode(["success"=>false,"message"=>"⚠️ Invalid file type. Only image or video allowed."]);
            exit;
        }

        $mediaPath = $target;
    }

    // Insert data
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (longitude, latitude, barangay, municipality, province, video_path, is_login, user_ip, date_created, student_id, section_id, youtube_link)
        VALUES (:lon, :lat, :barangay, :municipality, :province, :video_path, :is_login, :ip, NOW(), :student_id, :section_id, :youtube)
    ");
        $stmt->execute([
        ':lon'=>$lon,
        ':lat'=>$lat,
        ':barangay'=>$barangay,
        ':municipality'=>$municipality,
        ':province'=>$province,
        ':video_path'=>$mediaPath,   // <-- match SQL
        ':is_login'=>$is_login,
        ':ip'=>$user_ip,
        ':student_id'=>$_SESSION['user_id'],
        ':section_id'=>$sectionId,
        ':youtube'=>$youtube_link
    ]);


    echo json_encode(["success"=>true,"message"=>"✅ Upload saved successfully."]);

} catch (PDOException $e) {
    
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
    exit;
}
