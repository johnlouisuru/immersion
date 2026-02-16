<?php

//error_reporting(0);
/**
 * PHP Security & Sanitation + 404 Redirect Handler
 * Author: Buruler-PLate
 * Date: 2025-08-09
 *
 * Include this file at the top of all PHP scripts.
 * Handles:
 *  - Input sanitation
 *  - Secure DB queries
 *  - Safe 404 redirects
 */

// =========================
// BASIC SANITATION FUNCTIONS
// =========================

/**
 * Sanitize a string for HTML output
 */
 /**
  * Google Client ID : 3881994274-hgtelja2b8a7qho4t0t4akot9fd7am96.apps.googleusercontent.com
  * Google Client Secret: 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
  */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function navActive($currentPath, $page)
{
    return ($page == $currentPath) ? 'active bg-gradient-dark text-white' : 'text-dark';
}
function clean_text($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate integer
 */
function clean_int($number) {
    return filter_var($number, FILTER_VALIDATE_INT) !== false
        ? intval($number)
        : null;
}

/**
 * Validate email
 */
function clean_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ? $email
        : null;
}

/**
 * Validate URL
 */
function clean_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        ? $url
        : null;
}

/**
 * Whitelist check (only allow predefined values)
 */
function whitelist($value, $allowed_values) {
    return in_array($value, $allowed_values, true) ? $value : null;
}

/**
 * Sanitize file name
 */
function clean_filename($filename) {
    return preg_replace("/[^a-zA-Z0-9_\.-]/", "_", basename($filename));
}

// =========================
// DATABASE PROTECTION
// =========================



require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();         // throws error if .env is missing
// or
// $dotenv->safeLoad();  // skips if .env isn't found

// Optionally enforce required variables
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();


// Google OAuth configuration
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI']);


$dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset={$_ENV['DB_CHARSET']}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'], '', $options);
    //echo"DB Connected!<br>";
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit;
}

/**
 * Run secure query
 */
// Check if user is logged in
function isGoogleAuthenticated() {
    return isset($_SESSION['google_id'], $_SESSION['email']);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function isProfileComplete() {
    return !empty($_SESSION['profile_complete']);
}


function authenticate_email($db, $sql, $email){
    //$sql = "SELECT * FROM admins WHERE email=:email";
     $stmt = $db->prepare($sql);
     $stmt->execute([":email" => $email]);
     return $stmt;
}

function secure_query($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function secure_query_no_params($db, $sql) {
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt;
}

function secure_insert($db, $sql, $params) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $db->lastInsertId(); // return inserted ID
}



function get_section_name($db, $section_id){
    if($section_id == 0 || $section_id == NULL){
            $data['section_name'] = "N/A";
            $data['teacher_id'] = NULL;
        return $data;
    }
     $sql = "SELECT section_name, teacher_id FROM sections WHERE id=:section_id";
     $stmt = $db->prepare($sql);
     $stmt->execute([":section_id" => $section_id]);
     $row = $stmt->fetch(PDO::FETCH_ASSOC);
     $data = [];
     if($row){
        $data['section_name'] = $row['section_name'];
        $data['teacher_id'] = $row['teacher_id'];
        
     }
     else {
        $data['message'] = 'N/A';
     }
     return $data;
     
}

function get_teacher_section_assignment($db, $teacher_id) {
    if($teacher_id == 0 || $teacher_id == NULL){
            $data['teacher_name'] = "N/A";
        return $data;
    }
    $sql = "SELECT section_name FROM sections WHERE teacher_id = :teacher_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([":teacher_id" => $teacher_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows && count($rows) > 0) {
        $output = '';
        foreach ($rows as $row) {
            $output .= htmlspecialchars($row['section_name']) . '<br>';
        }
        return $output;
    } else {
        return 'No Assignment Yet';
    }
}


function get_teacher_name($db, $teacher_id){
     $sql = "SELECT lastname, firstname FROM teachers WHERE id=:teacher_id";
     $stmt = $db->prepare($sql);
     $stmt->execute([":teacher_id" => $teacher_id]);
     $row = $stmt->fetch(PDO::FETCH_ASSOC);
     if($row){

        return $row['firstname'] . ', '.$row['lastname'] ;
     }
     else {
        return 'No Assigned';
     }
     
}

function get_student_name($db, $student_id){
     $sql = "SELECT firstname, lastname FROM students WHERE id=:student_id";
     $stmt = $db->prepare($sql);
     $stmt->execute([":student_id" => $student_id]);
     $row = $stmt->fetch(PDO::FETCH_ASSOC);
     if($row){
        return $row['lastname'] . ", " . $row['firstname'];
     }
     else {
        return 'No Assigned';
     }
     
}

function total_count_of_requirement($db){
    $sql = "SELECT COUNT(*) AS total_requirements FROM requirements";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['total_requirements'] : 0;
}

function get_section_assignment($db, $teacher_id) {
    // Step 1: Get the section_id from teachers table
    $sql = "SELECT section_id FROM teachers WHERE id = :teacher_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([":teacher_id" => $teacher_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['section_id']) {
        return 'N/A'; // No section assigned
    }

    $section_id = $row['section_id'];

    // Step 2: Get the section_name from sections table
    $sql = "SELECT section_name FROM sections WHERE id = :section_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([":section_id" => $section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    return $section ? $section['section_name'] : 'N/A';
}


function get_requirement_percentage($db, $student_id) {
    $sql = "SELECT COUNT(*) AS checked_count 
            FROM requirements_status 
            WHERE student_id = :student_id AND is_checked = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([":student_id" => $student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row){
        $total_count_requirement = total_count_of_requirement($db);
        return  $percentage_to_return = ($row['checked_count'] / $total_count_requirement) * 100;
    }
    else {
        return 0;
    }
    
    //return $row ? (int)$row['checked_count'] : 0;
}

function get_type_of_vessel($db, $sql){
     $stmt = $db->prepare($sql);
     $stmt->execute();
     $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $row;
     
}

//Getting Total Counts using query provided with 2 Parameters para sa $pdo at $query mismo
//Ang Return nito mismo ay yung Total
function get_total_count($db, $query){
    return secure_query_no_params($db, $query)->rowCount();
}
//=================================
// Usage on this secure_query
//========SELECT===================

//========INSERT===================
// $sql = "INSERT INTO users (username, email) VALUES (:username, :email)";
// $params = [
//     ":username" => "zoro",
//     ":email" => "zoro@onepiece.com"
// ];

// secure_query($pdo, $sql, $params);
//==================================



// =========================
// 404 REDIRECT HANDLER
// =========================

/**
 * Handle 404 Not Found with safe redirect
 */
function handle_404_redirect($redirect_to = '/') {
    // Send proper 404 HTTP status
    http_response_code(404);

    // Log the missing URL for review
    file_put_contents(
        __DIR__ . '/404_log.txt',
        date("Y-m-d H:i:s") . " - " . $_SERVER['REQUEST_URI'] . "\n",
        FILE_APPEND
    );

    // Redirect safely (whitelist check)
    $allowed_redirects = ['/', '/portal', '/blank'];
    if (in_array($redirect_to, $allowed_redirects, true)) {
        header("Location: $redirect_to", true, 302);
        exit();
    }

    // Fallback message if redirect not allowed
    echo "<h1>404 - Page Not Found</h1>";
    echo "<p>The page you are looking for does not exist.</p>";
    exit();
}
?>
