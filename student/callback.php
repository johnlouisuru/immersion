<?php
require_once "db-config/security.php";

// Check if authorization code is present
if (!isset($_GET['code'])) {
    header('Location: index');
    exit;
}





$auth_code = $_GET['code'];

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $auth_code,
    'client_id' => $_ENV['GOOGLE_CLIENT_ID'],
    'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'],
    'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'],
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
$response = curl_exec($ch);
curl_close($ch);

$token_info = json_decode($response, true);

if (!isset($token_info['access_token'])) {
    die('Error: Unable to retrieve access token');
}

// Get user info from Google
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_info['access_token']
]);
$response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($response, true);

if (!isset($user_info['id']) || !isset($user_info['email'])) {
    die('Error: Unable to retrieve user information');
}

// Check if user exists in database
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM students WHERE google_id = :google_id");
$stmt->execute(['google_id' => $user_info['id']]);
$user = $stmt->fetch();

if ($user) {
    // User exists - log them in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['teacher_id'] = $user['id'];
    $_SESSION['section_id'] = $user['section_id'];
    $_SESSION['google_id'] = $user['google_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_complete'] = true;
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];
    $_SESSION['profile_picture'] = $user_info['picture'] ?? null;
    
    header('Location: dashboard');
} else {
    // New user - store Google info in session and redirect to complete profile
    $_SESSION['google_id'] = $user_info['id'];
    $_SESSION['email'] = $user_info['email'];
    $_SESSION['profile_picture'] = $user_info['picture'] ?? null;
    $_SESSION['profile_complete'] = false;

    $_SESSION['user_id'] = $user_info['id'];
    $_SESSION['teacher_id'] = $user_info['id'];
    $_SESSION['section_id'] = 0;
    
    header('Location: complete-profile');
}
exit;
?>