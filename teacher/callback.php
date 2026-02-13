<?php
require_once "db-config/security.php";
// At the top of your file after require_once
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Check if authorization code is present
if (!isset($_GET['code'])) {
    // header('Location: index');
    die("No Code Returned");
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

// Check if teacher email is allowed
// $conn = $pdo;
// $stmt = $conn->prepare("SELECT * FROM allowed_teachers WHERE allowed_email = :allowed_email AND is_allowed IS NOT NULL");
// $stmt->execute(['allowed_email' => $user_info['email']]);
// $allowed_email = $stmt->fetch();

// if(!$allowed_email){
//     echo "Your E-mail is not verified by the Administrator. Contact Mrs. Salcedo for permission. You will be redirected shortly.";
//     // header("refresh:3;url=index");
//     die('Until here.');
//     return;
// }


// Replace the user existence check section with this:
// Check if user exists in database - by google_id OR email
$conn = $pdo;
$stmt = $conn->prepare("SELECT * FROM teachers WHERE google_id = :google_id OR email = :email");
$stmt->execute([
    'google_id' => $user_info['id'],
    'email' => $user_info['email']
]);
$user = $stmt->fetch();

if ($user) {
    // User exists - check if it's the same google_id
    if ($user['google_id'] !== $user_info['id']) {
        // Email exists but with different Google ID - update it
        $update_stmt = $conn->prepare("UPDATE teachers SET google_id = :new_google_id WHERE id = :user_id");
        $update_stmt->execute([
            'new_google_id' => $user_info['id'],
            'user_id' => $user['id']
        ]);
        error_log("Updated google_id for user: " . $user['email'] . " from " . $user['google_id'] . " to " . $user_info['id']);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['teacher_id'] = $user['id'];
    $_SESSION['google_id'] = $user_info['id']; // Use the current Google ID
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_complete'] = true;
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];
    $_SESSION['profile_picture'] = $user_info['picture'] ?? null;
    
    header('Location: dashboard');
} else {
    // Brand new user
    $_SESSION['google_id'] = $user_info['id'];
    $_SESSION['email'] = $user_info['email'];
    $_SESSION['profile_picture'] = $user_info['picture'] ?? null;
    $_SESSION['profile_complete'] = false;
    
    header('Location: complete-profile');
}
exit;
?>